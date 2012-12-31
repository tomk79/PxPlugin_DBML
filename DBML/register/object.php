<?php

/**
 * PX Plugin "DBML"
 * @author Tomoya Koyanagi.
 */
class pxplugin_DBML_register_object{

	private $px;
	private $path_dbml;
	private $database_define;

	/**
	 * コンストラクタ
	 */
	public function __construct($px){
		$this->px = $px;

		$this->path_dbml = $px->get_conf('plugin.DBML.path_dbml');
		$this->parse_dbml();
	}//__construct()

	/**
	 * factory: XML DOM Parser
	 */
	private function factory_dom_parser( $src ){
		$class_name = $this->px->load_px_plugin_class( '/DBML/libs/PxXMLDomParser/PxXMLDomParser.php' );
		$dom_parser = new $class_name( $src, 'bin' );
		return $dom_parser;
	}//factory_dom_parser()

	/**
	 * DBMLファイルをパースして、内容を整理する。
	 */
	private function parse_dbml(){
		$this->database_define = array();
		$this->database_define['tables'] = array();

		$src = $this->px->dbh()->file_get_contents( $this->path_dbml );
		$dom_parser = $this->factory_dom_parser($src);
		$tables = $dom_parser->find('dbml > tables > table');
		foreach($tables as $table){
			$table_info = array();
			$table_info['name']         = $table['attributes']['name']        ;
			$table_info['logical_name'] = $table['attributes']['logical-name'];
			$table_info['skip_create']  = $table['attributes']['skip-create'] ;
			$table_info['columns'] = array();

			//  値のチューニング
			$table_info['name'] = $this->bind_meta_string($table_info['name']);
			$table_info['skip_create'] = $this->judge_boolean($table_info['skip_create']);
			//  / 値のチューニング

			$dom_parser = $this->factory_dom_parser($table['innerHTML']);
			$columns = $dom_parser->find('column');
			foreach($columns as $column){
				$column_info = array();
				$column_info['name']         = $column['attributes']['name']        ;
				$column_info['logical_name'] = $column['attributes']['logical-name'];
				$column_info['type']         = $column['attributes']['type']        ;
				$column_info['size']         = $column['attributes']['size']        ;
				$column_info['not_null']     = $column['attributes']['not-null']    ;
				$column_info['default']      = $column['attributes']['default']     ;
				$column_info['key_type']     = $column['attributes']['key-type']    ;

				//  値のチューニング
				$column_info['name'] = $this->bind_meta_string($column_info['name']);
				$column_info['size'] = (strlen($column_info['size'])?intval($column_info['size']):null);
				$column_info['not_null'] = $this->judge_boolean($column_info['not_null']);
				//  / 値のチューニング

				array_push( $table_info['columns'], $column_info );
			}

			array_push( $this->database_define['tables'], $table_info );
		}

		return true;
	}//parse_dbml()

	/**
	 * 埋め込み情報をバインドする
	 */
	private function bind_meta_string( $text ){
		$text = preg_replace( '/\{\$prefix\}/' , $this->px->get_conf('dbms.prefix') , $text );
		return $text;
	}//bind_meta_string()

	/**
	 * ブール型となる予定の入力値を評価し、結論を返す
	 */
	private function judge_boolean( $text ){
		switch(strtolower($text)){
			case 'true':
				$text = true; break;
			default:
				$text = false; break;
		}

		return $text;
	}//judge_boolean()

	/**
	 * データベース定義配列を取り出す。
	 */
	public function get_db_definition(){
		return $this->database_define;
	}//get_db_definition()

	/**
	 * テーブル定義配列を取り出す。
	 */
	public function get_table_definition( $table_name ){
		$table_name = $this->bind_meta_string($table_name);
		foreach( $this->database_define['tables'] as $table_info ){
			if($table_info['name'] == $table_name){
				return $table_info;
			}
		}
		return false;
	}//get_table_definition()

