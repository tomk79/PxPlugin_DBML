<?php
/**
 * class pxplugin_DBML_funcs_autolist
 */
/**
 * PX Plugin "DBML"
 */
class pxplugin_DBML_funcs_autolist{

	private $px;
	private $dbml;
	private $table_name;
	private $options;
	private $table_definition;
	/**
	 * パスパラメータ
	 */
	private $path_param;
	/**
	 * カレントページ
	 */
	private $current_page_info;

	/**
	 * コンストラクタ
	 * 
	 * @param $px = PxFWコアオブジェクト
	 * @param $dbml = プラグインオブジェクト
	 * @param $table_name = 対象のテーブル名
	 * @param $path_param = パスパラメータ
	 * @param $options = オプション
	 */
	public function __construct( $px, $dbml, $table_name, $path_param, $options = array() ){
		$this->px = $px;
		$this->dbml = $dbml;
		$this->table_name = $table_name;
		$this->path_param = $path_param;
		$this->options = $options;
		$this->table_definition = $this->dbml->get_table_definition( $this->table_name );
		$this->current_page_info = $this->px->site()->get_current_page_info();

	}

	/**
	 * パスパラメータを解析する。
	 * 
	 * @param string $path_param パスパラメータ
	 * @return array 解析データの連想配列
	 */
	private function parse_path_param($path_param){
		$path_param = preg_replace( '/(?:\/|^)'.$this->px->get_directory_index_preg_pattern().'$/', '', $path_param );
		if( !strlen($path_param) ){
			return array();
		}
		$rtn = explode('/', $path_param);
		return $rtn;
	}

	/**
	 * path_param 内でのリンク先情報を生成する。
	 */
	private function href( $link_to ){
		$href = $this->px->site()->bind_dynamic_path_param( $this->current_page_info['path'], array(''=>$link_to) );
		$rtn = $this->px->theme()->href( $href );
		return $rtn;
	}

	/**
	 * フォーム生成を実行する。
	 * 
	 * @return string|void 画面を描画するコンテンツエリアのHTMLソースを返します。またはリダイレクト処理の場合は値を返しません。
	 */
	public function execute(){
		// test::var_dump($this->table_name);
		// test::var_dump($this->table_definition);
		$ary_path_param = $this->parse_path_param( $this->path_param );

		$src = '';
		switch( $ary_path_param[0] ){
			case 'add':
				$src .= $this->page_add();
				break;
			case 'edit':
				$src .= $this->page_edit( $ary_path_param[1] );
				break;
			case 'delete':
				$src .= $this->page_delete( $ary_path_param[1] );
				break;
			default:
				$src .= $this->page_list();
				break;
		}

		return $src;
	}

	/**
	 * 一覧画面を作る。
	 */
	private function page_list(){

		$list = $this->dbml->select( $this->table_name, array() );
		// test::var_dump( $list );
		$list_columns = array();
		foreach( $this->table_definition['columns'] as $def ){
			switch( $def['type'] ){
				case 'serial':
				case 'serial_s':
				case 'password':
				case 'create_date':
				case 'update_date':
				case 'delete_date':
				case 'delete_flg':
					continue 2;
			}
			$list_columns[$def['name']] = $def;
		}

		$src = '';
		$src .= '<p><a href="'.t::h( $this->href( 'add/' ) ).'">新規追加</a></p>'."\n";
		$src .= '<div class="unit">'."\n";
		$src .= '<table class="def" style="width:100%;">'."\n";
		$src .= '<thead><tr>'."\n";
		foreach( $list_columns as $def ){
			$src .= '<th>'.t::h($def['logical_name']).'</th>'."\n";
		}
		$src .= '<th>-</th>'."\n";
		$src .= '<th>-</th>'."\n";
		$src .= '</tr></thead>'."\n";
		foreach( $list as $row ){
			$src .= '<tr>'."\n";
			foreach( $list_columns as $def ){
				$src .= '	<td>'.t::h( $row[$def['name']] ).'</td>'."\n";
			}
			$src .= '<td><a href="'.t::h( $this->href( 'edit/'.$row['id'].'/' ) ).'">編集</a></td>'."\n";
			$src .= '<td><a href="'.t::h( $this->href( 'delete/'.$row['id'].'/' ) ).'">削除</a></td>'."\n";
			$src .= '</tr>'."\n";
		}
		$src .= '</table>'."\n";
		$src .= '</div>'."\n";

		return $src;
	}

	/**
	 * 新規追加ページを作る。
	 */
	private function page_add(){
		return $this->dbml->auto_form( $this->table_name );
	}

	/**
	 * 編集ページを作る。
	 */
	private function page_edit( $target ){
		return $this->dbml->auto_form( $this->table_name, array('method'=>'update', 'target'=>$target) );
	}

	/**
	 * 削除ページを作る。
	 */
	private function page_delete( $target ){
		return $this->dbml->auto_form( $this->table_name, array('method'=>'delete', 'target'=>$target) );
	}

}

?>