<?php
/**
 * class pxplugin_DBML_funcs_autoform
 */
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
	 * フォームの種類
	 *
	 * insert|update|delete
	 */
	private $form_type;
	/**
	 * 対象レコードのID
	 */
	private $target;

	/**
	 * コンストラクタ
	 * 
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

		$this->form_type = 'insert';
		switch( @strtolower( $this->options['method'] ) ){
			case 'insert':
			case 'update':
			case 'delete':
				$this->form_type = @strtolower( $this->options['method'] );
				break;
		}
		if( $this->form_type == 'update' || $this->form_type == 'delete' ){
			$this->target = $options['target'];
		}
	}


	/**
	 * フォーム生成を実行する。
	 * 
	 * @return string|void 画面を描画するコンテンツエリアのHTMLソースを返します。またはリダイレクト処理の場合は値を返しません。
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
				if( $this->form_type == 'update' || $this->form_type == 'delete' ){
					$this->load_default_value( $this->target );//デフォルトの値を入力
				}
			}
			$src .= $this->page_input($errors);
		}
		return $src;
	}

	/**
	 * 入力画面を作る。
	 */
	private function page_input( $errors ){
		if( $this->form_type == 'delete' ){
			$src = '';
			ob_start(); ?>
<form action="?" method="post">
	<p>
		この項目を削除します。よろしいですか？<br />
	</p>
	<div class="unit form_buttons">
		<ul>
			<li class="form_buttons-submit"><button name="mode" value="apply">削除する</button></li>
		</ul>
	</div><!-- /.form_buttons -->
</form>
<?php
			$src .= ob_get_clean();
			return $src;
		}
		$src = '';
		ob_start(); ?>
<form action="?" method="post">
<table class="form_elements" style="width:100%;"><?php
foreach( $this->table_definition['columns'] as $column ){
	switch( strtolower($column['type']) ){
		//自動処理系の型
		case 'serial':
		case 'serial_s':
		case 'create_date':
		case 'update_date':
		case 'delete_date':
		case 'delete_flg':
			continue 2;
	}
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
	 * 確認画面を作る。
	 */
	private function page_confirm(){
		$src = '';
		ob_start(); ?>
<form action="?" method="post">
<table class="form_elements" style="width:100%;"><?php
foreach( $this->table_definition['columns'] as $column ){
	switch( strtolower($column['type']) ){
		//自動処理系の型
		case 'serial':
		case 'serial_s':
		case 'create_date':
		case 'update_date':
		case 'delete_date':
		case 'delete_flg':
			continue 2;
	}
	print '<tr>'."\n";
	print '<th>'.t::h( $column['logical_name'] ).'</th>'."\n";
	print '<td>';
	switch( strtolower($column['type']) ){
		case 'password':
			print '********';
			break;
		default:
			print ''.t::h( $this->px->req()->get_param($column['name']) ).'';
			break;
	}
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
	 * 処理を実行する。
	 */
	private function apply(){
		$values = array();
		foreach( $this->table_definition['columns'] as $column ){
			switch( strtolower($column['type']) ){
				//自動処理系の型
				case 'serial':
					if( $this->form_type == 'insert' ){
						$values[$column['name']] = null;//追加
					}
					break;
				case 'serial_s':
					if( $this->form_type == 'insert' ){
						$values[$column['name']] = uniqid();//追加
					}
					break;
				case 'create_date':
					if( $this->form_type == 'insert' ){
						$values[$column['name']] = $this->px->dbh()->int2datetime( time() );//追加
					}
					break;
				case 'update_date':
					// $values[$column['name']] = null;//追加
					if( $this->form_type == 'update' || $this->form_type == 'delete' ){
						$values[$column['name']] = $this->px->dbh()->int2datetime( time() );//編集・削除
					}
					break;
				case 'delete_date':
					// $values[$column['name']] = null;//追加
					if( $this->form_type == 'delete' ){
						$values[$column['name']] = $this->px->dbh()->int2datetime( time() );//削除
					}
					break;
				case 'delete_flg':
					$values[$column['name']] = 0;//追加
					if( $this->form_type == 'delete' ){
						$values[$column['name']] = 1;//削除
					}
					break;
				case 'password':
					if( strlen($this->px->req()->get_param($column['name'])) ){
						$values[$column['name']] = $this->px->user()->crypt_user_password( $this->px->req()->get_param($column['name']) );
					}
					break;
				default:
					if( !is_null($this->px->req()->get_param($column['name'])) ){
						$values[$column['name']] = $this->px->req()->get_param($column['name']);
					}
					break;
			}
		}
		switch( $this->form_type ){
			case 'insert':
				$result = $this->dbml->insert( $this->table_name, $values );
				break;
			case 'update':
				$result = $this->dbml->update( $this->table_name, array('id'=>$this->target), $values );
				break;
			case 'delete':
				$result = $this->dbml->update( $this->table_name, array('id'=>$this->target), $values );
				break;
		}
		if( !$result ){
			return '<p class="error">送信失敗しました。</p>';
		}
		return $this->px->redirect( $this->px->theme()->href('?mode=thanks') );
	}

	/**
	 * 完了画面を作る。
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
	 * 入力内容を検証する。
	 * 
	 * @return array 検出したエラーを格納する連想配列。エラーがない場合は空の配列
	 */
	private function validate(){
		if( $this->form_type == 'delete' ){
			// delete の場合バリデーションエラーはない。
			return array();
		}
		$errors = array();
		foreach( $this->table_definition['columns'] as $column ){
			$input_value = $this->px->req()->get_param($column['name']);
			$type = strtolower($column['type']);
			if( $type == 'serial' ){
				continue;
			}
			elseif( $type == 'serial_s' ){
				continue;
			}
			elseif( $type == 'create_date' ){
				continue;
			}
			elseif( $type == 'update_date' ){
				continue;
			}
			elseif( $type == 'delete_date' ){
				continue;
			}
			elseif( $type == 'delete_flg' ){
				continue;
			}
			elseif( $type == 'varchar' ){
				continue;
			}
			elseif( $type == 'email' ){
				if( !preg_match( '/^.+\@.+$/s', $input_value ) ){
					$errors[$column['name']] = array('message'=>'形式が不正です。');
				}

			}
			elseif( $type == 'int' ){
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

	/**
	 * 編集前のデータをロードする。
	 */
	private function load_default_value($target = null){
		$val = $this->dbml->select( $this->table_name, array('id'=>$target) );
		foreach( $this->table_definition['columns'] as $column ){
			switch( strtolower($column['type']) ){
				//自動処理系の型
				case 'serial':
				case 'serial_s':
				case 'create_date':
				case 'update_date':
				case 'delete_date':
				case 'delete_flg':
					continue 2;
				case 'password'://パスワードは復元できない
					continue 2;
			}
			$this->px->req()->set_param( $column['name'], $val[0][$column['name']] );
		}
		return true;
	}

}

?>