	/**
	 * テーブルのレコード数を数える
	 */
	public function get_count_table_rows( $table_name ){
		$table_name = $this->bind_meta_string($table_name);

		ob_start();?>
SELECT count(*) AS count FROM :D:table_name;
<?php
		$sql = @ob_get_clean();

		$bind_data = array(
			'table_name'=>$table_name,
		);
		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}
		$value = $this->px->dbh()->get_results();

		return intval($value[0]['count']);
	}//get_count_table_rows()

	/**
	 * テーブルに行を追加する
	 */
	public function insert( $table_name, $row ){
		$table_name = $this->bind_meta_string($table_name);

		$table_definition = $this->get_table_definition($table_name);
		$ary_key = array();
		$ary_val = array();
		$bind_data = array();
		foreach( $table_definition['columns'] as $column_info ){
			//  シリアル型(=auto inclement)は、指定できない。
			if($column_info['type'] == 'series'){
				continue;
			}

			//  VARCHARマスターキーを自動生成
			if( $column_info['key_type'] == 'master' && $column_info['type'] == 'varchar' && is_null( $row[$column_info['name']] ) ){
				$row[$column_info['name']] = uniqid();
			}

			//  メタ文字の接頭辞を決める
			$type_prefix = ':S:';
			switch($column_info['type']){
				case 'int':
				case 'serial':
					$type_prefix = ':N:';
					break;
				default:
					$type_prefix = ':S:';
			}

			//  NOT NULL の処理
			if( $column_info['not_null'] && is_null($row[$column_info['name']])){
				if( !is_null($column_info['default']) ){
					$row[$column_info['name']] = $column_info['default'];
				}else{
					//  NOT NULL でかつ DEFAULT が指定されていないカラムに
					//  NULL を入れようとした場合は、続行不能。
					return false;
				}
			}

			//  DATETIME型の "NOW" を処理。
			if( $column_info['type'] == 'datetime' && strtolower($row[$column_info['name']]) == 'now' ){
				$row[$column_info['name']] = date('Y-m-d H:i:s');
			}

			//  要素をセット
			$bind_data[$column_info['name']] = $row[$column_info['name']];
			array_push($ary_key,$column_info['name']);
			array_push($ary_val,$type_prefix.$column_info['name']);

		}

		$sql = '';
		$sql .= 'INSERT ';
		$sql .= 'INTO '.$this->px->dbh()->bind( ':D:table_name' , array('table_name'=>$table_name) ).' ';
		$sql .= '('.implode(',',$ary_key).') ';
		$sql .= 'VALUES ('.implode(',',$ary_val).')';
		$sql .= ';';

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}

		return true;
	}//insert()

	/**
	 * テーブルの行を書き換える
	 */
	public function update( $table_name, $conditions, $row ){
		$table_name = $this->bind_meta_string($table_name);

		$table_definition = $this->get_table_definition($table_name);
		$ary_conditions = array();
		$ary_key_val = array();
		$bind_data = array();
		foreach( $table_definition['columns'] as $column_info ){
			//  メタ文字の接頭辞を決める
			$type_prefix = ':S:';
			switch($column_info['type']){
				case 'int':
				case 'serial':
					$type_prefix = ':N:';
					break;
				default:
					$type_prefix = ':S:';
			}

			//  DATETIME型の "NOW" を処理。
			if( $column_info['type'] == 'datetime' ){
				if( strtolower($row[$column_info['name']]) == 'now' ){ $row[$column_info['name']] = date('Y-m-d H:i:s'); }
				if( strtolower($conditions[$column_info['name']]) == 'now' ){ $conditions[$column_info['name']] = date('Y-m-d H:i:s'); }
			}

			//  要素をセット
			if( array_key_exists( $column_info['name'], $row ) ){
				$bind_data['VALUES_'.$column_info['name']] = $row[$column_info['name']];
				array_push($ary_key_val, $column_info['name'].'='.$type_prefix.'VALUES_'.$column_info['name']);
			}
			if( array_key_exists( $column_info['name'], $conditions ) ){
				array_push($ary_conditions, $column_info['name'].'='.$type_prefix.'CONDITIONS_'.$column_info['name']);
			}

		}
		//  条件式のバインドデータを作成
		foreach( $conditions as $key=>$val ){
			$bind_data['CONDITIONS_'.$key] = $val;
		}
		$sql = '';
		$sql .= 'UPDATE ';
		$sql .= ''.$this->px->dbh()->bind( ':D:table_name' , array('table_name'=>$table_name) ).' ';
		if(count($ary_key_val)){
			$sql .= 'SET ';
			$sql .= ''.implode(', ',$ary_key_val).' ';
		}
		if(count($ary_conditions)){
			$sql .= 'WHERE ';
			$sql .= ''.implode(' AND ',$ary_conditions).' ';
		}
		$sql .= ';';

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}

		return true;
	}//update()

	/**
	 * テーブルから行を取得する
	 */
	public function select( $table_name, $conditions, $limit = null, $offset = 0 ){
		$table_name = $this->bind_meta_string($table_name);

		$offset = intval( $offset );
		if( !is_null( $limit ) ){
			$limit = intval( $limit );
		}

		$table_definition = $this->get_table_definition($table_name);
		$ary_conditions = array();
		$bind_data = array();
		foreach( $table_definition['columns'] as $column_info ){
			if( !array_key_exists( $column_info['name'], $conditions ) ){ continue; }

			//  メタ文字の接頭辞を決める
			$type_prefix = ':S:';
			switch($column_info['type']){
				case 'int':
				case 'serial':
					$type_prefix = ':N:';
					break;
				default:
					$type_prefix = ':S:';
			}

			//  要素をセット
			$bind_data['CONDITIONS_'.$column_info['name']] = $conditions[$column_info['name']];
			array_push($ary_conditions,$column_info['name'].'='.$type_prefix.'CONDITIONS_'.$column_info['name']);

		}

		$sql = '';
		$sql .= 'SELECT * ';
		$sql .= 'FROM '.$this->px->dbh()->bind( ':D:table_name' , array('table_name'=>$table_name) ).' ';
		if(count($ary_conditions)){
			$sql .= 'WHERE ';
			$sql .= ''.implode(' AND ',$ary_conditions).' ';
		}
		if( $limit > 0 ){
			$sql .= $this->px->dbh()->mk_sql_limit($limit, $offset);
		}
		$sql .= ';';

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}

		$value = $this->px->dbh()->get_results();

		return $value;
	}//select()

	/**
	 * テーブルの行を削除する
	 */
	public function delete( $table_name, $conditions ){
		$table_name = $this->bind_meta_string($table_name);

		$table_definition = $this->get_table_definition($table_name);
		$ary_conditions = array();
		$bind_data = array();
		foreach( $table_definition['columns'] as $column_info ){
			if( !array_key_exists( $column_info['name'], $conditions ) ){ continue; }

			//  メタ文字の接頭辞を決める
			$type_prefix = ':S:';
			switch($column_info['type']){
				case 'int':
				case 'serial':
					$type_prefix = ':N:';
					break;
				default:
					$type_prefix = ':S:';
			}

			//  要素をセット
			$bind_data['CONDITIONS_'.$column_info['name']] = $conditions[$column_info['name']];
			array_push($ary_conditions,$column_info['name'].'='.$type_prefix.'CONDITIONS_'.$column_info['name']);

		}

		$sql = '';
		$sql .= 'DELETE ';
		$sql .= 'FROM '.$this->px->dbh()->bind( ':D:table_name' , array('table_name'=>$table_name) ).' ';
		$sql .= 'WHERE ';
		$sql .= ''.implode(' AND ',$ary_conditions).' ';
		$sql .= ';';

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}

		return true;
	}//delete()

}

?>