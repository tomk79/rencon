<?php

/* --------------------------------------
 * ログインユーザーのIDとパスワードの対を設定します。
 * 
 * rencon の初期画面は、ログイン画面から始まります。
 * `$conf->users` に 登録されたユーザーが、ログインを許可されます。
 * ユーザーIDを キー に、sha1ハッシュ化されたパスワード文字列を 値 に持つ連想配列で設定してください。
 * ユーザーは、複数登録できます。
 */
$conf->users = array(
	"admin" => sha1("admin"),
);

/*
 * ユーザーを登録しない場合は、ログインなしで使用できます。
 * そうしたい場合は、`$conf->users` を `null` に設定してください。
 */
// $conf->users = null;


/* --------------------------------------
 * データベースの接続情報を設定します。
 */
$conf->databases = array(
	"main_sample" => array(
		"driver" => "sqlite",
		"database" => "database.db",
	),
	"sqlite_memory_sample" => array(
		"driver" => "sqlite",
		"database" => ":memory:",
	),
	"dsn_sample" => array(
		"dsn" => "sqlite::memory:",
		"username" => null,
		"password" => null,
		"options" => array(),
	),
	"db2_mysql_sample" => array(
		"driver" => "mysql",
		"host" => "127.0.0.1",
		"port" => 3306,
		"database" => "dbname",
		"username" => "user",
		"password" => "passwd",
	),
	"db3_pgsql_sample" => array(
		"driver" => "pgsql",
		"host" => "127.0.0.1",
		"port" => 5432,
		"database" => "dbname",
		"username" => "user",
		"password" => "passwd",
	),
);
