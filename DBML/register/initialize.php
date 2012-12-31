<?php

/**
 * PX Plugin "DBML"
 * @author Tomoya Koyanagi.
 */
class pxplugin_DBML_register_initialize{

	private $px;
	private $errors = array();
	private $logs = array();

	private $obj_dbml;

	/**
	 * コンストラクタ
	 */
	public function __construct($px){
		$this->px = $px;

		//  プラグインオブジェクトを生成する。
		//      initializeが実行されるタイミングでは、
		//      プラグインオブジェクトはロードされていないから。
		$class_name = $this->px->load_px_plugin_class('/DBML/register/object.php');
		$this->obj_dbml = new $class_name($this->px);
	}//__construct()

	/**
	 * トリガーメソッド
	 * PxFWはインスタンスを作成した後、このメソッドをキックします。
	 * @return 正常終了した場合に true , 異常が発生した場合に false を返します。
	 */
	public function execute($behavior=0){
		$behavior = intval($behavior);

		$database_define = $this->obj_dbml->get_db_definition();

		$sql = array();
		$sqlAlterTables = array();
		foreach( $database_define['tables'] as $table_info ){
			if( $table_info['skip_create'] ){ continue; }

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
				if( $column_info['unique'] ){
					if( $this->px->get_conf('dbms.dbms') == 'postgresql' ){
						#	PostgreSQL
						array_push( $sqlAlterTables, 'ALTER TABLE '.$table_info['name'].' ADD UNIQUE ( '.$column_info['name'].' );' );
					}elseif( $this->px->get_conf('dbms.dbms') == 'sqlite' ){
						#	SQLite
						$sqlColumnSrc .= 'UNIQUE ';
					}elseif( $this->px->get_conf('dbms.dbms') == 'mysql' ){
						#	MySQL
						array_push( $sqlAlterTables, 'CREATE UNIQUE INDEX id ON '.$table_info['name'].' ('.$column_info['name'].''.(strlen($column_info['size'])?'('.$column_info['size'].')':'').');' );
					}
				}
				if( $column_info['not_null'] ){
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

		$sql = array_merge($sql, $sqlAlterTables);

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
					$this->log('[ERROR] database query error. (see error log)',__LINE__);

					//トランザクション：ロールバック
					$this->px->dbh()->rollback();
					return false;
				}else{
					$this->log('database query done. ['.$sql_final.']',__LINE__);
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
	}

	/**
	 * エラー取得メソッド
	 * PxFWはinitialize処理が終了した後(=execute()がreturnした後)、
	 * このメソッドを通じてエラー内容を受け取ります。
	 * @return 配列。配列の要素は、message, file, line の3つを持った連想配列。
	 */
	public function get_errors(){
		return $this->errors;
	}

	/**
	 * 内部エラー発行メソッド
	 * 本オブジェクト内部で発生したエラーを受け取り、メンバー変数に記憶します。
	 * ここで記憶したエラー情報は、最終的に get_errors() により引き出されます。
	 */
	private function error_log( $error_message , $line ){
		array_push( $this->errors, array(
			'message'=>$error_message ,
			'file'=>__FILE__ ,
			'line'=>$line ,
		) );
		return true;
	}

	/**
	 * ログ取得メソッド
	 * PxFWはinitialize処理が終了した後(=execute()がreturnした後)、
	 * このメソッドを通じて実行された処理の内容を受け取ります。
	 * @return 配列。
	 */
	public function get_logs(){
		return $this->logs;
	}

	/**
	 * 内部ログ記録メソッド
	 * 本オブジェクト内部で処理した内容をテキストで受け取り、メンバー変数に記憶します。
	 * ここで記憶した情報は、最終的に get_logs() により引き出されます。
	 */
	private function log( $message ){
		array_push( $this->logs, $message );
		return true;
	}

}

?>