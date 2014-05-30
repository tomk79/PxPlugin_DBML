# PxPlugin "DBML" for Pickles Framework 1.x

<a href="http://pickles.pxt.jp/">Pickles Framework</a> に組み込んで使用するプラグインです。

詳しい説明は準備中です。


## インストール方法

1. PxFW(Pickles Framework) をセットアップする。
2. PxFW の plugins ディレクトリに、
   DBMLディレクトリをアップする。

* Pickles Framework については、
  次のページから入手してください。
  https://github.com/tomk79/PxFW-1.x/tags


## 拡張コンフィグ項目

---- ここから
[plugin-DBML]
; プラグイン "DBML" 拡張項目
path_dbml = "./_PX/data/database.xml" ; DBMLファイルのパス
---- ここまで

## DBMLファイルサンプル

```
<?xml version="1.0" encoding="utf-8"?>
<dbml>
	<tables>
		<table name="{$prefix}_user" logical-name="PxFWユーザーマスタテーブル" skip-create="true">
			<column name="id"           logical-name="ユーザーID"                     type="varchar"  size="64"  not-null="true"                key-type="master"               />
			<column name="user_account" logical-name="ユーザーアカウント"             type="varchar"  size="64"  not-null="true"                                  unique="true" />
			<column name="user_pw"      logical-name="パスワード"                     type="varchar"  size="32"  not-null="true"                                                />
			<column name="user_name"    logical-name="ユーザー名"                     type="varchar"  size="128"                                                                />
			<column name="user_email"   logical-name="メールアドレス"                 type="varchar"  size="128"                                                                />
			<column name="auth_level"   logical-name="認証レベル"                     type="int"      size="1"   not-null="true" default="0"                                    />
			<column name="tmp_pw"       logical-name="一時パスワード"                 type="varchar"  size="32"                                                                 />
			<column name="tmp_email"    logical-name="一時メールアドレス"             type="varchar"  size="128"                                                                />
			<column name="tmp_data"     logical-name="その他の一時的なデータ"         type="text"                                                                               />
			<column name="login_date"   logical-name="最後にログインした日時"         type="datetime"                            default=""                                     />
			<column name="set_pw_date"  logical-name="最後にパスワードを設定した日時" type="datetime"                            default=""                                     />
			<column name="create_date"  logical-name="登録日時"                       type="datetime"                            default=""                                     />
			<column name="update_date"  logical-name="更新日時"                       type="datetime"                            default=""                                     />
			<column name="delete_date"  logical-name="削除日時"                       type="datetime"                            default=""                                     />
			<column name="delete_flg"   logical-name="削除フラグ"                     type="int"      size="1"   not-null="true" default="0"                                    />
		</table>

	</tables>
</dbml>
```


------
(C)Tomoya Koyanagi.
http://www.pxt.jp/

