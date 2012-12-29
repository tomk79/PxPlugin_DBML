<?php

/**
 * PxFW Plugin "DBML"
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
	}

	/**
	 * DBMLファイルをパースして、内容を整理する。
	 */
	private function parse_dbml(){
		$this->database_define = array();
		$this->database_define['tables'] = array();

		$src = $this->px->dbh()->file_get_contents( $this->path_dbml );
		$dom_parser = $this->factory_dom_parser($src);
		$tables = $dom_parser->find('dbml > table');
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
				$column_info['notnull']      = $column['attributes']['not-null']    ;
				$column_info['default']      = $column['attributes']['default']     ;

				//  値のチューニング
				$column_info['name'] = $this->bind_meta_string($column_info['name']);
				$column_info['size'] = (strlen($column_info['size'])?intval($column_info['size']):null);
				$column_info['notnull'] = $this->judge_boolean($column_info['notnull']);
				//  / 値のチューニング

				array_push( $table_info['columns'], $column_info );
			}

			array_push( $this->database_define['tables'], $table_info );
		}

//test::var_dump($this->create_tables(2));
		return true;
	}//parse_dbml()

	/**
	 * 埋め込み情報をバインドする
	 */
	private function bind_meta_string( $text ){
		$text = preg_replace( '/\{\$prefix\}/' , $this->px->get_conf('dbms.prefix') , $text );
		return $text;
	}

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
	}

	/**
	 * テーブルを生成する
	 * @params int $behavior: 振る舞い。0(既定値)=SQLを実行する|1=SQL文全体を配列として返す|2=SQL全体を文字列として返す
	 * @return $behavior=0 の場合、SQLを実行した結果の成否(bool), $behavior=1 の場合、1つのSQL文を1要素として持つ配列, $behavior=2 の場合、全SQL文を結合した文字列としてのSQL
	 */
	public function create_tables($behavior=0){
		$behavior = intval($behavior);

		$sql = array();
		foreach( $this->database_define['tables'] as $table_info ){
			$sqlSrc = '';
			$sqlSrc .= 'CREATE TABLE '.$table_info['name'].' ('."\r\n";

			$sqlColumnSrcs = array();
			foreach($table_info['columns'] as $column_info){
				$sqlColumnSrc = '';
				$sqlColumnSrc .= $column_info['name'].'    ';
				if( strtoupper($column_info['type']) == 'DATETIME' && $this->px->get_conf('dbms.dbms') == 'postgresql' ){
					$sqlColumnSrc .= strtoupper('TIMESTAMP');
				}else{
					$sqlColumnSrc .= strtoupper($column_info['type']);
				}
				if( strlen($column_info['size']) && $this->px->get_conf('dbms.dbms') != 'postgresql' ){
					$sqlColumnSrc .= '('.$column_info['size'].')';
				}
				$sqlColumnSrc .= ' ';
				if( $column_info['notnull'] ){
					$sqlColumnSrc .= 'NOT NULL ';
				}
				if( strlen($column_info['default']) ){
					$sqlColumnSrc .= 'DEFAULT '.$column_info['default'].' ';
				}
				array_push( $sqlColumnSrcs , $sqlColumnSrc );
			}

			$sqlSrc .= '    '.implode( ','."\r\n".'    ' , $sqlColumnSrcs )."\r\n";
			$sqlSrc .= ');'."\r\n";
			array_push( $sql , $sqlSrc );
		}

		if( !$behavior ){
			//  トランザクション：スタート
			$this->px->dbh()->start_transaction();
		}

		$rtn_sql = array();
		foreach( $sql as $sql_final ){
			if( !strlen( $sql_final ) ){ continue; }

			if( !$behavior ){
				if( !$this->px->dbh()->send_query( $sql_final ) ){
					$this->px->error()->error_log('database query error ['.$sql_final.']');
					$this->error_log('database query error ['.$sql_final.']',__LINE__);

					//トランザクション：ロールバック
					$this->px->dbh()->rollback();
					return false;
				}
			}else{
				array_push( $rtn_sql , $sql_final );
			}
			unset($sql_final);
		}

		if( !$behavior ){
			//  トランザクション：コミット
			$this->px->dbh()->commit();
			return true;
		}
		if( $behavior === 1 ){
			return $rtn_sql;
		}
		if( $behavior === 2 ){
			return implode( "\r\n\r\n\r\n", $rtn_sql );
		}

		//  想定外の$behavior
		return false;
	}//create_tables()

}

?>