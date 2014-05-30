<?php

/**
 * PX Plugin "DBML"
 */
class pxplugin_DBML_funcs_autoform{

	private $px;
	private $dbml;
	private $table_name;
	private $options;
	private $table_definition;

	/**
	 * コンストラクタ
	 * @param $px = PxFWコアオブジェクト
	 * @param $dbml = プラグインオブジェクト
	 * @param $table_name = 対象のテーブル名
	 * @param $options = オプション
	 */
	public function __construct( $px, $dbml, $table_name, $options = array() ){
		$this->px = $px;
		$this->dbml = $dbml;
		$this->table_name = $table_name;
		$this->options = $options;
		$this->table_definition = $this->dbml->get_table_definition( $this->table_name );

		$method = 'insert';
		if( strlen( $this->options['target_record'] ) ){
			$method = 'update';
		}
	}


	/**
	 * フォーム生成を実行する
	 */
	public function execute(){
		// test::var_dump($this->table_name);
		// test::var_dump($this->table_definition);

		$mode = $this->px->req()->get_param('mode');
		$errors = $this->validate();

		$src = '';
		if( $mode == 'thanks' ){
			$src .= $this->page_thanks();

		}elseif( $mode == 'confirm' && !count($errors) ){
			$src .= $this->page_confirm();

		}elseif( $mode == 'apply' && !count($errors) ){
			$src .= $this->apply();

		}else{
			if( !strlen($mode) ){
				$errors = array();
			}
			$src .= $this->page_input($errors);
		}
		return $src;
	}

	/**
	 * 入力画面を作る
	 */
	private function page_input( $errors ){
		$src = '';
		ob_start(); ?>
<form action="?" method="post">
<table class="form_elements" style="width:100%;"><?php
foreach( $this->table_definition['columns'] as $column ){
	print '<tr>'."\n";
	print '<th>'.t::h( $column['logical_name'] ).'</th>'."\n";
	print '<td>';
	switch( strtolower($column['type']) ){
		case 'text':
			print '<textarea name="'.t::h( $column['name'] ).'">'.t::h( $this->px->req()->get_param($column['name']) ).'</textarea>';
			break;
		case 'password':
			print '<input type="password" name="'.t::h( $column['name'] ).'" value="'.t::h( $this->px->req()->get_param($column['name']) ).'" />';
			break;
		case 'int':
		case 'datetime':
		case 'varchar':
		default:
			print '<input type="text" name="'.t::h( $column['name'] ).'" value="'.t::h( $this->px->req()->get_param($column['name']) ).'" />';
			break;
	}
	if( $errors[$column['name']] ){
		print '<div class="error">'.t::h( $errors[$column['name']]['message'] ).'</div>'."\n";
	}
	print '</td>'."\n";
	print '</tr>'."\n";
}
?></table>
	<div class="unit form_buttons">
		<ul>
			<li class="form_buttons-submit"><button name="mode" value="confirm">確認する</button></li>
		</ul>
	</div><!-- /.form_buttons -->
</form>
<?php /***
<div class="unit form_buttons">
	<ul>
		<li class="form_buttons-cancel"><button onclick="">キャンセル</button></li>
	</ul>
</div><!-- /.form_buttons -->
/***/ ?>
<?php
		$src .= ob_get_clean();
		return $src;
	}

	/**
	 * 確認画面を作る
	 */
	private function page_confirm(){
		$src = '';
		ob_start(); ?>
<form action="?" method="post">
<table class="form_elements" style="width:100%;"><?php
foreach( $this->table_definition['columns'] as $column ){
	print '<tr>'."\n";
	print '<th>'.t::h( $column['logical_name'] ).'</th>'."\n";
	print '<td>';
	print ''.t::h( $this->px->req()->get_param($column['name']) ).'';
	print '<input type="hidden" name="'.t::h( $column['name'] ).'" value="'.t::h( $this->px->req()->get_param($column['name']) ).'" />';
	print '</td>'."\n";
	print '</tr>'."\n";
}
?></table>
	<div class="unit form_buttons">
		<ul>
			<li class="form_buttons-submit"><button name="mode" value="apply">送信する</button></li>
			<li class="form_buttons-revise"><button name="mode" value="input">修正する</button></li>
		</ul>
	</div><!-- /.form_buttons -->
</form>
<?php
		$src .= ob_get_clean();
		return $src;
	}

	/**
	 * 処理を実行する
	 */
	private function apply(){
		$values = array();
		foreach( $this->table_definition['columns'] as $column ){
			$values[$column['name']] = $this->px->req()->get_param($column['name']);
		}
		$result = $this->dbml->insert( $this->table_name, $values );
		if( !$result ){
			return '<p class="error">送信失敗しました。</p>';
		}
		return $this->px->redirect( $this->px->theme()->href('?mode=thanks') );
	}

	/**
	 * 完了画面を作る
	 */
	private function page_thanks(){
		$src = '';
		ob_start(); ?>
<p>完了しました。</p>
<?php
		$src .= ob_get_clean();
		return $src;
	}

	/**
	 * 入力内容を検証する
	 */
	private function validate(){
		$errors = array();
		foreach( $this->table_definition['columns'] as $column ){
			$input_value = $this->px->req()->get_param($column['name']);
			if( $column['type'] == 'varchar' ){

			}
			elseif( $column['type'] == 'email' ){
				if( !preg_match( '/^.+\@.+$/s', $input_value ) ){
					$errors[$column['name']] = array('message'=>'形式が不正です。');
				}

			}
			elseif( $column['type'] == 'int' ){
				if( !preg_match( '/^(?:[1-9][0-9]*|0)$/s', $input_value ) ){
					$errors[$column['name']] = array('message'=>'使用できない文字が含まれています。');
				}
			}

			if( $column['not_null'] && !strlen($input_value) ){
				$errors[$column['name']] = array('message'=>'必須項目です。');
			}elseif( strlen($input_value) > $column['size'] ){
				$errors[$column['name']] = array('message'=>$column['size'].'バイト以内で入力してください。');
			}

			if( !$column['not_null'] && !strlen($input_value) ){
				unset( $errors[$column['name']] );
			}
		}

		return $errors;
	}
}

?>