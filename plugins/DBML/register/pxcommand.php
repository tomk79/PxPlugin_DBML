<?php
$this->load_px_class('/bases/pxcommand.php');

/**
 * PX Plugin "DBML"
 */
class pxplugin_DBML_register_pxcommand extends px_bases_pxcommand{

	private $local_sitemap = array();// ページ名等を定義する

	/**
	 * コンストラクタ
	 * @param $command = PXコマンド配列
	 * @param $px = PxFWコアオブジェクト
	 */
	public function __construct( $command , $px ){
		parent::__construct( $command , $px );
		$this->px = $px;

		switch( $command[2] ){
			default:
				$fin = $this->page_home(); break;
		}
		print $this->html_template($fin);
		exit;
	}


	/**
	 * コンテンツ内へのリンク先を調整する。
	 */
	private function href( $linkto = null ){
		if(is_null($linkto)){
			return '?PX='.implode('.',$this->pxcommand_name);
		}
		if($linkto == ':'){
			return '?PX=plugins.DBML';
		}
		$param = '';
		if( preg_match('/^(.*?)(?:\?(.*))?$/s', $linkto, $matched) ){
			$linkto = $matched[1];
			$param = '&'.$matched[2];
		}

		$rtn = preg_replace('/^\:/','?PX=plugins.DBML.',$linkto);
		$rtn .= $param;

		$rtn = $this->px->theme()->href( $rtn );
		return $rtn;
	}

	/**
	 * コンテンツ内へのリンクを生成する。
	 */
	private function mk_link( $linkto , $options = array() ){
		if( !strlen($options['label']) ){
			if( $this->local_sitemap[$linkto] ){
				$options['label'] = $this->local_sitemap[$linkto]['title'];
			}
		}
		$rtn = $this->href($linkto);

		$rtn = $this->px->theme()->mk_link( $rtn , $options );
		return $rtn;
	}

	// ----------------------------------------------------------------------------

	/**
	 * ホームページを表示する。
	 */
	private function page_home(){
		$obj = $this->px->get_plugin_object('DBML');
		// test::var_dump($obj);

		$db_definition = $obj->get_db_definition();
		// test::var_dump($db_definition);

		$src = '';
		$src .= '<h2>tables</h2>'."\n";
		foreach( $db_definition['tables'] as $table_definition ){
			$src .= '<h3>'.t::h($table_definition['name']).' - '.t::h($table_definition['logical_name']).'</h3>'."\n";
			$src .= '<table class="def" style="width:100%;">';
			$src .= '<thead>';
			$src .= '<tr>';
			$src .= '<th>name</th>';
			$src .= '<th>logical_name</th>';
			$src .= '<th>type</th>';
			$src .= '<th>size</th>';
			$src .= '<th>default</th>';
			$src .= '<th>not_null</th>';
			$src .= '<th>key_type</th>';
			$src .= '<th>foreign_key</th>';
			$src .= '<th>unique</th>';
			$src .= '</tr>';
			$src .= '</thead>';
			foreach( $table_definition['columns'] as $column ){
				$src .= '<tr>';
				$src .= '<th>'.t::h( $column['name'] ).'</th>';
				$src .= '<td>'.t::h( $column['logical_name'] ).'</td>';
				$src .= '<td>'.t::h( $column['type'] ).'</td>';
				$src .= '<td style="text-align:right;">'.t::h( $column['size'] ).'</td>';
				$src .= '<td>'.t::h( $column['default'] ).'</td>';
				$src .= '<td style="text-align:center;">'.($column['not_null']?'○':'').'</td>';
				$src .= '<td>'.t::h( $column['key_type'] ).'</td>';
				$src .= '<td>'.t::h( $column['foreign_key'] ).'</td>';
				$src .= '<td style="text-align:center;">'.($column['unique']?'○':'').'</td>';
				$src .= '</tr>';
			}
			$src .= '</table>';
// test::var_dump( $table_definition );
		}
		return $src;
	}

}

?>