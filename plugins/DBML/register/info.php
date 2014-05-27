<?php

/**
 * PX Plugin "DBML"
 * @author Tomoya Koyanagi.
 */
class pxplugin_DBML_register_info{

	/**
	 * プラグインのバージョン情報を取得する
	 * @return string バージョン番号を示す文字列
	 */
	public function get_version(){
		return '0.0.0a1-nb';
	}

	/**
	 * コンフィグ項目を定義する
	 */
	public function config_define(){
		return array(
			'plugin-DBML.path_dbml'=>
				array(
					'description'=>'.dbml ファイルのパス',
					'type'=>'realpath' ,
					'required'=>true ,
				) ,
		);
	}

}

?>