<?php

/* --------------------------------------
 * ログインユーザーのIDとパスワードの対
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
 * 無効にする機能
 */
$conf->disabled = array(
	// 'db',
	// 'files',
);

/* --------------------------------------
 * データベースの接続情報
 */
$conf->databases = array(

	/* 設定例: SQLite
	 */
	/* *
	"sqlite_sample" => array(
		"driver" => "sqlite",
		"database" => "database.db",
	), /* */

	/* 設定例: MySQL
	 */
	/* *
	"mysql_sample" => array(
		"driver" => "mysql",
		"host" => "127.0.0.1",
		"port" => 3306,
		"database" => "dbname",
		"username" => "user",
		"password" => "passwd",
	), /* */

	/* 設定例: PostgreSQL
	 */
	/* *
	"pgsql_sample" => array(
		"driver" => "pgsql",
		"host" => "127.0.0.1",
		"port" => 5432,
		"database" => "dbname",
		"username" => "user",
		"password" => "passwd",
	), /* */

	/* 設定例: DSN
	 * `dsn` を直接設定することもできます。
	 * 設定方法は、PHP の PDOマニュアルを参照してください。
	 */
	/* *
	"dsn_sample" => array(
		"dsn" => "sqlite::memory:",
		"username" => null,
		"password" => null,
		"options" => array(),
	), /* */

);



/* --------------------------------------
 * 表示させないファイルの一覧
 */
$conf->files_paths_invisible = array();

/* --------------------------------------
 * 編集できなくするファイルの一覧
 */
$conf->files_paths_readonly = array(
	'/*',
);
