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

}

?>