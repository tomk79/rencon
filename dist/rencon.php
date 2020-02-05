<?php
/* ---------------------
  rencon v0.0.1-alpha.1+dev
  (C)Tomoya Koyanagi
  -- developers preview build @2020-02-05T03:17:50+00:00 --
--------------------- */

// =-=-=-=-=-=-=-=-=-=-=-= Configuration START =-=-=-=-=-=-=-=-=-=-=-=
$conf = new stdClass();



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


// =-=-=-=-=-=-=-=-=-=-=-= / Configuration END =-=-=-=-=-=-=-=-=-=-=-=



$rencon = new rencon($conf);
$rencon->execute();
exit;

?><?php
/**
 * rencon core class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon{
	private $conf;
	private $theme;
	private $resourceMgr;
	private $request;
	private $view;

	/**
	 * Constructor
	 */
	public function __construct( $conf ){
		$this->conf = new rencon_conf( $conf );
		$this->theme = new rencon_theme($this);
		$this->resourceMgr = new rencon_resourceMgr($this);
		$this->request = new rencon_request();
		$this->view = new rencon_view($this);
	}

	/**
	 * アプリケーションを実行
	 */
	public function execute(){
		if( strlen( $this->request->get_param('res') ) ){
			$this->resourceMgr->echo_resource( $this->request->get_param('res') );
			return;
		}

		header('Content-type: text/html'); // default

		$login = new rencon_login($this);
		if( !$login->check() ){
			$login->please_login();
			exit;
		}

		$action = $this->request->get_param('a');
		$action_ary = explode( '.', $action );
		// var_dump($action_ary);

		if( $action == 'logout' ){
			$login->logout();
			exit;

		}elseif( !strlen($action) ){
			$this->theme->set_h1('ホーム');
			ob_start(); ?>
<ul>
	<li><a href="?a=db">データベース管理</a></li>
	<li><a href="?a=files">ファイルとフォルダ</a></li>
</ul>
<?php
			echo $this->theme->bind( ob_get_clean() );
			exit;
		}

		$router = new rencon_router($this);
		$router->route();

		$this->notfound();
		exit;
	}


	/**
	 * Not Found 画面を出力
	 */
	public function notfound(){
		header('HTTP/1.1 404 Not Found');
		header('Content-type: text/html');
		$this->theme->set_h1('Not Found');
		echo $this->theme->bind('<p>お探しの画面はありません。<a href="?">戻る</a></p>');
		exit;
	}

	/**
	 * Forbidden 画面を出力
	 */
	public function forbidden(){
		header('HTTP/1.1 403 Forbidden');
		header('Content-type: text/html');
		$this->theme->set_h1('Forbidden');
		echo $this->theme->bind('<p>ログインしてください。<a href="?a='.urlencode($this->action()).'">戻る</a></p>');
		exit;
	}

	/**
	 * 現在のアクションを返す
	 */
	public function action(){
		$action = $this->request->get_param('a');
		if( !strlen($action) ){
			return '';
		}
		$action_ary = explode('.', $action);
		if( count($action_ary) == 1 ){
			$action_ary[1] = 'index';
		}elseif( array_key_exists(1, $action_ary) && !strlen($action_ary[1]) ){
			$action_ary[1] = 'index';
		}
		return implode('.', $action_ary);
	}

	/**
	 * リンク先を返す
	 */
	public function href( $action = '' ){
		if( is_array( $action ) ){
			$action = implode('.', $action);
		}
		return '?a='.urlencode($action);
	}

	/**
	 * Config Object
	 */
	public function conf(){
		return $this->conf;
	}

	/**
	 * Request Object
	 */
	public function req(){
		return $this->request;
	}

	/**
	 * Theme Object
	 */
	public function theme(){
		return $this->theme;
	}

	/**
	 * View Object
	 */
	public function view(){
		return $this->view;
	}
}
?>
<?php
/**
 * rencon conf class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_conf{
	private $conf;
	public $users;
	public $disabled;
	public $databases;
	public $files_paths_invisible;
	public $files_paths_readonly;

	/**
	 * Constructor
	 */
	public function __construct( $conf ){
		$this->conf = (object) $conf;

		// --------------------------------------
		// $conf->users
		$this->users = null;
		if( property_exists( $conf, 'users' ) && !is_null( $conf->users ) ){
			$this->users = (array) $conf->users;
		}

		// --------------------------------------
		// $conf->disabled
		$this->disabled = array();
		if( property_exists( $conf, 'disabled' ) && !is_null( $conf->disabled ) ){
			$this->disabled = (array) $conf->disabled;
		}

		// --------------------------------------
		// $conf->databases
		$this->databases = null;
		if( property_exists( $conf, 'databases' ) && !is_null( $conf->databases ) ){
			$this->databases = (array) $conf->databases;
		}

		// --------------------------------------
		// $conf->files_paths_invisible
		$this->files_paths_invisible = null;
		if( property_exists( $conf, 'files_paths_invisible' ) && !is_null( $conf->files_paths_invisible ) ){
			$this->files_paths_invisible = (array) $conf->files_paths_invisible;
		}

		// --------------------------------------
		// $conf->files_paths_readonly
		$this->files_paths_readonly = null;
		if( property_exists( $conf, 'files_paths_readonly' ) && !is_null( $conf->files_paths_readonly ) ){
			$this->files_paths_readonly = (array) $conf->files_paths_readonly;
		}

	}

	/**
	 * ログインが必要か？
	 */
	public function is_login_required(){
		if( !is_array($this->users) ){
			return false;
		}
		return true;
	}

}
?>
<?php
/**
 * rencon router class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_router{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * ルーティング
	 */
	public function route(){
		$action = $this->rencon->action();
		$action_ary = explode('.', $action);

		$app = $action_ary[0];
		$act = $action_ary[1];

		if( !strlen($app) ){
			return false;
		}

		// 利用制限の処理
		$disabled = $this->rencon->conf()->disabled;
		if( array_search($app, $disabled) !== false ){
			$this->rencon->theme()->set_h1($app);
			ob_start(); ?>
<p>この機能 <code><?= htmlspecialchars($app) ?></code> は、利用が制限されています。</p>
<p>設定を編集して、制限を解除することができます。</p>
<?php
			$code = ob_get_clean();
			echo $this->rencon->theme()->bind( $code );
			exit;
		}

		$className = 'rencon_apps_'.$app.'_ctrl';
		if( class_exists( $className ) ){
			$appObj = new $className( $this->rencon );
			if( method_exists($appObj, $act) ){
				return $appObj->$act();
			}
		}

		return false;
	}

}
?>
<?php
/**
 * rencon theme class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_theme{
	private $rencon;
	private $h1 = 'Home';

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * h1テキストを登録
	 */
	public function set_h1( $h1 ){
		$this->h1 = $h1;
		return true;
	}

	/**
	 * テーマにコンテンツを包んで返す
	 */
	public function bind( $content ){
		ob_start();
		?>
<!doctype html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>rencon</title>
		<meta name="robots" content="nofollow, noindex, noarchive" />
		<link rel="stylesheet" href="?res=bootstrap4/css/bootstrap.min.css" />
		<script src="?res=bootstrap4/js/bootstrap.min.js"></script>
		<link rel="stylesheet" href="?res=styles/common.css" />
	</head>
	<body>
		<div class="container">
			<p><a href="<?= htmlspecialchars($this->rencon->href()); ?>">rencon</a></p>
		</div>
		<div class="container">
			<h1><?= htmlspecialchars( nl2br( $this->h1 ) ); ?></h1>
<div class="contents">
<?= $content ?>
</div>

		</div>
		<div class="container">
<?php if( $this->rencon->conf()->is_login_required() ){ ?>
			<p><a href="?a=logout">Logout</a></p>
<?php } ?>
		</div>
	</body>
</html>
<?php
		$rtn = ob_get_clean();
		return $rtn;
	}
}
?>
<?php
/**
 * rencon login class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_login{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * ログインしているか調べる
	 */
	public function check(){

		if( !$this->rencon->conf()->is_login_required() ){
			// ユーザーが設定されていなければ、ログインの評価を行わない。
			return true;
		}

		$users = (array) $this->rencon->conf()->users;

		$login_id = $this->rencon->req()->get_param('login_id');
		$login_pw = $this->rencon->req()->get_param('login_pw');
		$login_try = $this->rencon->req()->get_param('login_try');
		if( strlen( $login_try ) && strlen($login_id) && strlen($login_pw) ){
			// ログイン評価
			if( array_key_exists($login_id, $users) && $users[$login_id] == sha1($login_pw) ){
				$this->rencon->req()->set_session('rencon_ses_login_id', $login_id);
				$this->rencon->req()->set_session('rencon_ses_login_pw', sha1($login_pw));
				return true;
			}
		}


		$login_id = $this->rencon->req()->get_session('rencon_ses_login_id');
		$login_pw_hash = $this->rencon->req()->get_session('rencon_ses_login_pw');
		if( strlen($login_id) && strlen($login_pw_hash) ){
			// ログイン済みか評価
			if( array_key_exists($login_id, $users) && $users[$login_id] == $login_pw_hash ){
				return true;
			}
			$this->rencon->req()->delete_session('rencon_ses_login_id');
			$this->rencon->req()->delete_session('rencon_ses_login_pw');
			$this->rencon->forbidden();
			exit;
		}

		return false;
	}

	/**
	 * ログイン画面を表示して終了する
	 */
	public function please_login(){
		header('Content-type: text/html');
		ob_start();
		?>
<!doctype html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>rencon</title>
		<meta name="robots" content="nofollow, noindex, noarchive" />
		<link rel="stylesheet" href="?res=bootstrap4/css/bootstrap.min.css" />
		<script src="?res=bootstrap4/js/bootstrap.min.js"></script>
		<link rel="stylesheet" href="?res=styles/common.css" />
	</head>
	<body>
		<div class="container">
			<h1>rencon</h1>
			<?php if( strlen($this->rencon->req()->get_param('login_try')) ){ ?>
				<div class="alert alert-danger" role="alert">
					<div>IDまたはパスワードが違います。</div>
				</div>
			<?php } ?>

			<form action="?" method="post">
ID: <input type="text" name="login_id" value="" class="form-element" />
PW: <input type="password" name="login_pw" value="" class="form-element" />
<input type="submit" value="Login" class="btn btn-primary" />
<input type="hidden" name="login_try" value="1" />
<input type="hidden" name="a" value="<?= htmlspecialchars($this->rencon->action()) ?>" />
			</form>
		</div>
	</body>
</html>
<?php
		$rtn = ob_get_clean();
		print $rtn;
		exit;
	}

	/**
	 * ログアウトして終了する
	 */
	public function logout(){
		$this->rencon->req()->delete_session('rencon_ses_login_id');
		$this->rencon->req()->delete_session('rencon_ses_login_pw');

		header('Content-type: text/html');
		ob_start();
		?>
<!doctype html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>rencon</title>
		<meta name="robots" content="nofollow, noindex, noarchive" />
		<link rel="stylesheet" href="?res=bootstrap4/css/bootstrap.min.css" />
		<script src="?res=bootstrap4/js/bootstrap.min.js"></script>
		<link rel="stylesheet" href="?res=styles/common.css" />
	</head>
	<body>
		<div class="container">
			<h1>rencon</h1>
			<p>Logged out.</p>
			<p><a href="?">Back to Home</a></p>
		</div>
	</body>
</html>
<?php
		$rtn = ob_get_clean();
		print $rtn;
		exit;
	}

}
?>
<?php
/**
 * tomk79/filesystem core class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_filesystem{

	/**
	 * ファイルおよびディレクトリ操作時のデフォルトパーミッション
	 */
	private $default_permission = array('dir'=>0775,'file'=>0775);
	/**
	 * ファイルシステムの文字セット
	 */
	private $filesystem_encoding = null;

	/**
	 * コンストラクタ
	 *
	 * @param object $conf 設定オブジェクト
	 */
	public function __construct($conf=null){
		$conf = json_decode( json_encode($conf), true );
		if(!is_array($conf)){
			$conf = array();
		}
		if( array_key_exists('file_default_permission', $conf) && strlen( $conf['file_default_permission'] ) ){
			$this->default_permission['file'] = octdec( $conf['file_default_permission'] );
		}
		if( array_key_exists('dir_default_permission', $conf) && strlen( $conf['dir_default_permission'] ) ){
			$this->default_permission['dir'] = octdec( $conf['dir_default_permission'] );
		}
		if( array_key_exists('filesystem_encoding', $conf) && strlen( $conf['filesystem_encoding'] ) ){
			$this->filesystem_encoding = trim( $conf['filesystem_encoding'] );
		}
	}

	/**
	 * 書き込み/上書きしてよいアイテムか検証する。
	 *
	 * @param string $path 検証対象のパス
	 * @return bool 書き込み可能な場合 `true`、不可能な場合に `false` を返します。
	 */
	public function is_writable( $path ){
		$path = $this->localize_path($path);
		if( !$this->is_file($path) ){
			return @is_writable( dirname($path) );
		}
		return @is_writable( $path );
	}//is_writable()

	/**
	 * 読み込んでよいアイテムか検証する。
	 *
	 * @param string $path 検証対象のパス
	 * @return bool 読み込み可能な場合 `true`、不可能な場合に `false` を返します。
	 */
	public function is_readable( $path ){
		$path = $this->localize_path($path);
		return @is_readable( $path );
	}//is_readable()

	/**
	 * ファイルが存在するかどうか調べる。
	 *
	 * @param string $path 検証対象のパス
	 * @return bool ファイルが存在する場合 `true`、存在しない場合、またはディレクトリが存在する場合に `false` を返します。
	 */
	public function is_file( $path ){
		$path = $this->localize_path($path);
		return @is_file( $path );
	}//is_file()

	/**
	 * シンボリックリンクかどうか調べる。
	 *
	 * @param string $path 検証対象のパス
	 * @return bool ファイルがシンボリックリンクの場合 `true`、存在しない場合、それ以外の場合に `false` を返します。
	 */
	public function is_link( $path ){
		$path = $this->localize_path($path);
		return @is_link( $path );
	}//is_link()

	/**
	 * ディレクトリが存在するかどうか調べる。
	 *
	 * @param string $path 検証対象のパス
	 * @return bool ディレクトリが存在する場合 `true`、存在しない場合、またはファイルが存在する場合に `false` を返します。
	 */
	public function is_dir( $path ){
		$path = $this->localize_path($path);
		return @is_dir( $path );
	}//is_dir()

	/**
	 * ファイルまたはディレクトリが存在するかどうか調べる。
	 *
	 * @param string $path 検証対象のパス
	 * @return bool ファイルまたはディレクトリが存在する場合 `true`、存在しない場合に `false` を返します。
	 */
	public function file_exists( $path ){
		$path = $this->localize_path($path);
		return @file_exists( $path );
	}//file_exists()

	/**
	 * ディレクトリを作成する。
	 *
	 * @param string $dirpath 作成するディレクトリのパス
	 * @param int $perm 作成するディレクトリに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function mkdir( $dirpath , $perm = null ){
		$dirpath = $this->localize_path($dirpath);

		if( $this->is_dir( $dirpath ) ){
			// 既にディレクトリがあったら、作成を試みない。
			$this->chmod( $dirpath , $perm );
			return true;
		}
		$result = @mkdir( $dirpath );
		$this->chmod( $dirpath , $perm );
		clearstatcache();
		return	$result;
	}//mkdir()

	/**
	 * ディレクトリを作成する(上層ディレクトリも全て作成)
	 *
	 * @param string $dirpath 作成するディレクトリのパス
	 * @param int $perm 作成するディレクトリに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function mkdir_r( $dirpath , $perm = null ){
		$dirpath = $this->localize_path($dirpath);
		if( $this->is_dir( $dirpath ) ){
			return true;
		}
		if( $this->is_file( $dirpath ) ){
			return false;
		}
		$patharray = explode( DIRECTORY_SEPARATOR , $this->localize_path( $this->get_realpath($dirpath) ) );
		$targetpath = '';
		foreach( $patharray as $idx=>$Line ){
			if( !strlen( $Line ) || $Line == '.' || $Line == '..' ){ continue; }
			if(!($idx===0 && DIRECTORY_SEPARATOR == '\\' && preg_match('/^[a-zA-Z]\:$/s', $Line))){
				$targetpath .= DIRECTORY_SEPARATOR;
			}
			$targetpath .= $Line;

			// clearstatcache();
			if( !$this->is_dir( $targetpath ) ){
				$targetpath = $this->localize_path( $targetpath );
				if( !$this->mkdir( $targetpath , $perm ) ){
					return false;
				}
			}
		}
		return true;
	}//mkdir_r()

	/**
	 * ファイルやディレクトリを中身ごと完全に削除する。
	 *
	 * このメソッドは、ファイルやシンボリックリンクも削除します。
	 * ディレクトリを削除する場合は、中身ごと完全に削除します。
	 * シンボリックリンクは、その先を追わず、シンボリックリンク本体のみを削除します。
	 *
	 * @param string $path 対象のパス
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function rm( $path ){
		$path = $this->localize_path($path);

		if( !$this->is_writable( $path ) ){
			return false;
		}
		$path = @realpath( $path );
		if( $path === false ){ return false; }
		if( $this->is_file( $path ) || $this->is_link( $path ) ){
			// ファイルまたはシンボリックリンクの場合の処理
			$result = @unlink( $path );
			return	$result;

		}elseif( $this->is_dir( $path ) ){
			// ディレクトリの処理
			$flist = $this->ls( $path );
			if( is_array($flist) ){
				foreach ( $flist as $Line ){
					if( $Line == '.' || $Line == '..' ){ continue; }
					$this->rm( $path.DIRECTORY_SEPARATOR.$Line );
				}
			}
			$result = @rmdir( $path );
			return	$result;

		}

		return false;
	}//rm()

	/**
	 * ディレクトリを削除する。
	 *
	 * このメソッドはディレクトリを削除します。
	 * 中身のない、空のディレクトリ以外は削除できません。
	 *
	 * @param string $path 対象ディレクトリのパス
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function rmdir( $path ){
		$path = $this->localize_path($path);

		if( !$this->is_writable( $path ) ){
			return false;
		}
		$path = @realpath( $path );
		if( $path === false ){
			return false;
		}
		if( $this->is_file( $path ) || $this->is_link( $path ) ){
			// ファイルまたはシンボリックリンクの場合の処理
			// ディレクトリ以外は削除できません。
			return false;

		}elseif( $this->is_dir( $path ) ){
			// ディレクトリの処理
			// rmdir() は再帰的削除を行いません。
			// 再帰的に削除したい場合は、代わりに `rm()` または `rmdir_r()` を使用します。
			return @rmdir( $path );
		}

		return false;
	}//rmdir()

	/**
	 * ディレクトリを再帰的に削除する。
	 *
	 * このメソッドはディレクトリを再帰的に削除します。
	 * 中身のない、空のディレクトリ以外は削除できません。
	 *
	 * @param string $path 対象ディレクトリのパス
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function rmdir_r( $path ){
		$path = $this->localize_path($path);

		if( !$this->is_writable( $path ) ){
			return false;
		}
		$path = @realpath( $path );
		if( $path === false ){
			return false;
		}
		if( $this->is_file( $path ) || $this->is_link( $path ) ){
			// ファイルまたはシンボリックリンクの場合の処理
			// ディレクトリ以外は削除できません。
			return false;

		}elseif( $this->is_dir( $path ) ){
			// ディレクトリの処理
			$filelist = $this->ls($path);
			if( is_array($filelist) ){
				foreach( $filelist as $basename ){
					if( $this->is_file( $path.DIRECTORY_SEPARATOR.$basename ) ){
						$this->rm( $path.DIRECTORY_SEPARATOR.$basename );
					}else if( !$this->rmdir_r( $path.DIRECTORY_SEPARATOR.$basename ) ){
						return false;
					}
				}
			}
			return $this->rmdir( $path );
		}

		return false;
	}//rmdir_r()


	/**
	 * ファイルを上書き保存する。
	 *
	 * このメソッドは、`$filepath` にデータを保存します。
	 * もともと保存されていた内容は破棄され、新しいデータで上書きします。
	 *
	 * @param string $filepath 保存先ファイルのパス
	 * @param string $content 保存する内容
	 * @param int $perm 保存するファイルに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function save_file( $filepath , $content , $perm = null ){
		$filepath = $this->get_realpath($filepath);
		$filepath = $this->localize_path($filepath);

		if( $this->is_dir( $filepath ) ){
			return false;
		}
		if( !$this->is_writable( $filepath ) ){
			return false;
		}

		if( !strlen( $content ) ){
			// 空白のファイルで上書きしたい場合
			if( $this->is_file( $filepath ) ){
				@unlink( $filepath );
			}
			@touch( $filepath );
			$this->chmod( $filepath , $perm );
			clearstatcache();
			return $this->is_file( $filepath );
		}

		clearstatcache();
		$fp = fopen( $filepath, 'w' );
		if( !is_resource( $fp ) ){
			return false;
		}

		for ($written = 0; $written < strlen($content); $written += $fwrite) {
			$fwrite = fwrite($fp, substr($content, $written));
			if ($fwrite === false) {
				break;
			}
		}

		fclose($fp);

		$this->chmod( $filepath , $perm );
		clearstatcache();
		return !empty( $written );
	}//save_file()

	/**
	 * ファイルの中身を文字列として取得する。
	 *
	 * @param string $path ファイルのパス
	 * @return string ファイル `$path` の内容
	 */
	public function read_file( $path ){
		$path = $this->localize_path($path);
		return file_get_contents( $path );
	}//file_get_contents()

	/**
	 * ファイルの更新日時を比較する。
	 *
	 * @param string $path_a 比較対象A
	 * @param string $path_b 比較対象B
	 * @return bool|null
	 * `$path_a` の方が新しかった場合に `true`、
	 * `$path_b` の方が新しかった場合に `false`、
	 * 同時だった場合に `null` を返します。
	 *
	 * いずれか一方、または両方のファイルが存在しない場合、次のように振る舞います。
	 * - 両方のファイルが存在しない場合 = `null`
	 * - $path_a が存在せず、$path_b は存在する場合 = `false`
	 * - $path_a が存在し、$path_b は存在しない場合 = `true`
	 */
	public function is_newer_a_than_b( $path_a , $path_b ){
		$path_a = $this->localize_path($path_a);
		$path_b = $this->localize_path($path_b);

		// 比較できない場合に
		if(!file_exists($path_a) && !file_exists($path_b)){return null;}
		if(!file_exists($path_a)){return false;}
		if(!file_exists($path_b)){return true;}

		$mtime_a = filemtime( $path_a );
		$mtime_b = filemtime( $path_b );
		if( $mtime_a > $mtime_b ){
			return true;
		}elseif( $mtime_a < $mtime_b ){
			return false;
		}
		return null;
	}//is_newer_a_than_b()

	/**
	 * ファイル名/ディレクトリ名を変更する。
	 *
	 * @param string $original 現在のファイルまたはディレクトリ名
	 * @param string $newname 変更後のファイルまたはディレクトリ名
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function rename( $original , $newname ){
		$original = $this->localize_path($original);
		$newname  = $this->localize_path($newname );

		if( !@file_exists( $original ) ){ return false; }
		if( !$this->is_writable( $original ) ){ return false; }
		return @rename( $original , $newname );
	}//rename()

	/**
	 * ファイル名/ディレクトリ名を強制的に変更する。
	 *
	 * 移動先の親ディレクトリが存在しない場合にも、親ディレクトリを作成して移動するよう試みます。
	 *
	 * @param string $original 現在のファイルまたはディレクトリ名
	 * @param string $newname 変更後のファイルまたはディレクトリ名
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function rename_f( $original , $newname ){
		$original = $this->localize_path($original);
		$newname  = $this->localize_path($newname );

		if( !@file_exists( $original ) ){ return false; }
		if( !$this->is_writable( $original ) ){ return false; }
		$dirname = dirname( $newname );
		if( !$this->is_dir( $dirname ) ){
			if( !$this->mkdir_r( $dirname ) ){
				return false;
			}
		}
		return @rename( $original , $newname );
	}//rename_f()

	/**
	 * 絶対パスを得る。
	 *
	 * パス情報を受け取り、スラッシュから始まるサーバー内部絶対パスに変換して返します。
	 *
	 * このメソッドは、PHPの `realpath()` と異なり、存在しないパスも絶対パスに変換します。
	 *
	 * @param string $path 対象のパス
	 * @param string $cd カレントディレクトリパス。
	 * 実在する有効なディレクトリのパス、または絶対パスの表現で指定される必要があります。
	 * 省略時、カレントディレクトリを自動採用します。
	 * @return string 絶対パス
	 */
	public function get_realpath( $path, $cd = '.' ){
		$is_dir = false;
		if( preg_match( '/(\/|\\\\)+$/s', $path ) ){
			$is_dir = true;
		}
		$path = $this->localize_path($path);
		if( is_null($cd) ){ $cd = '.'; }
		$cd = $this->localize_path($cd);
		$preg_dirsep = preg_quote(DIRECTORY_SEPARATOR, '/');

		if( $this->is_dir($cd) ){
			$cd = realpath($cd);
		}elseif( !preg_match('/^((?:[A-Za-z]\\:'.$preg_dirsep.')|'.$preg_dirsep.'{1,2})(.*?)$/', $cd) ){
			$cd = false;
		}
		if( $cd === false ){
			return false;
		}

		$prefix = '';
		$localpath = $path;
		if( preg_match('/^((?:[A-Za-z]\\:'.$preg_dirsep.')|'.$preg_dirsep.'{1,2})(.*?)$/', $path, $matched) ){
			// もともと絶対パスの指定か調べる
			$prefix = preg_replace('/'.$preg_dirsep.'$/', '', $matched[1]);
			$localpath = $matched[2];
			$cd = null; // 元の指定が絶対パスだったら、カレントディレクトリは関係ないので捨てる。
		}

		$path = $cd.DIRECTORY_SEPARATOR.'.'.DIRECTORY_SEPARATOR.$localpath;

		if( file_exists( $prefix.$path ) ){
			$rtn = realpath( $prefix.$path );
			if( $is_dir && $rtn != realpath('/') ){
				$rtn .= DIRECTORY_SEPARATOR;
			}
			return $rtn;
		}

		$paths = explode( DIRECTORY_SEPARATOR, $path );
		$path = '';
		foreach( $paths as $idx=>$row ){
			if( $row == '' || $row == '.' ){
				continue;
			}
			if( $row == '..' ){
				$path = dirname($path);
				if($path == DIRECTORY_SEPARATOR){
					$path = '';
				}
				continue;
			}
			if(!($idx===0 && DIRECTORY_SEPARATOR == '\\' && preg_match('/^[a-zA-Z]\:$/s', $row))){
				$path .= DIRECTORY_SEPARATOR;
			}
			$path .= $row;
		}

		$rtn = $prefix.$path;
		if( $is_dir ){
			$rtn .= DIRECTORY_SEPARATOR;
		}
		return $rtn;
	}

	/**
	 * 相対パスを得る。
	 *
	 * パス情報を受け取り、ドットスラッシュから始まる相対絶対パスに変換して返します。
	 *
	 * @param string $path 対象のパス
	 * @param string $cd カレントディレクトリパス。
	 * 実在する有効なディレクトリのパス、または絶対パスの表現で指定される必要があります。
	 * 省略時、カレントディレクトリを自動採用します。
	 * @return string 相対パス
	 */
	public function get_relatedpath( $path, $cd = '.' ){
		$is_dir = false;
		if( preg_match( '/(\/|\\\\)+$/s', $path ) ){
			$is_dir = true;
		}
		if( @!strlen( $cd ) ){
			$cd = realpath('.');
		}elseif( $this->is_dir($cd) ){
			$cd = realpath($cd);
		}elseif( $this->is_file($cd) ){
			$cd = realpath(dirname($cd));
		}
		$path = $this->get_realpath($path, $cd);

		$normalize = function( $tmp_path, $fs ){
			$tmp_path = $fs->localize_path( $tmp_path );
			$preg_dirsep = preg_quote(DIRECTORY_SEPARATOR, '/');
			if( DIRECTORY_SEPARATOR == '\\' ){
				$tmp_path = preg_replace( '/^[a-zA-Z]\:/s', '', $tmp_path );
			}
			$tmp_path = preg_replace( '/^('.$preg_dirsep.')+/s', '', $tmp_path );
			$tmp_path = preg_replace( '/('.$preg_dirsep.')+$/s', '', $tmp_path );
			if( strlen($tmp_path) ){
				$tmp_path = explode( DIRECTORY_SEPARATOR, $tmp_path );
			}else{
				$tmp_path = array();
			}

			return $tmp_path;
		};

		$cd = $normalize($cd, $this);
		$path = $normalize($path, $this);

		$rtn = array();
		while( 1 ){
			if( !count($cd) || !count($path) ){
				break;
			}
			if( $cd[0] === $path[0] ){
				array_shift( $cd );
				array_shift( $path );
				continue;
			}
			break;
		}
		if( count($cd) ){
			foreach($cd as $dirname){
				array_push( $rtn, '..' );
			}
		}else{
			array_push( $rtn, '.' );
		}
		$rtn = array_merge( $rtn, $path );
		$rtn = implode( DIRECTORY_SEPARATOR, $rtn );

		if( $is_dir ){
			$rtn .= DIRECTORY_SEPARATOR;
		}
		return $rtn;
	}

	/**
	 * パス情報を得る。
	 *
	 * @param string $path 対象のパス
	 * @return array パス情報
	 */
	public function pathinfo( $path ){
		if(strpos($path,'#')!==false){ list($path, $hash) = @explode( '#', $path, 2 ); }
		if(strpos($path,'?')!==false){ list($path, $query) = @explode( '?', $path, 2 ); }

		$pathinfo = pathinfo( $path );
		$pathinfo['filename'] = $this->trim_extension( $pathinfo['basename'] );
		$pathinfo['extension'] = $this->get_extension( $pathinfo['basename'] );
		$pathinfo['query'] = (@strlen($query) ? '?'.$query : null);
		$pathinfo['hash'] = (@strlen($hash) ? '#'.$hash : null);
		return $pathinfo;
	}

	/**
	 * パス情報から、ファイル名を取得する。
	 *
	 * @param string $path 対象のパス
	 * @return string 抜き出されたファイル名
	 */
	public function get_basename( $path ){
		$path = pathinfo( $path , PATHINFO_BASENAME );
		if( !strlen($path) ){$path = null;}
		return $path;
	}

	/**
	 * パス情報から、拡張子を除いたファイル名を取得する。
	 *
	 * @param string $path 対象のパス
	 * @return string 拡張子が除かれたパス
	 */
	public function trim_extension( $path ){
		$pathinfo = pathinfo( $path );
		$RTN = preg_replace( '/\.'.preg_quote( @$pathinfo['extension'], '/' ).'$/' , '' , $path );
		return $RTN;
	}

	/**
	 * ファイル名を含むパス情報から、ファイルが格納されているディレクトリ名を取得する。
	 *
	 * @param string $path 対象のパス
	 * @return string 親ディレクトリのパス
	 */
	public function get_dirpath( $path ){
		$path = pathinfo( $path , PATHINFO_DIRNAME );
		if( !strlen($path) ){$path = null;}
		return $path;
	}

	/**
	 * パス情報から、拡張子を取得する。
	 *
	 * @param string $path 対象のパス
	 * @return string 拡張子
	 */
	public function get_extension( $path ){
		$path = preg_replace('/\#.*$/si', '', $path);
		$path = preg_replace('/\?.*$/si', '', $path);
		$path = pathinfo( $path , PATHINFO_EXTENSION );
		if(!strlen($path)){$path = null;}
		return $path;
	}


	/**
	 * CSVファイルを読み込む。
	 *
	 * @param string $path 対象のCSVファイルのパス
	 * @param array $options オプション
	 * - delimiter = 区切り文字(省略時、カンマ)
	 * - enclosure = クロージャー文字(省略時、ダブルクオート)
	 * - size = 一度に読み込むサイズ(省略時、10000)
	 * - charset = 文字セット(省略時、UTF-8)
	 * @return array|bool 読み込みに成功した場合、行列を格納した配列、失敗した場合には `false` を返します。
	 */
	public function read_csv( $path , $options = array() ){
		// $options['charset'] は、保存されているCSVファイルの文字エンコードです。
		// 省略時は UTF-8 から、内部エンコーディングに変換します。

		$path = $this->localize_path($path);

		if( !$this->is_file( $path ) ){
			// ファイルがなければfalseを返す
			return false;
		}

		if( !strlen( @$options['delimiter'] ) )    { $options['delimiter'] = ','; }
		if( !strlen( @$options['enclosure'] ) )    { $options['enclosure'] = '"'; }
		if( !strlen( @$options['size'] ) )         { $options['size'] = 10000; }
		if( !strlen( @$options['charset'] ) )      { $options['charset'] = 'UTF-8'; }//←CSVの文字セット

		$RTN = array();
		$fp = fopen( $path, 'r' );
		if( !is_resource( $fp ) ){
			return false;
		}

		while( $SMMEMO = fgetcsv( $fp , intval( $options['size'] ) , $options['delimiter'] , $options['enclosure'] ) ){
			foreach( $SMMEMO as $key=>$row ){
				$SMMEMO[$key] = mb_convert_encoding( $row , mb_internal_encoding() , $options['charset'].',UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP' );
			}
			array_push( $RTN , $SMMEMO );
		}
		fclose($fp);
		return $RTN;
	}//read_csv()

	/**
	 * 配列をCSV形式に変換する。
	 *
	 * 改行コードはLFで出力されます。
	 *
	 * @param array $array 2次元配列
	 * @param array $options オプション
	 * - charset = 文字セット(省略時、UTF-8)
	 * @return string 生成されたCSV形式のテキスト
	 */
	public function mk_csv( $array , $options = array() ){
		// $options['charset'] は、出力されるCSV形式の文字エンコードを指定します。
		// 省略時は UTF-8 に変換して返します。
		if( !is_array( $array ) ){ $array = array(); }

		if( @!strlen( $options['charset'] ) ){
			$options['charset'] = 'UTF-8';
		}
		$RTN = '';
		foreach( $array as $Line ){
			if( is_null( $Line ) ){ continue; }
			if( !is_array( $Line ) ){ $Line = array(); }
			foreach( $Line as $cell ){
				$cell = mb_convert_encoding( $cell , $options['charset'] , mb_internal_encoding().',UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP' );
				if( preg_match( '/"/' , $cell ) ){
					$cell = preg_replace( '/"/' , '""' , $cell);
				}
				if( strlen( $cell ) ){
					$cell = '"'.$cell.'"';
				}
				$RTN .= $cell.',';
			}
			$RTN = preg_replace( '/,$/' , '' , $RTN );
			$RTN .= "\n";
		}
		return $RTN;
	}//mk_csv()

	/**
	 * ファイルを複製する。
	 *
	 * @param string $from コピー元ファイルのパス
	 * @param string $to コピー先のパス
	 * @param int $perm 保存するファイルに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function copy( $from , $to , $perm = null ){
		$from = $this->localize_path($from);
		$to   = $this->localize_path($to  );

		if( !$this->is_file( $from ) ){
			return false;
		}
		if( !$this->is_readable( $from ) ){
			return false;
		}

		if( $this->is_file( $to ) ){
			//	まったく同じファイルだった場合は、複製しないでtrueを返す。
			if( md5_file( $from ) == md5_file( $to ) && filesize( $from ) == filesize( $to ) ){
				return true;
			}
		}
		if( !@copy( $from , $to ) ){
			return false;
		}
		$this->chmod( $to , $perm );
		return true;
	}//copy()

	/**
	 * ディレクトリを複製する(下層ディレクトリも全てコピー)
	 *
	 * @param string $from コピー元ファイルのパス
	 * @param string $to コピー先のパス
	 * @param int $perm 保存するファイルに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function copy_r( $from , $to , $perm = null ){
		$from = $this->localize_path($from);
		$to   = $this->localize_path($to  );

		$result = true;

		if( $this->is_file( $from ) ){
			if( $this->mkdir_r( dirname( $to ) ) ){
				if( !$this->copy( $from , $to , $perm ) ){
					$result = false;
				}
			}else{
				$result = false;
			}
		}elseif( $this->is_dir( $from ) ){
			if( !$this->is_dir( $to ) ){
				if( !$this->mkdir_r( $to ) ){
					$result = false;
				}
			}
			$itemlist = $this->ls( $from );
			if( is_array($itemlist) ){
				foreach( $itemlist as $Line ){
					if( $Line == '.' || $Line == '..' ){ continue; }
					if( $this->is_dir( $from.DIRECTORY_SEPARATOR.$Line ) ){
						if( $this->is_file( $to.DIRECTORY_SEPARATOR.$Line ) ){
							continue;
						}elseif( !$this->is_dir( $to.DIRECTORY_SEPARATOR.$Line ) ){
							if( !$this->mkdir_r( $to.DIRECTORY_SEPARATOR.$Line ) ){
								$result = false;
							}
						}
						if( !$this->copy_r( $from.DIRECTORY_SEPARATOR.$Line , $to.DIRECTORY_SEPARATOR.$Line , $perm ) ){
							$result = false;
						}
						continue;
					}elseif( $this->is_file( $from.DIRECTORY_SEPARATOR.$Line ) ){
						if( !$this->copy_r( $from.DIRECTORY_SEPARATOR.$Line , $to.DIRECTORY_SEPARATOR.$Line , $perm ) ){
							$result = false;
						}
						continue;
					}
				}
			}
		}

		return $result;
	}//copy_r()

	/**
	 * パーミッションを変更する。
	 *
	 * @param string $filepath 対象のパス
	 * @param int $perm 保存するファイルに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function chmod( $filepath , $perm = null ){
		$filepath = $this->localize_path($filepath);

		if( is_null( $perm ) ){
			if( $this->is_dir( $filepath ) ){
				$perm = $this->default_permission['dir'];
			}else{
				$perm = $this->default_permission['file'];
			}
		}
		if( is_null( $perm ) ){
			$perm = 0775; // コンフィグに設定モレがあった場合
		}
		return @chmod( $filepath , $perm );
	}//chmod()

	/**
	 * パーミッション情報を調べ、3桁の数字で返す。
	 *
	 * @param string $path 対象のパス
	 * @return int|bool 成功時に 3桁の数字、失敗時に `false` を返します。
	 */
	public function get_permission( $path ){
		$path = $this->localize_path($path);

		if( !@file_exists( $path ) ){
			return false;
		}
		$perm = rtrim( sprintf( "%o\n" , fileperms( $path ) ) );
		$start = strlen( $perm ) - 3;
		return substr( $perm , $start , 3 );
	}//get_permission()


	/**
	 * ディレクトリにあるファイル名のリストを配列で返す。
	 *
	 * @param string $path 対象ディレクトリのパス
	 * @return array|bool 成功時にファイルまたはディレクトリ名の一覧を格納した配列、失敗時に `false` を返します。
	 */
	public function ls($path){
		$path = $this->localize_path($path);

		if( $path === false ){ return false; }
		if( !@file_exists( $path ) ){ return false; }
		if( !$this->is_dir( $path ) ){ return false; }

		$RTN = array();
		$dr = @opendir($path);
		while( ( $ent = readdir( $dr ) ) !== false ){
			// CurrentDirとParentDirは含めない
			if( $ent == '.' || $ent == '..' ){ continue; }
			array_push( $RTN , $ent );
		}
		closedir($dr);
		if( strlen( $this->filesystem_encoding ) ){
			//PxFW 0.6.4 追加
			$RTN = @$this->convert_filesystem_encoding( $RTN );
		}
		usort($RTN, "strnatcmp");
		return	$RTN;
	}//ls()

	/**
	 * ディレクトリの内部を比較し、$comparisonに含まれない要素を$targetから削除する。
	 *
	 * @param string $target クリーニング対象のディレクトリパス
	 * @param string $comparison 比較するディレクトリのパス
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function compare_and_cleanup( $target , $comparison ){
		if( is_null( $comparison ) || is_null( $target ) ){ return false; }

		$target = $this->localize_path($target);
		$comparison = $this->localize_path($comparison);

		if( !@file_exists( $comparison ) && @file_exists( $target ) ){
			$this->rm( $target );
			return true;
		}

		if( $this->is_dir( $target ) ){
			$flist = $this->ls( $target );
		}else{
			return true;
		}

		if( is_array($flist) ){
			foreach ( $flist as $Line ){
				if( $Line == '.' || $Line == '..' ){ continue; }
				$this->compare_and_cleanup( $target.DIRECTORY_SEPARATOR.$Line , $comparison.DIRECTORY_SEPARATOR.$Line );
			}
		}

		return true;
	}//compare_and_cleanup()

	/**
	 * ディレクトリを同期する。
	 *
	 * @param string $path_sync_from 同期元ディレクトリ
	 * @param string $path_sync_to 同期先ディレクトリ
	 * @return bool 常に `true` を返します。
	 */
	public function sync_dir( $path_sync_from , $path_sync_to ){
		$this->copy_r( $path_sync_from , $path_sync_to );
		$this->compare_and_cleanup( $path_sync_to , $path_sync_from );
		return true;
	}//sync_dir()

	/**
	 * 指定されたディレクトリ以下の、全ての空っぽのディレクトリを削除する。
	 *
	 * @param string $path ディレクトリパス
	 * @param array $options オプション
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function remove_empty_dir( $path , $options = array() ){
		$path = $this->localize_path($path);

		if( !$this->is_writable( $path ) ){ return false; }
		if( !$this->is_dir( $path ) ){ return false; }
		if( $this->is_file( $path ) || $this->is_link( $path ) ){ return false; }
		$path = @realpath( $path );
		if( $path === false ){ return false; }

		// --------------------------------------
		// 次の階層を処理するかどうかのスイッチ
		$switch_donext = false;
		if( is_null( $options['depth'] ) ){
			// 深さの指定がなければ掘る
			$switch_donext = true;
		}elseif( !is_int( $options['depth'] ) ){
			// 指定がnullでも数値でもなければ掘らない
			$switch_donext = false;
		}elseif( $options['depth'] <= 0 ){
			// 指定がゼロ以下なら、今回の処理をして終了
			$switch_donext = false;
		}elseif( $options['depth'] > 0 ){
			// 指定が正の数(ゼロは含まない)なら、掘る
			$options['depth'] --;
			$switch_donext = true;
		}else{
			return false;
		}
		// / 次の階層を処理するかどうかのスイッチ
		// --------------------------------------

		$flist = $this->ls( $path );
		if( !count( $flist ) ){
			// 開いたディレクトリの中身が
			// "." と ".." のみだった場合
			// 削除して終了
			$result = @rmdir( $path );
			return	$result;
		}
		$alive = false;
		foreach ( $flist as $Line ){
			if( $Line == '.' || $Line == '..' ){ continue; }
			if( $this->is_link( $path.DIRECTORY_SEPARATOR.$Line ) ){
				// シンボリックリンクは無視する。
			}elseif( $this->is_dir( $path.DIRECTORY_SEPARATOR.$Line ) ){
				if( $switch_donext ){
					// さらに掘れと指令があれば、掘る。
					$this->remove_empty_dir( $path.DIRECTORY_SEPARATOR.$Line , $options );
				}
			}
			if( @file_exists( $path.DIRECTORY_SEPARATOR.$Line ) ){
				$alive = true;
			}
		}
		if( !$alive ){
			$result = @rmdir( $path );
			return	$result;
		}
		return true;
	}//remove_empty_dir()


	/**
	 * 指定された2つのディレクトリの内容を比較し、まったく同じかどうか調べる。
	 *
	 * @param string $dir_a 比較対象ディレクトリA
	 * @param string $dir_b 比較対象ディレクトリB
	 * @param array $options オプション
	 * <dl>
	 *   <dt>bool $options['compare_filecontent']</dt>
	 * 	   <dd>ファイルの中身も比較するか？</dd>
	 *   <dt>bool $options['compare_emptydir']</dt>
	 * 	   <dd>空っぽのディレクトリの有無も評価に含めるか？</dd>
	 * </dl>
	 * @return bool 同じ場合に `true`、異なる場合に `false` を返します。
	 */
	public function compare_dir( $dir_a , $dir_b , $options = array() ){

		if( strlen( $this->filesystem_encoding ) ){
			//PxFW 0.6.4 追加
			$dir_a = @$this->convert_filesystem_encoding( $dir_a );
			$dir_b = @$this->convert_filesystem_encoding( $dir_b );
		}

		if( ( $this->is_file( $dir_a ) && !$this->is_file( $dir_b ) ) || ( !$this->is_file( $dir_a ) && $this->is_file( $dir_b ) ) ){
			return false;
		}
		if( ( ( $this->is_dir( $dir_a ) && !$this->is_dir( $dir_b ) ) || ( !$this->is_dir( $dir_a ) && $this->is_dir( $dir_b ) ) ) && $options['compare_emptydir'] ){
			return false;
		}

		if( $this->is_file( $dir_a ) && $this->is_file( $dir_b ) ){
			// --------------------------------------
			// 両方ファイルだったら
			if( $options['compare_filecontent'] ){
				// ファイルの内容も比較する設定の場合、
				// それぞれファイルを開いて同じかどうかを比較
				$filecontent_a = $this->read_file( $dir_a );
				$filecontent_b = $this->read_file( $dir_b );
				if( $filecontent_a !== $filecontent_b ){
					return false;
				}
			}
			return true;
		}

		if( $this->is_dir( $dir_a ) || $this->is_dir( $dir_b ) ){
			// --------------------------------------
			// 両方ディレクトリだったら
			$contlist_a = $this->ls( $dir_a );
			$contlist_b = $this->ls( $dir_b );

			if( $options['compare_emptydir'] && $contlist_a !== $contlist_b ){
				// 空っぽのディレクトリも厳密に評価する設定で、
				// ディレクトリ内の要素配列の内容が異なれば、false。
				return false;
			}

			$done = array();
			foreach( $contlist_a as $Line ){
				// Aをチェック
				if( $Line == '..' || $Line == '.' ){ continue; }
				if( !$this->compare_dir( $dir_a.DIRECTORY_SEPARATOR.$Line , $dir_b.DIRECTORY_SEPARATOR.$Line , $options ) ){
					return false;
				}
				$done[$Line] = true;
			}

			foreach( $contlist_b as $Line ){
				// Aに含まれなかったBをチェック
				if( $done[$Line] ){ continue; }
				if( $Line == '..' || $Line == '.' ){ continue; }
				if( !$this->compare_dir( $dir_a.DIRECTORY_SEPARATOR.$Line , $dir_b.DIRECTORY_SEPARATOR.$Line , $options ) ){
					return false;
				}
				$done[$Line] = true;
			}

		}

		return true;
	}//compare_dir()


	/**
	 * サーバがUNIXパスか調べる。
	 *
	 * @return bool UNIXパスなら `true`、それ以外なら `false` を返します。
	 */
	public function is_unix(){
		if( DIRECTORY_SEPARATOR == '/' ){
			return true;
		}
		return false;
	}//is_unix()

	/**
	 * サーバがWindowsパスか調べる。
	 *
	 * @return bool Windowsパスなら `true`、それ以外なら `false` を返します。
	 */
	public function is_windows(){
		if( DIRECTORY_SEPARATOR == '\\' ){
			return true;
		}
		return false;
	}//is_windows()


	/**
	 * パスを正規化する。
	 *
	 * 受け取ったパスを、スラッシュ区切りの表現に正規化します。
	 * Windowsのボリュームラベルが付いている場合は削除します。
	 * URIスキーム(http, https, ftp など) で始まる場合、2つのスラッシュで始まる場合(`//www.example.com/abc/` など)、これを残して正規化します。
	 *
	 *  - 例： `\a\b\c.html` → `/a/b/c.html` バックスラッシュはスラッシュに置き換えられます。
	 *  - 例： `/a/b////c.html` → `/a/b/c.html` 余計なスラッシュはまとめられます。
	 *  - 例： `C:\a\b\c.html` → `/a/b/c.html` ボリュームラベルは削除されます。
	 *  - 例： `http://a/b/c.html` → `http://a/b/c.html` URIスキームは残されます。
	 *  - 例： `//a/b/c.html` → `//a/b/c.html` ドメイン名は残されます。
	 *
	 * @param string $path 正規化するパス
	 * @return string 正規化されたパス
	 */
	public function normalize_path($path){
		$path = trim($path);
		$path = $this->convert_encoding( $path );//文字コードを揃える
		$path = preg_replace( '/\\/|\\\\/s', '/', $path );//バックスラッシュをスラッシュに置き換える。
		$path = preg_replace( '/^[A-Z]\\:\\//s', '/', $path );//Windowsのボリュームラベルを削除
		$prefix = '';
		if( preg_match( '/^((?:[a-zA-Z0-9]+\\:)?\\/)(\\/.*)$/', $path, $matched ) ){
			$prefix = $matched[1];
			$path = $matched[2];
		}
		$path = preg_replace( '/\\/+/s', '/', $path );//重複するスラッシュを1つにまとめる
		return $prefix.$path;
	}


	/**
	 * パスをOSの標準的な表現に変換する。
	 *
	 * 受け取ったパスを、OSの標準的な表現に変換します。
	 * - スラッシュとバックスラッシュの違いを吸収し、`DIRECTORY_SEPARATOR` に置き換えます。
	 *
	 * @param string $path ローカライズするパス
	 * @return string ローカライズされたパス
	 */
	public function localize_path($path){
		$path = $this->convert_filesystem_encoding( $path );//文字コードを揃える
		$path = preg_replace( '/\\/|\\\\/s', '/', $path );//一旦スラッシュに置き換える。
		if( $this->is_unix() ){
			// Windows以外だった場合に、ボリュームラベルを受け取ったら削除する
			$path = preg_replace( '/^[A-Z]\\:\\//s', '/', $path );//Windowsのボリュームラベルを削除
		}
		$path = preg_replace( '/\\/+/s', '/', $path );//重複するスラッシュを1つにまとめる
		$path = preg_replace( '/\\/|\\\\/s', DIRECTORY_SEPARATOR, $path );
		return $path;
	}



	/**
	 * 受け取ったテキストを、ファイルシステムエンコードに変換する。
	 *
	 * @param mixed $text テキスト
	 * @return string 文字セット変換後のテキスト
	 */
	private function convert_filesystem_encoding( $text ){
		$RTN = $text;
		if( !is_callable( 'mb_internal_encoding' ) ){
			return $text;
		}
		if( !strlen( $this->filesystem_encoding ) ){
			return $text;
		}

		$to_encoding = $this->filesystem_encoding;
		$from_encoding = mb_internal_encoding().',UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,JIS,ASCII';

		return $this->convert_encoding( $text, $to_encoding, $from_encoding );

	}//convert_filesystem_encoding()

	/**
	 * 受け取ったテキストを、ファイルシステムエンコードに変換する。
	 *
	 * @param mixed $text テキスト
	 * @param string $to_encoding 文字セット(省略時、内部文字セット)
	 * @param string $from_encoding 変換前の文字セット
	 * @return string 文字セット変換後のテキスト
	 */
	public function convert_encoding( $text, $to_encoding = null, $from_encoding = null ){
		$RTN = $text;
		if( !is_callable( 'mb_internal_encoding' ) ){
			return $text;
		}

		$to_encoding_fin = $to_encoding;
		if( !strlen($to_encoding_fin) ){
			$to_encoding_fin = mb_internal_encoding();
		}
		if( !strlen($to_encoding_fin) ){
			$to_encoding_fin = 'UTF-8';
		}

		$from_encoding_fin = (strlen($from_encoding)?$from_encoding.',':'').mb_internal_encoding().',UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,JIS,ASCII';

		// ---
		if( is_array( $text ) ){
			$RTN = array();
			if( !count( $text ) ){
				return $text;
			}
			foreach( $text as $key=>$row ){
				$RTN[$key] = $this->convert_encoding( $row, $to_encoding, $from_encoding );
			}
		}else{
			if( !strlen( $text ) ){
				return $text;
			}
			$RTN = mb_convert_encoding( $text, $to_encoding_fin, $from_encoding_fin );
		}
		return $RTN;
	}//convert_encoding()

	/**
	 * 受け取ったテキストを、指定の改行コードに変換する。
	 *
	 * @param mixed $text テキスト
	 * @param string $crlf 改行コード名。CR|LF(default)|CRLF
	 * @return string 改行コード変換後のテキスト
	 */
	public function convert_crlf( $text, $crlf = null ){
		if( !strlen($crlf) ){
			$crlf = 'LF';
		}
		$crlf_code = "\n";
		switch(strtoupper($crlf)){
			case 'CR':
				$crlf_code = "\r";
				break;
			case 'CRLF':
				$crlf_code = "\r\n";
				break;
			case 'LF':
			default:
				$crlf_code = "\n";
				break;
		}
		$RTN = $text;
		if( is_array( $text ) ){
			$RTN = array();
			if( !count( $text ) ){
				return $text;
			}
			foreach( $text as $key=>$val ){
				$RTN[$key] = $this->convert_crlf( $val , $crlf );
			}
		}else{
			if( !strlen( $text ) ){
				return $text;
			}
			$RTN = preg_replace( '/\r\n|\r|\n/', $crlf_code, $text );
		}
		return $RTN;
	}

}
?>
<?php
/**
 * tomk79/request core class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_request{
	/**
	 * 設定オブジェクト
	 */
	private $conf;
	/**
	 * ファイルシステムオブジェクト
	 */
	private $fs;
	/**
	 * URLパラメータ
	 */
	private $param = array();
	/**
	 * コマンドからのアクセス フラグ
	 */
	private $flg_cmd = false;
	/**
	 * 優先ディレクトリインデックス
	 */
	private $directory_index_primary;
	/**
	 * コマンドラインオプション
	 */
	private $cli_options;
	/**
	 * コマンドラインパラメータ
	 */
	private $cli_params;

	/**
	 * コンストラクタ
	 *
	 * @param object $conf 設定オブジェクト
	 */
	public function __construct($conf=null){
		$this->conf = $conf;
		if( !is_object($this->conf) ){
			$this->conf = json_decode('{}');
		}

		if(!property_exists($this->conf, 'get') || !@is_array($this->conf->get)){
			$this->conf->get = $_GET;
		}
		if(!property_exists($this->conf, 'post') || !@is_array($this->conf->post)){
			$this->conf->post = $_POST;
		}
		if(!property_exists($this->conf, 'files') || !@is_array($this->conf->files)){
			$this->conf->files = $_FILES;
		}
		if(!property_exists($this->conf, 'server') || !@is_array($this->conf->server)){
			$this->conf->server = $_SERVER;
		}
		if( !array_key_exists( 'PATH_INFO' , $this->conf->server ) ){
			$this->conf->server['PATH_INFO'] = null;
		}
		if( !array_key_exists( 'HTTP_USER_AGENT' , $this->conf->server ) ){
			$this->conf->server['HTTP_USER_AGENT'] = null;
		}
		if( !array_key_exists( 'argv' , $this->conf->server ) ){
			$this->conf->server['argv'] = null;
		}
		if(!property_exists($this->conf, 'session_name') || !@strlen($this->conf->session_name)){
			$this->conf->session_name = 'SESSID';
		}
		if(!property_exists($this->conf, 'session_expire') || !@strlen($this->conf->session_expire)){
			$this->conf->session_expire = 1800;
		}
		if(!property_exists($this->conf, 'directory_index_primary') || !@strlen($this->conf->directory_index_primary)){
			$this->conf->directory_index_primary = 'index.html';
		}
		if(!property_exists($this->conf, 'cookie_default_path') || !@strlen($this->conf->cookie_default_path)){
			// クッキーのデフォルトのパス
			// session の範囲もこの設定に従う。
			$this->conf->cookie_default_path = $this->get_path_current_dir();
		}

		$this->parse_input();
		$this->session_start();
	}

	/**
	 *	入力値を解析する。
	 *
	 * `$_GET`, `$_POST`, `$_FILES` に送られたパラメータ情報を取りまとめ、1つの連想配列としてまとめま、オブジェクト内に保持します。
	 *
	 * コマンドラインから実行された場合は、コマンドラインオプションをそれぞれ `=` 記号で区切り、URLパラメータ同様にパースします。
	 *
	 * このメソッドの処理には、入力文字コードの変換(UTF-8へ統一)などの整形処理が含まれます。
	 *
	 * @return bool 常に `true`
	 */
	private function parse_input(){
		$this->cli_params = array();
		$this->cli_options = array();

		if( !array_key_exists( 'REMOTE_ADDR' , $this->conf->server ) ){
			//  コマンドラインからの実行か否か判断
			$this->flg_cmd = true;//コマンドラインから実行しているかフラグ
			if( is_array( $this->conf->server['argv'] ) && count( $this->conf->server['argv'] ) ){
				$tmp_path = null;
				for( $i = 0; count( $this->conf->server['argv'] ) > $i; $i ++ ){
					if( preg_match( '/^\-/', $this->conf->server['argv'][$i] ) ){
						$this->cli_params = array();//オプションの前に引数は付けられない
						$this->cli_options[$this->conf->server['argv'][$i]] = $this->conf->server['argv'][$i+1];
						$i ++;
					}else{
						array_push( $this->cli_params, $this->conf->server['argv'][$i] );
					}
				}
				$tmp_path = @$this->cli_params[count($this->cli_params)-1];
				if( preg_match( '/^\//', $tmp_path ) && @is_array($this->conf->server['argv']) ){
					$tmp_path = array_pop( $this->conf->server['argv'] );
					$tmp_path = parse_url($tmp_path);
					@parse_str( $tmp_path['query'], $query );
					if( is_array($query) ){
						$this->conf->get = array_merge( $this->conf->get, $query );
					}
				}
				unset( $tmp_path );
			}
		}

		if( ini_get('magic_quotes_gpc') ){
			// PHPINIのmagic_quotes_gpc設定がOnだったら、
			// エスケープ文字を削除。
			foreach( array_keys( $this->conf->get ) as $Line ){
				$this->conf->get[$Line] = self::stripslashes( $this->conf->get[$Line] );
			}
			foreach( array_keys( $this->conf->post ) as $Line ){
				$this->conf->post[$Line] = self::stripslashes( $this->conf->post[$Line] );
			}
		}

		$this->conf->get = self::convert_encoding( $this->conf->get );
		$this->conf->post = self::convert_encoding( $this->conf->post );
		$param = array_merge( $this->conf->get , $this->conf->post );
		$param = $this->normalize_input( $param );

		if( is_array( $this->conf->files ) ){
			$FILES_KEYS = array_keys( $this->conf->files );
			foreach($FILES_KEYS as $Line){
				$this->conf->files[$Line]['name'] = self::convert_encoding( $this->conf->files[$Line]['name'] );
				$this->conf->files[$Line]['name'] = mb_convert_kana( $this->conf->files[$Line]['name'] , 'KV' , mb_internal_encoding() );
				$param[$Line] = $this->conf->files[$Line];
			}
		}

		$this->param = $param;
		unset($param);

		return	true;
	}//parse_input()

	/**
	 *	入力値に対する標準的な変換処理
	 *
	 * @param array $param パラメータ
	 * @return array 変換後のパラメータ
	 */
	private function normalize_input( $param ){
		$is_callable_mb_check_encoding = is_callable( 'mb_check_encoding' );
		foreach( $param as $key=>$val ){
			// URLパラメータを加工
			if( is_array( $val ) ){
				// 配列なら
				$param[$key] = $this->normalize_input( $param[$key] );
			}elseif( is_string( $param[$key] ) ){
				// 文字列なら
				$param[$key] = mb_convert_kana( $param[$key] , 'KV' , mb_internal_encoding() );
					// 半角カナは全角に統一
				$param[$key] = preg_replace( '/\r\n|\r|\n/' , "\n" , $param[$key] );
					// 改行コードはLFに統一
				if( $is_callable_mb_check_encoding ){
					// 不正なバイトコードのチェック
					if( !mb_check_encoding( $key , mb_internal_encoding() ) ){
						// キーの中に見つけたらパラメータごと削除
						unset( $param[$key] );
					}
					if( !mb_check_encoding( $param[$key] , mb_internal_encoding() ) ){
						// 値の中に見つけたら false に置き換える
						$param[$key] = false;
					}
				}
			}
		}
		return $param;
	}//normalize_input()

	/**
	 * パラメータを取得する。
	 *
	 * `$_GET`, `$_POST`、`$_FILES` を合わせた連想配列の中から `$key` に当たる値を引いて返します。
	 * キーが定義されていない場合は、`null` を返します。
	 *
	 * @param string $key URLパラメータ名
	 * @return mixed URLパラメータ値
	 */
	public function get_param( $key ){
		if( !array_key_exists($key, $this->param) ){ return null; }
		return @$this->param[$key];
	}//get_param()

	/**
	 * パラメータをセットする。
	 *
	 * @param string $key パラメータ名
	 * @param mixed $val パラメータ値
	 * @return bool 常に `true`
	 */
	public function set_param( $key , $val ){
		$this->param[$key] = $val;
		return true;
	}//set_param()

	/**
	 * パラメータをすべて取得する。
	 *
	 * @return array すべてのパラメータを格納する連想配列
	 */
	public function get_all_params(){
		return $this->param;
	}

	/**
	 * コマンドラインオプションを取得する
	 * @param string $name オプション名
	 * @return string 指定されたオプション値
	 */
	public function get_cli_option( $name ){
		if( !array_key_exists($name, $this->cli_options) ){
			return null;
		}
		return @$this->cli_options[$name];
	}

	/**
	 * すべてのコマンドラインオプションを連想配列で取得する
	 * @return array すべてのコマンドラインオプション
	 */
	public function get_cli_options(){
		return @$this->cli_options;
	}

	/**
	 * コマンドラインパラメータを取得する
	 * @param string $idx パラメータ番号
	 * @return string 指定されたオプション値
	 */
	public function get_cli_param( $idx = 0 ){
		if($idx < 0){
			// マイナスのインデックスが与えられた場合、
			// 配列の最後から数える
			$idx = count($this->cli_params)+$idx;
		}
		return @$this->cli_params[$idx];
	}

	/**
	 * すべてのコマンドラインパラメータを配列で取得する
	 * @return array すべてのコマンドラインパラメータ
	 */
	public function get_cli_params(){
		return @$this->cli_params;
	}



	// ----- cookies -----

	/**
	 * クッキー情報を取得する。
	 *
	 * @param string $key クッキー名
	 * @return mixed クッキーの値
	 */
	public function get_cookie( $key ){
		if( @!is_array( $_COOKIE ) ){ return null; }
		if( @!array_key_exists($key, $_COOKIE) ){ return null; }
		return	@$_COOKIE[$key];
	}//get_cookie()

	/**
	 * クッキー情報をセットする。
	 *
	 * @param string $key クッキー名
	 * @param string $val クッキー値
	 * @param string $expire クッキーの有効期限
	 * @param string $path サーバー上での、クッキーを有効としたいパス
	 * @param string $domain クッキーが有効なドメイン
	 * @param bool $secure クライアントからのセキュアな HTTPS 接続の場合にのみクッキーが送信されるようにします。デフォルトは `true`
	 * @return 成功時 `true`、失敗時 `false` を返します。
	 */
	public function set_cookie( $key , $val , $expire = null , $path = null , $domain = null , $secure = true ){
		if( is_null( $path ) ){
			$path = $this->conf->cookie_default_path;
			if( !strlen( $path ) ){
				$path = $this->get_path_current_dir();
			}
			if( !strlen( $path ) ){
				$path = '/';
			}
		}
		if( !@setcookie( $key , $val , $expire , $path , $domain , $secure ) ){
			return false;
		}

		$_COOKIE[$key] = $val;//現在の処理からも呼び出せるように
		return true;
	}//set_cookie()

	/**
	 * クッキー情報を削除する。
	 *
	 * @param string $key クッキー名
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function delete_cookie( $key ){
		if( !@setcookie( $key , null ) ){
			return false;
		}
		unset( $_COOKIE[$key] );
		return true;
	}//delete_cookie()



	// ----- session -----

	/**
	 * セッションを開始する。
	 *
	 * @param string $sid セッションID。省略時、自動発行。
	 * @return bool セッションが正常に開始した場合に `true`、それ以外の場合に `false` を返します。
	 */
	private function session_start( $sid = null ){
		$expire = intval($this->conf->session_expire);
		$cache_limiter = 'nocache';
		$session_name = 'SESSID';
		if( strlen( $this->conf->session_name ) ){
			$session_name = $this->conf->session_name;
		}
		$path = $this->conf->cookie_default_path;
		if( !strlen( $path ) ){
			$path = $this->get_path_current_dir();
		}
		if( !strlen( $path ) ){
			$path = '/';
		}

		@session_name( $session_name );
		@session_cache_limiter( $cache_limiter );
		@session_cache_expire( intval($expire/60) );

		if( intval( ini_get( 'session.gc_maxlifetime' ) ) < $expire + 10 ){
			// ガベージコレクションの生存期間が
			// $expireよりも短い場合は、上書きする。
			// バッファは固定値で10秒。
			@ini_set( 'session.gc_maxlifetime' , $expire + 10 );
		}

		@session_set_cookie_params( 0 , $path );
			//  セッションクッキー自体の寿命は定めない(=0)
			//  そのかわり、SESSION_LAST_MODIFIED を新設し、自分で寿命を管理する。

		if( strlen( $sid ) ){
			// セッションIDに指定があれば、有効にする。
			session_id( $sid );
		}

		// セッションを開始
		$rtn = @session_start();

		// セッションの有効期限を評価
		if( strlen( $this->get_session( 'SESSION_LAST_MODIFIED' ) ) && intval( $this->get_session( 'SESSION_LAST_MODIFIED' ) ) < intval( time() - $expire ) ){
			#	セッションの有効期限が切れていたら、セッションキーを再発行。
			if( is_callable('session_regenerate_id') ){
				@session_regenerate_id( true );
			}
		}
		$this->set_session( 'SESSION_LAST_MODIFIED' , time() );
		return $rtn;
	}//session_start()

	/**
	 * セッションIDを取得する。
	 *
	 * @return string セッションID
	 */
	public function get_session_id(){
		return session_id();
	}//get_session_id()

	/**
	 * セッション情報を取得する。
	 *
	 * @param string $key セッションキー
	 * @return mixed `$key` に対応するセッション値
	 */
	public function get_session( $key ){
		if( @!is_array( $_SESSION ) ){ return null; }
		if( @!array_key_exists($key, $_SESSION) ){ return null; }
		return @$_SESSION[$key];
	}//get_session()

	/**
	 * セッション情報をセットする。
	 *
	 * @param string $key セッションキー
	 * @param mixed $val `$key` に対応するセッション値
	 * @return bool 常に `true` を返します。
	 */
	public function set_session( $key , $val ){
		$_SESSION[$key] = $val;
		return true;
	}//set_session()

	/**
	 * セッション情報を削除する。
	 *
	 * @param string $key セッションキー
	 * @return bool 常に `true` を返します。
	 */
	public function delete_session( $key ){
		unset( $_SESSION[$key] );
		return true;
	}//delete_session()


	// ----- upload file access -----

	/**
	 * アップロードされたファイルをセッションに保存する。
	 *
	 * @param string $key セッションキー
	 * @param array $ulfileinfo アップロードファイル情報
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function save_uploadfile( $key , $ulfileinfo ){
		// base64でエンコードして、バイナリデータを持ちます。
		// $ulfileinfo['content'] にバイナリを格納して渡すか、
		// $ulfileinfo['tmp_name'] または $ulfileinfo['path'] のいずれかに、
		// アップロードファイルのパスを指定してください。
		$fileinfo = array();
		$fileinfo['name'] = $ulfileinfo['name'];
		$fileinfo['type'] = $ulfileinfo['type'];

		if( $ulfileinfo['content'] ){
			$fileinfo['content'] = base64_encode( $ulfileinfo['content'] );
		}else{
			$filepath = '';
			if( @is_file( $ulfileinfo['tmp_name'] ) ){
				$filepath = $ulfileinfo['tmp_name'];
			}elseif( @is_file( $ulfileinfo['path'] ) ){
				$filepath = $ulfileinfo['path'];
			}else{
				return false;
			}
			$fileinfo['content'] = base64_encode( file_get_contents( $filepath ) );
		}

		if( @!is_array( $_SESSION ) ){
			$_SESSION = array();
		}
		if( @!array_key_exists('FILE', $_SESSION) ){
			$_SESSION['FILE'] = array();
		}

		$_SESSION['FILE'][$key] = $fileinfo;
		return	true;
	}
	/**
	 * セッションに保存されたファイル情報を取得する。
	 *
	 * @param string $key セッションキー
	 * @return array|bool 成功時、ファイル情報 を格納した連想配列、失敗時 `false` を返します。
	 */
	public function get_uploadfile( $key ){
		if(!strlen($key)){ return false; }
		if( @!is_array( $_SESSION ) ){
			return false;
		}
		if( @!array_key_exists('FILE', $_SESSION) ){
			return false;
		}
		if( @!array_key_exists($key, $_SESSION['FILE']) ){
			return false;
		}

		$rtn = @$_SESSION['FILE'][$key];
		if( is_null( $rtn ) ){ return false; }

		$rtn['content'] = base64_decode( @$rtn['content'] );
		return	$rtn;
	}
	/**
	 * セッションに保存されたファイル情報の一覧を取得する。
	 *
	 * @return array ファイル情報 を格納した連想配列
	 */
	public function get_uploadfile_list(){
		if( @!array_key_exists('FILE', $_SESSION) ){
			return false;
		}
		return	array_keys( $_SESSION['FILE'] );
	}
	/**
	 * セッションに保存されたファイルを削除する。
	 *
	 * @param string $key セッションキー
	 * @return bool 常に `true` を返します。
	 */
	public function delete_uploadfile( $key ){
		if( @!array_key_exists('FILE', $_SESSION) ){
			return true;
		}
		unset( $_SESSION['FILE'][$key] );
		return	true;
	}
	/**
	 * セッションに保存されたファイルを全て削除する。
	 *
	 * @return bool 常に `true` を返します。
	 */
	public function delete_uploadfile_all(){
		return	$this->delete_session( 'FILE' );
	}


	// ----- utils -----

	/**
	 * USER_AGENT を取得する。
	 *
	 * @return string USER_AGENT
	 */
	public function get_user_agent(){
		return @$this->conf->server['HTTP_USER_AGENT'];
	}//get_user_agent()

	/**
	 *  SSL通信か調べる
	 *
	 * @return bool SSL通信の場合 `true`、それ以外の場合 `false` を返します。
	 */
	public function is_ssl(){
		if( @$this->conf->server['HTTP_SSL'] || @$this->conf->server['HTTPS'] ){
			// SSL通信が有効か否か判断
			return true;
		}
		return false;
	}

	/**
	 * コマンドラインによる実行か確認する。
	 *
	 * @return bool コマンドからの実行の場合 `true`、ウェブからの実行の場合 `false` を返します。
	 */
	public function is_cmd(){
		if( array_key_exists( 'REMOTE_ADDR' , $this->conf->server ) ){
			return false;
		}
		return	true;
	}


	// ----- private -----

	/**
	 * 受け取ったテキストを、指定の文字セットに変換する。
	 *
	 * @param mixed $text テキスト
	 * @param string $encode 変換後の文字セット。省略時、`mb_internal_encoding()` から取得
	 * @param string $encodefrom 変換前の文字セット。省略時、自動検出
	 * @return string 文字セット変換後のテキスト
	 */
	private static function convert_encoding( $text, $encode = null, $encodefrom = null ){
		if( !is_callable( 'mb_internal_encoding' ) ){ return $text; }
		if( !strlen( $encodefrom ) ){ $encodefrom = mb_internal_encoding().',UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,JIS,ASCII'; }
		if( !strlen( $encode ) ){ $encode = mb_internal_encoding(); }

		if( is_array( $text ) ){
			$rtn = array();
			if( !count( $text ) ){ return $text; }
			$TEXT_KEYS = array_keys( $text );
			foreach( $TEXT_KEYS as $Line ){
				$KEY = mb_convert_encoding( $Line , $encode , $encodefrom );
				if( is_array( $text[$Line] ) ){
					$rtn[$KEY] = self::convert_encoding( $text[$Line] , $encode , $encodefrom );
				}else{
					$rtn[$KEY] = @mb_convert_encoding( $text[$Line] , $encode , $encodefrom );
				}
			}
		}else{
			if( !strlen( $text ) ){ return $text; }
			$rtn = @mb_convert_encoding( $text , $encode , $encodefrom );
		}
		return $rtn;
	}

	/**
	 * クォートされた文字列のクォート部分を取り除く。
	 *
	 * この関数は、PHPの `stripslashes()` のラッパーです。
	 * 配列を受け取ると再帰的に文字列を変換して返します。
	 *
	 * @param mixed $text テキスト
	 * @return string クォートが元に戻されたテキスト
	 */
	private static function stripslashes( $text ){
		if( is_array( $text ) ){
			// 配列なら
			foreach( $text as $key=>$val ){
				$text[$key] = self::stripslashes( $val );
			}
		}elseif( is_string( $text ) ){
			// 文字列なら
			$text = stripslashes( $text );
		}
		return	$text;
	}

	/**
	 * カレントディレクトリのパスを取得
	 * @return string ドキュメントルートからのパス(スラッシュ閉じ)
	 */
	private function get_path_current_dir(){
		//  環境変数から自動的に判断。
		$rtn = dirname( $this->conf->server['SCRIPT_NAME'] );
		if( !array_key_exists( 'REMOTE_ADDR' , $this->conf->server ) ){
			//  CUIから起動された場合
			//  ドキュメントルートが判定できないので、
			//  ドキュメントルート直下にあるものとする。
			$rtn = '/';
		}
		$rtn = str_replace('\\','/',$rtn);
		$rtn .= ($rtn!='/'?'/':'');
		return $rtn;
	}//get_path_current_dir()

}
?>
<?php
/**
 * rencon dbh class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_dbh{
	private $pdo;
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * PDOが有効か調べる
	 */
	public function is_pdo_enabled(){
		if( !class_exists('\\PDO') ){
			return false;
		}
		return true;
	}

	/**
	 * dbkey から dbinfo を取得する
	 */
	public function get_dbinfo_by_key( $dbkey ){
		$conf = $this->rencon->conf();
		$dbinfo = false;
		if( strlen($dbkey) && array_key_exists($dbkey, $conf->databases) ){
			$dbinfo = $conf->databases[$dbkey];
		}
		return $dbinfo;
	}

	/**
	 * DSNを生成する
	 */
	public function get_dsn_info_by_dbinfo( $dbinfo ){
		$dbinfo = (array) $dbinfo;
		$rtn = array(
			'dsn' => '',
			'username' => '',
			'password' => '',
			'options' => array(),
		);
		if( array_key_exists( 'username', $dbinfo ) ){
			$rtn['username'] = $dbinfo['username'];
		}
		if( array_key_exists( 'password', $dbinfo ) ){
			$rtn['password'] = $dbinfo['password'];
		}
		if( array_key_exists( 'options', $dbinfo ) ){
			$rtn['options'] = $dbinfo['options'];
		}

		if( array_key_exists( 'dsn', $dbinfo ) ){
			$rtn['dsn'] = $dbinfo['dsn'];
		}elseif( array_key_exists( 'driver', $dbinfo ) ){
			$rtn['dsn'] = strtolower($dbinfo['driver']).':';
			switch(strtolower($dbinfo['driver'])){
				case 'sqlite':
					$rtn['dsn'] .= $dbinfo['database'];
					break;
				case 'mysql':
				case 'pgsql':
					$array_dsns = array();
					if( array_key_exists( 'host', $dbinfo ) && strlen($dbinfo['host']) ){
						$array_dsns[] = 'host='.$dbinfo['host'];
					}
					if( array_key_exists( 'port', $dbinfo ) && strlen($dbinfo['port']) ){
						$array_dsns[] = 'port='.$dbinfo['port'];
					}
					if( array_key_exists( 'database', $dbinfo ) && strlen($dbinfo['database']) ){
						$array_dsns[] = 'dbname='.$dbinfo['database'];
					}
					if( array_key_exists( 'username', $dbinfo ) && strlen($dbinfo['username']) ){
						$array_dsns[] = 'user='.$dbinfo['username'];
					}
					if( array_key_exists( 'password', $dbinfo ) && strlen($dbinfo['password']) ){
						$array_dsns[] = 'password='.$dbinfo['password'];
					}
					$rtn['dsn'] .= implode(';', $array_dsns);
					break;
			}
		}
		return $rtn;
	}

	/**
	 * データベースに接続する
	 */
	public function connect( $dbkey ){
		$dbinfo = $this->get_dbinfo_by_key($dbkey);
		$dsn = $this->get_dsn_info_by_dbinfo($dbinfo);
		$this->pdo = new \PDO(
			$dsn['dsn'],
			$dsn['username'],
			$dsn['password'],
			$dsn['options']
		);
		return true;
	}

	/**
	 * PDO に直接アクセスする
	 */
	public function pdo(){
		return $this->pdo;
	}

	/**
	 * PDO::getAvailableDrivers()
	 */
	public function get_available_drivers(){
		if( !$this->is_pdo_enabled() ){
			return false;
		}
		return \PDO::getAvailableDrivers();
	}
}
?>
<?php
/**
 * node-remote-finder class
 * Copied from https://github.com/tomk79/node-remote-finder/blob/master/php/main.php
 * v0.3.0
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_vendor_tomk79_remoteFinder_main{

	private $fs;
	private $paths_root_dir = array();
	private $paths_readonly = array();
	private $paths_invisible = array();

	/**
	 * Constructor
	 */
	public function __construct($paths_root_dir, $options = array()){
		$this->fs = new rencon_filesystem();
		$this->paths_root_dir = $paths_root_dir;
		if( array_key_exists('paths_readonly', $options) ){
			$this->paths_readonly = $options['paths_readonly'];
		}
		if( array_key_exists('paths_invisible', $options) ){
			$this->paths_invisible = $options['paths_invisible'];
		}
	}


	/**
	 * ファイルとフォルダの一覧を取得する
	 */
	private function gpi_getItemList($path, $options){
		$realpath = $this->getRealpath($path);
		// var_dump($realpath);
		$rtn = array(
			'result' => true,
			'message' => "OK",
			'list' => array()
		);
		$list = $this->fs->ls($realpath);
		if( !is_array( $list ) ){
			$rtn['result'] = false;
			$rtn['message'] = 'Failed to read directory.';
			return $rtn;
		}

		sort($list);

		foreach($list as $idx=>$filename){
			$item = array();
			$item['name'] = $filename;
			if(is_dir($realpath.'/'.$item['name'])){
				$item['type'] = 'dir';
			}elseif(is_file($realpath.'/'.$item['name'])){
				$item['type'] = 'file';
			}
			$item['visible'] = $this->isVisiblePath($path.'/'.$item['name']);
			if(!$item['visible']){
				continue;
			}
			$item['writable'] = $this->isWritablePath($path.'/'.$item['name']);
			$item['ext'] = null;
			if( preg_match('/\.([a-zA-Z0-9\-\_]+)$/', $item['name'], $matched) ){
				$item['ext'] = $matched[1];
				$item['ext'] = strtolower($item['ext']);
			}
			array_push($rtn['list'], $item);
		}

		return $rtn;
	}

	/**
	 * 新しいファイルを作成する
	 */
	private function gpi_createNewFile($path, $options){
		if( !$this->isWritablePath( $path ) ){
			return array(
				'result' => false,
				'message' => "NOT writable path."
			);
		}

		$realpath = $this->getRealpath($path);

		if( file_exists($realpath) ){
			return array(
				'result' => false,
				'message' => "Already exists."
			);
		}

		$result = $this->fs->save_file($realpath, '');
		return array(
			'result' => !!$result,
			'message' => (!$result ? 'Failed to write file. ' . $path : 'OK')
		);
	}

	/**
	 * 新しいフォルダを作成する
	 */
	private function gpi_createNewFolder($path, $options){

		if( !$this->isWritablePath( $path ) ){
			return array(
				'result' => false,
				'message' => "NOT writable path."
			);
		}

		$realpath = $this->getRealpath($path);

		if( file_exists($realpath) ){
			return array(
				'result' => false,
				'message' => "Already exists."
			);
		}

		$result = $this->fs->mkdir($realpath);
		return array(
			'result' => !!$result,
			'message' => (!$result ? 'Failed to mkdir. ' . $path : 'OK')
		);
	}

	/**
	 * ファイルやフォルダを複製する
	 */
	private function gpi_copy($pathFrom, $options){
		$pathTo = $options->to;
		$rootDir = $this->paths_root_dir['default'];
		$realpathFrom = $this->getRealpath($pathFrom);
		$realpathTo = $this->getRealpath($pathTo);

		if( !$this->isWritablePath( $pathTo ) ){
			return array(
				'result' => false,
				'message' => "NOT writable path."
			);
		}

		if( !file_exists($realpathFrom) ){
			return array(
				'result' => false,
				'message' => "File or directory NOT exists." . $pathFrom
			);
		}

		if( file_exists($realpathTo) ){
			return array(
				'result' => false,
				'message' => "Already exists." . $pathTo
			);
		}

		$result = $this->fs->copy_r($realpathFrom, $realpathTo);
		return array(
			'result' => !!$result,
			'message' => (!$result ? 'Failed to copy file or directory. from ' . $pathFrom . ' to ' . $pathTo : 'OK')
		);
	}

	/**
	 * ファイルやフォルダを移動する
	 */
	private function gpi_rename($pathFrom, $options){
		$pathTo = $options->to;
		$rootDir = $this->paths_root_dir['default'];
		$realpathFrom = $this->getRealpath($pathFrom);
		$realpathTo = $this->getRealpath($pathTo);

		if( !$this->isWritablePath( $pathFrom ) ){
			return array(
				'result' => false,
				'message' => "NOT writable path."
			);
		}

		if( !$this->isWritablePath( $pathTo ) ){
			return array(
				'result' => false,
				'message' => "NOT writable path."
			);
		}

		if( !file_exists($realpathFrom) ){
			return array(
				'result' => false,
				'message' => "File or directory NOT exists." . $pathFrom
			);
		}

		if( file_exists($realpathTo) ){
			return array(
				'result' => false,
				'message' => "Already exists." . $pathTo
			);
		}

		$result = $this->fs->rename($realpathFrom, $realpathTo);
		return array(
			'result' => !!$result,
			'message' => (!$result ? 'Failed to rename file or directory. from ' . $pathFrom . ' to ' . $pathTo : 'OK')
		);
	}

	/**
	 * ファイルやフォルダを削除する
	 */
	private function gpi_remove($path, $options){
		if( !$this->isWritablePath( $path ) ){
			return array(
				'result' => false,
				'message' => "NOT writable path."
			);
		}

		$realpath = $this->getRealpath($path);

		if( !file_exists($realpath) ){
			return array(
				'result' => false,
				'message' => "Item NOT exists."
			);
		}

		$result = $this->fs->rm($realpath);
		return array(
			'result' => !!$result,
			'message' => (!$result ? 'Failed to remove file or directory. ' . $path : 'OK')
		);
	}

	/**
	 * 絶対パスを取得する
	 */
	public function getRealpath($path){
		$rootDir = $this->paths_root_dir['default'];
		$resolvedPath = $this->getResolvedPath($path);
		$realpath = $this->fs->get_realpath('.'.$resolvedPath, $rootDir);
		return $realpath;
	}

	/**
	 * パスを解決する
	 */
	public function getResolvedPath($path){
		$resolvedPath = $path;
		$resolvedPath = preg_replace('/[\\/\\\\]/', '/', $resolvedPath);
		$resolvedPath = preg_replace('/^[A-Z]\:+/i', '/', $resolvedPath);
		$resolvedPath = $this->fs->get_realpath('./'.$resolvedPath, '/');
		$resolvedPath = preg_replace('/^[^\\/\\\\]+/', '', $resolvedPath);
		$resolvedPath = preg_replace('/[\\/\\\\]/', '/', $resolvedPath);
		return $resolvedPath;
	}

	/**
	 * パスが表示可能か調べる
	 */
	public function isVisiblePath($path){
		$path = $this->getResolvedPath($path);
		$blackList = $this->paths_invisible;
		foreach($blackList as $i=>$ptn){
			$ptn = '/^'.preg_quote($ptn, '/').'$/';
			$ptn = str_replace( preg_quote('*', '/'), '.*', $ptn );
			if( preg_match( $ptn, $path ) ){
				return false;
			}
			if( preg_match( $ptn, $path.'/' ) ){
				return false;
			}
		}
		return true;
	}

	/**
	 * パスが書き込み可能か調べる
	 */
	public function isWritablePath($path){
		if( !$this->isVisiblePath($path) ){
			// 見えないパスは書き込みもできないべき。
			return false;
		}
		$path = $this->getResolvedPath($path);
		$blackList = $this->paths_readonly;
		foreach($blackList as $i=>$ptn){
			$ptn = '/^'.preg_quote($ptn, '/').'$/';
			$ptn = str_replace( preg_quote('*', '/'), '.*', $ptn );
			if( preg_match( $ptn, $path ) ){
				return false;
			}
			if( preg_match( $ptn, $path.'/' ) ){
				return false;
			}
		}
		return true;
	}

	/**
	 * General Purpose Interface
	 */
	public function gpi($input){
		try{
			if( preg_match('/[^a-zA-Z0-9]/s', $input->api) ){
				return array(
					'result' => false,
					'message' => '"'.$input->api.'" is an invalid API name.',
				);
			}
			if( is_callable( array($this, 'gpi_'.$input->api) ) ){
				$options = json_decode('{}');
				if( property_exists($input, 'options') ){
					$options = $input->options;
				}
				$result = $this->{'gpi_'.$input->api}($input->path, $options);
				$result = json_decode( json_encode($result) );
				return $result;
			}
			return array(
				'result' => false,
				'message' => 'An API "'.$input->api.'" is undefined, or not callable.',
			);
		}catch(\Exception $ex){
			return array(
				'result' => false,
				'Unknown Error.',
			);
		}
	}
}
?>
<?php
/**
 * rencon app
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_apps_db_ctrl{
	private $rencon;
	private $dbh;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
		$this->dbh = new rencon_dbh($this->rencon);

		$rencon->view()->set('dbh', $this->dbh);
		$rencon->theme()->set_h1('データベース管理');
	}

	/**
	 * デフォルトアクション
	 */
	public function index(){
		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

	/**
	 * テーブル一覧
	 */
	public function tables(){
		$conf = $this->rencon->conf();
		$dbkey = $this->rencon->req()->get_param('dbkey');
		$dbinfo = $this->dbh->get_dbinfo_by_key( $dbkey );
		$this->rencon->view()->set('dbkey', $dbkey);
		$this->rencon->view()->set('dbinfo', $dbinfo);
		if( $dbinfo ){
			$this->dbh->connect($dbkey);
		}

		$sql = $this->rencon->req()->get_param('db_sql');
		$sql = trim($sql);
		$result = null;
		$affectedRows = 0;
		if( strlen($sql) ){
			$sth = $this->dbh->pdo()->query($sql);
			if($sth){
				$result = $sth->fetchAll(PDO::FETCH_ASSOC);
				$affectedRows = $sth->rowCount();
			}
		}
		$this->rencon->view()->set('result', $result);
		$this->rencon->view()->set('affectedRows', $affectedRows);

		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

}
?>
<?php
/**
 * rencon app
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_apps_files_ctrl{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * デフォルトアクション
	 */
	public function index(){
		$this->rencon->theme()->set_h1('ファイルとフォルダ');

		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

	/**
	 * remoteFinder GPI
	 */
	public function rfgpi(){
		$remoteFinder = new rencon_vendor_tomk79_remoteFinder_main(array(
			'default' => '/'
		), array(
			'paths_invisible' => $this->rencon->conf()->files_paths_invisible,
			'paths_readonly' => $this->rencon->conf()->files_paths_readonly,
		));
		$value = $remoteFinder->gpi( json_decode( $_REQUEST['data'] ) );
		header('Content-type: text/json');
		echo json_encode($value);
		exit;
	}

}
?>
<?php
/**
 * rencon views class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_view{
	private $rencon;
	private $values = array();

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * 値をセット
	 */
	public function set($key, $val){
		$this->values[$key] = $val;
		return true;
	}

	/**
	 * 値を取得
	 */
	public function get($key){
		if( !array_key_exists($key, $this->values) ){
			return false;
		}
		return $this->values[$key];
	}

	/**
	 * ビューに値をバインド
	 */
	public function bind( $action = null ){
		if(!strlen( $action )){
			$action = $this->rencon->action();
		}
		$action_ary = explode('.', $action);
		$app = null;
		$act = null;

		if( array_key_exists(0, $action_ary) ){
			$app = $action_ary[0];
		}
		if( array_key_exists(1, $action_ary) ){
			$act = $action_ary[1];
		}
		if( !strlen($act) ){
			$act = 'index';
		}

if( $app == 'db' && $act == 'index' ){
ob_start(); ?><p>db app のビューです。</p>
<?php

if( !class_exists('PDO') ){
	echo '<p>PDOが利用できません。</p>';
}else{
	$conf = $this->rencon->conf();
	// var_dump($conf);
	echo '<ul>'."\n";
	foreach($conf->databases as $dbkey=>$dbinfo){
		echo '<li>';
		echo '<a href="?a=db.tables&dbkey='.urlencode($dbkey).'">'.htmlspecialchars($dbkey).'</a>';
		echo '</li>'."\n";

	}
	echo '</ul>'."\n";

	$drivers = $this->get('dbh')->get_available_drivers();
	echo '<p>'.implode(', ', $drivers).'</p>';
}


?>
<?php return ob_get_clean();
}
if( $app == 'db' && $act == 'tables' ){
ob_start(); ?><p>テーブル一覧です。</p>
<form action="" method="post">
<textarea name="db_sql" class="form-control"><?= htmlspecialchars( $this->rencon->req()->get_param('db_sql') ); ?></textarea>
<input type="submit" value="クエリを実行" class="btn btn-primary" />
</form>
<hr />


<?php
$results = $this->rencon->view()->get('result');
$affectedRows = $this->rencon->view()->get('affectedRows');
?>
<p><?= intval($affectedRows) ?> 件に影響</p>


<?php
if( !is_array($results) || !count($results) ){

}else{
	echo '<div class="table-responsive">'."\n";
	echo '<table class="table table-sm">'."\n";
	echo '<thead>'."\n";
	foreach($results as $result){
		echo '<tr>'."\n";
		foreach($result as $key=>$val){
			echo '<th>'.htmlspecialchars($key).'</th>'."\n";
		}
		echo '</tr>'."\n";
		break;
	}
	echo '</thead>'."\n";
	echo '<tbody>'."\n";
	foreach($results as $result){
		echo '<tr>'."\n";
		foreach($result as $key=>$val){
			echo '<td>'.htmlspecialchars($val).'</td>'."\n";
		}
		echo '</tr>'."\n";
	}
	echo '</tbody>'."\n";
	echo '</table>'."\n";
	echo '</div>'."\n";
	// var_dump( $results );
}
?>


<hr />
<p>a = <?= htmlspecialchars( $this->rencon->req()->get_param('a') ); ?></p>
<p>dbkey = <?= htmlspecialchars( $this->rencon->req()->get_param('dbkey') ); ?></p>
<hr />
<p><a href="?a=db">戻る</a></p>
<?php return ob_get_clean();
}
if( $app == 'files' && $act == 'index' ){
ob_start(); ?><div id="finder1"></div>

<link rel="stylesheet" href="?res=remote-finder/remote-finder.css" />
<script src="?res=remote-finder/remote-finder.js"></script>
<script>
var remoteFinder = window.remoteFinder = new RemoteFinder(
	document.getElementById('finder1'),
	{
		"gpiBridge": function(input, callback){ // required
			// console.log(input);
			var data = {
				'data': JSON.stringify(input)
			};
			var dataBody = Object.keys(data).map(function(key){ return key+"="+ encodeURIComponent(data[key]) }).join("&")
			// console.log(dataBody);
			fetch("?a=files.rfgpi", {
				method: "post",
				headers: {
					'content-type': 'application/x-www-form-urlencoded'
				},
				body: dataBody
			}).then(function (response) {
				var contentType = response.headers.get('content-type').toLowerCase();
				if(contentType.indexOf('application/json') === 0 || contentType.indexOf('text/json') === 0) {
					response.json().then(function(json){
						callback(json);
					});
				} else {
					response.text().then(function(text){
						callback(text);
					});
				}
			}).catch(function (response) {
				// console.log(response);
				callback(response);
			});
		},
		"open": function(fileinfo, callback){
			alert('ファイル ' + fileinfo.path + ' を開こうとしています。この機能は開発中のため利用できません。');
			callback(true);
		},
		"mkdir": function(current_dir, callback){
			var foldername = prompt('Folder name:');
			if( !foldername ){ return; }
			callback( foldername );
			return;
		},
		"mkfile": function(current_dir, callback){
			var filename = prompt('File name:');
			if( !filename ){ return; }
			callback( filename );
			return;
		},
		"rename": function(renameFrom, callback){
			var renameTo = prompt('Rename from '+renameFrom+' to:', renameFrom);
			callback( renameFrom, renameTo );
			return;
		},
		"remove": function(path_target, callback){
			if( !confirm(path_target + 'を削除しようとしています。 本当に削除してよろしいですか？') ){
				return;
			}
			callback();
			return;
		},
		"mkdir": function(current_dir, callback){
			var foldername = prompt('Folder name:');
			if( !foldername ){ return; }
			callback( foldername );
			return;
		},
		"mkdir": function(current_dir, callback){
			var foldername = prompt('Folder name:');
			if( !foldername ){ return; }
			callback( foldername );
			return;
		}
	}
);
// console.log(remoteFinder);
remoteFinder.init(<?= var_export(__DIR__, true) ?>, {}, function(){
	console.log('ready.');
});
</script>
<?php return ob_get_clean();
}


		return false;
	}

}
?>
<?php
/**
 * rencon resourceMgr class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_resourceMgr{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	public function echo_resource( $path ){
		$ext = null;
		if( preg_match('/\.([a-zA-Z0-9\_\-]*)$/', $path, $matched) ){
			$ext = $matched[1];
			$ext = strtolower($ext);
			switch( $ext ){
				case 'html': case 'htm': header('Content-type: text/html'); break;
				case 'js': header('Content-type: text/javascript'); break;
				case 'css': header('Content-type: text/css'); break;
				case 'jpg': case 'jpe': case 'jpeg': header('Content-type: image/jpeg'); break;
				case 'gif': header('Content-type: image/gif'); break;
				case 'png': header('Content-type: image/png'); break;
				case 'svg': header('Content-type: image/svg+xml'); break;
			}
		}
		echo $this->get($path);
		exit;
	}

	/**
	 * リソースを取得
	 */
	public function get( $path ){
		$path = preg_replace( '/$(?:\/*|\.\.?\/)*/', '', $path );

if($path == 'styles/common.css'){ return base64_decode('Ym9keXsKICAgIGJhY2tncm91bmQtY29sb3I6ICNmOWY5Zjk7Cn0K'); }
if($path == 'bootstrap4/css/bootstrap.min.css'){ return base64_decode('LyohCiAqIEJvb3RzdHJhcCB2NC40LjEgKGh0dHBzOi8vZ2V0Ym9vdHN0cmFwLmNvbS8pCiAqIENvcHlyaWdodCAyMDExLTIwMTkgVGhlIEJvb3RzdHJhcCBBdXRob3JzCiAqIENvcHlyaWdodCAyMDExLTIwMTkgVHdpdHRlciwgSW5jLgogKiBMaWNlbnNlZCB1bmRlciBNSVQgKGh0dHBzOi8vZ2l0aHViLmNvbS90d2JzL2Jvb3RzdHJhcC9ibG9iL21hc3Rlci9MSUNFTlNFKQogKi86cm9vdHstLWJsdWU6IzAwN2JmZjstLWluZGlnbzojNjYxMGYyOy0tcHVycGxlOiM2ZjQyYzE7LS1waW5rOiNlODNlOGM7LS1yZWQ6I2RjMzU0NTstLW9yYW5nZTojZmQ3ZTE0Oy0teWVsbG93OiNmZmMxMDc7LS1ncmVlbjojMjhhNzQ1Oy0tdGVhbDojMjBjOTk3Oy0tY3lhbjojMTdhMmI4Oy0td2hpdGU6I2ZmZjstLWdyYXk6IzZjNzU3ZDstLWdyYXktZGFyazojMzQzYTQwOy0tcHJpbWFyeTojMDA3YmZmOy0tc2Vjb25kYXJ5OiM2Yzc1N2Q7LS1zdWNjZXNzOiMyOGE3NDU7LS1pbmZvOiMxN2EyYjg7LS13YXJuaW5nOiNmZmMxMDc7LS1kYW5nZXI6I2RjMzU0NTstLWxpZ2h0OiNmOGY5ZmE7LS1kYXJrOiMzNDNhNDA7LS1icmVha3BvaW50LXhzOjA7LS1icmVha3BvaW50LXNtOjU3NnB4Oy0tYnJlYWtwb2ludC1tZDo3NjhweDstLWJyZWFrcG9pbnQtbGc6OTkycHg7LS1icmVha3BvaW50LXhsOjEyMDBweDstLWZvbnQtZmFtaWx5LXNhbnMtc2VyaWY6LWFwcGxlLXN5c3RlbSxCbGlua01hY1N5c3RlbUZvbnQsIlNlZ29lIFVJIixSb2JvdG8sIkhlbHZldGljYSBOZXVlIixBcmlhbCwiTm90byBTYW5zIixzYW5zLXNlcmlmLCJBcHBsZSBDb2xvciBFbW9qaSIsIlNlZ29lIFVJIEVtb2ppIiwiU2Vnb2UgVUkgU3ltYm9sIiwiTm90byBDb2xvciBFbW9qaSI7LS1mb250LWZhbWlseS1tb25vc3BhY2U6U0ZNb25vLVJlZ3VsYXIsTWVubG8sTW9uYWNvLENvbnNvbGFzLCJMaWJlcmF0aW9uIE1vbm8iLCJDb3VyaWVyIE5ldyIsbW9ub3NwYWNlfSosOjphZnRlciw6OmJlZm9yZXtib3gtc2l6aW5nOmJvcmRlci1ib3h9aHRtbHtmb250LWZhbWlseTpzYW5zLXNlcmlmO2xpbmUtaGVpZ2h0OjEuMTU7LXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OjEwMCU7LXdlYmtpdC10YXAtaGlnaGxpZ2h0LWNvbG9yOnRyYW5zcGFyZW50fWFydGljbGUsYXNpZGUsZmlnY2FwdGlvbixmaWd1cmUsZm9vdGVyLGhlYWRlcixoZ3JvdXAsbWFpbixuYXYsc2VjdGlvbntkaXNwbGF5OmJsb2NrfWJvZHl7bWFyZ2luOjA7Zm9udC1mYW1pbHk6LWFwcGxlLXN5c3RlbSxCbGlua01hY1N5c3RlbUZvbnQsIlNlZ29lIFVJIixSb2JvdG8sIkhlbHZldGljYSBOZXVlIixBcmlhbCwiTm90byBTYW5zIixzYW5zLXNlcmlmLCJBcHBsZSBDb2xvciBFbW9qaSIsIlNlZ29lIFVJIEVtb2ppIiwiU2Vnb2UgVUkgU3ltYm9sIiwiTm90byBDb2xvciBFbW9qaSI7Zm9udC1zaXplOjFyZW07Zm9udC13ZWlnaHQ6NDAwO2xpbmUtaGVpZ2h0OjEuNTtjb2xvcjojMjEyNTI5O3RleHQtYWxpZ246bGVmdDtiYWNrZ3JvdW5kLWNvbG9yOiNmZmZ9W3RhYmluZGV4PSItMSJdOmZvY3VzOm5vdCg6Zm9jdXMtdmlzaWJsZSl7b3V0bGluZTowIWltcG9ydGFudH1ocntib3gtc2l6aW5nOmNvbnRlbnQtYm94O2hlaWdodDowO292ZXJmbG93OnZpc2libGV9aDEsaDIsaDMsaDQsaDUsaDZ7bWFyZ2luLXRvcDowO21hcmdpbi1ib3R0b206LjVyZW19cHttYXJnaW4tdG9wOjA7bWFyZ2luLWJvdHRvbToxcmVtfWFiYnJbZGF0YS1vcmlnaW5hbC10aXRsZV0sYWJiclt0aXRsZV17dGV4dC1kZWNvcmF0aW9uOnVuZGVybGluZTstd2Via2l0LXRleHQtZGVjb3JhdGlvbjp1bmRlcmxpbmUgZG90dGVkO3RleHQtZGVjb3JhdGlvbjp1bmRlcmxpbmUgZG90dGVkO2N1cnNvcjpoZWxwO2JvcmRlci1ib3R0b206MDstd2Via2l0LXRleHQtZGVjb3JhdGlvbi1za2lwLWluazpub25lO3RleHQtZGVjb3JhdGlvbi1za2lwLWluazpub25lfWFkZHJlc3N7bWFyZ2luLWJvdHRvbToxcmVtO2ZvbnQtc3R5bGU6bm9ybWFsO2xpbmUtaGVpZ2h0OmluaGVyaXR9ZGwsb2wsdWx7bWFyZ2luLXRvcDowO21hcmdpbi1ib3R0b206MXJlbX1vbCBvbCxvbCB1bCx1bCBvbCx1bCB1bHttYXJnaW4tYm90dG9tOjB9ZHR7Zm9udC13ZWlnaHQ6NzAwfWRke21hcmdpbi1ib3R0b206LjVyZW07bWFyZ2luLWxlZnQ6MH1ibG9ja3F1b3Rle21hcmdpbjowIDAgMXJlbX1iLHN0cm9uZ3tmb250LXdlaWdodDpib2xkZXJ9c21hbGx7Zm9udC1zaXplOjgwJX1zdWIsc3Vwe3Bvc2l0aW9uOnJlbGF0aXZlO2ZvbnQtc2l6ZTo3NSU7bGluZS1oZWlnaHQ6MDt2ZXJ0aWNhbC1hbGlnbjpiYXNlbGluZX1zdWJ7Ym90dG9tOi0uMjVlbX1zdXB7dG9wOi0uNWVtfWF7Y29sb3I6IzAwN2JmZjt0ZXh0LWRlY29yYXRpb246bm9uZTtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fWE6aG92ZXJ7Y29sb3I6IzAwNTZiMzt0ZXh0LWRlY29yYXRpb246dW5kZXJsaW5lfWE6bm90KFtocmVmXSl7Y29sb3I6aW5oZXJpdDt0ZXh0LWRlY29yYXRpb246bm9uZX1hOm5vdChbaHJlZl0pOmhvdmVye2NvbG9yOmluaGVyaXQ7dGV4dC1kZWNvcmF0aW9uOm5vbmV9Y29kZSxrYmQscHJlLHNhbXB7Zm9udC1mYW1pbHk6U0ZNb25vLVJlZ3VsYXIsTWVubG8sTW9uYWNvLENvbnNvbGFzLCJMaWJlcmF0aW9uIE1vbm8iLCJDb3VyaWVyIE5ldyIsbW9ub3NwYWNlO2ZvbnQtc2l6ZToxZW19cHJle21hcmdpbi10b3A6MDttYXJnaW4tYm90dG9tOjFyZW07b3ZlcmZsb3c6YXV0b31maWd1cmV7bWFyZ2luOjAgMCAxcmVtfWltZ3t2ZXJ0aWNhbC1hbGlnbjptaWRkbGU7Ym9yZGVyLXN0eWxlOm5vbmV9c3Zne292ZXJmbG93OmhpZGRlbjt2ZXJ0aWNhbC1hbGlnbjptaWRkbGV9dGFibGV7Ym9yZGVyLWNvbGxhcHNlOmNvbGxhcHNlfWNhcHRpb257cGFkZGluZy10b3A6Ljc1cmVtO3BhZGRpbmctYm90dG9tOi43NXJlbTtjb2xvcjojNmM3NTdkO3RleHQtYWxpZ246bGVmdDtjYXB0aW9uLXNpZGU6Ym90dG9tfXRoe3RleHQtYWxpZ246aW5oZXJpdH1sYWJlbHtkaXNwbGF5OmlubGluZS1ibG9jazttYXJnaW4tYm90dG9tOi41cmVtfWJ1dHRvbntib3JkZXItcmFkaXVzOjB9YnV0dG9uOmZvY3Vze291dGxpbmU6MXB4IGRvdHRlZDtvdXRsaW5lOjVweCBhdXRvIC13ZWJraXQtZm9jdXMtcmluZy1jb2xvcn1idXR0b24saW5wdXQsb3B0Z3JvdXAsc2VsZWN0LHRleHRhcmVhe21hcmdpbjowO2ZvbnQtZmFtaWx5OmluaGVyaXQ7Zm9udC1zaXplOmluaGVyaXQ7bGluZS1oZWlnaHQ6aW5oZXJpdH1idXR0b24saW5wdXR7b3ZlcmZsb3c6dmlzaWJsZX1idXR0b24sc2VsZWN0e3RleHQtdHJhbnNmb3JtOm5vbmV9c2VsZWN0e3dvcmQtd3JhcDpub3JtYWx9W3R5cGU9YnV0dG9uXSxbdHlwZT1yZXNldF0sW3R5cGU9c3VibWl0XSxidXR0b257LXdlYmtpdC1hcHBlYXJhbmNlOmJ1dHRvbn1bdHlwZT1idXR0b25dOm5vdCg6ZGlzYWJsZWQpLFt0eXBlPXJlc2V0XTpub3QoOmRpc2FibGVkKSxbdHlwZT1zdWJtaXRdOm5vdCg6ZGlzYWJsZWQpLGJ1dHRvbjpub3QoOmRpc2FibGVkKXtjdXJzb3I6cG9pbnRlcn1bdHlwZT1idXR0b25dOjotbW96LWZvY3VzLWlubmVyLFt0eXBlPXJlc2V0XTo6LW1vei1mb2N1cy1pbm5lcixbdHlwZT1zdWJtaXRdOjotbW96LWZvY3VzLWlubmVyLGJ1dHRvbjo6LW1vei1mb2N1cy1pbm5lcntwYWRkaW5nOjA7Ym9yZGVyLXN0eWxlOm5vbmV9aW5wdXRbdHlwZT1jaGVja2JveF0saW5wdXRbdHlwZT1yYWRpb117Ym94LXNpemluZzpib3JkZXItYm94O3BhZGRpbmc6MH1pbnB1dFt0eXBlPWRhdGVdLGlucHV0W3R5cGU9ZGF0ZXRpbWUtbG9jYWxdLGlucHV0W3R5cGU9bW9udGhdLGlucHV0W3R5cGU9dGltZV17LXdlYmtpdC1hcHBlYXJhbmNlOmxpc3Rib3h9dGV4dGFyZWF7b3ZlcmZsb3c6YXV0bztyZXNpemU6dmVydGljYWx9ZmllbGRzZXR7bWluLXdpZHRoOjA7cGFkZGluZzowO21hcmdpbjowO2JvcmRlcjowfWxlZ2VuZHtkaXNwbGF5OmJsb2NrO3dpZHRoOjEwMCU7bWF4LXdpZHRoOjEwMCU7cGFkZGluZzowO21hcmdpbi1ib3R0b206LjVyZW07Zm9udC1zaXplOjEuNXJlbTtsaW5lLWhlaWdodDppbmhlcml0O2NvbG9yOmluaGVyaXQ7d2hpdGUtc3BhY2U6bm9ybWFsfXByb2dyZXNze3ZlcnRpY2FsLWFsaWduOmJhc2VsaW5lfVt0eXBlPW51bWJlcl06Oi13ZWJraXQtaW5uZXItc3Bpbi1idXR0b24sW3R5cGU9bnVtYmVyXTo6LXdlYmtpdC1vdXRlci1zcGluLWJ1dHRvbntoZWlnaHQ6YXV0b31bdHlwZT1zZWFyY2hde291dGxpbmUtb2Zmc2V0Oi0ycHg7LXdlYmtpdC1hcHBlYXJhbmNlOm5vbmV9W3R5cGU9c2VhcmNoXTo6LXdlYmtpdC1zZWFyY2gtZGVjb3JhdGlvbnstd2Via2l0LWFwcGVhcmFuY2U6bm9uZX06Oi13ZWJraXQtZmlsZS11cGxvYWQtYnV0dG9ue2ZvbnQ6aW5oZXJpdDstd2Via2l0LWFwcGVhcmFuY2U6YnV0dG9ufW91dHB1dHtkaXNwbGF5OmlubGluZS1ibG9ja31zdW1tYXJ5e2Rpc3BsYXk6bGlzdC1pdGVtO2N1cnNvcjpwb2ludGVyfXRlbXBsYXRle2Rpc3BsYXk6bm9uZX1baGlkZGVuXXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5oMSwuaDIsLmgzLC5oNCwuaDUsLmg2LGgxLGgyLGgzLGg0LGg1LGg2e21hcmdpbi1ib3R0b206LjVyZW07Zm9udC13ZWlnaHQ6NTAwO2xpbmUtaGVpZ2h0OjEuMn0uaDEsaDF7Zm9udC1zaXplOjIuNXJlbX0uaDIsaDJ7Zm9udC1zaXplOjJyZW19LmgzLGgze2ZvbnQtc2l6ZToxLjc1cmVtfS5oNCxoNHtmb250LXNpemU6MS41cmVtfS5oNSxoNXtmb250LXNpemU6MS4yNXJlbX0uaDYsaDZ7Zm9udC1zaXplOjFyZW19LmxlYWR7Zm9udC1zaXplOjEuMjVyZW07Zm9udC13ZWlnaHQ6MzAwfS5kaXNwbGF5LTF7Zm9udC1zaXplOjZyZW07Zm9udC13ZWlnaHQ6MzAwO2xpbmUtaGVpZ2h0OjEuMn0uZGlzcGxheS0ye2ZvbnQtc2l6ZTo1LjVyZW07Zm9udC13ZWlnaHQ6MzAwO2xpbmUtaGVpZ2h0OjEuMn0uZGlzcGxheS0ze2ZvbnQtc2l6ZTo0LjVyZW07Zm9udC13ZWlnaHQ6MzAwO2xpbmUtaGVpZ2h0OjEuMn0uZGlzcGxheS00e2ZvbnQtc2l6ZTozLjVyZW07Zm9udC13ZWlnaHQ6MzAwO2xpbmUtaGVpZ2h0OjEuMn1ocnttYXJnaW4tdG9wOjFyZW07bWFyZ2luLWJvdHRvbToxcmVtO2JvcmRlcjowO2JvcmRlci10b3A6MXB4IHNvbGlkIHJnYmEoMCwwLDAsLjEpfS5zbWFsbCxzbWFsbHtmb250LXNpemU6ODAlO2ZvbnQtd2VpZ2h0OjQwMH0ubWFyayxtYXJre3BhZGRpbmc6LjJlbTtiYWNrZ3JvdW5kLWNvbG9yOiNmY2Y4ZTN9Lmxpc3QtdW5zdHlsZWR7cGFkZGluZy1sZWZ0OjA7bGlzdC1zdHlsZTpub25lfS5saXN0LWlubGluZXtwYWRkaW5nLWxlZnQ6MDtsaXN0LXN0eWxlOm5vbmV9Lmxpc3QtaW5saW5lLWl0ZW17ZGlzcGxheTppbmxpbmUtYmxvY2t9Lmxpc3QtaW5saW5lLWl0ZW06bm90KDpsYXN0LWNoaWxkKXttYXJnaW4tcmlnaHQ6LjVyZW19LmluaXRpYWxpc217Zm9udC1zaXplOjkwJTt0ZXh0LXRyYW5zZm9ybTp1cHBlcmNhc2V9LmJsb2NrcXVvdGV7bWFyZ2luLWJvdHRvbToxcmVtO2ZvbnQtc2l6ZToxLjI1cmVtfS5ibG9ja3F1b3RlLWZvb3RlcntkaXNwbGF5OmJsb2NrO2ZvbnQtc2l6ZTo4MCU7Y29sb3I6IzZjNzU3ZH0uYmxvY2txdW90ZS1mb290ZXI6OmJlZm9yZXtjb250ZW50OiJcMjAxNFwwMEEwIn0uaW1nLWZsdWlke21heC13aWR0aDoxMDAlO2hlaWdodDphdXRvfS5pbWctdGh1bWJuYWlse3BhZGRpbmc6LjI1cmVtO2JhY2tncm91bmQtY29sb3I6I2ZmZjtib3JkZXI6MXB4IHNvbGlkICNkZWUyZTY7Ym9yZGVyLXJhZGl1czouMjVyZW07bWF4LXdpZHRoOjEwMCU7aGVpZ2h0OmF1dG99LmZpZ3VyZXtkaXNwbGF5OmlubGluZS1ibG9ja30uZmlndXJlLWltZ3ttYXJnaW4tYm90dG9tOi41cmVtO2xpbmUtaGVpZ2h0OjF9LmZpZ3VyZS1jYXB0aW9ue2ZvbnQtc2l6ZTo5MCU7Y29sb3I6IzZjNzU3ZH1jb2Rle2ZvbnQtc2l6ZTo4Ny41JTtjb2xvcjojZTgzZThjO3dvcmQtd3JhcDpicmVhay13b3JkfWE+Y29kZXtjb2xvcjppbmhlcml0fWtiZHtwYWRkaW5nOi4ycmVtIC40cmVtO2ZvbnQtc2l6ZTo4Ny41JTtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzIxMjUyOTtib3JkZXItcmFkaXVzOi4ycmVtfWtiZCBrYmR7cGFkZGluZzowO2ZvbnQtc2l6ZToxMDAlO2ZvbnQtd2VpZ2h0OjcwMH1wcmV7ZGlzcGxheTpibG9jaztmb250LXNpemU6ODcuNSU7Y29sb3I6IzIxMjUyOX1wcmUgY29kZXtmb250LXNpemU6aW5oZXJpdDtjb2xvcjppbmhlcml0O3dvcmQtYnJlYWs6bm9ybWFsfS5wcmUtc2Nyb2xsYWJsZXttYXgtaGVpZ2h0OjM0MHB4O292ZXJmbG93LXk6c2Nyb2xsfS5jb250YWluZXJ7d2lkdGg6MTAwJTtwYWRkaW5nLXJpZ2h0OjE1cHg7cGFkZGluZy1sZWZ0OjE1cHg7bWFyZ2luLXJpZ2h0OmF1dG87bWFyZ2luLWxlZnQ6YXV0b31AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmNvbnRhaW5lcnttYXgtd2lkdGg6NTQwcHh9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsuY29udGFpbmVye21heC13aWR0aDo3MjBweH19QG1lZGlhIChtaW4td2lkdGg6OTkycHgpey5jb250YWluZXJ7bWF4LXdpZHRoOjk2MHB4fX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5jb250YWluZXJ7bWF4LXdpZHRoOjExNDBweH19LmNvbnRhaW5lci1mbHVpZCwuY29udGFpbmVyLWxnLC5jb250YWluZXItbWQsLmNvbnRhaW5lci1zbSwuY29udGFpbmVyLXhse3dpZHRoOjEwMCU7cGFkZGluZy1yaWdodDoxNXB4O3BhZGRpbmctbGVmdDoxNXB4O21hcmdpbi1yaWdodDphdXRvO21hcmdpbi1sZWZ0OmF1dG99QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey5jb250YWluZXIsLmNvbnRhaW5lci1zbXttYXgtd2lkdGg6NTQwcHh9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsuY29udGFpbmVyLC5jb250YWluZXItbWQsLmNvbnRhaW5lci1zbXttYXgtd2lkdGg6NzIwcHh9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuY29udGFpbmVyLC5jb250YWluZXItbGcsLmNvbnRhaW5lci1tZCwuY29udGFpbmVyLXNte21heC13aWR0aDo5NjBweH19QG1lZGlhIChtaW4td2lkdGg6MTIwMHB4KXsuY29udGFpbmVyLC5jb250YWluZXItbGcsLmNvbnRhaW5lci1tZCwuY29udGFpbmVyLXNtLC5jb250YWluZXIteGx7bWF4LXdpZHRoOjExNDBweH19LnJvd3tkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC13cmFwOndyYXA7ZmxleC13cmFwOndyYXA7bWFyZ2luLXJpZ2h0Oi0xNXB4O21hcmdpbi1sZWZ0Oi0xNXB4fS5uby1ndXR0ZXJze21hcmdpbi1yaWdodDowO21hcmdpbi1sZWZ0OjB9Lm5vLWd1dHRlcnM+LmNvbCwubm8tZ3V0dGVycz5bY2xhc3MqPWNvbC1de3BhZGRpbmctcmlnaHQ6MDtwYWRkaW5nLWxlZnQ6MH0uY29sLC5jb2wtMSwuY29sLTEwLC5jb2wtMTEsLmNvbC0xMiwuY29sLTIsLmNvbC0zLC5jb2wtNCwuY29sLTUsLmNvbC02LC5jb2wtNywuY29sLTgsLmNvbC05LC5jb2wtYXV0bywuY29sLWxnLC5jb2wtbGctMSwuY29sLWxnLTEwLC5jb2wtbGctMTEsLmNvbC1sZy0xMiwuY29sLWxnLTIsLmNvbC1sZy0zLC5jb2wtbGctNCwuY29sLWxnLTUsLmNvbC1sZy02LC5jb2wtbGctNywuY29sLWxnLTgsLmNvbC1sZy05LC5jb2wtbGctYXV0bywuY29sLW1kLC5jb2wtbWQtMSwuY29sLW1kLTEwLC5jb2wtbWQtMTEsLmNvbC1tZC0xMiwuY29sLW1kLTIsLmNvbC1tZC0zLC5jb2wtbWQtNCwuY29sLW1kLTUsLmNvbC1tZC02LC5jb2wtbWQtNywuY29sLW1kLTgsLmNvbC1tZC05LC5jb2wtbWQtYXV0bywuY29sLXNtLC5jb2wtc20tMSwuY29sLXNtLTEwLC5jb2wtc20tMTEsLmNvbC1zbS0xMiwuY29sLXNtLTIsLmNvbC1zbS0zLC5jb2wtc20tNCwuY29sLXNtLTUsLmNvbC1zbS02LC5jb2wtc20tNywuY29sLXNtLTgsLmNvbC1zbS05LC5jb2wtc20tYXV0bywuY29sLXhsLC5jb2wteGwtMSwuY29sLXhsLTEwLC5jb2wteGwtMTEsLmNvbC14bC0xMiwuY29sLXhsLTIsLmNvbC14bC0zLC5jb2wteGwtNCwuY29sLXhsLTUsLmNvbC14bC02LC5jb2wteGwtNywuY29sLXhsLTgsLmNvbC14bC05LC5jb2wteGwtYXV0b3twb3NpdGlvbjpyZWxhdGl2ZTt3aWR0aDoxMDAlO3BhZGRpbmctcmlnaHQ6MTVweDtwYWRkaW5nLWxlZnQ6MTVweH0uY29sey1tcy1mbGV4LXByZWZlcnJlZC1zaXplOjA7ZmxleC1iYXNpczowOy1tcy1mbGV4LXBvc2l0aXZlOjE7ZmxleC1ncm93OjE7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLTE+KnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5yb3ctY29scy0yPip7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0ucm93LWNvbHMtMz4qey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LnJvdy1jb2xzLTQ+KnstbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5yb3ctY29scy01Pip7LW1zLWZsZXg6MCAwIDIwJTtmbGV4OjAgMCAyMCU7bWF4LXdpZHRoOjIwJX0ucm93LWNvbHMtNj4qey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC1hdXRvey1tcy1mbGV4OjAgMCBhdXRvO2ZsZXg6MCAwIGF1dG87d2lkdGg6YXV0bzttYXgtd2lkdGg6MTAwJX0uY29sLTF7LW1zLWZsZXg6MCAwIDguMzMzMzMzJTtmbGV4OjAgMCA4LjMzMzMzMyU7bWF4LXdpZHRoOjguMzMzMzMzJX0uY29sLTJ7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLTN7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0uY29sLTR7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0uY29sLTV7LW1zLWZsZXg6MCAwIDQxLjY2NjY2NyU7ZmxleDowIDAgNDEuNjY2NjY3JTttYXgtd2lkdGg6NDEuNjY2NjY3JX0uY29sLTZ7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0uY29sLTd7LW1zLWZsZXg6MCAwIDU4LjMzMzMzMyU7ZmxleDowIDAgNTguMzMzMzMzJTttYXgtd2lkdGg6NTguMzMzMzMzJX0uY29sLTh7LW1zLWZsZXg6MCAwIDY2LjY2NjY2NyU7ZmxleDowIDAgNjYuNjY2NjY3JTttYXgtd2lkdGg6NjYuNjY2NjY3JX0uY29sLTl7LW1zLWZsZXg6MCAwIDc1JTtmbGV4OjAgMCA3NSU7bWF4LXdpZHRoOjc1JX0uY29sLTEwey1tcy1mbGV4OjAgMCA4My4zMzMzMzMlO2ZsZXg6MCAwIDgzLjMzMzMzMyU7bWF4LXdpZHRoOjgzLjMzMzMzMyV9LmNvbC0xMXstbXMtZmxleDowIDAgOTEuNjY2NjY3JTtmbGV4OjAgMCA5MS42NjY2NjclO21heC13aWR0aDo5MS42NjY2NjclfS5jb2wtMTJ7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ub3JkZXItZmlyc3R7LW1zLWZsZXgtb3JkZXI6LTE7b3JkZXI6LTF9Lm9yZGVyLWxhc3R7LW1zLWZsZXgtb3JkZXI6MTM7b3JkZXI6MTN9Lm9yZGVyLTB7LW1zLWZsZXgtb3JkZXI6MDtvcmRlcjowfS5vcmRlci0xey1tcy1mbGV4LW9yZGVyOjE7b3JkZXI6MX0ub3JkZXItMnstbXMtZmxleC1vcmRlcjoyO29yZGVyOjJ9Lm9yZGVyLTN7LW1zLWZsZXgtb3JkZXI6MztvcmRlcjozfS5vcmRlci00ey1tcy1mbGV4LW9yZGVyOjQ7b3JkZXI6NH0ub3JkZXItNXstbXMtZmxleC1vcmRlcjo1O29yZGVyOjV9Lm9yZGVyLTZ7LW1zLWZsZXgtb3JkZXI6NjtvcmRlcjo2fS5vcmRlci03ey1tcy1mbGV4LW9yZGVyOjc7b3JkZXI6N30ub3JkZXItOHstbXMtZmxleC1vcmRlcjo4O29yZGVyOjh9Lm9yZGVyLTl7LW1zLWZsZXgtb3JkZXI6OTtvcmRlcjo5fS5vcmRlci0xMHstbXMtZmxleC1vcmRlcjoxMDtvcmRlcjoxMH0ub3JkZXItMTF7LW1zLWZsZXgtb3JkZXI6MTE7b3JkZXI6MTF9Lm9yZGVyLTEyey1tcy1mbGV4LW9yZGVyOjEyO29yZGVyOjEyfS5vZmZzZXQtMXttYXJnaW4tbGVmdDo4LjMzMzMzMyV9Lm9mZnNldC0ye21hcmdpbi1sZWZ0OjE2LjY2NjY2NyV9Lm9mZnNldC0ze21hcmdpbi1sZWZ0OjI1JX0ub2Zmc2V0LTR7bWFyZ2luLWxlZnQ6MzMuMzMzMzMzJX0ub2Zmc2V0LTV7bWFyZ2luLWxlZnQ6NDEuNjY2NjY3JX0ub2Zmc2V0LTZ7bWFyZ2luLWxlZnQ6NTAlfS5vZmZzZXQtN3ttYXJnaW4tbGVmdDo1OC4zMzMzMzMlfS5vZmZzZXQtOHttYXJnaW4tbGVmdDo2Ni42NjY2NjclfS5vZmZzZXQtOXttYXJnaW4tbGVmdDo3NSV9Lm9mZnNldC0xMHttYXJnaW4tbGVmdDo4My4zMzMzMzMlfS5vZmZzZXQtMTF7bWFyZ2luLWxlZnQ6OTEuNjY2NjY3JX1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmNvbC1zbXstbXMtZmxleC1wcmVmZXJyZWQtc2l6ZTowO2ZsZXgtYmFzaXM6MDstbXMtZmxleC1wb3NpdGl2ZToxO2ZsZXgtZ3JvdzoxO21heC13aWR0aDoxMDAlfS5yb3ctY29scy1zbS0xPip7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ucm93LWNvbHMtc20tMj4qey1tcy1mbGV4OjAgMCA1MCU7ZmxleDowIDAgNTAlO21heC13aWR0aDo1MCV9LnJvdy1jb2xzLXNtLTM+KnstbXMtZmxleDowIDAgMzMuMzMzMzMzJTtmbGV4OjAgMCAzMy4zMzMzMzMlO21heC13aWR0aDozMy4zMzMzMzMlfS5yb3ctY29scy1zbS00Pip7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0ucm93LWNvbHMtc20tNT4qey1tcy1mbGV4OjAgMCAyMCU7ZmxleDowIDAgMjAlO21heC13aWR0aDoyMCV9LnJvdy1jb2xzLXNtLTY+KnstbXMtZmxleDowIDAgMTYuNjY2NjY3JTtmbGV4OjAgMCAxNi42NjY2NjclO21heC13aWR0aDoxNi42NjY2NjclfS5jb2wtc20tYXV0b3stbXMtZmxleDowIDAgYXV0bztmbGV4OjAgMCBhdXRvO3dpZHRoOmF1dG87bWF4LXdpZHRoOjEwMCV9LmNvbC1zbS0xey1tcy1mbGV4OjAgMCA4LjMzMzMzMyU7ZmxleDowIDAgOC4zMzMzMzMlO21heC13aWR0aDo4LjMzMzMzMyV9LmNvbC1zbS0yey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC1zbS0zey1tcy1mbGV4OjAgMCAyNSU7ZmxleDowIDAgMjUlO21heC13aWR0aDoyNSV9LmNvbC1zbS00ey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LmNvbC1zbS01ey1tcy1mbGV4OjAgMCA0MS42NjY2NjclO2ZsZXg6MCAwIDQxLjY2NjY2NyU7bWF4LXdpZHRoOjQxLjY2NjY2NyV9LmNvbC1zbS02ey1tcy1mbGV4OjAgMCA1MCU7ZmxleDowIDAgNTAlO21heC13aWR0aDo1MCV9LmNvbC1zbS03ey1tcy1mbGV4OjAgMCA1OC4zMzMzMzMlO2ZsZXg6MCAwIDU4LjMzMzMzMyU7bWF4LXdpZHRoOjU4LjMzMzMzMyV9LmNvbC1zbS04ey1tcy1mbGV4OjAgMCA2Ni42NjY2NjclO2ZsZXg6MCAwIDY2LjY2NjY2NyU7bWF4LXdpZHRoOjY2LjY2NjY2NyV9LmNvbC1zbS05ey1tcy1mbGV4OjAgMCA3NSU7ZmxleDowIDAgNzUlO21heC13aWR0aDo3NSV9LmNvbC1zbS0xMHstbXMtZmxleDowIDAgODMuMzMzMzMzJTtmbGV4OjAgMCA4My4zMzMzMzMlO21heC13aWR0aDo4My4zMzMzMzMlfS5jb2wtc20tMTF7LW1zLWZsZXg6MCAwIDkxLjY2NjY2NyU7ZmxleDowIDAgOTEuNjY2NjY3JTttYXgtd2lkdGg6OTEuNjY2NjY3JX0uY29sLXNtLTEyey1tcy1mbGV4OjAgMCAxMDAlO2ZsZXg6MCAwIDEwMCU7bWF4LXdpZHRoOjEwMCV9Lm9yZGVyLXNtLWZpcnN0ey1tcy1mbGV4LW9yZGVyOi0xO29yZGVyOi0xfS5vcmRlci1zbS1sYXN0ey1tcy1mbGV4LW9yZGVyOjEzO29yZGVyOjEzfS5vcmRlci1zbS0wey1tcy1mbGV4LW9yZGVyOjA7b3JkZXI6MH0ub3JkZXItc20tMXstbXMtZmxleC1vcmRlcjoxO29yZGVyOjF9Lm9yZGVyLXNtLTJ7LW1zLWZsZXgtb3JkZXI6MjtvcmRlcjoyfS5vcmRlci1zbS0zey1tcy1mbGV4LW9yZGVyOjM7b3JkZXI6M30ub3JkZXItc20tNHstbXMtZmxleC1vcmRlcjo0O29yZGVyOjR9Lm9yZGVyLXNtLTV7LW1zLWZsZXgtb3JkZXI6NTtvcmRlcjo1fS5vcmRlci1zbS02ey1tcy1mbGV4LW9yZGVyOjY7b3JkZXI6Nn0ub3JkZXItc20tN3stbXMtZmxleC1vcmRlcjo3O29yZGVyOjd9Lm9yZGVyLXNtLTh7LW1zLWZsZXgtb3JkZXI6ODtvcmRlcjo4fS5vcmRlci1zbS05ey1tcy1mbGV4LW9yZGVyOjk7b3JkZXI6OX0ub3JkZXItc20tMTB7LW1zLWZsZXgtb3JkZXI6MTA7b3JkZXI6MTB9Lm9yZGVyLXNtLTExey1tcy1mbGV4LW9yZGVyOjExO29yZGVyOjExfS5vcmRlci1zbS0xMnstbXMtZmxleC1vcmRlcjoxMjtvcmRlcjoxMn0ub2Zmc2V0LXNtLTB7bWFyZ2luLWxlZnQ6MH0ub2Zmc2V0LXNtLTF7bWFyZ2luLWxlZnQ6OC4zMzMzMzMlfS5vZmZzZXQtc20tMnttYXJnaW4tbGVmdDoxNi42NjY2NjclfS5vZmZzZXQtc20tM3ttYXJnaW4tbGVmdDoyNSV9Lm9mZnNldC1zbS00e21hcmdpbi1sZWZ0OjMzLjMzMzMzMyV9Lm9mZnNldC1zbS01e21hcmdpbi1sZWZ0OjQxLjY2NjY2NyV9Lm9mZnNldC1zbS02e21hcmdpbi1sZWZ0OjUwJX0ub2Zmc2V0LXNtLTd7bWFyZ2luLWxlZnQ6NTguMzMzMzMzJX0ub2Zmc2V0LXNtLTh7bWFyZ2luLWxlZnQ6NjYuNjY2NjY3JX0ub2Zmc2V0LXNtLTl7bWFyZ2luLWxlZnQ6NzUlfS5vZmZzZXQtc20tMTB7bWFyZ2luLWxlZnQ6ODMuMzMzMzMzJX0ub2Zmc2V0LXNtLTExe21hcmdpbi1sZWZ0OjkxLjY2NjY2NyV9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsuY29sLW1key1tcy1mbGV4LXByZWZlcnJlZC1zaXplOjA7ZmxleC1iYXNpczowOy1tcy1mbGV4LXBvc2l0aXZlOjE7ZmxleC1ncm93OjE7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLW1kLTE+KnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5yb3ctY29scy1tZC0yPip7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0ucm93LWNvbHMtbWQtMz4qey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LnJvdy1jb2xzLW1kLTQ+KnstbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5yb3ctY29scy1tZC01Pip7LW1zLWZsZXg6MCAwIDIwJTtmbGV4OjAgMCAyMCU7bWF4LXdpZHRoOjIwJX0ucm93LWNvbHMtbWQtNj4qey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC1tZC1hdXRvey1tcy1mbGV4OjAgMCBhdXRvO2ZsZXg6MCAwIGF1dG87d2lkdGg6YXV0bzttYXgtd2lkdGg6MTAwJX0uY29sLW1kLTF7LW1zLWZsZXg6MCAwIDguMzMzMzMzJTtmbGV4OjAgMCA4LjMzMzMzMyU7bWF4LXdpZHRoOjguMzMzMzMzJX0uY29sLW1kLTJ7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLW1kLTN7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0uY29sLW1kLTR7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0uY29sLW1kLTV7LW1zLWZsZXg6MCAwIDQxLjY2NjY2NyU7ZmxleDowIDAgNDEuNjY2NjY3JTttYXgtd2lkdGg6NDEuNjY2NjY3JX0uY29sLW1kLTZ7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0uY29sLW1kLTd7LW1zLWZsZXg6MCAwIDU4LjMzMzMzMyU7ZmxleDowIDAgNTguMzMzMzMzJTttYXgtd2lkdGg6NTguMzMzMzMzJX0uY29sLW1kLTh7LW1zLWZsZXg6MCAwIDY2LjY2NjY2NyU7ZmxleDowIDAgNjYuNjY2NjY3JTttYXgtd2lkdGg6NjYuNjY2NjY3JX0uY29sLW1kLTl7LW1zLWZsZXg6MCAwIDc1JTtmbGV4OjAgMCA3NSU7bWF4LXdpZHRoOjc1JX0uY29sLW1kLTEwey1tcy1mbGV4OjAgMCA4My4zMzMzMzMlO2ZsZXg6MCAwIDgzLjMzMzMzMyU7bWF4LXdpZHRoOjgzLjMzMzMzMyV9LmNvbC1tZC0xMXstbXMtZmxleDowIDAgOTEuNjY2NjY3JTtmbGV4OjAgMCA5MS42NjY2NjclO21heC13aWR0aDo5MS42NjY2NjclfS5jb2wtbWQtMTJ7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ub3JkZXItbWQtZmlyc3R7LW1zLWZsZXgtb3JkZXI6LTE7b3JkZXI6LTF9Lm9yZGVyLW1kLWxhc3R7LW1zLWZsZXgtb3JkZXI6MTM7b3JkZXI6MTN9Lm9yZGVyLW1kLTB7LW1zLWZsZXgtb3JkZXI6MDtvcmRlcjowfS5vcmRlci1tZC0xey1tcy1mbGV4LW9yZGVyOjE7b3JkZXI6MX0ub3JkZXItbWQtMnstbXMtZmxleC1vcmRlcjoyO29yZGVyOjJ9Lm9yZGVyLW1kLTN7LW1zLWZsZXgtb3JkZXI6MztvcmRlcjozfS5vcmRlci1tZC00ey1tcy1mbGV4LW9yZGVyOjQ7b3JkZXI6NH0ub3JkZXItbWQtNXstbXMtZmxleC1vcmRlcjo1O29yZGVyOjV9Lm9yZGVyLW1kLTZ7LW1zLWZsZXgtb3JkZXI6NjtvcmRlcjo2fS5vcmRlci1tZC03ey1tcy1mbGV4LW9yZGVyOjc7b3JkZXI6N30ub3JkZXItbWQtOHstbXMtZmxleC1vcmRlcjo4O29yZGVyOjh9Lm9yZGVyLW1kLTl7LW1zLWZsZXgtb3JkZXI6OTtvcmRlcjo5fS5vcmRlci1tZC0xMHstbXMtZmxleC1vcmRlcjoxMDtvcmRlcjoxMH0ub3JkZXItbWQtMTF7LW1zLWZsZXgtb3JkZXI6MTE7b3JkZXI6MTF9Lm9yZGVyLW1kLTEyey1tcy1mbGV4LW9yZGVyOjEyO29yZGVyOjEyfS5vZmZzZXQtbWQtMHttYXJnaW4tbGVmdDowfS5vZmZzZXQtbWQtMXttYXJnaW4tbGVmdDo4LjMzMzMzMyV9Lm9mZnNldC1tZC0ye21hcmdpbi1sZWZ0OjE2LjY2NjY2NyV9Lm9mZnNldC1tZC0ze21hcmdpbi1sZWZ0OjI1JX0ub2Zmc2V0LW1kLTR7bWFyZ2luLWxlZnQ6MzMuMzMzMzMzJX0ub2Zmc2V0LW1kLTV7bWFyZ2luLWxlZnQ6NDEuNjY2NjY3JX0ub2Zmc2V0LW1kLTZ7bWFyZ2luLWxlZnQ6NTAlfS5vZmZzZXQtbWQtN3ttYXJnaW4tbGVmdDo1OC4zMzMzMzMlfS5vZmZzZXQtbWQtOHttYXJnaW4tbGVmdDo2Ni42NjY2NjclfS5vZmZzZXQtbWQtOXttYXJnaW4tbGVmdDo3NSV9Lm9mZnNldC1tZC0xMHttYXJnaW4tbGVmdDo4My4zMzMzMzMlfS5vZmZzZXQtbWQtMTF7bWFyZ2luLWxlZnQ6OTEuNjY2NjY3JX19QG1lZGlhIChtaW4td2lkdGg6OTkycHgpey5jb2wtbGd7LW1zLWZsZXgtcHJlZmVycmVkLXNpemU6MDtmbGV4LWJhc2lzOjA7LW1zLWZsZXgtcG9zaXRpdmU6MTtmbGV4LWdyb3c6MTttYXgtd2lkdGg6MTAwJX0ucm93LWNvbHMtbGctMT4qey1tcy1mbGV4OjAgMCAxMDAlO2ZsZXg6MCAwIDEwMCU7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLWxnLTI+KnstbXMtZmxleDowIDAgNTAlO2ZsZXg6MCAwIDUwJTttYXgtd2lkdGg6NTAlfS5yb3ctY29scy1sZy0zPip7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0ucm93LWNvbHMtbGctND4qey1tcy1mbGV4OjAgMCAyNSU7ZmxleDowIDAgMjUlO21heC13aWR0aDoyNSV9LnJvdy1jb2xzLWxnLTU+KnstbXMtZmxleDowIDAgMjAlO2ZsZXg6MCAwIDIwJTttYXgtd2lkdGg6MjAlfS5yb3ctY29scy1sZy02Pip7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLWxnLWF1dG97LW1zLWZsZXg6MCAwIGF1dG87ZmxleDowIDAgYXV0bzt3aWR0aDphdXRvO21heC13aWR0aDoxMDAlfS5jb2wtbGctMXstbXMtZmxleDowIDAgOC4zMzMzMzMlO2ZsZXg6MCAwIDguMzMzMzMzJTttYXgtd2lkdGg6OC4zMzMzMzMlfS5jb2wtbGctMnstbXMtZmxleDowIDAgMTYuNjY2NjY3JTtmbGV4OjAgMCAxNi42NjY2NjclO21heC13aWR0aDoxNi42NjY2NjclfS5jb2wtbGctM3stbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5jb2wtbGctNHstbXMtZmxleDowIDAgMzMuMzMzMzMzJTtmbGV4OjAgMCAzMy4zMzMzMzMlO21heC13aWR0aDozMy4zMzMzMzMlfS5jb2wtbGctNXstbXMtZmxleDowIDAgNDEuNjY2NjY3JTtmbGV4OjAgMCA0MS42NjY2NjclO21heC13aWR0aDo0MS42NjY2NjclfS5jb2wtbGctNnstbXMtZmxleDowIDAgNTAlO2ZsZXg6MCAwIDUwJTttYXgtd2lkdGg6NTAlfS5jb2wtbGctN3stbXMtZmxleDowIDAgNTguMzMzMzMzJTtmbGV4OjAgMCA1OC4zMzMzMzMlO21heC13aWR0aDo1OC4zMzMzMzMlfS5jb2wtbGctOHstbXMtZmxleDowIDAgNjYuNjY2NjY3JTtmbGV4OjAgMCA2Ni42NjY2NjclO21heC13aWR0aDo2Ni42NjY2NjclfS5jb2wtbGctOXstbXMtZmxleDowIDAgNzUlO2ZsZXg6MCAwIDc1JTttYXgtd2lkdGg6NzUlfS5jb2wtbGctMTB7LW1zLWZsZXg6MCAwIDgzLjMzMzMzMyU7ZmxleDowIDAgODMuMzMzMzMzJTttYXgtd2lkdGg6ODMuMzMzMzMzJX0uY29sLWxnLTExey1tcy1mbGV4OjAgMCA5MS42NjY2NjclO2ZsZXg6MCAwIDkxLjY2NjY2NyU7bWF4LXdpZHRoOjkxLjY2NjY2NyV9LmNvbC1sZy0xMnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5vcmRlci1sZy1maXJzdHstbXMtZmxleC1vcmRlcjotMTtvcmRlcjotMX0ub3JkZXItbGctbGFzdHstbXMtZmxleC1vcmRlcjoxMztvcmRlcjoxM30ub3JkZXItbGctMHstbXMtZmxleC1vcmRlcjowO29yZGVyOjB9Lm9yZGVyLWxnLTF7LW1zLWZsZXgtb3JkZXI6MTtvcmRlcjoxfS5vcmRlci1sZy0yey1tcy1mbGV4LW9yZGVyOjI7b3JkZXI6Mn0ub3JkZXItbGctM3stbXMtZmxleC1vcmRlcjozO29yZGVyOjN9Lm9yZGVyLWxnLTR7LW1zLWZsZXgtb3JkZXI6NDtvcmRlcjo0fS5vcmRlci1sZy01ey1tcy1mbGV4LW9yZGVyOjU7b3JkZXI6NX0ub3JkZXItbGctNnstbXMtZmxleC1vcmRlcjo2O29yZGVyOjZ9Lm9yZGVyLWxnLTd7LW1zLWZsZXgtb3JkZXI6NztvcmRlcjo3fS5vcmRlci1sZy04ey1tcy1mbGV4LW9yZGVyOjg7b3JkZXI6OH0ub3JkZXItbGctOXstbXMtZmxleC1vcmRlcjo5O29yZGVyOjl9Lm9yZGVyLWxnLTEwey1tcy1mbGV4LW9yZGVyOjEwO29yZGVyOjEwfS5vcmRlci1sZy0xMXstbXMtZmxleC1vcmRlcjoxMTtvcmRlcjoxMX0ub3JkZXItbGctMTJ7LW1zLWZsZXgtb3JkZXI6MTI7b3JkZXI6MTJ9Lm9mZnNldC1sZy0we21hcmdpbi1sZWZ0OjB9Lm9mZnNldC1sZy0xe21hcmdpbi1sZWZ0OjguMzMzMzMzJX0ub2Zmc2V0LWxnLTJ7bWFyZ2luLWxlZnQ6MTYuNjY2NjY3JX0ub2Zmc2V0LWxnLTN7bWFyZ2luLWxlZnQ6MjUlfS5vZmZzZXQtbGctNHttYXJnaW4tbGVmdDozMy4zMzMzMzMlfS5vZmZzZXQtbGctNXttYXJnaW4tbGVmdDo0MS42NjY2NjclfS5vZmZzZXQtbGctNnttYXJnaW4tbGVmdDo1MCV9Lm9mZnNldC1sZy03e21hcmdpbi1sZWZ0OjU4LjMzMzMzMyV9Lm9mZnNldC1sZy04e21hcmdpbi1sZWZ0OjY2LjY2NjY2NyV9Lm9mZnNldC1sZy05e21hcmdpbi1sZWZ0Ojc1JX0ub2Zmc2V0LWxnLTEwe21hcmdpbi1sZWZ0OjgzLjMzMzMzMyV9Lm9mZnNldC1sZy0xMXttYXJnaW4tbGVmdDo5MS42NjY2NjclfX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5jb2wteGx7LW1zLWZsZXgtcHJlZmVycmVkLXNpemU6MDtmbGV4LWJhc2lzOjA7LW1zLWZsZXgtcG9zaXRpdmU6MTtmbGV4LWdyb3c6MTttYXgtd2lkdGg6MTAwJX0ucm93LWNvbHMteGwtMT4qey1tcy1mbGV4OjAgMCAxMDAlO2ZsZXg6MCAwIDEwMCU7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLXhsLTI+KnstbXMtZmxleDowIDAgNTAlO2ZsZXg6MCAwIDUwJTttYXgtd2lkdGg6NTAlfS5yb3ctY29scy14bC0zPip7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0ucm93LWNvbHMteGwtND4qey1tcy1mbGV4OjAgMCAyNSU7ZmxleDowIDAgMjUlO21heC13aWR0aDoyNSV9LnJvdy1jb2xzLXhsLTU+KnstbXMtZmxleDowIDAgMjAlO2ZsZXg6MCAwIDIwJTttYXgtd2lkdGg6MjAlfS5yb3ctY29scy14bC02Pip7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLXhsLWF1dG97LW1zLWZsZXg6MCAwIGF1dG87ZmxleDowIDAgYXV0bzt3aWR0aDphdXRvO21heC13aWR0aDoxMDAlfS5jb2wteGwtMXstbXMtZmxleDowIDAgOC4zMzMzMzMlO2ZsZXg6MCAwIDguMzMzMzMzJTttYXgtd2lkdGg6OC4zMzMzMzMlfS5jb2wteGwtMnstbXMtZmxleDowIDAgMTYuNjY2NjY3JTtmbGV4OjAgMCAxNi42NjY2NjclO21heC13aWR0aDoxNi42NjY2NjclfS5jb2wteGwtM3stbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5jb2wteGwtNHstbXMtZmxleDowIDAgMzMuMzMzMzMzJTtmbGV4OjAgMCAzMy4zMzMzMzMlO21heC13aWR0aDozMy4zMzMzMzMlfS5jb2wteGwtNXstbXMtZmxleDowIDAgNDEuNjY2NjY3JTtmbGV4OjAgMCA0MS42NjY2NjclO21heC13aWR0aDo0MS42NjY2NjclfS5jb2wteGwtNnstbXMtZmxleDowIDAgNTAlO2ZsZXg6MCAwIDUwJTttYXgtd2lkdGg6NTAlfS5jb2wteGwtN3stbXMtZmxleDowIDAgNTguMzMzMzMzJTtmbGV4OjAgMCA1OC4zMzMzMzMlO21heC13aWR0aDo1OC4zMzMzMzMlfS5jb2wteGwtOHstbXMtZmxleDowIDAgNjYuNjY2NjY3JTtmbGV4OjAgMCA2Ni42NjY2NjclO21heC13aWR0aDo2Ni42NjY2NjclfS5jb2wteGwtOXstbXMtZmxleDowIDAgNzUlO2ZsZXg6MCAwIDc1JTttYXgtd2lkdGg6NzUlfS5jb2wteGwtMTB7LW1zLWZsZXg6MCAwIDgzLjMzMzMzMyU7ZmxleDowIDAgODMuMzMzMzMzJTttYXgtd2lkdGg6ODMuMzMzMzMzJX0uY29sLXhsLTExey1tcy1mbGV4OjAgMCA5MS42NjY2NjclO2ZsZXg6MCAwIDkxLjY2NjY2NyU7bWF4LXdpZHRoOjkxLjY2NjY2NyV9LmNvbC14bC0xMnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5vcmRlci14bC1maXJzdHstbXMtZmxleC1vcmRlcjotMTtvcmRlcjotMX0ub3JkZXIteGwtbGFzdHstbXMtZmxleC1vcmRlcjoxMztvcmRlcjoxM30ub3JkZXIteGwtMHstbXMtZmxleC1vcmRlcjowO29yZGVyOjB9Lm9yZGVyLXhsLTF7LW1zLWZsZXgtb3JkZXI6MTtvcmRlcjoxfS5vcmRlci14bC0yey1tcy1mbGV4LW9yZGVyOjI7b3JkZXI6Mn0ub3JkZXIteGwtM3stbXMtZmxleC1vcmRlcjozO29yZGVyOjN9Lm9yZGVyLXhsLTR7LW1zLWZsZXgtb3JkZXI6NDtvcmRlcjo0fS5vcmRlci14bC01ey1tcy1mbGV4LW9yZGVyOjU7b3JkZXI6NX0ub3JkZXIteGwtNnstbXMtZmxleC1vcmRlcjo2O29yZGVyOjZ9Lm9yZGVyLXhsLTd7LW1zLWZsZXgtb3JkZXI6NztvcmRlcjo3fS5vcmRlci14bC04ey1tcy1mbGV4LW9yZGVyOjg7b3JkZXI6OH0ub3JkZXIteGwtOXstbXMtZmxleC1vcmRlcjo5O29yZGVyOjl9Lm9yZGVyLXhsLTEwey1tcy1mbGV4LW9yZGVyOjEwO29yZGVyOjEwfS5vcmRlci14bC0xMXstbXMtZmxleC1vcmRlcjoxMTtvcmRlcjoxMX0ub3JkZXIteGwtMTJ7LW1zLWZsZXgtb3JkZXI6MTI7b3JkZXI6MTJ9Lm9mZnNldC14bC0we21hcmdpbi1sZWZ0OjB9Lm9mZnNldC14bC0xe21hcmdpbi1sZWZ0OjguMzMzMzMzJX0ub2Zmc2V0LXhsLTJ7bWFyZ2luLWxlZnQ6MTYuNjY2NjY3JX0ub2Zmc2V0LXhsLTN7bWFyZ2luLWxlZnQ6MjUlfS5vZmZzZXQteGwtNHttYXJnaW4tbGVmdDozMy4zMzMzMzMlfS5vZmZzZXQteGwtNXttYXJnaW4tbGVmdDo0MS42NjY2NjclfS5vZmZzZXQteGwtNnttYXJnaW4tbGVmdDo1MCV9Lm9mZnNldC14bC03e21hcmdpbi1sZWZ0OjU4LjMzMzMzMyV9Lm9mZnNldC14bC04e21hcmdpbi1sZWZ0OjY2LjY2NjY2NyV9Lm9mZnNldC14bC05e21hcmdpbi1sZWZ0Ojc1JX0ub2Zmc2V0LXhsLTEwe21hcmdpbi1sZWZ0OjgzLjMzMzMzMyV9Lm9mZnNldC14bC0xMXttYXJnaW4tbGVmdDo5MS42NjY2NjclfX0udGFibGV7d2lkdGg6MTAwJTttYXJnaW4tYm90dG9tOjFyZW07Y29sb3I6IzIxMjUyOX0udGFibGUgdGQsLnRhYmxlIHRoe3BhZGRpbmc6Ljc1cmVtO3ZlcnRpY2FsLWFsaWduOnRvcDtib3JkZXItdG9wOjFweCBzb2xpZCAjZGVlMmU2fS50YWJsZSB0aGVhZCB0aHt2ZXJ0aWNhbC1hbGlnbjpib3R0b207Ym9yZGVyLWJvdHRvbToycHggc29saWQgI2RlZTJlNn0udGFibGUgdGJvZHkrdGJvZHl7Ym9yZGVyLXRvcDoycHggc29saWQgI2RlZTJlNn0udGFibGUtc20gdGQsLnRhYmxlLXNtIHRoe3BhZGRpbmc6LjNyZW19LnRhYmxlLWJvcmRlcmVke2JvcmRlcjoxcHggc29saWQgI2RlZTJlNn0udGFibGUtYm9yZGVyZWQgdGQsLnRhYmxlLWJvcmRlcmVkIHRoe2JvcmRlcjoxcHggc29saWQgI2RlZTJlNn0udGFibGUtYm9yZGVyZWQgdGhlYWQgdGQsLnRhYmxlLWJvcmRlcmVkIHRoZWFkIHRoe2JvcmRlci1ib3R0b20td2lkdGg6MnB4fS50YWJsZS1ib3JkZXJsZXNzIHRib2R5K3Rib2R5LC50YWJsZS1ib3JkZXJsZXNzIHRkLC50YWJsZS1ib3JkZXJsZXNzIHRoLC50YWJsZS1ib3JkZXJsZXNzIHRoZWFkIHRoe2JvcmRlcjowfS50YWJsZS1zdHJpcGVkIHRib2R5IHRyOm50aC1vZi10eXBlKG9kZCl7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLC4wNSl9LnRhYmxlLWhvdmVyIHRib2R5IHRyOmhvdmVye2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLC4wNzUpfS50YWJsZS1wcmltYXJ5LC50YWJsZS1wcmltYXJ5PnRkLC50YWJsZS1wcmltYXJ5PnRoe2JhY2tncm91bmQtY29sb3I6I2I4ZGFmZn0udGFibGUtcHJpbWFyeSB0Ym9keSt0Ym9keSwudGFibGUtcHJpbWFyeSB0ZCwudGFibGUtcHJpbWFyeSB0aCwudGFibGUtcHJpbWFyeSB0aGVhZCB0aHtib3JkZXItY29sb3I6IzdhYmFmZn0udGFibGUtaG92ZXIgLnRhYmxlLXByaW1hcnk6aG92ZXJ7YmFja2dyb3VuZC1jb2xvcjojOWZjZGZmfS50YWJsZS1ob3ZlciAudGFibGUtcHJpbWFyeTpob3Zlcj50ZCwudGFibGUtaG92ZXIgLnRhYmxlLXByaW1hcnk6aG92ZXI+dGh7YmFja2dyb3VuZC1jb2xvcjojOWZjZGZmfS50YWJsZS1zZWNvbmRhcnksLnRhYmxlLXNlY29uZGFyeT50ZCwudGFibGUtc2Vjb25kYXJ5PnRoe2JhY2tncm91bmQtY29sb3I6I2Q2ZDhkYn0udGFibGUtc2Vjb25kYXJ5IHRib2R5K3Rib2R5LC50YWJsZS1zZWNvbmRhcnkgdGQsLnRhYmxlLXNlY29uZGFyeSB0aCwudGFibGUtc2Vjb25kYXJ5IHRoZWFkIHRoe2JvcmRlci1jb2xvcjojYjNiN2JifS50YWJsZS1ob3ZlciAudGFibGUtc2Vjb25kYXJ5OmhvdmVye2JhY2tncm91bmQtY29sb3I6I2M4Y2JjZn0udGFibGUtaG92ZXIgLnRhYmxlLXNlY29uZGFyeTpob3Zlcj50ZCwudGFibGUtaG92ZXIgLnRhYmxlLXNlY29uZGFyeTpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOiNjOGNiY2Z9LnRhYmxlLXN1Y2Nlc3MsLnRhYmxlLXN1Y2Nlc3M+dGQsLnRhYmxlLXN1Y2Nlc3M+dGh7YmFja2dyb3VuZC1jb2xvcjojYzNlNmNifS50YWJsZS1zdWNjZXNzIHRib2R5K3Rib2R5LC50YWJsZS1zdWNjZXNzIHRkLC50YWJsZS1zdWNjZXNzIHRoLC50YWJsZS1zdWNjZXNzIHRoZWFkIHRoe2JvcmRlci1jb2xvcjojOGZkMTllfS50YWJsZS1ob3ZlciAudGFibGUtc3VjY2Vzczpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiNiMWRmYmJ9LnRhYmxlLWhvdmVyIC50YWJsZS1zdWNjZXNzOmhvdmVyPnRkLC50YWJsZS1ob3ZlciAudGFibGUtc3VjY2Vzczpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOiNiMWRmYmJ9LnRhYmxlLWluZm8sLnRhYmxlLWluZm8+dGQsLnRhYmxlLWluZm8+dGh7YmFja2dyb3VuZC1jb2xvcjojYmVlNWVifS50YWJsZS1pbmZvIHRib2R5K3Rib2R5LC50YWJsZS1pbmZvIHRkLC50YWJsZS1pbmZvIHRoLC50YWJsZS1pbmZvIHRoZWFkIHRoe2JvcmRlci1jb2xvcjojODZjZmRhfS50YWJsZS1ob3ZlciAudGFibGUtaW5mbzpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiNhYmRkZTV9LnRhYmxlLWhvdmVyIC50YWJsZS1pbmZvOmhvdmVyPnRkLC50YWJsZS1ob3ZlciAudGFibGUtaW5mbzpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOiNhYmRkZTV9LnRhYmxlLXdhcm5pbmcsLnRhYmxlLXdhcm5pbmc+dGQsLnRhYmxlLXdhcm5pbmc+dGh7YmFja2dyb3VuZC1jb2xvcjojZmZlZWJhfS50YWJsZS13YXJuaW5nIHRib2R5K3Rib2R5LC50YWJsZS13YXJuaW5nIHRkLC50YWJsZS13YXJuaW5nIHRoLC50YWJsZS13YXJuaW5nIHRoZWFkIHRoe2JvcmRlci1jb2xvcjojZmZkZjdlfS50YWJsZS1ob3ZlciAudGFibGUtd2FybmluZzpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiNmZmU4YTF9LnRhYmxlLWhvdmVyIC50YWJsZS13YXJuaW5nOmhvdmVyPnRkLC50YWJsZS1ob3ZlciAudGFibGUtd2FybmluZzpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOiNmZmU4YTF9LnRhYmxlLWRhbmdlciwudGFibGUtZGFuZ2VyPnRkLC50YWJsZS1kYW5nZXI+dGh7YmFja2dyb3VuZC1jb2xvcjojZjVjNmNifS50YWJsZS1kYW5nZXIgdGJvZHkrdGJvZHksLnRhYmxlLWRhbmdlciB0ZCwudGFibGUtZGFuZ2VyIHRoLC50YWJsZS1kYW5nZXIgdGhlYWQgdGh7Ym9yZGVyLWNvbG9yOiNlZDk2OWV9LnRhYmxlLWhvdmVyIC50YWJsZS1kYW5nZXI6aG92ZXJ7YmFja2dyb3VuZC1jb2xvcjojZjFiMGI3fS50YWJsZS1ob3ZlciAudGFibGUtZGFuZ2VyOmhvdmVyPnRkLC50YWJsZS1ob3ZlciAudGFibGUtZGFuZ2VyOmhvdmVyPnRoe2JhY2tncm91bmQtY29sb3I6I2YxYjBiN30udGFibGUtbGlnaHQsLnRhYmxlLWxpZ2h0PnRkLC50YWJsZS1saWdodD50aHtiYWNrZ3JvdW5kLWNvbG9yOiNmZGZkZmV9LnRhYmxlLWxpZ2h0IHRib2R5K3Rib2R5LC50YWJsZS1saWdodCB0ZCwudGFibGUtbGlnaHQgdGgsLnRhYmxlLWxpZ2h0IHRoZWFkIHRoe2JvcmRlci1jb2xvcjojZmJmY2ZjfS50YWJsZS1ob3ZlciAudGFibGUtbGlnaHQ6aG92ZXJ7YmFja2dyb3VuZC1jb2xvcjojZWNlY2Y2fS50YWJsZS1ob3ZlciAudGFibGUtbGlnaHQ6aG92ZXI+dGQsLnRhYmxlLWhvdmVyIC50YWJsZS1saWdodDpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOiNlY2VjZjZ9LnRhYmxlLWRhcmssLnRhYmxlLWRhcms+dGQsLnRhYmxlLWRhcms+dGh7YmFja2dyb3VuZC1jb2xvcjojYzZjOGNhfS50YWJsZS1kYXJrIHRib2R5K3Rib2R5LC50YWJsZS1kYXJrIHRkLC50YWJsZS1kYXJrIHRoLC50YWJsZS1kYXJrIHRoZWFkIHRoe2JvcmRlci1jb2xvcjojOTU5OTljfS50YWJsZS1ob3ZlciAudGFibGUtZGFyazpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiNiOWJiYmV9LnRhYmxlLWhvdmVyIC50YWJsZS1kYXJrOmhvdmVyPnRkLC50YWJsZS1ob3ZlciAudGFibGUtZGFyazpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOiNiOWJiYmV9LnRhYmxlLWFjdGl2ZSwudGFibGUtYWN0aXZlPnRkLC50YWJsZS1hY3RpdmU+dGh7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLC4wNzUpfS50YWJsZS1ob3ZlciAudGFibGUtYWN0aXZlOmhvdmVye2JhY2tncm91bmQtY29sb3I6cmdiYSgwLDAsMCwuMDc1KX0udGFibGUtaG92ZXIgLnRhYmxlLWFjdGl2ZTpob3Zlcj50ZCwudGFibGUtaG92ZXIgLnRhYmxlLWFjdGl2ZTpob3Zlcj50aHtiYWNrZ3JvdW5kLWNvbG9yOnJnYmEoMCwwLDAsLjA3NSl9LnRhYmxlIC50aGVhZC1kYXJrIHRoe2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMzQzYTQwO2JvcmRlci1jb2xvcjojNDU0ZDU1fS50YWJsZSAudGhlYWQtbGlnaHQgdGh7Y29sb3I6IzQ5NTA1NztiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWY7Ym9yZGVyLWNvbG9yOiNkZWUyZTZ9LnRhYmxlLWRhcmt7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMzNDNhNDB9LnRhYmxlLWRhcmsgdGQsLnRhYmxlLWRhcmsgdGgsLnRhYmxlLWRhcmsgdGhlYWQgdGh7Ym9yZGVyLWNvbG9yOiM0NTRkNTV9LnRhYmxlLWRhcmsudGFibGUtYm9yZGVyZWR7Ym9yZGVyOjB9LnRhYmxlLWRhcmsudGFibGUtc3RyaXBlZCB0Ym9keSB0cjpudGgtb2YtdHlwZShvZGQpe2JhY2tncm91bmQtY29sb3I6cmdiYSgyNTUsMjU1LDI1NSwuMDUpfS50YWJsZS1kYXJrLnRhYmxlLWhvdmVyIHRib2R5IHRyOmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDI1NSwyNTUsMjU1LC4wNzUpfUBtZWRpYSAobWF4LXdpZHRoOjU3NS45OHB4KXsudGFibGUtcmVzcG9uc2l2ZS1zbXtkaXNwbGF5OmJsb2NrO3dpZHRoOjEwMCU7b3ZlcmZsb3cteDphdXRvOy13ZWJraXQtb3ZlcmZsb3ctc2Nyb2xsaW5nOnRvdWNofS50YWJsZS1yZXNwb25zaXZlLXNtPi50YWJsZS1ib3JkZXJlZHtib3JkZXI6MH19QG1lZGlhIChtYXgtd2lkdGg6NzY3Ljk4cHgpey50YWJsZS1yZXNwb25zaXZlLW1ke2Rpc3BsYXk6YmxvY2s7d2lkdGg6MTAwJTtvdmVyZmxvdy14OmF1dG87LXdlYmtpdC1vdmVyZmxvdy1zY3JvbGxpbmc6dG91Y2h9LnRhYmxlLXJlc3BvbnNpdmUtbWQ+LnRhYmxlLWJvcmRlcmVke2JvcmRlcjowfX1AbWVkaWEgKG1heC13aWR0aDo5OTEuOThweCl7LnRhYmxlLXJlc3BvbnNpdmUtbGd7ZGlzcGxheTpibG9jazt3aWR0aDoxMDAlO292ZXJmbG93LXg6YXV0bzstd2Via2l0LW92ZXJmbG93LXNjcm9sbGluZzp0b3VjaH0udGFibGUtcmVzcG9uc2l2ZS1sZz4udGFibGUtYm9yZGVyZWR7Ym9yZGVyOjB9fUBtZWRpYSAobWF4LXdpZHRoOjExOTkuOThweCl7LnRhYmxlLXJlc3BvbnNpdmUteGx7ZGlzcGxheTpibG9jazt3aWR0aDoxMDAlO292ZXJmbG93LXg6YXV0bzstd2Via2l0LW92ZXJmbG93LXNjcm9sbGluZzp0b3VjaH0udGFibGUtcmVzcG9uc2l2ZS14bD4udGFibGUtYm9yZGVyZWR7Ym9yZGVyOjB9fS50YWJsZS1yZXNwb25zaXZle2Rpc3BsYXk6YmxvY2s7d2lkdGg6MTAwJTtvdmVyZmxvdy14OmF1dG87LXdlYmtpdC1vdmVyZmxvdy1zY3JvbGxpbmc6dG91Y2h9LnRhYmxlLXJlc3BvbnNpdmU+LnRhYmxlLWJvcmRlcmVke2JvcmRlcjowfS5mb3JtLWNvbnRyb2x7ZGlzcGxheTpibG9jazt3aWR0aDoxMDAlO2hlaWdodDpjYWxjKDEuNWVtICsgLjc1cmVtICsgMnB4KTtwYWRkaW5nOi4zNzVyZW0gLjc1cmVtO2ZvbnQtc2l6ZToxcmVtO2ZvbnQtd2VpZ2h0OjQwMDtsaW5lLWhlaWdodDoxLjU7Y29sb3I6IzQ5NTA1NztiYWNrZ3JvdW5kLWNvbG9yOiNmZmY7YmFja2dyb3VuZC1jbGlwOnBhZGRpbmctYm94O2JvcmRlcjoxcHggc29saWQgI2NlZDRkYTtib3JkZXItcmFkaXVzOi4yNXJlbTt0cmFuc2l0aW9uOmJvcmRlci1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJveC1zaGFkb3cgLjE1cyBlYXNlLWluLW91dH1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuZm9ybS1jb250cm9se3RyYW5zaXRpb246bm9uZX19LmZvcm0tY29udHJvbDo6LW1zLWV4cGFuZHtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlcjowfS5mb3JtLWNvbnRyb2w6LW1vei1mb2N1c3Jpbmd7Y29sb3I6dHJhbnNwYXJlbnQ7dGV4dC1zaGFkb3c6MCAwIDAgIzQ5NTA1N30uZm9ybS1jb250cm9sOmZvY3Vze2NvbG9yOiM0OTUwNTc7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlci1jb2xvcjojODBiZGZmO291dGxpbmU6MDtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMCwxMjMsMjU1LC4yNSl9LmZvcm0tY29udHJvbDo6LXdlYmtpdC1pbnB1dC1wbGFjZWhvbGRlcntjb2xvcjojNmM3NTdkO29wYWNpdHk6MX0uZm9ybS1jb250cm9sOjotbW96LXBsYWNlaG9sZGVye2NvbG9yOiM2Yzc1N2Q7b3BhY2l0eToxfS5mb3JtLWNvbnRyb2w6LW1zLWlucHV0LXBsYWNlaG9sZGVye2NvbG9yOiM2Yzc1N2Q7b3BhY2l0eToxfS5mb3JtLWNvbnRyb2w6Oi1tcy1pbnB1dC1wbGFjZWhvbGRlcntjb2xvcjojNmM3NTdkO29wYWNpdHk6MX0uZm9ybS1jb250cm9sOjpwbGFjZWhvbGRlcntjb2xvcjojNmM3NTdkO29wYWNpdHk6MX0uZm9ybS1jb250cm9sOmRpc2FibGVkLC5mb3JtLWNvbnRyb2xbcmVhZG9ubHlde2JhY2tncm91bmQtY29sb3I6I2U5ZWNlZjtvcGFjaXR5OjF9c2VsZWN0LmZvcm0tY29udHJvbDpmb2N1czo6LW1zLXZhbHVle2NvbG9yOiM0OTUwNTc7YmFja2dyb3VuZC1jb2xvcjojZmZmfS5mb3JtLWNvbnRyb2wtZmlsZSwuZm9ybS1jb250cm9sLXJhbmdle2Rpc3BsYXk6YmxvY2s7d2lkdGg6MTAwJX0uY29sLWZvcm0tbGFiZWx7cGFkZGluZy10b3A6Y2FsYyguMzc1cmVtICsgMXB4KTtwYWRkaW5nLWJvdHRvbTpjYWxjKC4zNzVyZW0gKyAxcHgpO21hcmdpbi1ib3R0b206MDtmb250LXNpemU6aW5oZXJpdDtsaW5lLWhlaWdodDoxLjV9LmNvbC1mb3JtLWxhYmVsLWxne3BhZGRpbmctdG9wOmNhbGMoLjVyZW0gKyAxcHgpO3BhZGRpbmctYm90dG9tOmNhbGMoLjVyZW0gKyAxcHgpO2ZvbnQtc2l6ZToxLjI1cmVtO2xpbmUtaGVpZ2h0OjEuNX0uY29sLWZvcm0tbGFiZWwtc217cGFkZGluZy10b3A6Y2FsYyguMjVyZW0gKyAxcHgpO3BhZGRpbmctYm90dG9tOmNhbGMoLjI1cmVtICsgMXB4KTtmb250LXNpemU6Ljg3NXJlbTtsaW5lLWhlaWdodDoxLjV9LmZvcm0tY29udHJvbC1wbGFpbnRleHR7ZGlzcGxheTpibG9jazt3aWR0aDoxMDAlO3BhZGRpbmc6LjM3NXJlbSAwO21hcmdpbi1ib3R0b206MDtmb250LXNpemU6MXJlbTtsaW5lLWhlaWdodDoxLjU7Y29sb3I6IzIxMjUyOTtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlcjpzb2xpZCB0cmFuc3BhcmVudDtib3JkZXItd2lkdGg6MXB4IDB9LmZvcm0tY29udHJvbC1wbGFpbnRleHQuZm9ybS1jb250cm9sLWxnLC5mb3JtLWNvbnRyb2wtcGxhaW50ZXh0LmZvcm0tY29udHJvbC1zbXtwYWRkaW5nLXJpZ2h0OjA7cGFkZGluZy1sZWZ0OjB9LmZvcm0tY29udHJvbC1zbXtoZWlnaHQ6Y2FsYygxLjVlbSArIC41cmVtICsgMnB4KTtwYWRkaW5nOi4yNXJlbSAuNXJlbTtmb250LXNpemU6Ljg3NXJlbTtsaW5lLWhlaWdodDoxLjU7Ym9yZGVyLXJhZGl1czouMnJlbX0uZm9ybS1jb250cm9sLWxne2hlaWdodDpjYWxjKDEuNWVtICsgMXJlbSArIDJweCk7cGFkZGluZzouNXJlbSAxcmVtO2ZvbnQtc2l6ZToxLjI1cmVtO2xpbmUtaGVpZ2h0OjEuNTtib3JkZXItcmFkaXVzOi4zcmVtfXNlbGVjdC5mb3JtLWNvbnRyb2xbbXVsdGlwbGVdLHNlbGVjdC5mb3JtLWNvbnRyb2xbc2l6ZV17aGVpZ2h0OmF1dG99dGV4dGFyZWEuZm9ybS1jb250cm9se2hlaWdodDphdXRvfS5mb3JtLWdyb3Vwe21hcmdpbi1ib3R0b206MXJlbX0uZm9ybS10ZXh0e2Rpc3BsYXk6YmxvY2s7bWFyZ2luLXRvcDouMjVyZW19LmZvcm0tcm93e2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LXdyYXA6d3JhcDtmbGV4LXdyYXA6d3JhcDttYXJnaW4tcmlnaHQ6LTVweDttYXJnaW4tbGVmdDotNXB4fS5mb3JtLXJvdz4uY29sLC5mb3JtLXJvdz5bY2xhc3MqPWNvbC1de3BhZGRpbmctcmlnaHQ6NXB4O3BhZGRpbmctbGVmdDo1cHh9LmZvcm0tY2hlY2t7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTpibG9jaztwYWRkaW5nLWxlZnQ6MS4yNXJlbX0uZm9ybS1jaGVjay1pbnB1dHtwb3NpdGlvbjphYnNvbHV0ZTttYXJnaW4tdG9wOi4zcmVtO21hcmdpbi1sZWZ0Oi0xLjI1cmVtfS5mb3JtLWNoZWNrLWlucHV0OmRpc2FibGVkfi5mb3JtLWNoZWNrLWxhYmVsLC5mb3JtLWNoZWNrLWlucHV0W2Rpc2FibGVkXX4uZm9ybS1jaGVjay1sYWJlbHtjb2xvcjojNmM3NTdkfS5mb3JtLWNoZWNrLWxhYmVse21hcmdpbi1ib3R0b206MH0uZm9ybS1jaGVjay1pbmxpbmV7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3g7ZGlzcGxheTppbmxpbmUtZmxleDstbXMtZmxleC1hbGlnbjpjZW50ZXI7YWxpZ24taXRlbXM6Y2VudGVyO3BhZGRpbmctbGVmdDowO21hcmdpbi1yaWdodDouNzVyZW19LmZvcm0tY2hlY2staW5saW5lIC5mb3JtLWNoZWNrLWlucHV0e3Bvc2l0aW9uOnN0YXRpYzttYXJnaW4tdG9wOjA7bWFyZ2luLXJpZ2h0Oi4zMTI1cmVtO21hcmdpbi1sZWZ0OjB9LnZhbGlkLWZlZWRiYWNre2Rpc3BsYXk6bm9uZTt3aWR0aDoxMDAlO21hcmdpbi10b3A6LjI1cmVtO2ZvbnQtc2l6ZTo4MCU7Y29sb3I6IzI4YTc0NX0udmFsaWQtdG9vbHRpcHtwb3NpdGlvbjphYnNvbHV0ZTt0b3A6MTAwJTt6LWluZGV4OjU7ZGlzcGxheTpub25lO21heC13aWR0aDoxMDAlO3BhZGRpbmc6LjI1cmVtIC41cmVtO21hcmdpbi10b3A6LjFyZW07Zm9udC1zaXplOi44NzVyZW07bGluZS1oZWlnaHQ6MS41O2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDQwLDE2Nyw2OSwuOSk7Ym9yZGVyLXJhZGl1czouMjVyZW19LmlzLXZhbGlkfi52YWxpZC1mZWVkYmFjaywuaXMtdmFsaWR+LnZhbGlkLXRvb2x0aXAsLndhcy12YWxpZGF0ZWQgOnZhbGlkfi52YWxpZC1mZWVkYmFjaywud2FzLXZhbGlkYXRlZCA6dmFsaWR+LnZhbGlkLXRvb2x0aXB7ZGlzcGxheTpibG9ja30uZm9ybS1jb250cm9sLmlzLXZhbGlkLC53YXMtdmFsaWRhdGVkIC5mb3JtLWNvbnRyb2w6dmFsaWR7Ym9yZGVyLWNvbG9yOiMyOGE3NDU7cGFkZGluZy1yaWdodDpjYWxjKDEuNWVtICsgLjc1cmVtKTtiYWNrZ3JvdW5kLWltYWdlOnVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc4JyBoZWlnaHQ9JzgnIHZpZXdCb3g9JzAgMCA4IDgnJTNlJTNjcGF0aCBmaWxsPSclMjMyOGE3NDUnIGQ9J00yLjMgNi43M0wuNiA0LjUzYy0uNC0xLjA0LjQ2LTEuNCAxLjEtLjhsMS4xIDEuNCAzLjQtMy44Yy42LS42MyAxLjYtLjI3IDEuMi43bC00IDQuNmMtLjQzLjUtLjguNC0xLjEuMXonLyUzZSUzYy9zdmclM2UiKTtiYWNrZ3JvdW5kLXJlcGVhdDpuby1yZXBlYXQ7YmFja2dyb3VuZC1wb3NpdGlvbjpyaWdodCBjYWxjKC4zNzVlbSArIC4xODc1cmVtKSBjZW50ZXI7YmFja2dyb3VuZC1zaXplOmNhbGMoLjc1ZW0gKyAuMzc1cmVtKSBjYWxjKC43NWVtICsgLjM3NXJlbSl9LmZvcm0tY29udHJvbC5pcy12YWxpZDpmb2N1cywud2FzLXZhbGlkYXRlZCAuZm9ybS1jb250cm9sOnZhbGlkOmZvY3Vze2JvcmRlci1jb2xvcjojMjhhNzQ1O2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSg0MCwxNjcsNjksLjI1KX0ud2FzLXZhbGlkYXRlZCB0ZXh0YXJlYS5mb3JtLWNvbnRyb2w6dmFsaWQsdGV4dGFyZWEuZm9ybS1jb250cm9sLmlzLXZhbGlke3BhZGRpbmctcmlnaHQ6Y2FsYygxLjVlbSArIC43NXJlbSk7YmFja2dyb3VuZC1wb3NpdGlvbjp0b3AgY2FsYyguMzc1ZW0gKyAuMTg3NXJlbSkgcmlnaHQgY2FsYyguMzc1ZW0gKyAuMTg3NXJlbSl9LmN1c3RvbS1zZWxlY3QuaXMtdmFsaWQsLndhcy12YWxpZGF0ZWQgLmN1c3RvbS1zZWxlY3Q6dmFsaWR7Ym9yZGVyLWNvbG9yOiMyOGE3NDU7cGFkZGluZy1yaWdodDpjYWxjKC43NWVtICsgMi4zMTI1cmVtKTtiYWNrZ3JvdW5kOnVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0JyBoZWlnaHQ9JzUnIHZpZXdCb3g9JzAgMCA0IDUnJTNlJTNjcGF0aCBmaWxsPSclMjMzNDNhNDAnIGQ9J00yIDBMMCAyaDR6bTAgNUwwIDNoNHonLyUzZSUzYy9zdmclM2UiKSBuby1yZXBlYXQgcmlnaHQgLjc1cmVtIGNlbnRlci84cHggMTBweCx1cmwoImRhdGE6aW1hZ2Uvc3ZnK3htbCwlM2NzdmcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB3aWR0aD0nOCcgaGVpZ2h0PSc4JyB2aWV3Qm94PScwIDAgOCA4JyUzZSUzY3BhdGggZmlsbD0nJTIzMjhhNzQ1JyBkPSdNMi4zIDYuNzNMLjYgNC41M2MtLjQtMS4wNC40Ni0xLjQgMS4xLS44bDEuMSAxLjQgMy40LTMuOGMuNi0uNjMgMS42LS4yNyAxLjIuN2wtNCA0LjZjLS40My41LS44LjQtMS4xLjF6Jy8lM2UlM2Mvc3ZnJTNlIikgI2ZmZiBuby1yZXBlYXQgY2VudGVyIHJpZ2h0IDEuNzVyZW0vY2FsYyguNzVlbSArIC4zNzVyZW0pIGNhbGMoLjc1ZW0gKyAuMzc1cmVtKX0uY3VzdG9tLXNlbGVjdC5pcy12YWxpZDpmb2N1cywud2FzLXZhbGlkYXRlZCAuY3VzdG9tLXNlbGVjdDp2YWxpZDpmb2N1c3tib3JkZXItY29sb3I6IzI4YTc0NTtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNDAsMTY3LDY5LC4yNSl9LmZvcm0tY2hlY2staW5wdXQuaXMtdmFsaWR+LmZvcm0tY2hlY2stbGFiZWwsLndhcy12YWxpZGF0ZWQgLmZvcm0tY2hlY2staW5wdXQ6dmFsaWR+LmZvcm0tY2hlY2stbGFiZWx7Y29sb3I6IzI4YTc0NX0uZm9ybS1jaGVjay1pbnB1dC5pcy12YWxpZH4udmFsaWQtZmVlZGJhY2ssLmZvcm0tY2hlY2staW5wdXQuaXMtdmFsaWR+LnZhbGlkLXRvb2x0aXAsLndhcy12YWxpZGF0ZWQgLmZvcm0tY2hlY2staW5wdXQ6dmFsaWR+LnZhbGlkLWZlZWRiYWNrLC53YXMtdmFsaWRhdGVkIC5mb3JtLWNoZWNrLWlucHV0OnZhbGlkfi52YWxpZC10b29sdGlwe2Rpc3BsYXk6YmxvY2t9LmN1c3RvbS1jb250cm9sLWlucHV0LmlzLXZhbGlkfi5jdXN0b20tY29udHJvbC1sYWJlbCwud2FzLXZhbGlkYXRlZCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6dmFsaWR+LmN1c3RvbS1jb250cm9sLWxhYmVse2NvbG9yOiMyOGE3NDV9LmN1c3RvbS1jb250cm9sLWlucHV0LmlzLXZhbGlkfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3JlLC53YXMtdmFsaWRhdGVkIC5jdXN0b20tY29udHJvbC1pbnB1dDp2YWxpZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtib3JkZXItY29sb3I6IzI4YTc0NX0uY3VzdG9tLWNvbnRyb2wtaW5wdXQuaXMtdmFsaWQ6Y2hlY2tlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZSwud2FzLXZhbGlkYXRlZCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6dmFsaWQ6Y2hlY2tlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtib3JkZXItY29sb3I6IzM0Y2U1NztiYWNrZ3JvdW5kLWNvbG9yOiMzNGNlNTd9LmN1c3RvbS1jb250cm9sLWlucHV0LmlzLXZhbGlkOmZvY3Vzfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3JlLC53YXMtdmFsaWRhdGVkIC5jdXN0b20tY29udHJvbC1pbnB1dDp2YWxpZDpmb2N1c34uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNDAsMTY3LDY5LC4yNSl9LmN1c3RvbS1jb250cm9sLWlucHV0LmlzLXZhbGlkOmZvY3VzOm5vdCg6Y2hlY2tlZCl+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmUsLndhcy12YWxpZGF0ZWQgLmN1c3RvbS1jb250cm9sLWlucHV0OnZhbGlkOmZvY3VzOm5vdCg6Y2hlY2tlZCl+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7Ym9yZGVyLWNvbG9yOiMyOGE3NDV9LmN1c3RvbS1maWxlLWlucHV0LmlzLXZhbGlkfi5jdXN0b20tZmlsZS1sYWJlbCwud2FzLXZhbGlkYXRlZCAuY3VzdG9tLWZpbGUtaW5wdXQ6dmFsaWR+LmN1c3RvbS1maWxlLWxhYmVse2JvcmRlci1jb2xvcjojMjhhNzQ1fS5jdXN0b20tZmlsZS1pbnB1dC5pcy12YWxpZDpmb2N1c34uY3VzdG9tLWZpbGUtbGFiZWwsLndhcy12YWxpZGF0ZWQgLmN1c3RvbS1maWxlLWlucHV0OnZhbGlkOmZvY3Vzfi5jdXN0b20tZmlsZS1sYWJlbHtib3JkZXItY29sb3I6IzI4YTc0NTtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNDAsMTY3LDY5LC4yNSl9LmludmFsaWQtZmVlZGJhY2t7ZGlzcGxheTpub25lO3dpZHRoOjEwMCU7bWFyZ2luLXRvcDouMjVyZW07Zm9udC1zaXplOjgwJTtjb2xvcjojZGMzNTQ1fS5pbnZhbGlkLXRvb2x0aXB7cG9zaXRpb246YWJzb2x1dGU7dG9wOjEwMCU7ei1pbmRleDo1O2Rpc3BsYXk6bm9uZTttYXgtd2lkdGg6MTAwJTtwYWRkaW5nOi4yNXJlbSAuNXJlbTttYXJnaW4tdG9wOi4xcmVtO2ZvbnQtc2l6ZTouODc1cmVtO2xpbmUtaGVpZ2h0OjEuNTtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6cmdiYSgyMjAsNTMsNjksLjkpO2JvcmRlci1yYWRpdXM6LjI1cmVtfS5pcy1pbnZhbGlkfi5pbnZhbGlkLWZlZWRiYWNrLC5pcy1pbnZhbGlkfi5pbnZhbGlkLXRvb2x0aXAsLndhcy12YWxpZGF0ZWQgOmludmFsaWR+LmludmFsaWQtZmVlZGJhY2ssLndhcy12YWxpZGF0ZWQgOmludmFsaWR+LmludmFsaWQtdG9vbHRpcHtkaXNwbGF5OmJsb2NrfS5mb3JtLWNvbnRyb2wuaXMtaW52YWxpZCwud2FzLXZhbGlkYXRlZCAuZm9ybS1jb250cm9sOmludmFsaWR7Ym9yZGVyLWNvbG9yOiNkYzM1NDU7cGFkZGluZy1yaWdodDpjYWxjKDEuNWVtICsgLjc1cmVtKTtiYWNrZ3JvdW5kLWltYWdlOnVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxMicgaGVpZ2h0PScxMicgZmlsbD0nbm9uZScgc3Ryb2tlPSclMjNkYzM1NDUnIHZpZXdCb3g9JzAgMCAxMiAxMiclM2UlM2NjaXJjbGUgY3g9JzYnIGN5PSc2JyByPSc0LjUnLyUzZSUzY3BhdGggc3Ryb2tlLWxpbmVqb2luPSdyb3VuZCcgZD0nTTUuOCAzLjZoLjRMNiA2LjV6Jy8lM2UlM2NjaXJjbGUgY3g9JzYnIGN5PSc4LjInIHI9Jy42JyBmaWxsPSclMjNkYzM1NDUnIHN0cm9rZT0nbm9uZScvJTNlJTNjL3N2ZyUzZSIpO2JhY2tncm91bmQtcmVwZWF0Om5vLXJlcGVhdDtiYWNrZ3JvdW5kLXBvc2l0aW9uOnJpZ2h0IGNhbGMoLjM3NWVtICsgLjE4NzVyZW0pIGNlbnRlcjtiYWNrZ3JvdW5kLXNpemU6Y2FsYyguNzVlbSArIC4zNzVyZW0pIGNhbGMoLjc1ZW0gKyAuMzc1cmVtKX0uZm9ybS1jb250cm9sLmlzLWludmFsaWQ6Zm9jdXMsLndhcy12YWxpZGF0ZWQgLmZvcm0tY29udHJvbDppbnZhbGlkOmZvY3Vze2JvcmRlci1jb2xvcjojZGMzNTQ1O2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjAsNTMsNjksLjI1KX0ud2FzLXZhbGlkYXRlZCB0ZXh0YXJlYS5mb3JtLWNvbnRyb2w6aW52YWxpZCx0ZXh0YXJlYS5mb3JtLWNvbnRyb2wuaXMtaW52YWxpZHtwYWRkaW5nLXJpZ2h0OmNhbGMoMS41ZW0gKyAuNzVyZW0pO2JhY2tncm91bmQtcG9zaXRpb246dG9wIGNhbGMoLjM3NWVtICsgLjE4NzVyZW0pIHJpZ2h0IGNhbGMoLjM3NWVtICsgLjE4NzVyZW0pfS5jdXN0b20tc2VsZWN0LmlzLWludmFsaWQsLndhcy12YWxpZGF0ZWQgLmN1c3RvbS1zZWxlY3Q6aW52YWxpZHtib3JkZXItY29sb3I6I2RjMzU0NTtwYWRkaW5nLXJpZ2h0OmNhbGMoLjc1ZW0gKyAyLjMxMjVyZW0pO2JhY2tncm91bmQ6dXJsKCJkYXRhOmltYWdlL3N2Zyt4bWwsJTNjc3ZnIHhtbG5zPSdodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2Zycgd2lkdGg9JzQnIGhlaWdodD0nNScgdmlld0JveD0nMCAwIDQgNSclM2UlM2NwYXRoIGZpbGw9JyUyMzM0M2E0MCcgZD0nTTIgMEwwIDJoNHptMCA1TDAgM2g0eicvJTNlJTNjL3N2ZyUzZSIpIG5vLXJlcGVhdCByaWdodCAuNzVyZW0gY2VudGVyLzhweCAxMHB4LHVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxMicgaGVpZ2h0PScxMicgZmlsbD0nbm9uZScgc3Ryb2tlPSclMjNkYzM1NDUnIHZpZXdCb3g9JzAgMCAxMiAxMiclM2UlM2NjaXJjbGUgY3g9JzYnIGN5PSc2JyByPSc0LjUnLyUzZSUzY3BhdGggc3Ryb2tlLWxpbmVqb2luPSdyb3VuZCcgZD0nTTUuOCAzLjZoLjRMNiA2LjV6Jy8lM2UlM2NjaXJjbGUgY3g9JzYnIGN5PSc4LjInIHI9Jy42JyBmaWxsPSclMjNkYzM1NDUnIHN0cm9rZT0nbm9uZScvJTNlJTNjL3N2ZyUzZSIpICNmZmYgbm8tcmVwZWF0IGNlbnRlciByaWdodCAxLjc1cmVtL2NhbGMoLjc1ZW0gKyAuMzc1cmVtKSBjYWxjKC43NWVtICsgLjM3NXJlbSl9LmN1c3RvbS1zZWxlY3QuaXMtaW52YWxpZDpmb2N1cywud2FzLXZhbGlkYXRlZCAuY3VzdG9tLXNlbGVjdDppbnZhbGlkOmZvY3Vze2JvcmRlci1jb2xvcjojZGMzNTQ1O2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjAsNTMsNjksLjI1KX0uZm9ybS1jaGVjay1pbnB1dC5pcy1pbnZhbGlkfi5mb3JtLWNoZWNrLWxhYmVsLC53YXMtdmFsaWRhdGVkIC5mb3JtLWNoZWNrLWlucHV0OmludmFsaWR+LmZvcm0tY2hlY2stbGFiZWx7Y29sb3I6I2RjMzU0NX0uZm9ybS1jaGVjay1pbnB1dC5pcy1pbnZhbGlkfi5pbnZhbGlkLWZlZWRiYWNrLC5mb3JtLWNoZWNrLWlucHV0LmlzLWludmFsaWR+LmludmFsaWQtdG9vbHRpcCwud2FzLXZhbGlkYXRlZCAuZm9ybS1jaGVjay1pbnB1dDppbnZhbGlkfi5pbnZhbGlkLWZlZWRiYWNrLC53YXMtdmFsaWRhdGVkIC5mb3JtLWNoZWNrLWlucHV0OmludmFsaWR+LmludmFsaWQtdG9vbHRpcHtkaXNwbGF5OmJsb2NrfS5jdXN0b20tY29udHJvbC1pbnB1dC5pcy1pbnZhbGlkfi5jdXN0b20tY29udHJvbC1sYWJlbCwud2FzLXZhbGlkYXRlZCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6aW52YWxpZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWx7Y29sb3I6I2RjMzU0NX0uY3VzdG9tLWNvbnRyb2wtaW5wdXQuaXMtaW52YWxpZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZSwud2FzLXZhbGlkYXRlZCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6aW52YWxpZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtib3JkZXItY29sb3I6I2RjMzU0NX0uY3VzdG9tLWNvbnRyb2wtaW5wdXQuaXMtaW52YWxpZDpjaGVja2Vkfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3JlLC53YXMtdmFsaWRhdGVkIC5jdXN0b20tY29udHJvbC1pbnB1dDppbnZhbGlkOmNoZWNrZWR+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7Ym9yZGVyLWNvbG9yOiNlNDYwNmQ7YmFja2dyb3VuZC1jb2xvcjojZTQ2MDZkfS5jdXN0b20tY29udHJvbC1pbnB1dC5pcy1pbnZhbGlkOmZvY3Vzfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3JlLC53YXMtdmFsaWRhdGVkIC5jdXN0b20tY29udHJvbC1pbnB1dDppbnZhbGlkOmZvY3Vzfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3Jle2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjAsNTMsNjksLjI1KX0uY3VzdG9tLWNvbnRyb2wtaW5wdXQuaXMtaW52YWxpZDpmb2N1czpub3QoOmNoZWNrZWQpfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3JlLC53YXMtdmFsaWRhdGVkIC5jdXN0b20tY29udHJvbC1pbnB1dDppbnZhbGlkOmZvY3VzOm5vdCg6Y2hlY2tlZCl+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7Ym9yZGVyLWNvbG9yOiNkYzM1NDV9LmN1c3RvbS1maWxlLWlucHV0LmlzLWludmFsaWR+LmN1c3RvbS1maWxlLWxhYmVsLC53YXMtdmFsaWRhdGVkIC5jdXN0b20tZmlsZS1pbnB1dDppbnZhbGlkfi5jdXN0b20tZmlsZS1sYWJlbHtib3JkZXItY29sb3I6I2RjMzU0NX0uY3VzdG9tLWZpbGUtaW5wdXQuaXMtaW52YWxpZDpmb2N1c34uY3VzdG9tLWZpbGUtbGFiZWwsLndhcy12YWxpZGF0ZWQgLmN1c3RvbS1maWxlLWlucHV0OmludmFsaWQ6Zm9jdXN+LmN1c3RvbS1maWxlLWxhYmVse2JvcmRlci1jb2xvcjojZGMzNTQ1O2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjAsNTMsNjksLjI1KX0uZm9ybS1pbmxpbmV7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtZmxvdzpyb3cgd3JhcDtmbGV4LWZsb3c6cm93IHdyYXA7LW1zLWZsZXgtYWxpZ246Y2VudGVyO2FsaWduLWl0ZW1zOmNlbnRlcn0uZm9ybS1pbmxpbmUgLmZvcm0tY2hlY2t7d2lkdGg6MTAwJX1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmZvcm0taW5saW5lIGxhYmVse2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7LW1zLWZsZXgtcGFjazpjZW50ZXI7anVzdGlmeS1jb250ZW50OmNlbnRlcjttYXJnaW4tYm90dG9tOjB9LmZvcm0taW5saW5lIC5mb3JtLWdyb3Vwe2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4OjAgMCBhdXRvO2ZsZXg6MCAwIGF1dG87LW1zLWZsZXgtZmxvdzpyb3cgd3JhcDtmbGV4LWZsb3c6cm93IHdyYXA7LW1zLWZsZXgtYWxpZ246Y2VudGVyO2FsaWduLWl0ZW1zOmNlbnRlcjttYXJnaW4tYm90dG9tOjB9LmZvcm0taW5saW5lIC5mb3JtLWNvbnRyb2x7ZGlzcGxheTppbmxpbmUtYmxvY2s7d2lkdGg6YXV0bzt2ZXJ0aWNhbC1hbGlnbjptaWRkbGV9LmZvcm0taW5saW5lIC5mb3JtLWNvbnRyb2wtcGxhaW50ZXh0e2Rpc3BsYXk6aW5saW5lLWJsb2NrfS5mb3JtLWlubGluZSAuY3VzdG9tLXNlbGVjdCwuZm9ybS1pbmxpbmUgLmlucHV0LWdyb3Vwe3dpZHRoOmF1dG99LmZvcm0taW5saW5lIC5mb3JtLWNoZWNre2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7LW1zLWZsZXgtcGFjazpjZW50ZXI7anVzdGlmeS1jb250ZW50OmNlbnRlcjt3aWR0aDphdXRvO3BhZGRpbmctbGVmdDowfS5mb3JtLWlubGluZSAuZm9ybS1jaGVjay1pbnB1dHtwb3NpdGlvbjpyZWxhdGl2ZTstbXMtZmxleC1uZWdhdGl2ZTowO2ZsZXgtc2hyaW5rOjA7bWFyZ2luLXRvcDowO21hcmdpbi1yaWdodDouMjVyZW07bWFyZ2luLWxlZnQ6MH0uZm9ybS1pbmxpbmUgLmN1c3RvbS1jb250cm9sey1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7LW1zLWZsZXgtcGFjazpjZW50ZXI7anVzdGlmeS1jb250ZW50OmNlbnRlcn0uZm9ybS1pbmxpbmUgLmN1c3RvbS1jb250cm9sLWxhYmVse21hcmdpbi1ib3R0b206MH19LmJ0bntkaXNwbGF5OmlubGluZS1ibG9jaztmb250LXdlaWdodDo0MDA7Y29sb3I6IzIxMjUyOTt0ZXh0LWFsaWduOmNlbnRlcjt2ZXJ0aWNhbC1hbGlnbjptaWRkbGU7Y3Vyc29yOnBvaW50ZXI7LXdlYmtpdC11c2VyLXNlbGVjdDpub25lOy1tb3otdXNlci1zZWxlY3Q6bm9uZTstbXMtdXNlci1zZWxlY3Q6bm9uZTt1c2VyLXNlbGVjdDpub25lO2JhY2tncm91bmQtY29sb3I6dHJhbnNwYXJlbnQ7Ym9yZGVyOjFweCBzb2xpZCB0cmFuc3BhcmVudDtwYWRkaW5nOi4zNzVyZW0gLjc1cmVtO2ZvbnQtc2l6ZToxcmVtO2xpbmUtaGVpZ2h0OjEuNTtib3JkZXItcmFkaXVzOi4yNXJlbTt0cmFuc2l0aW9uOmNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYmFja2dyb3VuZC1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJvcmRlci1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJveC1zaGFkb3cgLjE1cyBlYXNlLWluLW91dH1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuYnRue3RyYW5zaXRpb246bm9uZX19LmJ0bjpob3Zlcntjb2xvcjojMjEyNTI5O3RleHQtZGVjb3JhdGlvbjpub25lfS5idG4uZm9jdXMsLmJ0bjpmb2N1c3tvdXRsaW5lOjA7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDAsMTIzLDI1NSwuMjUpfS5idG4uZGlzYWJsZWQsLmJ0bjpkaXNhYmxlZHtvcGFjaXR5Oi42NX1hLmJ0bi5kaXNhYmxlZCxmaWVsZHNldDpkaXNhYmxlZCBhLmJ0bntwb2ludGVyLWV2ZW50czpub25lfS5idG4tcHJpbWFyeXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzAwN2JmZjtib3JkZXItY29sb3I6IzAwN2JmZn0uYnRuLXByaW1hcnk6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMwMDY5ZDk7Ym9yZGVyLWNvbG9yOiMwMDYyY2N9LmJ0bi1wcmltYXJ5LmZvY3VzLC5idG4tcHJpbWFyeTpmb2N1c3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzAwNjlkOTtib3JkZXItY29sb3I6IzAwNjJjYztib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMzgsMTQzLDI1NSwuNSl9LmJ0bi1wcmltYXJ5LmRpc2FibGVkLC5idG4tcHJpbWFyeTpkaXNhYmxlZHtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzAwN2JmZjtib3JkZXItY29sb3I6IzAwN2JmZn0uYnRuLXByaW1hcnk6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlLC5idG4tcHJpbWFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1wcmltYXJ5LmRyb3Bkb3duLXRvZ2dsZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzAwNjJjYztib3JkZXItY29sb3I6IzAwNWNiZn0uYnRuLXByaW1hcnk6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlOmZvY3VzLC5idG4tcHJpbWFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1wcmltYXJ5LmRyb3Bkb3duLXRvZ2dsZTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMzgsMTQzLDI1NSwuNSl9LmJ0bi1zZWNvbmRhcnl7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM2Yzc1N2Q7Ym9yZGVyLWNvbG9yOiM2Yzc1N2R9LmJ0bi1zZWNvbmRhcnk6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM1YTYyNjg7Ym9yZGVyLWNvbG9yOiM1NDViNjJ9LmJ0bi1zZWNvbmRhcnkuZm9jdXMsLmJ0bi1zZWNvbmRhcnk6Zm9jdXN7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM1YTYyNjg7Ym9yZGVyLWNvbG9yOiM1NDViNjI7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDEzMCwxMzgsMTQ1LC41KX0uYnRuLXNlY29uZGFyeS5kaXNhYmxlZCwuYnRuLXNlY29uZGFyeTpkaXNhYmxlZHtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzZjNzU3ZDtib3JkZXItY29sb3I6IzZjNzU3ZH0uYnRuLXNlY29uZGFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmUsLmJ0bi1zZWNvbmRhcnk6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlLC5zaG93Pi5idG4tc2Vjb25kYXJ5LmRyb3Bkb3duLXRvZ2dsZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzU0NWI2Mjtib3JkZXItY29sb3I6IzRlNTU1Yn0uYnRuLXNlY29uZGFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmU6Zm9jdXMsLmJ0bi1zZWNvbmRhcnk6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlOmZvY3VzLC5zaG93Pi5idG4tc2Vjb25kYXJ5LmRyb3Bkb3duLXRvZ2dsZTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMTMwLDEzOCwxNDUsLjUpfS5idG4tc3VjY2Vzc3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzI4YTc0NTtib3JkZXItY29sb3I6IzI4YTc0NX0uYnRuLXN1Y2Nlc3M6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMyMTg4Mzg7Ym9yZGVyLWNvbG9yOiMxZTdlMzR9LmJ0bi1zdWNjZXNzLmZvY3VzLC5idG4tc3VjY2Vzczpmb2N1c3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzIxODgzODtib3JkZXItY29sb3I6IzFlN2UzNDtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNzIsMTgwLDk3LC41KX0uYnRuLXN1Y2Nlc3MuZGlzYWJsZWQsLmJ0bi1zdWNjZXNzOmRpc2FibGVke2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMjhhNzQ1O2JvcmRlci1jb2xvcjojMjhhNzQ1fS5idG4tc3VjY2Vzczpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmUsLmJ0bi1zdWNjZXNzOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpOmFjdGl2ZSwuc2hvdz4uYnRuLXN1Y2Nlc3MuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMWU3ZTM0O2JvcmRlci1jb2xvcjojMWM3NDMwfS5idG4tc3VjY2Vzczpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmU6Zm9jdXMsLmJ0bi1zdWNjZXNzOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpOmFjdGl2ZTpmb2N1cywuc2hvdz4uYnRuLXN1Y2Nlc3MuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSg3MiwxODAsOTcsLjUpfS5idG4taW5mb3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzE3YTJiODtib3JkZXItY29sb3I6IzE3YTJiOH0uYnRuLWluZm86aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMxMzg0OTY7Ym9yZGVyLWNvbG9yOiMxMTdhOGJ9LmJ0bi1pbmZvLmZvY3VzLC5idG4taW5mbzpmb2N1c3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzEzODQ5Njtib3JkZXItY29sb3I6IzExN2E4Yjtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNTgsMTc2LDE5NSwuNSl9LmJ0bi1pbmZvLmRpc2FibGVkLC5idG4taW5mbzpkaXNhYmxlZHtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzE3YTJiODtib3JkZXItY29sb3I6IzE3YTJiOH0uYnRuLWluZm86bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlLC5idG4taW5mbzpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1pbmZvLmRyb3Bkb3duLXRvZ2dsZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzExN2E4Yjtib3JkZXItY29sb3I6IzEwNzA3Zn0uYnRuLWluZm86bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlOmZvY3VzLC5idG4taW5mbzpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1pbmZvLmRyb3Bkb3duLXRvZ2dsZTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNTgsMTc2LDE5NSwuNSl9LmJ0bi13YXJuaW5ne2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZmZjMTA3O2JvcmRlci1jb2xvcjojZmZjMTA3fS5idG4td2FybmluZzpob3Zlcntjb2xvcjojMjEyNTI5O2JhY2tncm91bmQtY29sb3I6I2UwYTgwMDtib3JkZXItY29sb3I6I2QzOWUwMH0uYnRuLXdhcm5pbmcuZm9jdXMsLmJ0bi13YXJuaW5nOmZvY3Vze2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZTBhODAwO2JvcmRlci1jb2xvcjojZDM5ZTAwO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjIsMTcwLDEyLC41KX0uYnRuLXdhcm5pbmcuZGlzYWJsZWQsLmJ0bi13YXJuaW5nOmRpc2FibGVke2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZmZjMTA3O2JvcmRlci1jb2xvcjojZmZjMTA3fS5idG4td2FybmluZzpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmUsLmJ0bi13YXJuaW5nOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpOmFjdGl2ZSwuc2hvdz4uYnRuLXdhcm5pbmcuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZDM5ZTAwO2JvcmRlci1jb2xvcjojYzY5NTAwfS5idG4td2FybmluZzpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmU6Zm9jdXMsLmJ0bi13YXJuaW5nOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpOmFjdGl2ZTpmb2N1cywuc2hvdz4uYnRuLXdhcm5pbmcuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjIsMTcwLDEyLC41KX0uYnRuLWRhbmdlcntjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6I2RjMzU0NTtib3JkZXItY29sb3I6I2RjMzU0NX0uYnRuLWRhbmdlcjpob3Zlcntjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6I2M4MjMzMztib3JkZXItY29sb3I6I2JkMjEzMH0uYnRuLWRhbmdlci5mb2N1cywuYnRuLWRhbmdlcjpmb2N1c3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6I2M4MjMzMztib3JkZXItY29sb3I6I2JkMjEzMDtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMjI1LDgzLDk3LC41KX0uYnRuLWRhbmdlci5kaXNhYmxlZCwuYnRuLWRhbmdlcjpkaXNhYmxlZHtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6I2RjMzU0NTtib3JkZXItY29sb3I6I2RjMzU0NX0uYnRuLWRhbmdlcjpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmUsLmJ0bi1kYW5nZXI6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlLC5zaG93Pi5idG4tZGFuZ2VyLmRyb3Bkb3duLXRvZ2dsZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6I2JkMjEzMDtib3JkZXItY29sb3I6I2IyMWYyZH0uYnRuLWRhbmdlcjpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmU6Zm9jdXMsLmJ0bi1kYW5nZXI6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlOmZvY3VzLC5zaG93Pi5idG4tZGFuZ2VyLmRyb3Bkb3duLXRvZ2dsZTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMjI1LDgzLDk3LC41KX0uYnRuLWxpZ2h0e2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZjhmOWZhO2JvcmRlci1jb2xvcjojZjhmOWZhfS5idG4tbGlnaHQ6aG92ZXJ7Y29sb3I6IzIxMjUyOTtiYWNrZ3JvdW5kLWNvbG9yOiNlMmU2ZWE7Ym9yZGVyLWNvbG9yOiNkYWUwZTV9LmJ0bi1saWdodC5mb2N1cywuYnRuLWxpZ2h0OmZvY3Vze2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZTJlNmVhO2JvcmRlci1jb2xvcjojZGFlMGU1O2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMTYsMjE3LDIxOSwuNSl9LmJ0bi1saWdodC5kaXNhYmxlZCwuYnRuLWxpZ2h0OmRpc2FibGVke2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZjhmOWZhO2JvcmRlci1jb2xvcjojZjhmOWZhfS5idG4tbGlnaHQ6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlLC5idG4tbGlnaHQ6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlLC5zaG93Pi5idG4tbGlnaHQuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZGFlMGU1O2JvcmRlci1jb2xvcjojZDNkOWRmfS5idG4tbGlnaHQ6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlOmZvY3VzLC5idG4tbGlnaHQ6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlOmZvY3VzLC5zaG93Pi5idG4tbGlnaHQuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMTYsMjE3LDIxOSwuNSl9LmJ0bi1kYXJre2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMzQzYTQwO2JvcmRlci1jb2xvcjojMzQzYTQwfS5idG4tZGFyazpob3Zlcntjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzIzMjcyYjtib3JkZXItY29sb3I6IzFkMjEyNH0uYnRuLWRhcmsuZm9jdXMsLmJ0bi1kYXJrOmZvY3Vze2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMjMyNzJiO2JvcmRlci1jb2xvcjojMWQyMTI0O2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSg4Miw4OCw5MywuNSl9LmJ0bi1kYXJrLmRpc2FibGVkLC5idG4tZGFyazpkaXNhYmxlZHtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzM0M2E0MDtib3JkZXItY29sb3I6IzM0M2E0MH0uYnRuLWRhcms6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlLC5idG4tZGFyazpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1kYXJrLmRyb3Bkb3duLXRvZ2dsZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzFkMjEyNDtib3JkZXItY29sb3I6IzE3MWExZH0uYnRuLWRhcms6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlOmZvY3VzLC5idG4tZGFyazpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1kYXJrLmRyb3Bkb3duLXRvZ2dsZTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoODIsODgsOTMsLjUpfS5idG4tb3V0bGluZS1wcmltYXJ5e2NvbG9yOiMwMDdiZmY7Ym9yZGVyLWNvbG9yOiMwMDdiZmZ9LmJ0bi1vdXRsaW5lLXByaW1hcnk6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMwMDdiZmY7Ym9yZGVyLWNvbG9yOiMwMDdiZmZ9LmJ0bi1vdXRsaW5lLXByaW1hcnkuZm9jdXMsLmJ0bi1vdXRsaW5lLXByaW1hcnk6Zm9jdXN7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDAsMTIzLDI1NSwuNSl9LmJ0bi1vdXRsaW5lLXByaW1hcnkuZGlzYWJsZWQsLmJ0bi1vdXRsaW5lLXByaW1hcnk6ZGlzYWJsZWR7Y29sb3I6IzAwN2JmZjtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fS5idG4tb3V0bGluZS1wcmltYXJ5Om5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZSwuYnRuLW91dGxpbmUtcHJpbWFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1vdXRsaW5lLXByaW1hcnkuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmO2JvcmRlci1jb2xvcjojMDA3YmZmfS5idG4tb3V0bGluZS1wcmltYXJ5Om5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZTpmb2N1cywuYnRuLW91dGxpbmUtcHJpbWFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1vdXRsaW5lLXByaW1hcnkuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgwLDEyMywyNTUsLjUpfS5idG4tb3V0bGluZS1zZWNvbmRhcnl7Y29sb3I6IzZjNzU3ZDtib3JkZXItY29sb3I6IzZjNzU3ZH0uYnRuLW91dGxpbmUtc2Vjb25kYXJ5OmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojNmM3NTdkO2JvcmRlci1jb2xvcjojNmM3NTdkfS5idG4tb3V0bGluZS1zZWNvbmRhcnkuZm9jdXMsLmJ0bi1vdXRsaW5lLXNlY29uZGFyeTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMTA4LDExNywxMjUsLjUpfS5idG4tb3V0bGluZS1zZWNvbmRhcnkuZGlzYWJsZWQsLmJ0bi1vdXRsaW5lLXNlY29uZGFyeTpkaXNhYmxlZHtjb2xvcjojNmM3NTdkO2JhY2tncm91bmQtY29sb3I6dHJhbnNwYXJlbnR9LmJ0bi1vdXRsaW5lLXNlY29uZGFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmUsLmJ0bi1vdXRsaW5lLXNlY29uZGFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1vdXRsaW5lLXNlY29uZGFyeS5kcm9wZG93bi10b2dnbGV7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM2Yzc1N2Q7Ym9yZGVyLWNvbG9yOiM2Yzc1N2R9LmJ0bi1vdXRsaW5lLXNlY29uZGFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmU6Zm9jdXMsLmJ0bi1vdXRsaW5lLXNlY29uZGFyeTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1vdXRsaW5lLXNlY29uZGFyeS5kcm9wZG93bi10b2dnbGU6Zm9jdXN7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDEwOCwxMTcsMTI1LC41KX0uYnRuLW91dGxpbmUtc3VjY2Vzc3tjb2xvcjojMjhhNzQ1O2JvcmRlci1jb2xvcjojMjhhNzQ1fS5idG4tb3V0bGluZS1zdWNjZXNzOmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMjhhNzQ1O2JvcmRlci1jb2xvcjojMjhhNzQ1fS5idG4tb3V0bGluZS1zdWNjZXNzLmZvY3VzLC5idG4tb3V0bGluZS1zdWNjZXNzOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSg0MCwxNjcsNjksLjUpfS5idG4tb3V0bGluZS1zdWNjZXNzLmRpc2FibGVkLC5idG4tb3V0bGluZS1zdWNjZXNzOmRpc2FibGVke2NvbG9yOiMyOGE3NDU7YmFja2dyb3VuZC1jb2xvcjp0cmFuc3BhcmVudH0uYnRuLW91dGxpbmUtc3VjY2Vzczpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmUsLmJ0bi1vdXRsaW5lLXN1Y2Nlc3M6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlLC5zaG93Pi5idG4tb3V0bGluZS1zdWNjZXNzLmRyb3Bkb3duLXRvZ2dsZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzI4YTc0NTtib3JkZXItY29sb3I6IzI4YTc0NX0uYnRuLW91dGxpbmUtc3VjY2Vzczpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKS5hY3RpdmU6Zm9jdXMsLmJ0bi1vdXRsaW5lLXN1Y2Nlc3M6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlOmZvY3VzLC5zaG93Pi5idG4tb3V0bGluZS1zdWNjZXNzLmRyb3Bkb3duLXRvZ2dsZTpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNDAsMTY3LDY5LC41KX0uYnRuLW91dGxpbmUtaW5mb3tjb2xvcjojMTdhMmI4O2JvcmRlci1jb2xvcjojMTdhMmI4fS5idG4tb3V0bGluZS1pbmZvOmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMTdhMmI4O2JvcmRlci1jb2xvcjojMTdhMmI4fS5idG4tb3V0bGluZS1pbmZvLmZvY3VzLC5idG4tb3V0bGluZS1pbmZvOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMywxNjIsMTg0LC41KX0uYnRuLW91dGxpbmUtaW5mby5kaXNhYmxlZCwuYnRuLW91dGxpbmUtaW5mbzpkaXNhYmxlZHtjb2xvcjojMTdhMmI4O2JhY2tncm91bmQtY29sb3I6dHJhbnNwYXJlbnR9LmJ0bi1vdXRsaW5lLWluZm86bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlLC5idG4tb3V0bGluZS1pbmZvOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpOmFjdGl2ZSwuc2hvdz4uYnRuLW91dGxpbmUtaW5mby5kcm9wZG93bi10b2dnbGV7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMxN2EyYjg7Ym9yZGVyLWNvbG9yOiMxN2EyYjh9LmJ0bi1vdXRsaW5lLWluZm86bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlOmZvY3VzLC5idG4tb3V0bGluZS1pbmZvOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpOmFjdGl2ZTpmb2N1cywuc2hvdz4uYnRuLW91dGxpbmUtaW5mby5kcm9wZG93bi10b2dnbGU6Zm9jdXN7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDIzLDE2MiwxODQsLjUpfS5idG4tb3V0bGluZS13YXJuaW5ne2NvbG9yOiNmZmMxMDc7Ym9yZGVyLWNvbG9yOiNmZmMxMDd9LmJ0bi1vdXRsaW5lLXdhcm5pbmc6aG92ZXJ7Y29sb3I6IzIxMjUyOTtiYWNrZ3JvdW5kLWNvbG9yOiNmZmMxMDc7Ym9yZGVyLWNvbG9yOiNmZmMxMDd9LmJ0bi1vdXRsaW5lLXdhcm5pbmcuZm9jdXMsLmJ0bi1vdXRsaW5lLXdhcm5pbmc6Zm9jdXN7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDI1NSwxOTMsNywuNSl9LmJ0bi1vdXRsaW5lLXdhcm5pbmcuZGlzYWJsZWQsLmJ0bi1vdXRsaW5lLXdhcm5pbmc6ZGlzYWJsZWR7Y29sb3I6I2ZmYzEwNztiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fS5idG4tb3V0bGluZS13YXJuaW5nOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZSwuYnRuLW91dGxpbmUtd2FybmluZzpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1vdXRsaW5lLXdhcm5pbmcuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZmZjMTA3O2JvcmRlci1jb2xvcjojZmZjMTA3fS5idG4tb3V0bGluZS13YXJuaW5nOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZTpmb2N1cywuYnRuLW91dGxpbmUtd2FybmluZzpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1vdXRsaW5lLXdhcm5pbmcuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyNTUsMTkzLDcsLjUpfS5idG4tb3V0bGluZS1kYW5nZXJ7Y29sb3I6I2RjMzU0NTtib3JkZXItY29sb3I6I2RjMzU0NX0uYnRuLW91dGxpbmUtZGFuZ2VyOmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojZGMzNTQ1O2JvcmRlci1jb2xvcjojZGMzNTQ1fS5idG4tb3V0bGluZS1kYW5nZXIuZm9jdXMsLmJ0bi1vdXRsaW5lLWRhbmdlcjpmb2N1c3tib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMjIwLDUzLDY5LC41KX0uYnRuLW91dGxpbmUtZGFuZ2VyLmRpc2FibGVkLC5idG4tb3V0bGluZS1kYW5nZXI6ZGlzYWJsZWR7Y29sb3I6I2RjMzU0NTtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fS5idG4tb3V0bGluZS1kYW5nZXI6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlLC5idG4tb3V0bGluZS1kYW5nZXI6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlLC5zaG93Pi5idG4tb3V0bGluZS1kYW5nZXIuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojZGMzNTQ1O2JvcmRlci1jb2xvcjojZGMzNTQ1fS5idG4tb3V0bGluZS1kYW5nZXI6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCkuYWN0aXZlOmZvY3VzLC5idG4tb3V0bGluZS1kYW5nZXI6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlOmZvY3VzLC5zaG93Pi5idG4tb3V0bGluZS1kYW5nZXIuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjAsNTMsNjksLjUpfS5idG4tb3V0bGluZS1saWdodHtjb2xvcjojZjhmOWZhO2JvcmRlci1jb2xvcjojZjhmOWZhfS5idG4tb3V0bGluZS1saWdodDpob3Zlcntjb2xvcjojMjEyNTI5O2JhY2tncm91bmQtY29sb3I6I2Y4ZjlmYTtib3JkZXItY29sb3I6I2Y4ZjlmYX0uYnRuLW91dGxpbmUtbGlnaHQuZm9jdXMsLmJ0bi1vdXRsaW5lLWxpZ2h0OmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyNDgsMjQ5LDI1MCwuNSl9LmJ0bi1vdXRsaW5lLWxpZ2h0LmRpc2FibGVkLC5idG4tb3V0bGluZS1saWdodDpkaXNhYmxlZHtjb2xvcjojZjhmOWZhO2JhY2tncm91bmQtY29sb3I6dHJhbnNwYXJlbnR9LmJ0bi1vdXRsaW5lLWxpZ2h0Om5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZSwuYnRuLW91dGxpbmUtbGlnaHQ6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlLC5zaG93Pi5idG4tb3V0bGluZS1saWdodC5kcm9wZG93bi10b2dnbGV7Y29sb3I6IzIxMjUyOTtiYWNrZ3JvdW5kLWNvbG9yOiNmOGY5ZmE7Ym9yZGVyLWNvbG9yOiNmOGY5ZmF9LmJ0bi1vdXRsaW5lLWxpZ2h0Om5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZTpmb2N1cywuYnRuLW91dGxpbmUtbGlnaHQ6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6YWN0aXZlOmZvY3VzLC5zaG93Pi5idG4tb3V0bGluZS1saWdodC5kcm9wZG93bi10b2dnbGU6Zm9jdXN7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDI0OCwyNDksMjUwLC41KX0uYnRuLW91dGxpbmUtZGFya3tjb2xvcjojMzQzYTQwO2JvcmRlci1jb2xvcjojMzQzYTQwfS5idG4tb3V0bGluZS1kYXJrOmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMzQzYTQwO2JvcmRlci1jb2xvcjojMzQzYTQwfS5idG4tb3V0bGluZS1kYXJrLmZvY3VzLC5idG4tb3V0bGluZS1kYXJrOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSg1Miw1OCw2NCwuNSl9LmJ0bi1vdXRsaW5lLWRhcmsuZGlzYWJsZWQsLmJ0bi1vdXRsaW5lLWRhcms6ZGlzYWJsZWR7Y29sb3I6IzM0M2E0MDtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fS5idG4tb3V0bGluZS1kYXJrOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZSwuYnRuLW91dGxpbmUtZGFyazpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmUsLnNob3c+LmJ0bi1vdXRsaW5lLWRhcmsuZHJvcGRvd24tdG9nZ2xle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMzQzYTQwO2JvcmRlci1jb2xvcjojMzQzYTQwfS5idG4tb3V0bGluZS1kYXJrOm5vdCg6ZGlzYWJsZWQpOm5vdCguZGlzYWJsZWQpLmFjdGl2ZTpmb2N1cywuYnRuLW91dGxpbmUtZGFyazpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTphY3RpdmU6Zm9jdXMsLnNob3c+LmJ0bi1vdXRsaW5lLWRhcmsuZHJvcGRvd24tdG9nZ2xlOmZvY3Vze2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSg1Miw1OCw2NCwuNSl9LmJ0bi1saW5re2ZvbnQtd2VpZ2h0OjQwMDtjb2xvcjojMDA3YmZmO3RleHQtZGVjb3JhdGlvbjpub25lfS5idG4tbGluazpob3Zlcntjb2xvcjojMDA1NmIzO3RleHQtZGVjb3JhdGlvbjp1bmRlcmxpbmV9LmJ0bi1saW5rLmZvY3VzLC5idG4tbGluazpmb2N1c3t0ZXh0LWRlY29yYXRpb246dW5kZXJsaW5lO2JveC1zaGFkb3c6bm9uZX0uYnRuLWxpbmsuZGlzYWJsZWQsLmJ0bi1saW5rOmRpc2FibGVke2NvbG9yOiM2Yzc1N2Q7cG9pbnRlci1ldmVudHM6bm9uZX0uYnRuLWdyb3VwLWxnPi5idG4sLmJ0bi1sZ3twYWRkaW5nOi41cmVtIDFyZW07Zm9udC1zaXplOjEuMjVyZW07bGluZS1oZWlnaHQ6MS41O2JvcmRlci1yYWRpdXM6LjNyZW19LmJ0bi1ncm91cC1zbT4uYnRuLC5idG4tc217cGFkZGluZzouMjVyZW0gLjVyZW07Zm9udC1zaXplOi44NzVyZW07bGluZS1oZWlnaHQ6MS41O2JvcmRlci1yYWRpdXM6LjJyZW19LmJ0bi1ibG9ja3tkaXNwbGF5OmJsb2NrO3dpZHRoOjEwMCV9LmJ0bi1ibG9jaysuYnRuLWJsb2Nre21hcmdpbi10b3A6LjVyZW19aW5wdXRbdHlwZT1idXR0b25dLmJ0bi1ibG9jayxpbnB1dFt0eXBlPXJlc2V0XS5idG4tYmxvY2ssaW5wdXRbdHlwZT1zdWJtaXRdLmJ0bi1ibG9ja3t3aWR0aDoxMDAlfS5mYWRle3RyYW5zaXRpb246b3BhY2l0eSAuMTVzIGxpbmVhcn1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuZmFkZXt0cmFuc2l0aW9uOm5vbmV9fS5mYWRlOm5vdCguc2hvdyl7b3BhY2l0eTowfS5jb2xsYXBzZTpub3QoLnNob3cpe2Rpc3BsYXk6bm9uZX0uY29sbGFwc2luZ3twb3NpdGlvbjpyZWxhdGl2ZTtoZWlnaHQ6MDtvdmVyZmxvdzpoaWRkZW47dHJhbnNpdGlvbjpoZWlnaHQgLjM1cyBlYXNlfUBtZWRpYSAocHJlZmVycy1yZWR1Y2VkLW1vdGlvbjpyZWR1Y2Upey5jb2xsYXBzaW5ne3RyYW5zaXRpb246bm9uZX19LmRyb3Bkb3duLC5kcm9wbGVmdCwuZHJvcHJpZ2h0LC5kcm9wdXB7cG9zaXRpb246cmVsYXRpdmV9LmRyb3Bkb3duLXRvZ2dsZXt3aGl0ZS1zcGFjZTpub3dyYXB9LmRyb3Bkb3duLXRvZ2dsZTo6YWZ0ZXJ7ZGlzcGxheTppbmxpbmUtYmxvY2s7bWFyZ2luLWxlZnQ6LjI1NWVtO3ZlcnRpY2FsLWFsaWduOi4yNTVlbTtjb250ZW50OiIiO2JvcmRlci10b3A6LjNlbSBzb2xpZDtib3JkZXItcmlnaHQ6LjNlbSBzb2xpZCB0cmFuc3BhcmVudDtib3JkZXItYm90dG9tOjA7Ym9yZGVyLWxlZnQ6LjNlbSBzb2xpZCB0cmFuc3BhcmVudH0uZHJvcGRvd24tdG9nZ2xlOmVtcHR5OjphZnRlcnttYXJnaW4tbGVmdDowfS5kcm9wZG93bi1tZW51e3Bvc2l0aW9uOmFic29sdXRlO3RvcDoxMDAlO2xlZnQ6MDt6LWluZGV4OjEwMDA7ZGlzcGxheTpub25lO2Zsb2F0OmxlZnQ7bWluLXdpZHRoOjEwcmVtO3BhZGRpbmc6LjVyZW0gMDttYXJnaW46LjEyNXJlbSAwIDA7Zm9udC1zaXplOjFyZW07Y29sb3I6IzIxMjUyOTt0ZXh0LWFsaWduOmxlZnQ7bGlzdC1zdHlsZTpub25lO2JhY2tncm91bmQtY29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNsaXA6cGFkZGluZy1ib3g7Ym9yZGVyOjFweCBzb2xpZCByZ2JhKDAsMCwwLC4xNSk7Ym9yZGVyLXJhZGl1czouMjVyZW19LmRyb3Bkb3duLW1lbnUtbGVmdHtyaWdodDphdXRvO2xlZnQ6MH0uZHJvcGRvd24tbWVudS1yaWdodHtyaWdodDowO2xlZnQ6YXV0b31AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmRyb3Bkb3duLW1lbnUtc20tbGVmdHtyaWdodDphdXRvO2xlZnQ6MH0uZHJvcGRvd24tbWVudS1zbS1yaWdodHtyaWdodDowO2xlZnQ6YXV0b319QG1lZGlhIChtaW4td2lkdGg6NzY4cHgpey5kcm9wZG93bi1tZW51LW1kLWxlZnR7cmlnaHQ6YXV0bztsZWZ0OjB9LmRyb3Bkb3duLW1lbnUtbWQtcmlnaHR7cmlnaHQ6MDtsZWZ0OmF1dG99fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuZHJvcGRvd24tbWVudS1sZy1sZWZ0e3JpZ2h0OmF1dG87bGVmdDowfS5kcm9wZG93bi1tZW51LWxnLXJpZ2h0e3JpZ2h0OjA7bGVmdDphdXRvfX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5kcm9wZG93bi1tZW51LXhsLWxlZnR7cmlnaHQ6YXV0bztsZWZ0OjB9LmRyb3Bkb3duLW1lbnUteGwtcmlnaHR7cmlnaHQ6MDtsZWZ0OmF1dG99fS5kcm9wdXAgLmRyb3Bkb3duLW1lbnV7dG9wOmF1dG87Ym90dG9tOjEwMCU7bWFyZ2luLXRvcDowO21hcmdpbi1ib3R0b206LjEyNXJlbX0uZHJvcHVwIC5kcm9wZG93bi10b2dnbGU6OmFmdGVye2Rpc3BsYXk6aW5saW5lLWJsb2NrO21hcmdpbi1sZWZ0Oi4yNTVlbTt2ZXJ0aWNhbC1hbGlnbjouMjU1ZW07Y29udGVudDoiIjtib3JkZXItdG9wOjA7Ym9yZGVyLXJpZ2h0Oi4zZW0gc29saWQgdHJhbnNwYXJlbnQ7Ym9yZGVyLWJvdHRvbTouM2VtIHNvbGlkO2JvcmRlci1sZWZ0Oi4zZW0gc29saWQgdHJhbnNwYXJlbnR9LmRyb3B1cCAuZHJvcGRvd24tdG9nZ2xlOmVtcHR5OjphZnRlcnttYXJnaW4tbGVmdDowfS5kcm9wcmlnaHQgLmRyb3Bkb3duLW1lbnV7dG9wOjA7cmlnaHQ6YXV0bztsZWZ0OjEwMCU7bWFyZ2luLXRvcDowO21hcmdpbi1sZWZ0Oi4xMjVyZW19LmRyb3ByaWdodCAuZHJvcGRvd24tdG9nZ2xlOjphZnRlcntkaXNwbGF5OmlubGluZS1ibG9jazttYXJnaW4tbGVmdDouMjU1ZW07dmVydGljYWwtYWxpZ246LjI1NWVtO2NvbnRlbnQ6IiI7Ym9yZGVyLXRvcDouM2VtIHNvbGlkIHRyYW5zcGFyZW50O2JvcmRlci1yaWdodDowO2JvcmRlci1ib3R0b206LjNlbSBzb2xpZCB0cmFuc3BhcmVudDtib3JkZXItbGVmdDouM2VtIHNvbGlkfS5kcm9wcmlnaHQgLmRyb3Bkb3duLXRvZ2dsZTplbXB0eTo6YWZ0ZXJ7bWFyZ2luLWxlZnQ6MH0uZHJvcHJpZ2h0IC5kcm9wZG93bi10b2dnbGU6OmFmdGVye3ZlcnRpY2FsLWFsaWduOjB9LmRyb3BsZWZ0IC5kcm9wZG93bi1tZW51e3RvcDowO3JpZ2h0OjEwMCU7bGVmdDphdXRvO21hcmdpbi10b3A6MDttYXJnaW4tcmlnaHQ6LjEyNXJlbX0uZHJvcGxlZnQgLmRyb3Bkb3duLXRvZ2dsZTo6YWZ0ZXJ7ZGlzcGxheTppbmxpbmUtYmxvY2s7bWFyZ2luLWxlZnQ6LjI1NWVtO3ZlcnRpY2FsLWFsaWduOi4yNTVlbTtjb250ZW50OiIifS5kcm9wbGVmdCAuZHJvcGRvd24tdG9nZ2xlOjphZnRlcntkaXNwbGF5Om5vbmV9LmRyb3BsZWZ0IC5kcm9wZG93bi10b2dnbGU6OmJlZm9yZXtkaXNwbGF5OmlubGluZS1ibG9jazttYXJnaW4tcmlnaHQ6LjI1NWVtO3ZlcnRpY2FsLWFsaWduOi4yNTVlbTtjb250ZW50OiIiO2JvcmRlci10b3A6LjNlbSBzb2xpZCB0cmFuc3BhcmVudDtib3JkZXItcmlnaHQ6LjNlbSBzb2xpZDtib3JkZXItYm90dG9tOi4zZW0gc29saWQgdHJhbnNwYXJlbnR9LmRyb3BsZWZ0IC5kcm9wZG93bi10b2dnbGU6ZW1wdHk6OmFmdGVye21hcmdpbi1sZWZ0OjB9LmRyb3BsZWZ0IC5kcm9wZG93bi10b2dnbGU6OmJlZm9yZXt2ZXJ0aWNhbC1hbGlnbjowfS5kcm9wZG93bi1tZW51W3gtcGxhY2VtZW50Xj1ib3R0b21dLC5kcm9wZG93bi1tZW51W3gtcGxhY2VtZW50Xj1sZWZ0XSwuZHJvcGRvd24tbWVudVt4LXBsYWNlbWVudF49cmlnaHRdLC5kcm9wZG93bi1tZW51W3gtcGxhY2VtZW50Xj10b3Bde3JpZ2h0OmF1dG87Ym90dG9tOmF1dG99LmRyb3Bkb3duLWRpdmlkZXJ7aGVpZ2h0OjA7bWFyZ2luOi41cmVtIDA7b3ZlcmZsb3c6aGlkZGVuO2JvcmRlci10b3A6MXB4IHNvbGlkICNlOWVjZWZ9LmRyb3Bkb3duLWl0ZW17ZGlzcGxheTpibG9jazt3aWR0aDoxMDAlO3BhZGRpbmc6LjI1cmVtIDEuNXJlbTtjbGVhcjpib3RoO2ZvbnQtd2VpZ2h0OjQwMDtjb2xvcjojMjEyNTI5O3RleHQtYWxpZ246aW5oZXJpdDt3aGl0ZS1zcGFjZTpub3dyYXA7YmFja2dyb3VuZC1jb2xvcjp0cmFuc3BhcmVudDtib3JkZXI6MH0uZHJvcGRvd24taXRlbTpmb2N1cywuZHJvcGRvd24taXRlbTpob3Zlcntjb2xvcjojMTYxODFiO3RleHQtZGVjb3JhdGlvbjpub25lO2JhY2tncm91bmQtY29sb3I6I2Y4ZjlmYX0uZHJvcGRvd24taXRlbS5hY3RpdmUsLmRyb3Bkb3duLWl0ZW06YWN0aXZle2NvbG9yOiNmZmY7dGV4dC1kZWNvcmF0aW9uOm5vbmU7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmfS5kcm9wZG93bi1pdGVtLmRpc2FibGVkLC5kcm9wZG93bi1pdGVtOmRpc2FibGVke2NvbG9yOiM2Yzc1N2Q7cG9pbnRlci1ldmVudHM6bm9uZTtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fS5kcm9wZG93bi1tZW51LnNob3d7ZGlzcGxheTpibG9ja30uZHJvcGRvd24taGVhZGVye2Rpc3BsYXk6YmxvY2s7cGFkZGluZzouNXJlbSAxLjVyZW07bWFyZ2luLWJvdHRvbTowO2ZvbnQtc2l6ZTouODc1cmVtO2NvbG9yOiM2Yzc1N2Q7d2hpdGUtc3BhY2U6bm93cmFwfS5kcm9wZG93bi1pdGVtLXRleHR7ZGlzcGxheTpibG9jaztwYWRkaW5nOi4yNXJlbSAxLjVyZW07Y29sb3I6IzIxMjUyOX0uYnRuLWdyb3VwLC5idG4tZ3JvdXAtdmVydGljYWx7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3g7ZGlzcGxheTppbmxpbmUtZmxleDt2ZXJ0aWNhbC1hbGlnbjptaWRkbGV9LmJ0bi1ncm91cC12ZXJ0aWNhbD4uYnRuLC5idG4tZ3JvdXA+LmJ0bntwb3NpdGlvbjpyZWxhdGl2ZTstbXMtZmxleDoxIDEgYXV0bztmbGV4OjEgMSBhdXRvfS5idG4tZ3JvdXAtdmVydGljYWw+LmJ0bjpob3ZlciwuYnRuLWdyb3VwPi5idG46aG92ZXJ7ei1pbmRleDoxfS5idG4tZ3JvdXAtdmVydGljYWw+LmJ0bi5hY3RpdmUsLmJ0bi1ncm91cC12ZXJ0aWNhbD4uYnRuOmFjdGl2ZSwuYnRuLWdyb3VwLXZlcnRpY2FsPi5idG46Zm9jdXMsLmJ0bi1ncm91cD4uYnRuLmFjdGl2ZSwuYnRuLWdyb3VwPi5idG46YWN0aXZlLC5idG4tZ3JvdXA+LmJ0bjpmb2N1c3t6LWluZGV4OjF9LmJ0bi10b29sYmFye2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LXdyYXA6d3JhcDtmbGV4LXdyYXA6d3JhcDstbXMtZmxleC1wYWNrOnN0YXJ0O2p1c3RpZnktY29udGVudDpmbGV4LXN0YXJ0fS5idG4tdG9vbGJhciAuaW5wdXQtZ3JvdXB7d2lkdGg6YXV0b30uYnRuLWdyb3VwPi5idG4tZ3JvdXA6bm90KDpmaXJzdC1jaGlsZCksLmJ0bi1ncm91cD4uYnRuOm5vdCg6Zmlyc3QtY2hpbGQpe21hcmdpbi1sZWZ0Oi0xcHh9LmJ0bi1ncm91cD4uYnRuLWdyb3VwOm5vdCg6bGFzdC1jaGlsZCk+LmJ0biwuYnRuLWdyb3VwPi5idG46bm90KDpsYXN0LWNoaWxkKTpub3QoLmRyb3Bkb3duLXRvZ2dsZSl7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6MDtib3JkZXItYm90dG9tLXJpZ2h0LXJhZGl1czowfS5idG4tZ3JvdXA+LmJ0bi1ncm91cDpub3QoOmZpcnN0LWNoaWxkKT4uYnRuLC5idG4tZ3JvdXA+LmJ0bjpub3QoOmZpcnN0LWNoaWxkKXtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjA7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czowfS5kcm9wZG93bi10b2dnbGUtc3BsaXR7cGFkZGluZy1yaWdodDouNTYyNXJlbTtwYWRkaW5nLWxlZnQ6LjU2MjVyZW19LmRyb3Bkb3duLXRvZ2dsZS1zcGxpdDo6YWZ0ZXIsLmRyb3ByaWdodCAuZHJvcGRvd24tdG9nZ2xlLXNwbGl0OjphZnRlciwuZHJvcHVwIC5kcm9wZG93bi10b2dnbGUtc3BsaXQ6OmFmdGVye21hcmdpbi1sZWZ0OjB9LmRyb3BsZWZ0IC5kcm9wZG93bi10b2dnbGUtc3BsaXQ6OmJlZm9yZXttYXJnaW4tcmlnaHQ6MH0uYnRuLWdyb3VwLXNtPi5idG4rLmRyb3Bkb3duLXRvZ2dsZS1zcGxpdCwuYnRuLXNtKy5kcm9wZG93bi10b2dnbGUtc3BsaXR7cGFkZGluZy1yaWdodDouMzc1cmVtO3BhZGRpbmctbGVmdDouMzc1cmVtfS5idG4tZ3JvdXAtbGc+LmJ0bisuZHJvcGRvd24tdG9nZ2xlLXNwbGl0LC5idG4tbGcrLmRyb3Bkb3duLXRvZ2dsZS1zcGxpdHtwYWRkaW5nLXJpZ2h0Oi43NXJlbTtwYWRkaW5nLWxlZnQ6Ljc1cmVtfS5idG4tZ3JvdXAtdmVydGljYWx7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbjtmbGV4LWRpcmVjdGlvbjpjb2x1bW47LW1zLWZsZXgtYWxpZ246c3RhcnQ7YWxpZ24taXRlbXM6ZmxleC1zdGFydDstbXMtZmxleC1wYWNrOmNlbnRlcjtqdXN0aWZ5LWNvbnRlbnQ6Y2VudGVyfS5idG4tZ3JvdXAtdmVydGljYWw+LmJ0biwuYnRuLWdyb3VwLXZlcnRpY2FsPi5idG4tZ3JvdXB7d2lkdGg6MTAwJX0uYnRuLWdyb3VwLXZlcnRpY2FsPi5idG4tZ3JvdXA6bm90KDpmaXJzdC1jaGlsZCksLmJ0bi1ncm91cC12ZXJ0aWNhbD4uYnRuOm5vdCg6Zmlyc3QtY2hpbGQpe21hcmdpbi10b3A6LTFweH0uYnRuLWdyb3VwLXZlcnRpY2FsPi5idG4tZ3JvdXA6bm90KDpsYXN0LWNoaWxkKT4uYnRuLC5idG4tZ3JvdXAtdmVydGljYWw+LmJ0bjpub3QoOmxhc3QtY2hpbGQpOm5vdCguZHJvcGRvd24tdG9nZ2xlKXtib3JkZXItYm90dG9tLXJpZ2h0LXJhZGl1czowO2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6MH0uYnRuLWdyb3VwLXZlcnRpY2FsPi5idG4tZ3JvdXA6bm90KDpmaXJzdC1jaGlsZCk+LmJ0biwuYnRuLWdyb3VwLXZlcnRpY2FsPi5idG46bm90KDpmaXJzdC1jaGlsZCl7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czowO2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOjB9LmJ0bi1ncm91cC10b2dnbGU+LmJ0biwuYnRuLWdyb3VwLXRvZ2dsZT4uYnRuLWdyb3VwPi5idG57bWFyZ2luLWJvdHRvbTowfS5idG4tZ3JvdXAtdG9nZ2xlPi5idG4gaW5wdXRbdHlwZT1jaGVja2JveF0sLmJ0bi1ncm91cC10b2dnbGU+LmJ0biBpbnB1dFt0eXBlPXJhZGlvXSwuYnRuLWdyb3VwLXRvZ2dsZT4uYnRuLWdyb3VwPi5idG4gaW5wdXRbdHlwZT1jaGVja2JveF0sLmJ0bi1ncm91cC10b2dnbGU+LmJ0bi1ncm91cD4uYnRuIGlucHV0W3R5cGU9cmFkaW9de3Bvc2l0aW9uOmFic29sdXRlO2NsaXA6cmVjdCgwLDAsMCwwKTtwb2ludGVyLWV2ZW50czpub25lfS5pbnB1dC1ncm91cHtwb3NpdGlvbjpyZWxhdGl2ZTtkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC13cmFwOndyYXA7ZmxleC13cmFwOndyYXA7LW1zLWZsZXgtYWxpZ246c3RyZXRjaDthbGlnbi1pdGVtczpzdHJldGNoO3dpZHRoOjEwMCV9LmlucHV0LWdyb3VwPi5jdXN0b20tZmlsZSwuaW5wdXQtZ3JvdXA+LmN1c3RvbS1zZWxlY3QsLmlucHV0LWdyb3VwPi5mb3JtLWNvbnRyb2wsLmlucHV0LWdyb3VwPi5mb3JtLWNvbnRyb2wtcGxhaW50ZXh0e3Bvc2l0aW9uOnJlbGF0aXZlOy1tcy1mbGV4OjEgMSAwJTtmbGV4OjEgMSAwJTttaW4td2lkdGg6MDttYXJnaW4tYm90dG9tOjB9LmlucHV0LWdyb3VwPi5jdXN0b20tZmlsZSsuY3VzdG9tLWZpbGUsLmlucHV0LWdyb3VwPi5jdXN0b20tZmlsZSsuY3VzdG9tLXNlbGVjdCwuaW5wdXQtZ3JvdXA+LmN1c3RvbS1maWxlKy5mb3JtLWNvbnRyb2wsLmlucHV0LWdyb3VwPi5jdXN0b20tc2VsZWN0Ky5jdXN0b20tZmlsZSwuaW5wdXQtZ3JvdXA+LmN1c3RvbS1zZWxlY3QrLmN1c3RvbS1zZWxlY3QsLmlucHV0LWdyb3VwPi5jdXN0b20tc2VsZWN0Ky5mb3JtLWNvbnRyb2wsLmlucHV0LWdyb3VwPi5mb3JtLWNvbnRyb2wrLmN1c3RvbS1maWxlLC5pbnB1dC1ncm91cD4uZm9ybS1jb250cm9sKy5jdXN0b20tc2VsZWN0LC5pbnB1dC1ncm91cD4uZm9ybS1jb250cm9sKy5mb3JtLWNvbnRyb2wsLmlucHV0LWdyb3VwPi5mb3JtLWNvbnRyb2wtcGxhaW50ZXh0Ky5jdXN0b20tZmlsZSwuaW5wdXQtZ3JvdXA+LmZvcm0tY29udHJvbC1wbGFpbnRleHQrLmN1c3RvbS1zZWxlY3QsLmlucHV0LWdyb3VwPi5mb3JtLWNvbnRyb2wtcGxhaW50ZXh0Ky5mb3JtLWNvbnRyb2x7bWFyZ2luLWxlZnQ6LTFweH0uaW5wdXQtZ3JvdXA+LmN1c3RvbS1maWxlIC5jdXN0b20tZmlsZS1pbnB1dDpmb2N1c34uY3VzdG9tLWZpbGUtbGFiZWwsLmlucHV0LWdyb3VwPi5jdXN0b20tc2VsZWN0OmZvY3VzLC5pbnB1dC1ncm91cD4uZm9ybS1jb250cm9sOmZvY3Vze3otaW5kZXg6M30uaW5wdXQtZ3JvdXA+LmN1c3RvbS1maWxlIC5jdXN0b20tZmlsZS1pbnB1dDpmb2N1c3t6LWluZGV4OjR9LmlucHV0LWdyb3VwPi5jdXN0b20tc2VsZWN0Om5vdCg6bGFzdC1jaGlsZCksLmlucHV0LWdyb3VwPi5mb3JtLWNvbnRyb2w6bm90KDpsYXN0LWNoaWxkKXtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czowO2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjB9LmlucHV0LWdyb3VwPi5jdXN0b20tc2VsZWN0Om5vdCg6Zmlyc3QtY2hpbGQpLC5pbnB1dC1ncm91cD4uZm9ybS1jb250cm9sOm5vdCg6Zmlyc3QtY2hpbGQpe2JvcmRlci10b3AtbGVmdC1yYWRpdXM6MDtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjB9LmlucHV0LWdyb3VwPi5jdXN0b20tZmlsZXtkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC1hbGlnbjpjZW50ZXI7YWxpZ24taXRlbXM6Y2VudGVyfS5pbnB1dC1ncm91cD4uY3VzdG9tLWZpbGU6bm90KDpsYXN0LWNoaWxkKSAuY3VzdG9tLWZpbGUtbGFiZWwsLmlucHV0LWdyb3VwPi5jdXN0b20tZmlsZTpub3QoOmxhc3QtY2hpbGQpIC5jdXN0b20tZmlsZS1sYWJlbDo6YWZ0ZXJ7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6MDtib3JkZXItYm90dG9tLXJpZ2h0LXJhZGl1czowfS5pbnB1dC1ncm91cD4uY3VzdG9tLWZpbGU6bm90KDpmaXJzdC1jaGlsZCkgLmN1c3RvbS1maWxlLWxhYmVse2JvcmRlci10b3AtbGVmdC1yYWRpdXM6MDtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjB9LmlucHV0LWdyb3VwLWFwcGVuZCwuaW5wdXQtZ3JvdXAtcHJlcGVuZHtkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleH0uaW5wdXQtZ3JvdXAtYXBwZW5kIC5idG4sLmlucHV0LWdyb3VwLXByZXBlbmQgLmJ0bntwb3NpdGlvbjpyZWxhdGl2ZTt6LWluZGV4OjJ9LmlucHV0LWdyb3VwLWFwcGVuZCAuYnRuOmZvY3VzLC5pbnB1dC1ncm91cC1wcmVwZW5kIC5idG46Zm9jdXN7ei1pbmRleDozfS5pbnB1dC1ncm91cC1hcHBlbmQgLmJ0bisuYnRuLC5pbnB1dC1ncm91cC1hcHBlbmQgLmJ0bisuaW5wdXQtZ3JvdXAtdGV4dCwuaW5wdXQtZ3JvdXAtYXBwZW5kIC5pbnB1dC1ncm91cC10ZXh0Ky5idG4sLmlucHV0LWdyb3VwLWFwcGVuZCAuaW5wdXQtZ3JvdXAtdGV4dCsuaW5wdXQtZ3JvdXAtdGV4dCwuaW5wdXQtZ3JvdXAtcHJlcGVuZCAuYnRuKy5idG4sLmlucHV0LWdyb3VwLXByZXBlbmQgLmJ0bisuaW5wdXQtZ3JvdXAtdGV4dCwuaW5wdXQtZ3JvdXAtcHJlcGVuZCAuaW5wdXQtZ3JvdXAtdGV4dCsuYnRuLC5pbnB1dC1ncm91cC1wcmVwZW5kIC5pbnB1dC1ncm91cC10ZXh0Ky5pbnB1dC1ncm91cC10ZXh0e21hcmdpbi1sZWZ0Oi0xcHh9LmlucHV0LWdyb3VwLXByZXBlbmR7bWFyZ2luLXJpZ2h0Oi0xcHh9LmlucHV0LWdyb3VwLWFwcGVuZHttYXJnaW4tbGVmdDotMXB4fS5pbnB1dC1ncm91cC10ZXh0e2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7cGFkZGluZzouMzc1cmVtIC43NXJlbTttYXJnaW4tYm90dG9tOjA7Zm9udC1zaXplOjFyZW07Zm9udC13ZWlnaHQ6NDAwO2xpbmUtaGVpZ2h0OjEuNTtjb2xvcjojNDk1MDU3O3RleHQtYWxpZ246Y2VudGVyO3doaXRlLXNwYWNlOm5vd3JhcDtiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWY7Ym9yZGVyOjFweCBzb2xpZCAjY2VkNGRhO2JvcmRlci1yYWRpdXM6LjI1cmVtfS5pbnB1dC1ncm91cC10ZXh0IGlucHV0W3R5cGU9Y2hlY2tib3hdLC5pbnB1dC1ncm91cC10ZXh0IGlucHV0W3R5cGU9cmFkaW9de21hcmdpbi10b3A6MH0uaW5wdXQtZ3JvdXAtbGc+LmN1c3RvbS1zZWxlY3QsLmlucHV0LWdyb3VwLWxnPi5mb3JtLWNvbnRyb2w6bm90KHRleHRhcmVhKXtoZWlnaHQ6Y2FsYygxLjVlbSArIDFyZW0gKyAycHgpfS5pbnB1dC1ncm91cC1sZz4uY3VzdG9tLXNlbGVjdCwuaW5wdXQtZ3JvdXAtbGc+LmZvcm0tY29udHJvbCwuaW5wdXQtZ3JvdXAtbGc+LmlucHV0LWdyb3VwLWFwcGVuZD4uYnRuLC5pbnB1dC1ncm91cC1sZz4uaW5wdXQtZ3JvdXAtYXBwZW5kPi5pbnB1dC1ncm91cC10ZXh0LC5pbnB1dC1ncm91cC1sZz4uaW5wdXQtZ3JvdXAtcHJlcGVuZD4uYnRuLC5pbnB1dC1ncm91cC1sZz4uaW5wdXQtZ3JvdXAtcHJlcGVuZD4uaW5wdXQtZ3JvdXAtdGV4dHtwYWRkaW5nOi41cmVtIDFyZW07Zm9udC1zaXplOjEuMjVyZW07bGluZS1oZWlnaHQ6MS41O2JvcmRlci1yYWRpdXM6LjNyZW19LmlucHV0LWdyb3VwLXNtPi5jdXN0b20tc2VsZWN0LC5pbnB1dC1ncm91cC1zbT4uZm9ybS1jb250cm9sOm5vdCh0ZXh0YXJlYSl7aGVpZ2h0OmNhbGMoMS41ZW0gKyAuNXJlbSArIDJweCl9LmlucHV0LWdyb3VwLXNtPi5jdXN0b20tc2VsZWN0LC5pbnB1dC1ncm91cC1zbT4uZm9ybS1jb250cm9sLC5pbnB1dC1ncm91cC1zbT4uaW5wdXQtZ3JvdXAtYXBwZW5kPi5idG4sLmlucHV0LWdyb3VwLXNtPi5pbnB1dC1ncm91cC1hcHBlbmQ+LmlucHV0LWdyb3VwLXRleHQsLmlucHV0LWdyb3VwLXNtPi5pbnB1dC1ncm91cC1wcmVwZW5kPi5idG4sLmlucHV0LWdyb3VwLXNtPi5pbnB1dC1ncm91cC1wcmVwZW5kPi5pbnB1dC1ncm91cC10ZXh0e3BhZGRpbmc6LjI1cmVtIC41cmVtO2ZvbnQtc2l6ZTouODc1cmVtO2xpbmUtaGVpZ2h0OjEuNTtib3JkZXItcmFkaXVzOi4ycmVtfS5pbnB1dC1ncm91cC1sZz4uY3VzdG9tLXNlbGVjdCwuaW5wdXQtZ3JvdXAtc20+LmN1c3RvbS1zZWxlY3R7cGFkZGluZy1yaWdodDoxLjc1cmVtfS5pbnB1dC1ncm91cD4uaW5wdXQtZ3JvdXAtYXBwZW5kOmxhc3QtY2hpbGQ+LmJ0bjpub3QoOmxhc3QtY2hpbGQpOm5vdCguZHJvcGRvd24tdG9nZ2xlKSwuaW5wdXQtZ3JvdXA+LmlucHV0LWdyb3VwLWFwcGVuZDpsYXN0LWNoaWxkPi5pbnB1dC1ncm91cC10ZXh0Om5vdCg6bGFzdC1jaGlsZCksLmlucHV0LWdyb3VwPi5pbnB1dC1ncm91cC1hcHBlbmQ6bm90KDpsYXN0LWNoaWxkKT4uYnRuLC5pbnB1dC1ncm91cD4uaW5wdXQtZ3JvdXAtYXBwZW5kOm5vdCg6bGFzdC1jaGlsZCk+LmlucHV0LWdyb3VwLXRleHQsLmlucHV0LWdyb3VwPi5pbnB1dC1ncm91cC1wcmVwZW5kPi5idG4sLmlucHV0LWdyb3VwPi5pbnB1dC1ncm91cC1wcmVwZW5kPi5pbnB1dC1ncm91cC10ZXh0e2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOjA7Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6MH0uaW5wdXQtZ3JvdXA+LmlucHV0LWdyb3VwLWFwcGVuZD4uYnRuLC5pbnB1dC1ncm91cD4uaW5wdXQtZ3JvdXAtYXBwZW5kPi5pbnB1dC1ncm91cC10ZXh0LC5pbnB1dC1ncm91cD4uaW5wdXQtZ3JvdXAtcHJlcGVuZDpmaXJzdC1jaGlsZD4uYnRuOm5vdCg6Zmlyc3QtY2hpbGQpLC5pbnB1dC1ncm91cD4uaW5wdXQtZ3JvdXAtcHJlcGVuZDpmaXJzdC1jaGlsZD4uaW5wdXQtZ3JvdXAtdGV4dDpub3QoOmZpcnN0LWNoaWxkKSwuaW5wdXQtZ3JvdXA+LmlucHV0LWdyb3VwLXByZXBlbmQ6bm90KDpmaXJzdC1jaGlsZCk+LmJ0biwuaW5wdXQtZ3JvdXA+LmlucHV0LWdyb3VwLXByZXBlbmQ6bm90KDpmaXJzdC1jaGlsZCk+LmlucHV0LWdyb3VwLXRleHR7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czowO2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6MH0uY3VzdG9tLWNvbnRyb2x7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTpibG9jazttaW4taGVpZ2h0OjEuNXJlbTtwYWRkaW5nLWxlZnQ6MS41cmVtfS5jdXN0b20tY29udHJvbC1pbmxpbmV7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3g7ZGlzcGxheTppbmxpbmUtZmxleDttYXJnaW4tcmlnaHQ6MXJlbX0uY3VzdG9tLWNvbnRyb2wtaW5wdXR7cG9zaXRpb246YWJzb2x1dGU7bGVmdDowO3otaW5kZXg6LTE7d2lkdGg6MXJlbTtoZWlnaHQ6MS4yNXJlbTtvcGFjaXR5OjB9LmN1c3RvbS1jb250cm9sLWlucHV0OmNoZWNrZWR+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7Y29sb3I6I2ZmZjtib3JkZXItY29sb3I6IzAwN2JmZjtiYWNrZ3JvdW5kLWNvbG9yOiMwMDdiZmZ9LmN1c3RvbS1jb250cm9sLWlucHV0OmZvY3Vzfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3Jle2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgwLDEyMywyNTUsLjI1KX0uY3VzdG9tLWNvbnRyb2wtaW5wdXQ6Zm9jdXM6bm90KDpjaGVja2VkKX4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtib3JkZXItY29sb3I6IzgwYmRmZn0uY3VzdG9tLWNvbnRyb2wtaW5wdXQ6bm90KDpkaXNhYmxlZCk6YWN0aXZlfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3Jle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojYjNkN2ZmO2JvcmRlci1jb2xvcjojYjNkN2ZmfS5jdXN0b20tY29udHJvbC1pbnB1dDpkaXNhYmxlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWwsLmN1c3RvbS1jb250cm9sLWlucHV0W2Rpc2FibGVkXX4uY3VzdG9tLWNvbnRyb2wtbGFiZWx7Y29sb3I6IzZjNzU3ZH0uY3VzdG9tLWNvbnRyb2wtaW5wdXQ6ZGlzYWJsZWR+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmUsLmN1c3RvbS1jb250cm9sLWlucHV0W2Rpc2FibGVkXX4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWZ9LmN1c3RvbS1jb250cm9sLWxhYmVse3Bvc2l0aW9uOnJlbGF0aXZlO21hcmdpbi1ib3R0b206MDt2ZXJ0aWNhbC1hbGlnbjp0b3B9LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7cG9zaXRpb246YWJzb2x1dGU7dG9wOi4yNXJlbTtsZWZ0Oi0xLjVyZW07ZGlzcGxheTpibG9jazt3aWR0aDoxcmVtO2hlaWdodDoxcmVtO3BvaW50ZXItZXZlbnRzOm5vbmU7Y29udGVudDoiIjtiYWNrZ3JvdW5kLWNvbG9yOiNmZmY7Ym9yZGVyOiNhZGI1YmQgc29saWQgMXB4fS5jdXN0b20tY29udHJvbC1sYWJlbDo6YWZ0ZXJ7cG9zaXRpb246YWJzb2x1dGU7dG9wOi4yNXJlbTtsZWZ0Oi0xLjVyZW07ZGlzcGxheTpibG9jazt3aWR0aDoxcmVtO2hlaWdodDoxcmVtO2NvbnRlbnQ6IiI7YmFja2dyb3VuZDpuby1yZXBlYXQgNTAlLzUwJSA1MCV9LmN1c3RvbS1jaGVja2JveCAuY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtib3JkZXItcmFkaXVzOi4yNXJlbX0uY3VzdG9tLWNoZWNrYm94IC5jdXN0b20tY29udHJvbC1pbnB1dDpjaGVja2Vkfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YWZ0ZXJ7YmFja2dyb3VuZC1pbWFnZTp1cmwoImRhdGE6aW1hZ2Uvc3ZnK3htbCwlM2NzdmcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB3aWR0aD0nOCcgaGVpZ2h0PSc4JyB2aWV3Qm94PScwIDAgOCA4JyUzZSUzY3BhdGggZmlsbD0nJTIzZmZmJyBkPSdNNi41NjQuNzVsLTMuNTkgMy42MTItMS41MzgtMS41NUwwIDQuMjZsMi45NzQgMi45OUw4IDIuMTkzeicvJTNlJTNjL3N2ZyUzZSIpfS5jdXN0b20tY2hlY2tib3ggLmN1c3RvbS1jb250cm9sLWlucHV0OmluZGV0ZXJtaW5hdGV+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7Ym9yZGVyLWNvbG9yOiMwMDdiZmY7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmfS5jdXN0b20tY2hlY2tib3ggLmN1c3RvbS1jb250cm9sLWlucHV0OmluZGV0ZXJtaW5hdGV+LmN1c3RvbS1jb250cm9sLWxhYmVsOjphZnRlcntiYWNrZ3JvdW5kLWltYWdlOnVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0JyBoZWlnaHQ9JzQnIHZpZXdCb3g9JzAgMCA0IDQnJTNlJTNjcGF0aCBzdHJva2U9JyUyM2ZmZicgZD0nTTAgMmg0Jy8lM2UlM2Mvc3ZnJTNlIil9LmN1c3RvbS1jaGVja2JveCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6ZGlzYWJsZWQ6Y2hlY2tlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtiYWNrZ3JvdW5kLWNvbG9yOnJnYmEoMCwxMjMsMjU1LC41KX0uY3VzdG9tLWNoZWNrYm94IC5jdXN0b20tY29udHJvbC1pbnB1dDpkaXNhYmxlZDppbmRldGVybWluYXRlfi5jdXN0b20tY29udHJvbC1sYWJlbDo6YmVmb3Jle2JhY2tncm91bmQtY29sb3I6cmdiYSgwLDEyMywyNTUsLjUpfS5jdXN0b20tcmFkaW8gLmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7Ym9yZGVyLXJhZGl1czo1MCV9LmN1c3RvbS1yYWRpbyAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6Y2hlY2tlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmFmdGVye2JhY2tncm91bmQtaW1hZ2U6dXJsKCJkYXRhOmltYWdlL3N2Zyt4bWwsJTNjc3ZnIHhtbG5zPSdodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2Zycgd2lkdGg9JzEyJyBoZWlnaHQ9JzEyJyB2aWV3Qm94PSctNCAtNCA4IDgnJTNlJTNjY2lyY2xlIHI9JzMnIGZpbGw9JyUyM2ZmZicvJTNlJTNjL3N2ZyUzZSIpfS5jdXN0b20tcmFkaW8gLmN1c3RvbS1jb250cm9sLWlucHV0OmRpc2FibGVkOmNoZWNrZWR+LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMTIzLDI1NSwuNSl9LmN1c3RvbS1zd2l0Y2h7cGFkZGluZy1sZWZ0OjIuMjVyZW19LmN1c3RvbS1zd2l0Y2ggLmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmV7bGVmdDotMi4yNXJlbTt3aWR0aDoxLjc1cmVtO3BvaW50ZXItZXZlbnRzOmFsbDtib3JkZXItcmFkaXVzOi41cmVtfS5jdXN0b20tc3dpdGNoIC5jdXN0b20tY29udHJvbC1sYWJlbDo6YWZ0ZXJ7dG9wOmNhbGMoLjI1cmVtICsgMnB4KTtsZWZ0OmNhbGMoLTIuMjVyZW0gKyAycHgpO3dpZHRoOmNhbGMoMXJlbSAtIDRweCk7aGVpZ2h0OmNhbGMoMXJlbSAtIDRweCk7YmFja2dyb3VuZC1jb2xvcjojYWRiNWJkO2JvcmRlci1yYWRpdXM6LjVyZW07dHJhbnNpdGlvbjpiYWNrZ3JvdW5kLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm9yZGVyLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm94LXNoYWRvdyAuMTVzIGVhc2UtaW4tb3V0LC13ZWJraXQtdHJhbnNmb3JtIC4xNXMgZWFzZS1pbi1vdXQ7dHJhbnNpdGlvbjp0cmFuc2Zvcm0gLjE1cyBlYXNlLWluLW91dCxiYWNrZ3JvdW5kLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm9yZGVyLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm94LXNoYWRvdyAuMTVzIGVhc2UtaW4tb3V0O3RyYW5zaXRpb246dHJhbnNmb3JtIC4xNXMgZWFzZS1pbi1vdXQsYmFja2dyb3VuZC1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJvcmRlci1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJveC1zaGFkb3cgLjE1cyBlYXNlLWluLW91dCwtd2Via2l0LXRyYW5zZm9ybSAuMTVzIGVhc2UtaW4tb3V0fUBtZWRpYSAocHJlZmVycy1yZWR1Y2VkLW1vdGlvbjpyZWR1Y2Upey5jdXN0b20tc3dpdGNoIC5jdXN0b20tY29udHJvbC1sYWJlbDo6YWZ0ZXJ7dHJhbnNpdGlvbjpub25lfX0uY3VzdG9tLXN3aXRjaCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6Y2hlY2tlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmFmdGVye2JhY2tncm91bmQtY29sb3I6I2ZmZjstd2Via2l0LXRyYW5zZm9ybTp0cmFuc2xhdGVYKC43NXJlbSk7dHJhbnNmb3JtOnRyYW5zbGF0ZVgoLjc1cmVtKX0uY3VzdG9tLXN3aXRjaCAuY3VzdG9tLWNvbnRyb2wtaW5wdXQ6ZGlzYWJsZWQ6Y2hlY2tlZH4uY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZXtiYWNrZ3JvdW5kLWNvbG9yOnJnYmEoMCwxMjMsMjU1LC41KX0uY3VzdG9tLXNlbGVjdHtkaXNwbGF5OmlubGluZS1ibG9jazt3aWR0aDoxMDAlO2hlaWdodDpjYWxjKDEuNWVtICsgLjc1cmVtICsgMnB4KTtwYWRkaW5nOi4zNzVyZW0gMS43NXJlbSAuMzc1cmVtIC43NXJlbTtmb250LXNpemU6MXJlbTtmb250LXdlaWdodDo0MDA7bGluZS1oZWlnaHQ6MS41O2NvbG9yOiM0OTUwNTc7dmVydGljYWwtYWxpZ246bWlkZGxlO2JhY2tncm91bmQ6I2ZmZiB1cmwoImRhdGE6aW1hZ2Uvc3ZnK3htbCwlM2NzdmcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB3aWR0aD0nNCcgaGVpZ2h0PSc1JyB2aWV3Qm94PScwIDAgNCA1JyUzZSUzY3BhdGggZmlsbD0nJTIzMzQzYTQwJyBkPSdNMiAwTDAgMmg0em0wIDVMMCAzaDR6Jy8lM2UlM2Mvc3ZnJTNlIikgbm8tcmVwZWF0IHJpZ2h0IC43NXJlbSBjZW50ZXIvOHB4IDEwcHg7Ym9yZGVyOjFweCBzb2xpZCAjY2VkNGRhO2JvcmRlci1yYWRpdXM6LjI1cmVtOy13ZWJraXQtYXBwZWFyYW5jZTpub25lOy1tb3otYXBwZWFyYW5jZTpub25lO2FwcGVhcmFuY2U6bm9uZX0uY3VzdG9tLXNlbGVjdDpmb2N1c3tib3JkZXItY29sb3I6IzgwYmRmZjtvdXRsaW5lOjA7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDAsMTIzLDI1NSwuMjUpfS5jdXN0b20tc2VsZWN0OmZvY3VzOjotbXMtdmFsdWV7Y29sb3I6IzQ5NTA1NztiYWNrZ3JvdW5kLWNvbG9yOiNmZmZ9LmN1c3RvbS1zZWxlY3RbbXVsdGlwbGVdLC5jdXN0b20tc2VsZWN0W3NpemVdOm5vdChbc2l6ZT0iMSJdKXtoZWlnaHQ6YXV0bztwYWRkaW5nLXJpZ2h0Oi43NXJlbTtiYWNrZ3JvdW5kLWltYWdlOm5vbmV9LmN1c3RvbS1zZWxlY3Q6ZGlzYWJsZWR7Y29sb3I6IzZjNzU3ZDtiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWZ9LmN1c3RvbS1zZWxlY3Q6Oi1tcy1leHBhbmR7ZGlzcGxheTpub25lfS5jdXN0b20tc2VsZWN0Oi1tb3otZm9jdXNyaW5ne2NvbG9yOnRyYW5zcGFyZW50O3RleHQtc2hhZG93OjAgMCAwICM0OTUwNTd9LmN1c3RvbS1zZWxlY3Qtc217aGVpZ2h0OmNhbGMoMS41ZW0gKyAuNXJlbSArIDJweCk7cGFkZGluZy10b3A6LjI1cmVtO3BhZGRpbmctYm90dG9tOi4yNXJlbTtwYWRkaW5nLWxlZnQ6LjVyZW07Zm9udC1zaXplOi44NzVyZW19LmN1c3RvbS1zZWxlY3QtbGd7aGVpZ2h0OmNhbGMoMS41ZW0gKyAxcmVtICsgMnB4KTtwYWRkaW5nLXRvcDouNXJlbTtwYWRkaW5nLWJvdHRvbTouNXJlbTtwYWRkaW5nLWxlZnQ6MXJlbTtmb250LXNpemU6MS4yNXJlbX0uY3VzdG9tLWZpbGV7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTppbmxpbmUtYmxvY2s7d2lkdGg6MTAwJTtoZWlnaHQ6Y2FsYygxLjVlbSArIC43NXJlbSArIDJweCk7bWFyZ2luLWJvdHRvbTowfS5jdXN0b20tZmlsZS1pbnB1dHtwb3NpdGlvbjpyZWxhdGl2ZTt6LWluZGV4OjI7d2lkdGg6MTAwJTtoZWlnaHQ6Y2FsYygxLjVlbSArIC43NXJlbSArIDJweCk7bWFyZ2luOjA7b3BhY2l0eTowfS5jdXN0b20tZmlsZS1pbnB1dDpmb2N1c34uY3VzdG9tLWZpbGUtbGFiZWx7Ym9yZGVyLWNvbG9yOiM4MGJkZmY7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDAsMTIzLDI1NSwuMjUpfS5jdXN0b20tZmlsZS1pbnB1dDpkaXNhYmxlZH4uY3VzdG9tLWZpbGUtbGFiZWwsLmN1c3RvbS1maWxlLWlucHV0W2Rpc2FibGVkXX4uY3VzdG9tLWZpbGUtbGFiZWx7YmFja2dyb3VuZC1jb2xvcjojZTllY2VmfS5jdXN0b20tZmlsZS1pbnB1dDpsYW5nKGVuKX4uY3VzdG9tLWZpbGUtbGFiZWw6OmFmdGVye2NvbnRlbnQ6IkJyb3dzZSJ9LmN1c3RvbS1maWxlLWlucHV0fi5jdXN0b20tZmlsZS1sYWJlbFtkYXRhLWJyb3dzZV06OmFmdGVye2NvbnRlbnQ6YXR0cihkYXRhLWJyb3dzZSl9LmN1c3RvbS1maWxlLWxhYmVse3Bvc2l0aW9uOmFic29sdXRlO3RvcDowO3JpZ2h0OjA7bGVmdDowO3otaW5kZXg6MTtoZWlnaHQ6Y2FsYygxLjVlbSArIC43NXJlbSArIDJweCk7cGFkZGluZzouMzc1cmVtIC43NXJlbTtmb250LXdlaWdodDo0MDA7bGluZS1oZWlnaHQ6MS41O2NvbG9yOiM0OTUwNTc7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlcjoxcHggc29saWQgI2NlZDRkYTtib3JkZXItcmFkaXVzOi4yNXJlbX0uY3VzdG9tLWZpbGUtbGFiZWw6OmFmdGVye3Bvc2l0aW9uOmFic29sdXRlO3RvcDowO3JpZ2h0OjA7Ym90dG9tOjA7ei1pbmRleDozO2Rpc3BsYXk6YmxvY2s7aGVpZ2h0OmNhbGMoMS41ZW0gKyAuNzVyZW0pO3BhZGRpbmc6LjM3NXJlbSAuNzVyZW07bGluZS1oZWlnaHQ6MS41O2NvbG9yOiM0OTUwNTc7Y29udGVudDoiQnJvd3NlIjtiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWY7Ym9yZGVyLWxlZnQ6aW5oZXJpdDtib3JkZXItcmFkaXVzOjAgLjI1cmVtIC4yNXJlbSAwfS5jdXN0b20tcmFuZ2V7d2lkdGg6MTAwJTtoZWlnaHQ6MS40cmVtO3BhZGRpbmc6MDtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50Oy13ZWJraXQtYXBwZWFyYW5jZTpub25lOy1tb3otYXBwZWFyYW5jZTpub25lO2FwcGVhcmFuY2U6bm9uZX0uY3VzdG9tLXJhbmdlOmZvY3Vze291dGxpbmU6MH0uY3VzdG9tLXJhbmdlOmZvY3VzOjotd2Via2l0LXNsaWRlci10aHVtYntib3gtc2hhZG93OjAgMCAwIDFweCAjZmZmLDAgMCAwIC4ycmVtIHJnYmEoMCwxMjMsMjU1LC4yNSl9LmN1c3RvbS1yYW5nZTpmb2N1czo6LW1vei1yYW5nZS10aHVtYntib3gtc2hhZG93OjAgMCAwIDFweCAjZmZmLDAgMCAwIC4ycmVtIHJnYmEoMCwxMjMsMjU1LC4yNSl9LmN1c3RvbS1yYW5nZTpmb2N1czo6LW1zLXRodW1ie2JveC1zaGFkb3c6MCAwIDAgMXB4ICNmZmYsMCAwIDAgLjJyZW0gcmdiYSgwLDEyMywyNTUsLjI1KX0uY3VzdG9tLXJhbmdlOjotbW96LWZvY3VzLW91dGVye2JvcmRlcjowfS5jdXN0b20tcmFuZ2U6Oi13ZWJraXQtc2xpZGVyLXRodW1ie3dpZHRoOjFyZW07aGVpZ2h0OjFyZW07bWFyZ2luLXRvcDotLjI1cmVtO2JhY2tncm91bmQtY29sb3I6IzAwN2JmZjtib3JkZXI6MDtib3JkZXItcmFkaXVzOjFyZW07LXdlYmtpdC10cmFuc2l0aW9uOmJhY2tncm91bmQtY29sb3IgLjE1cyBlYXNlLWluLW91dCxib3JkZXItY29sb3IgLjE1cyBlYXNlLWluLW91dCxib3gtc2hhZG93IC4xNXMgZWFzZS1pbi1vdXQ7dHJhbnNpdGlvbjpiYWNrZ3JvdW5kLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm9yZGVyLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm94LXNoYWRvdyAuMTVzIGVhc2UtaW4tb3V0Oy13ZWJraXQtYXBwZWFyYW5jZTpub25lO2FwcGVhcmFuY2U6bm9uZX1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuY3VzdG9tLXJhbmdlOjotd2Via2l0LXNsaWRlci10aHVtYnstd2Via2l0LXRyYW5zaXRpb246bm9uZTt0cmFuc2l0aW9uOm5vbmV9fS5jdXN0b20tcmFuZ2U6Oi13ZWJraXQtc2xpZGVyLXRodW1iOmFjdGl2ZXtiYWNrZ3JvdW5kLWNvbG9yOiNiM2Q3ZmZ9LmN1c3RvbS1yYW5nZTo6LXdlYmtpdC1zbGlkZXItcnVubmFibGUtdHJhY2t7d2lkdGg6MTAwJTtoZWlnaHQ6LjVyZW07Y29sb3I6dHJhbnNwYXJlbnQ7Y3Vyc29yOnBvaW50ZXI7YmFja2dyb3VuZC1jb2xvcjojZGVlMmU2O2JvcmRlci1jb2xvcjp0cmFuc3BhcmVudDtib3JkZXItcmFkaXVzOjFyZW19LmN1c3RvbS1yYW5nZTo6LW1vei1yYW5nZS10aHVtYnt3aWR0aDoxcmVtO2hlaWdodDoxcmVtO2JhY2tncm91bmQtY29sb3I6IzAwN2JmZjtib3JkZXI6MDtib3JkZXItcmFkaXVzOjFyZW07LW1vei10cmFuc2l0aW9uOmJhY2tncm91bmQtY29sb3IgLjE1cyBlYXNlLWluLW91dCxib3JkZXItY29sb3IgLjE1cyBlYXNlLWluLW91dCxib3gtc2hhZG93IC4xNXMgZWFzZS1pbi1vdXQ7dHJhbnNpdGlvbjpiYWNrZ3JvdW5kLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm9yZGVyLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm94LXNoYWRvdyAuMTVzIGVhc2UtaW4tb3V0Oy1tb3otYXBwZWFyYW5jZTpub25lO2FwcGVhcmFuY2U6bm9uZX1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuY3VzdG9tLXJhbmdlOjotbW96LXJhbmdlLXRodW1iey1tb3otdHJhbnNpdGlvbjpub25lO3RyYW5zaXRpb246bm9uZX19LmN1c3RvbS1yYW5nZTo6LW1vei1yYW5nZS10aHVtYjphY3RpdmV7YmFja2dyb3VuZC1jb2xvcjojYjNkN2ZmfS5jdXN0b20tcmFuZ2U6Oi1tb3otcmFuZ2UtdHJhY2t7d2lkdGg6MTAwJTtoZWlnaHQ6LjVyZW07Y29sb3I6dHJhbnNwYXJlbnQ7Y3Vyc29yOnBvaW50ZXI7YmFja2dyb3VuZC1jb2xvcjojZGVlMmU2O2JvcmRlci1jb2xvcjp0cmFuc3BhcmVudDtib3JkZXItcmFkaXVzOjFyZW19LmN1c3RvbS1yYW5nZTo6LW1zLXRodW1ie3dpZHRoOjFyZW07aGVpZ2h0OjFyZW07bWFyZ2luLXRvcDowO21hcmdpbi1yaWdodDouMnJlbTttYXJnaW4tbGVmdDouMnJlbTtiYWNrZ3JvdW5kLWNvbG9yOiMwMDdiZmY7Ym9yZGVyOjA7Ym9yZGVyLXJhZGl1czoxcmVtOy1tcy10cmFuc2l0aW9uOmJhY2tncm91bmQtY29sb3IgLjE1cyBlYXNlLWluLW91dCxib3JkZXItY29sb3IgLjE1cyBlYXNlLWluLW91dCxib3gtc2hhZG93IC4xNXMgZWFzZS1pbi1vdXQ7dHJhbnNpdGlvbjpiYWNrZ3JvdW5kLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm9yZGVyLWNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYm94LXNoYWRvdyAuMTVzIGVhc2UtaW4tb3V0O2FwcGVhcmFuY2U6bm9uZX1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuY3VzdG9tLXJhbmdlOjotbXMtdGh1bWJ7LW1zLXRyYW5zaXRpb246bm9uZTt0cmFuc2l0aW9uOm5vbmV9fS5jdXN0b20tcmFuZ2U6Oi1tcy10aHVtYjphY3RpdmV7YmFja2dyb3VuZC1jb2xvcjojYjNkN2ZmfS5jdXN0b20tcmFuZ2U6Oi1tcy10cmFja3t3aWR0aDoxMDAlO2hlaWdodDouNXJlbTtjb2xvcjp0cmFuc3BhcmVudDtjdXJzb3I6cG9pbnRlcjtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlci1jb2xvcjp0cmFuc3BhcmVudDtib3JkZXItd2lkdGg6LjVyZW19LmN1c3RvbS1yYW5nZTo6LW1zLWZpbGwtbG93ZXJ7YmFja2dyb3VuZC1jb2xvcjojZGVlMmU2O2JvcmRlci1yYWRpdXM6MXJlbX0uY3VzdG9tLXJhbmdlOjotbXMtZmlsbC11cHBlcnttYXJnaW4tcmlnaHQ6MTVweDtiYWNrZ3JvdW5kLWNvbG9yOiNkZWUyZTY7Ym9yZGVyLXJhZGl1czoxcmVtfS5jdXN0b20tcmFuZ2U6ZGlzYWJsZWQ6Oi13ZWJraXQtc2xpZGVyLXRodW1ie2JhY2tncm91bmQtY29sb3I6I2FkYjViZH0uY3VzdG9tLXJhbmdlOmRpc2FibGVkOjotd2Via2l0LXNsaWRlci1ydW5uYWJsZS10cmFja3tjdXJzb3I6ZGVmYXVsdH0uY3VzdG9tLXJhbmdlOmRpc2FibGVkOjotbW96LXJhbmdlLXRodW1ie2JhY2tncm91bmQtY29sb3I6I2FkYjViZH0uY3VzdG9tLXJhbmdlOmRpc2FibGVkOjotbW96LXJhbmdlLXRyYWNre2N1cnNvcjpkZWZhdWx0fS5jdXN0b20tcmFuZ2U6ZGlzYWJsZWQ6Oi1tcy10aHVtYntiYWNrZ3JvdW5kLWNvbG9yOiNhZGI1YmR9LmN1c3RvbS1jb250cm9sLWxhYmVsOjpiZWZvcmUsLmN1c3RvbS1maWxlLWxhYmVsLC5jdXN0b20tc2VsZWN0e3RyYW5zaXRpb246YmFja2dyb3VuZC1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJvcmRlci1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJveC1zaGFkb3cgLjE1cyBlYXNlLWluLW91dH1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuY3VzdG9tLWNvbnRyb2wtbGFiZWw6OmJlZm9yZSwuY3VzdG9tLWZpbGUtbGFiZWwsLmN1c3RvbS1zZWxlY3R7dHJhbnNpdGlvbjpub25lfX0ubmF2e2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LXdyYXA6d3JhcDtmbGV4LXdyYXA6d3JhcDtwYWRkaW5nLWxlZnQ6MDttYXJnaW4tYm90dG9tOjA7bGlzdC1zdHlsZTpub25lfS5uYXYtbGlua3tkaXNwbGF5OmJsb2NrO3BhZGRpbmc6LjVyZW0gMXJlbX0ubmF2LWxpbms6Zm9jdXMsLm5hdi1saW5rOmhvdmVye3RleHQtZGVjb3JhdGlvbjpub25lfS5uYXYtbGluay5kaXNhYmxlZHtjb2xvcjojNmM3NTdkO3BvaW50ZXItZXZlbnRzOm5vbmU7Y3Vyc29yOmRlZmF1bHR9Lm5hdi10YWJze2JvcmRlci1ib3R0b206MXB4IHNvbGlkICNkZWUyZTZ9Lm5hdi10YWJzIC5uYXYtaXRlbXttYXJnaW4tYm90dG9tOi0xcHh9Lm5hdi10YWJzIC5uYXYtbGlua3tib3JkZXI6MXB4IHNvbGlkIHRyYW5zcGFyZW50O2JvcmRlci10b3AtbGVmdC1yYWRpdXM6LjI1cmVtO2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOi4yNXJlbX0ubmF2LXRhYnMgLm5hdi1saW5rOmZvY3VzLC5uYXYtdGFicyAubmF2LWxpbms6aG92ZXJ7Ym9yZGVyLWNvbG9yOiNlOWVjZWYgI2U5ZWNlZiAjZGVlMmU2fS5uYXYtdGFicyAubmF2LWxpbmsuZGlzYWJsZWR7Y29sb3I6IzZjNzU3ZDtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlci1jb2xvcjp0cmFuc3BhcmVudH0ubmF2LXRhYnMgLm5hdi1pdGVtLnNob3cgLm5hdi1saW5rLC5uYXYtdGFicyAubmF2LWxpbmsuYWN0aXZle2NvbG9yOiM0OTUwNTc7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlci1jb2xvcjojZGVlMmU2ICNkZWUyZTYgI2ZmZn0ubmF2LXRhYnMgLmRyb3Bkb3duLW1lbnV7bWFyZ2luLXRvcDotMXB4O2JvcmRlci10b3AtbGVmdC1yYWRpdXM6MDtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czowfS5uYXYtcGlsbHMgLm5hdi1saW5re2JvcmRlci1yYWRpdXM6LjI1cmVtfS5uYXYtcGlsbHMgLm5hdi1saW5rLmFjdGl2ZSwubmF2LXBpbGxzIC5zaG93Pi5uYXYtbGlua3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzAwN2JmZn0ubmF2LWZpbGwgLm5hdi1pdGVtey1tcy1mbGV4OjEgMSBhdXRvO2ZsZXg6MSAxIGF1dG87dGV4dC1hbGlnbjpjZW50ZXJ9Lm5hdi1qdXN0aWZpZWQgLm5hdi1pdGVtey1tcy1mbGV4LXByZWZlcnJlZC1zaXplOjA7ZmxleC1iYXNpczowOy1tcy1mbGV4LXBvc2l0aXZlOjE7ZmxleC1ncm93OjE7dGV4dC1hbGlnbjpjZW50ZXJ9LnRhYi1jb250ZW50Pi50YWItcGFuZXtkaXNwbGF5Om5vbmV9LnRhYi1jb250ZW50Pi5hY3RpdmV7ZGlzcGxheTpibG9ja30ubmF2YmFye3Bvc2l0aW9uOnJlbGF0aXZlO2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LXdyYXA6d3JhcDtmbGV4LXdyYXA6d3JhcDstbXMtZmxleC1hbGlnbjpjZW50ZXI7YWxpZ24taXRlbXM6Y2VudGVyOy1tcy1mbGV4LXBhY2s6anVzdGlmeTtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYmV0d2VlbjtwYWRkaW5nOi41cmVtIDFyZW19Lm5hdmJhciAuY29udGFpbmVyLC5uYXZiYXIgLmNvbnRhaW5lci1mbHVpZCwubmF2YmFyIC5jb250YWluZXItbGcsLm5hdmJhciAuY29udGFpbmVyLW1kLC5uYXZiYXIgLmNvbnRhaW5lci1zbSwubmF2YmFyIC5jb250YWluZXIteGx7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtd3JhcDp3cmFwO2ZsZXgtd3JhcDp3cmFwOy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7LW1zLWZsZXgtcGFjazpqdXN0aWZ5O2p1c3RpZnktY29udGVudDpzcGFjZS1iZXR3ZWVufS5uYXZiYXItYnJhbmR7ZGlzcGxheTppbmxpbmUtYmxvY2s7cGFkZGluZy10b3A6LjMxMjVyZW07cGFkZGluZy1ib3R0b206LjMxMjVyZW07bWFyZ2luLXJpZ2h0OjFyZW07Zm9udC1zaXplOjEuMjVyZW07bGluZS1oZWlnaHQ6aW5oZXJpdDt3aGl0ZS1zcGFjZTpub3dyYXB9Lm5hdmJhci1icmFuZDpmb2N1cywubmF2YmFyLWJyYW5kOmhvdmVye3RleHQtZGVjb3JhdGlvbjpub25lfS5uYXZiYXItbmF2e2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW47ZmxleC1kaXJlY3Rpb246Y29sdW1uO3BhZGRpbmctbGVmdDowO21hcmdpbi1ib3R0b206MDtsaXN0LXN0eWxlOm5vbmV9Lm5hdmJhci1uYXYgLm5hdi1saW5re3BhZGRpbmctcmlnaHQ6MDtwYWRkaW5nLWxlZnQ6MH0ubmF2YmFyLW5hdiAuZHJvcGRvd24tbWVudXtwb3NpdGlvbjpzdGF0aWM7ZmxvYXQ6bm9uZX0ubmF2YmFyLXRleHR7ZGlzcGxheTppbmxpbmUtYmxvY2s7cGFkZGluZy10b3A6LjVyZW07cGFkZGluZy1ib3R0b206LjVyZW19Lm5hdmJhci1jb2xsYXBzZXstbXMtZmxleC1wcmVmZXJyZWQtc2l6ZToxMDAlO2ZsZXgtYmFzaXM6MTAwJTstbXMtZmxleC1wb3NpdGl2ZToxO2ZsZXgtZ3JvdzoxOy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXJ9Lm5hdmJhci10b2dnbGVye3BhZGRpbmc6LjI1cmVtIC43NXJlbTtmb250LXNpemU6MS4yNXJlbTtsaW5lLWhlaWdodDoxO2JhY2tncm91bmQtY29sb3I6dHJhbnNwYXJlbnQ7Ym9yZGVyOjFweCBzb2xpZCB0cmFuc3BhcmVudDtib3JkZXItcmFkaXVzOi4yNXJlbX0ubmF2YmFyLXRvZ2dsZXI6Zm9jdXMsLm5hdmJhci10b2dnbGVyOmhvdmVye3RleHQtZGVjb3JhdGlvbjpub25lfS5uYXZiYXItdG9nZ2xlci1pY29ue2Rpc3BsYXk6aW5saW5lLWJsb2NrO3dpZHRoOjEuNWVtO2hlaWdodDoxLjVlbTt2ZXJ0aWNhbC1hbGlnbjptaWRkbGU7Y29udGVudDoiIjtiYWNrZ3JvdW5kOm5vLXJlcGVhdCBjZW50ZXIgY2VudGVyO2JhY2tncm91bmQtc2l6ZToxMDAlIDEwMCV9QG1lZGlhIChtYXgtd2lkdGg6NTc1Ljk4cHgpey5uYXZiYXItZXhwYW5kLXNtPi5jb250YWluZXIsLm5hdmJhci1leHBhbmQtc20+LmNvbnRhaW5lci1mbHVpZCwubmF2YmFyLWV4cGFuZC1zbT4uY29udGFpbmVyLWxnLC5uYXZiYXItZXhwYW5kLXNtPi5jb250YWluZXItbWQsLm5hdmJhci1leHBhbmQtc20+LmNvbnRhaW5lci1zbSwubmF2YmFyLWV4cGFuZC1zbT4uY29udGFpbmVyLXhse3BhZGRpbmctcmlnaHQ6MDtwYWRkaW5nLWxlZnQ6MH19QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey5uYXZiYXItZXhwYW5kLXNtey1tcy1mbGV4LWZsb3c6cm93IG5vd3JhcDtmbGV4LWZsb3c6cm93IG5vd3JhcDstbXMtZmxleC1wYWNrOnN0YXJ0O2p1c3RpZnktY29udGVudDpmbGV4LXN0YXJ0fS5uYXZiYXItZXhwYW5kLXNtIC5uYXZiYXItbmF2ey1tcy1mbGV4LWRpcmVjdGlvbjpyb3c7ZmxleC1kaXJlY3Rpb246cm93fS5uYXZiYXItZXhwYW5kLXNtIC5uYXZiYXItbmF2IC5kcm9wZG93bi1tZW51e3Bvc2l0aW9uOmFic29sdXRlfS5uYXZiYXItZXhwYW5kLXNtIC5uYXZiYXItbmF2IC5uYXYtbGlua3twYWRkaW5nLXJpZ2h0Oi41cmVtO3BhZGRpbmctbGVmdDouNXJlbX0ubmF2YmFyLWV4cGFuZC1zbT4uY29udGFpbmVyLC5uYXZiYXItZXhwYW5kLXNtPi5jb250YWluZXItZmx1aWQsLm5hdmJhci1leHBhbmQtc20+LmNvbnRhaW5lci1sZywubmF2YmFyLWV4cGFuZC1zbT4uY29udGFpbmVyLW1kLC5uYXZiYXItZXhwYW5kLXNtPi5jb250YWluZXItc20sLm5hdmJhci1leHBhbmQtc20+LmNvbnRhaW5lci14bHstbXMtZmxleC13cmFwOm5vd3JhcDtmbGV4LXdyYXA6bm93cmFwfS5uYXZiYXItZXhwYW5kLXNtIC5uYXZiYXItY29sbGFwc2V7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudDstbXMtZmxleC1wcmVmZXJyZWQtc2l6ZTphdXRvO2ZsZXgtYmFzaXM6YXV0b30ubmF2YmFyLWV4cGFuZC1zbSAubmF2YmFyLXRvZ2dsZXJ7ZGlzcGxheTpub25lfX1AbWVkaWEgKG1heC13aWR0aDo3NjcuOThweCl7Lm5hdmJhci1leHBhbmQtbWQ+LmNvbnRhaW5lciwubmF2YmFyLWV4cGFuZC1tZD4uY29udGFpbmVyLWZsdWlkLC5uYXZiYXItZXhwYW5kLW1kPi5jb250YWluZXItbGcsLm5hdmJhci1leHBhbmQtbWQ+LmNvbnRhaW5lci1tZCwubmF2YmFyLWV4cGFuZC1tZD4uY29udGFpbmVyLXNtLC5uYXZiYXItZXhwYW5kLW1kPi5jb250YWluZXIteGx7cGFkZGluZy1yaWdodDowO3BhZGRpbmctbGVmdDowfX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7Lm5hdmJhci1leHBhbmQtbWR7LW1zLWZsZXgtZmxvdzpyb3cgbm93cmFwO2ZsZXgtZmxvdzpyb3cgbm93cmFwOy1tcy1mbGV4LXBhY2s6c3RhcnQ7anVzdGlmeS1jb250ZW50OmZsZXgtc3RhcnR9Lm5hdmJhci1leHBhbmQtbWQgLm5hdmJhci1uYXZ7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdztmbGV4LWRpcmVjdGlvbjpyb3d9Lm5hdmJhci1leHBhbmQtbWQgLm5hdmJhci1uYXYgLmRyb3Bkb3duLW1lbnV7cG9zaXRpb246YWJzb2x1dGV9Lm5hdmJhci1leHBhbmQtbWQgLm5hdmJhci1uYXYgLm5hdi1saW5re3BhZGRpbmctcmlnaHQ6LjVyZW07cGFkZGluZy1sZWZ0Oi41cmVtfS5uYXZiYXItZXhwYW5kLW1kPi5jb250YWluZXIsLm5hdmJhci1leHBhbmQtbWQ+LmNvbnRhaW5lci1mbHVpZCwubmF2YmFyLWV4cGFuZC1tZD4uY29udGFpbmVyLWxnLC5uYXZiYXItZXhwYW5kLW1kPi5jb250YWluZXItbWQsLm5hdmJhci1leHBhbmQtbWQ+LmNvbnRhaW5lci1zbSwubmF2YmFyLWV4cGFuZC1tZD4uY29udGFpbmVyLXhsey1tcy1mbGV4LXdyYXA6bm93cmFwO2ZsZXgtd3JhcDpub3dyYXB9Lm5hdmJhci1leHBhbmQtbWQgLm5hdmJhci1jb2xsYXBzZXtkaXNwbGF5Oi1tcy1mbGV4Ym94IWltcG9ydGFudDtkaXNwbGF5OmZsZXghaW1wb3J0YW50Oy1tcy1mbGV4LXByZWZlcnJlZC1zaXplOmF1dG87ZmxleC1iYXNpczphdXRvfS5uYXZiYXItZXhwYW5kLW1kIC5uYXZiYXItdG9nZ2xlcntkaXNwbGF5Om5vbmV9fUBtZWRpYSAobWF4LXdpZHRoOjk5MS45OHB4KXsubmF2YmFyLWV4cGFuZC1sZz4uY29udGFpbmVyLC5uYXZiYXItZXhwYW5kLWxnPi5jb250YWluZXItZmx1aWQsLm5hdmJhci1leHBhbmQtbGc+LmNvbnRhaW5lci1sZywubmF2YmFyLWV4cGFuZC1sZz4uY29udGFpbmVyLW1kLC5uYXZiYXItZXhwYW5kLWxnPi5jb250YWluZXItc20sLm5hdmJhci1leHBhbmQtbGc+LmNvbnRhaW5lci14bHtwYWRkaW5nLXJpZ2h0OjA7cGFkZGluZy1sZWZ0OjB9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsubmF2YmFyLWV4cGFuZC1sZ3stbXMtZmxleC1mbG93OnJvdyBub3dyYXA7ZmxleC1mbG93OnJvdyBub3dyYXA7LW1zLWZsZXgtcGFjazpzdGFydDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1zdGFydH0ubmF2YmFyLWV4cGFuZC1sZyAubmF2YmFyLW5hdnstbXMtZmxleC1kaXJlY3Rpb246cm93O2ZsZXgtZGlyZWN0aW9uOnJvd30ubmF2YmFyLWV4cGFuZC1sZyAubmF2YmFyLW5hdiAuZHJvcGRvd24tbWVudXtwb3NpdGlvbjphYnNvbHV0ZX0ubmF2YmFyLWV4cGFuZC1sZyAubmF2YmFyLW5hdiAubmF2LWxpbmt7cGFkZGluZy1yaWdodDouNXJlbTtwYWRkaW5nLWxlZnQ6LjVyZW19Lm5hdmJhci1leHBhbmQtbGc+LmNvbnRhaW5lciwubmF2YmFyLWV4cGFuZC1sZz4uY29udGFpbmVyLWZsdWlkLC5uYXZiYXItZXhwYW5kLWxnPi5jb250YWluZXItbGcsLm5hdmJhci1leHBhbmQtbGc+LmNvbnRhaW5lci1tZCwubmF2YmFyLWV4cGFuZC1sZz4uY29udGFpbmVyLXNtLC5uYXZiYXItZXhwYW5kLWxnPi5jb250YWluZXIteGx7LW1zLWZsZXgtd3JhcDpub3dyYXA7ZmxleC13cmFwOm5vd3JhcH0ubmF2YmFyLWV4cGFuZC1sZyAubmF2YmFyLWNvbGxhcHNle2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnQ7LW1zLWZsZXgtcHJlZmVycmVkLXNpemU6YXV0bztmbGV4LWJhc2lzOmF1dG99Lm5hdmJhci1leHBhbmQtbGcgLm5hdmJhci10b2dnbGVye2Rpc3BsYXk6bm9uZX19QG1lZGlhIChtYXgtd2lkdGg6MTE5OS45OHB4KXsubmF2YmFyLWV4cGFuZC14bD4uY29udGFpbmVyLC5uYXZiYXItZXhwYW5kLXhsPi5jb250YWluZXItZmx1aWQsLm5hdmJhci1leHBhbmQteGw+LmNvbnRhaW5lci1sZywubmF2YmFyLWV4cGFuZC14bD4uY29udGFpbmVyLW1kLC5uYXZiYXItZXhwYW5kLXhsPi5jb250YWluZXItc20sLm5hdmJhci1leHBhbmQteGw+LmNvbnRhaW5lci14bHtwYWRkaW5nLXJpZ2h0OjA7cGFkZGluZy1sZWZ0OjB9fUBtZWRpYSAobWluLXdpZHRoOjEyMDBweCl7Lm5hdmJhci1leHBhbmQteGx7LW1zLWZsZXgtZmxvdzpyb3cgbm93cmFwO2ZsZXgtZmxvdzpyb3cgbm93cmFwOy1tcy1mbGV4LXBhY2s6c3RhcnQ7anVzdGlmeS1jb250ZW50OmZsZXgtc3RhcnR9Lm5hdmJhci1leHBhbmQteGwgLm5hdmJhci1uYXZ7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdztmbGV4LWRpcmVjdGlvbjpyb3d9Lm5hdmJhci1leHBhbmQteGwgLm5hdmJhci1uYXYgLmRyb3Bkb3duLW1lbnV7cG9zaXRpb246YWJzb2x1dGV9Lm5hdmJhci1leHBhbmQteGwgLm5hdmJhci1uYXYgLm5hdi1saW5re3BhZGRpbmctcmlnaHQ6LjVyZW07cGFkZGluZy1sZWZ0Oi41cmVtfS5uYXZiYXItZXhwYW5kLXhsPi5jb250YWluZXIsLm5hdmJhci1leHBhbmQteGw+LmNvbnRhaW5lci1mbHVpZCwubmF2YmFyLWV4cGFuZC14bD4uY29udGFpbmVyLWxnLC5uYXZiYXItZXhwYW5kLXhsPi5jb250YWluZXItbWQsLm5hdmJhci1leHBhbmQteGw+LmNvbnRhaW5lci1zbSwubmF2YmFyLWV4cGFuZC14bD4uY29udGFpbmVyLXhsey1tcy1mbGV4LXdyYXA6bm93cmFwO2ZsZXgtd3JhcDpub3dyYXB9Lm5hdmJhci1leHBhbmQteGwgLm5hdmJhci1jb2xsYXBzZXtkaXNwbGF5Oi1tcy1mbGV4Ym94IWltcG9ydGFudDtkaXNwbGF5OmZsZXghaW1wb3J0YW50Oy1tcy1mbGV4LXByZWZlcnJlZC1zaXplOmF1dG87ZmxleC1iYXNpczphdXRvfS5uYXZiYXItZXhwYW5kLXhsIC5uYXZiYXItdG9nZ2xlcntkaXNwbGF5Om5vbmV9fS5uYXZiYXItZXhwYW5key1tcy1mbGV4LWZsb3c6cm93IG5vd3JhcDtmbGV4LWZsb3c6cm93IG5vd3JhcDstbXMtZmxleC1wYWNrOnN0YXJ0O2p1c3RpZnktY29udGVudDpmbGV4LXN0YXJ0fS5uYXZiYXItZXhwYW5kPi5jb250YWluZXIsLm5hdmJhci1leHBhbmQ+LmNvbnRhaW5lci1mbHVpZCwubmF2YmFyLWV4cGFuZD4uY29udGFpbmVyLWxnLC5uYXZiYXItZXhwYW5kPi5jb250YWluZXItbWQsLm5hdmJhci1leHBhbmQ+LmNvbnRhaW5lci1zbSwubmF2YmFyLWV4cGFuZD4uY29udGFpbmVyLXhse3BhZGRpbmctcmlnaHQ6MDtwYWRkaW5nLWxlZnQ6MH0ubmF2YmFyLWV4cGFuZCAubmF2YmFyLW5hdnstbXMtZmxleC1kaXJlY3Rpb246cm93O2ZsZXgtZGlyZWN0aW9uOnJvd30ubmF2YmFyLWV4cGFuZCAubmF2YmFyLW5hdiAuZHJvcGRvd24tbWVudXtwb3NpdGlvbjphYnNvbHV0ZX0ubmF2YmFyLWV4cGFuZCAubmF2YmFyLW5hdiAubmF2LWxpbmt7cGFkZGluZy1yaWdodDouNXJlbTtwYWRkaW5nLWxlZnQ6LjVyZW19Lm5hdmJhci1leHBhbmQ+LmNvbnRhaW5lciwubmF2YmFyLWV4cGFuZD4uY29udGFpbmVyLWZsdWlkLC5uYXZiYXItZXhwYW5kPi5jb250YWluZXItbGcsLm5hdmJhci1leHBhbmQ+LmNvbnRhaW5lci1tZCwubmF2YmFyLWV4cGFuZD4uY29udGFpbmVyLXNtLC5uYXZiYXItZXhwYW5kPi5jb250YWluZXIteGx7LW1zLWZsZXgtd3JhcDpub3dyYXA7ZmxleC13cmFwOm5vd3JhcH0ubmF2YmFyLWV4cGFuZCAubmF2YmFyLWNvbGxhcHNle2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnQ7LW1zLWZsZXgtcHJlZmVycmVkLXNpemU6YXV0bztmbGV4LWJhc2lzOmF1dG99Lm5hdmJhci1leHBhbmQgLm5hdmJhci10b2dnbGVye2Rpc3BsYXk6bm9uZX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItYnJhbmR7Y29sb3I6cmdiYSgwLDAsMCwuOSl9Lm5hdmJhci1saWdodCAubmF2YmFyLWJyYW5kOmZvY3VzLC5uYXZiYXItbGlnaHQgLm5hdmJhci1icmFuZDpob3Zlcntjb2xvcjpyZ2JhKDAsMCwwLC45KX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItbmF2IC5uYXYtbGlua3tjb2xvcjpyZ2JhKDAsMCwwLC41KX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItbmF2IC5uYXYtbGluazpmb2N1cywubmF2YmFyLWxpZ2h0IC5uYXZiYXItbmF2IC5uYXYtbGluazpob3Zlcntjb2xvcjpyZ2JhKDAsMCwwLC43KX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItbmF2IC5uYXYtbGluay5kaXNhYmxlZHtjb2xvcjpyZ2JhKDAsMCwwLC4zKX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItbmF2IC5hY3RpdmU+Lm5hdi1saW5rLC5uYXZiYXItbGlnaHQgLm5hdmJhci1uYXYgLm5hdi1saW5rLmFjdGl2ZSwubmF2YmFyLWxpZ2h0IC5uYXZiYXItbmF2IC5uYXYtbGluay5zaG93LC5uYXZiYXItbGlnaHQgLm5hdmJhci1uYXYgLnNob3c+Lm5hdi1saW5re2NvbG9yOnJnYmEoMCwwLDAsLjkpfS5uYXZiYXItbGlnaHQgLm5hdmJhci10b2dnbGVye2NvbG9yOnJnYmEoMCwwLDAsLjUpO2JvcmRlci1jb2xvcjpyZ2JhKDAsMCwwLC4xKX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItdG9nZ2xlci1pY29ue2JhY2tncm91bmQtaW1hZ2U6dXJsKCJkYXRhOmltYWdlL3N2Zyt4bWwsJTNjc3ZnIHhtbG5zPSdodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2Zycgd2lkdGg9JzMwJyBoZWlnaHQ9JzMwJyB2aWV3Qm94PScwIDAgMzAgMzAnJTNlJTNjcGF0aCBzdHJva2U9J3JnYmEoMCwgMCwgMCwgMC41KScgc3Ryb2tlLWxpbmVjYXA9J3JvdW5kJyBzdHJva2UtbWl0ZXJsaW1pdD0nMTAnIHN0cm9rZS13aWR0aD0nMicgZD0nTTQgN2gyMk00IDE1aDIyTTQgMjNoMjInLyUzZSUzYy9zdmclM2UiKX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItdGV4dHtjb2xvcjpyZ2JhKDAsMCwwLC41KX0ubmF2YmFyLWxpZ2h0IC5uYXZiYXItdGV4dCBhe2NvbG9yOnJnYmEoMCwwLDAsLjkpfS5uYXZiYXItbGlnaHQgLm5hdmJhci10ZXh0IGE6Zm9jdXMsLm5hdmJhci1saWdodCAubmF2YmFyLXRleHQgYTpob3Zlcntjb2xvcjpyZ2JhKDAsMCwwLC45KX0ubmF2YmFyLWRhcmsgLm5hdmJhci1icmFuZHtjb2xvcjojZmZmfS5uYXZiYXItZGFyayAubmF2YmFyLWJyYW5kOmZvY3VzLC5uYXZiYXItZGFyayAubmF2YmFyLWJyYW5kOmhvdmVye2NvbG9yOiNmZmZ9Lm5hdmJhci1kYXJrIC5uYXZiYXItbmF2IC5uYXYtbGlua3tjb2xvcjpyZ2JhKDI1NSwyNTUsMjU1LC41KX0ubmF2YmFyLWRhcmsgLm5hdmJhci1uYXYgLm5hdi1saW5rOmZvY3VzLC5uYXZiYXItZGFyayAubmF2YmFyLW5hdiAubmF2LWxpbms6aG92ZXJ7Y29sb3I6cmdiYSgyNTUsMjU1LDI1NSwuNzUpfS5uYXZiYXItZGFyayAubmF2YmFyLW5hdiAubmF2LWxpbmsuZGlzYWJsZWR7Y29sb3I6cmdiYSgyNTUsMjU1LDI1NSwuMjUpfS5uYXZiYXItZGFyayAubmF2YmFyLW5hdiAuYWN0aXZlPi5uYXYtbGluaywubmF2YmFyLWRhcmsgLm5hdmJhci1uYXYgLm5hdi1saW5rLmFjdGl2ZSwubmF2YmFyLWRhcmsgLm5hdmJhci1uYXYgLm5hdi1saW5rLnNob3csLm5hdmJhci1kYXJrIC5uYXZiYXItbmF2IC5zaG93Pi5uYXYtbGlua3tjb2xvcjojZmZmfS5uYXZiYXItZGFyayAubmF2YmFyLXRvZ2dsZXJ7Y29sb3I6cmdiYSgyNTUsMjU1LDI1NSwuNSk7Ym9yZGVyLWNvbG9yOnJnYmEoMjU1LDI1NSwyNTUsLjEpfS5uYXZiYXItZGFyayAubmF2YmFyLXRvZ2dsZXItaWNvbntiYWNrZ3JvdW5kLWltYWdlOnVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSczMCcgaGVpZ2h0PSczMCcgdmlld0JveD0nMCAwIDMwIDMwJyUzZSUzY3BhdGggc3Ryb2tlPSdyZ2JhKDI1NSwgMjU1LCAyNTUsIDAuNSknIHN0cm9rZS1saW5lY2FwPSdyb3VuZCcgc3Ryb2tlLW1pdGVybGltaXQ9JzEwJyBzdHJva2Utd2lkdGg9JzInIGQ9J000IDdoMjJNNCAxNWgyMk00IDIzaDIyJy8lM2UlM2Mvc3ZnJTNlIil9Lm5hdmJhci1kYXJrIC5uYXZiYXItdGV4dHtjb2xvcjpyZ2JhKDI1NSwyNTUsMjU1LC41KX0ubmF2YmFyLWRhcmsgLm5hdmJhci10ZXh0IGF7Y29sb3I6I2ZmZn0ubmF2YmFyLWRhcmsgLm5hdmJhci10ZXh0IGE6Zm9jdXMsLm5hdmJhci1kYXJrIC5uYXZiYXItdGV4dCBhOmhvdmVye2NvbG9yOiNmZmZ9LmNhcmR7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbjtmbGV4LWRpcmVjdGlvbjpjb2x1bW47bWluLXdpZHRoOjA7d29yZC13cmFwOmJyZWFrLXdvcmQ7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JhY2tncm91bmQtY2xpcDpib3JkZXItYm94O2JvcmRlcjoxcHggc29saWQgcmdiYSgwLDAsMCwuMTI1KTtib3JkZXItcmFkaXVzOi4yNXJlbX0uY2FyZD5ocnttYXJnaW4tcmlnaHQ6MDttYXJnaW4tbGVmdDowfS5jYXJkPi5saXN0LWdyb3VwOmZpcnN0LWNoaWxkIC5saXN0LWdyb3VwLWl0ZW06Zmlyc3QtY2hpbGR7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czouMjVyZW07Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6LjI1cmVtfS5jYXJkPi5saXN0LWdyb3VwOmxhc3QtY2hpbGQgLmxpc3QtZ3JvdXAtaXRlbTpsYXN0LWNoaWxke2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOi4yNXJlbTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOi4yNXJlbX0uY2FyZC1ib2R5ey1tcy1mbGV4OjEgMSBhdXRvO2ZsZXg6MSAxIGF1dG87bWluLWhlaWdodDoxcHg7cGFkZGluZzoxLjI1cmVtfS5jYXJkLXRpdGxle21hcmdpbi1ib3R0b206Ljc1cmVtfS5jYXJkLXN1YnRpdGxle21hcmdpbi10b3A6LS4zNzVyZW07bWFyZ2luLWJvdHRvbTowfS5jYXJkLXRleHQ6bGFzdC1jaGlsZHttYXJnaW4tYm90dG9tOjB9LmNhcmQtbGluazpob3Zlcnt0ZXh0LWRlY29yYXRpb246bm9uZX0uY2FyZC1saW5rKy5jYXJkLWxpbmt7bWFyZ2luLWxlZnQ6MS4yNXJlbX0uY2FyZC1oZWFkZXJ7cGFkZGluZzouNzVyZW0gMS4yNXJlbTttYXJnaW4tYm90dG9tOjA7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLC4wMyk7Ym9yZGVyLWJvdHRvbToxcHggc29saWQgcmdiYSgwLDAsMCwuMTI1KX0uY2FyZC1oZWFkZXI6Zmlyc3QtY2hpbGR7Ym9yZGVyLXJhZGl1czpjYWxjKC4yNXJlbSAtIDFweCkgY2FsYyguMjVyZW0gLSAxcHgpIDAgMH0uY2FyZC1oZWFkZXIrLmxpc3QtZ3JvdXAgLmxpc3QtZ3JvdXAtaXRlbTpmaXJzdC1jaGlsZHtib3JkZXItdG9wOjB9LmNhcmQtZm9vdGVye3BhZGRpbmc6Ljc1cmVtIDEuMjVyZW07YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLC4wMyk7Ym9yZGVyLXRvcDoxcHggc29saWQgcmdiYSgwLDAsMCwuMTI1KX0uY2FyZC1mb290ZXI6bGFzdC1jaGlsZHtib3JkZXItcmFkaXVzOjAgMCBjYWxjKC4yNXJlbSAtIDFweCkgY2FsYyguMjVyZW0gLSAxcHgpfS5jYXJkLWhlYWRlci10YWJze21hcmdpbi1yaWdodDotLjYyNXJlbTttYXJnaW4tYm90dG9tOi0uNzVyZW07bWFyZ2luLWxlZnQ6LS42MjVyZW07Ym9yZGVyLWJvdHRvbTowfS5jYXJkLWhlYWRlci1waWxsc3ttYXJnaW4tcmlnaHQ6LS42MjVyZW07bWFyZ2luLWxlZnQ6LS42MjVyZW19LmNhcmQtaW1nLW92ZXJsYXl7cG9zaXRpb246YWJzb2x1dGU7dG9wOjA7cmlnaHQ6MDtib3R0b206MDtsZWZ0OjA7cGFkZGluZzoxLjI1cmVtfS5jYXJkLWltZywuY2FyZC1pbWctYm90dG9tLC5jYXJkLWltZy10b3B7LW1zLWZsZXgtbmVnYXRpdmU6MDtmbGV4LXNocmluazowO3dpZHRoOjEwMCV9LmNhcmQtaW1nLC5jYXJkLWltZy10b3B7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czpjYWxjKC4yNXJlbSAtIDFweCk7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6Y2FsYyguMjVyZW0gLSAxcHgpfS5jYXJkLWltZywuY2FyZC1pbWctYm90dG9te2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOmNhbGMoLjI1cmVtIC0gMXB4KTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOmNhbGMoLjI1cmVtIC0gMXB4KX0uY2FyZC1kZWNrIC5jYXJke21hcmdpbi1ib3R0b206MTVweH1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmNhcmQtZGVja3tkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC1mbG93OnJvdyB3cmFwO2ZsZXgtZmxvdzpyb3cgd3JhcDttYXJnaW4tcmlnaHQ6LTE1cHg7bWFyZ2luLWxlZnQ6LTE1cHh9LmNhcmQtZGVjayAuY2FyZHstbXMtZmxleDoxIDAgMCU7ZmxleDoxIDAgMCU7bWFyZ2luLXJpZ2h0OjE1cHg7bWFyZ2luLWJvdHRvbTowO21hcmdpbi1sZWZ0OjE1cHh9fS5jYXJkLWdyb3VwPi5jYXJke21hcmdpbi1ib3R0b206MTVweH1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmNhcmQtZ3JvdXB7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtZmxvdzpyb3cgd3JhcDtmbGV4LWZsb3c6cm93IHdyYXB9LmNhcmQtZ3JvdXA+LmNhcmR7LW1zLWZsZXg6MSAwIDAlO2ZsZXg6MSAwIDAlO21hcmdpbi1ib3R0b206MH0uY2FyZC1ncm91cD4uY2FyZCsuY2FyZHttYXJnaW4tbGVmdDowO2JvcmRlci1sZWZ0OjB9LmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpsYXN0LWNoaWxkKXtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czowO2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjB9LmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpsYXN0LWNoaWxkKSAuY2FyZC1oZWFkZXIsLmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpsYXN0LWNoaWxkKSAuY2FyZC1pbWctdG9we2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOjB9LmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpsYXN0LWNoaWxkKSAuY2FyZC1mb290ZXIsLmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpsYXN0LWNoaWxkKSAuY2FyZC1pbWctYm90dG9te2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjB9LmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpmaXJzdC1jaGlsZCl7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czowO2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6MH0uY2FyZC1ncm91cD4uY2FyZDpub3QoOmZpcnN0LWNoaWxkKSAuY2FyZC1oZWFkZXIsLmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpmaXJzdC1jaGlsZCkgLmNhcmQtaW1nLXRvcHtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjB9LmNhcmQtZ3JvdXA+LmNhcmQ6bm90KDpmaXJzdC1jaGlsZCkgLmNhcmQtZm9vdGVyLC5jYXJkLWdyb3VwPi5jYXJkOm5vdCg6Zmlyc3QtY2hpbGQpIC5jYXJkLWltZy1ib3R0b217Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czowfX0uY2FyZC1jb2x1bW5zIC5jYXJke21hcmdpbi1ib3R0b206Ljc1cmVtfUBtZWRpYSAobWluLXdpZHRoOjU3NnB4KXsuY2FyZC1jb2x1bW5zey13ZWJraXQtY29sdW1uLWNvdW50OjM7LW1vei1jb2x1bW4tY291bnQ6Mztjb2x1bW4tY291bnQ6Mzstd2Via2l0LWNvbHVtbi1nYXA6MS4yNXJlbTstbW96LWNvbHVtbi1nYXA6MS4yNXJlbTtjb2x1bW4tZ2FwOjEuMjVyZW07b3JwaGFuczoxO3dpZG93czoxfS5jYXJkLWNvbHVtbnMgLmNhcmR7ZGlzcGxheTppbmxpbmUtYmxvY2s7d2lkdGg6MTAwJX19LmFjY29yZGlvbj4uY2FyZHtvdmVyZmxvdzpoaWRkZW59LmFjY29yZGlvbj4uY2FyZDpub3QoOmxhc3Qtb2YtdHlwZSl7Ym9yZGVyLWJvdHRvbTowO2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjA7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czowfS5hY2NvcmRpb24+LmNhcmQ6bm90KDpmaXJzdC1vZi10eXBlKXtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjA7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6MH0uYWNjb3JkaW9uPi5jYXJkPi5jYXJkLWhlYWRlcntib3JkZXItcmFkaXVzOjA7bWFyZ2luLWJvdHRvbTotMXB4fS5icmVhZGNydW1ie2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LXdyYXA6d3JhcDtmbGV4LXdyYXA6d3JhcDtwYWRkaW5nOi43NXJlbSAxcmVtO21hcmdpbi1ib3R0b206MXJlbTtsaXN0LXN0eWxlOm5vbmU7YmFja2dyb3VuZC1jb2xvcjojZTllY2VmO2JvcmRlci1yYWRpdXM6LjI1cmVtfS5icmVhZGNydW1iLWl0ZW0rLmJyZWFkY3J1bWItaXRlbXtwYWRkaW5nLWxlZnQ6LjVyZW19LmJyZWFkY3J1bWItaXRlbSsuYnJlYWRjcnVtYi1pdGVtOjpiZWZvcmV7ZGlzcGxheTppbmxpbmUtYmxvY2s7cGFkZGluZy1yaWdodDouNXJlbTtjb2xvcjojNmM3NTdkO2NvbnRlbnQ6Ii8ifS5icmVhZGNydW1iLWl0ZW0rLmJyZWFkY3J1bWItaXRlbTpob3Zlcjo6YmVmb3Jle3RleHQtZGVjb3JhdGlvbjp1bmRlcmxpbmV9LmJyZWFkY3J1bWItaXRlbSsuYnJlYWRjcnVtYi1pdGVtOmhvdmVyOjpiZWZvcmV7dGV4dC1kZWNvcmF0aW9uOm5vbmV9LmJyZWFkY3J1bWItaXRlbS5hY3RpdmV7Y29sb3I6IzZjNzU3ZH0ucGFnaW5hdGlvbntkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDtwYWRkaW5nLWxlZnQ6MDtsaXN0LXN0eWxlOm5vbmU7Ym9yZGVyLXJhZGl1czouMjVyZW19LnBhZ2UtbGlua3twb3NpdGlvbjpyZWxhdGl2ZTtkaXNwbGF5OmJsb2NrO3BhZGRpbmc6LjVyZW0gLjc1cmVtO21hcmdpbi1sZWZ0Oi0xcHg7bGluZS1oZWlnaHQ6MS4yNTtjb2xvcjojMDA3YmZmO2JhY2tncm91bmQtY29sb3I6I2ZmZjtib3JkZXI6MXB4IHNvbGlkICNkZWUyZTZ9LnBhZ2UtbGluazpob3Zlcnt6LWluZGV4OjI7Y29sb3I6IzAwNTZiMzt0ZXh0LWRlY29yYXRpb246bm9uZTtiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWY7Ym9yZGVyLWNvbG9yOiNkZWUyZTZ9LnBhZ2UtbGluazpmb2N1c3t6LWluZGV4OjM7b3V0bGluZTowO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgwLDEyMywyNTUsLjI1KX0ucGFnZS1pdGVtOmZpcnN0LWNoaWxkIC5wYWdlLWxpbmt7bWFyZ2luLWxlZnQ6MDtib3JkZXItdG9wLWxlZnQtcmFkaXVzOi4yNXJlbTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOi4yNXJlbX0ucGFnZS1pdGVtOmxhc3QtY2hpbGQgLnBhZ2UtbGlua3tib3JkZXItdG9wLXJpZ2h0LXJhZGl1czouMjVyZW07Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6LjI1cmVtfS5wYWdlLWl0ZW0uYWN0aXZlIC5wYWdlLWxpbmt7ei1pbmRleDozO2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmO2JvcmRlci1jb2xvcjojMDA3YmZmfS5wYWdlLWl0ZW0uZGlzYWJsZWQgLnBhZ2UtbGlua3tjb2xvcjojNmM3NTdkO3BvaW50ZXItZXZlbnRzOm5vbmU7Y3Vyc29yOmF1dG87YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlci1jb2xvcjojZGVlMmU2fS5wYWdpbmF0aW9uLWxnIC5wYWdlLWxpbmt7cGFkZGluZzouNzVyZW0gMS41cmVtO2ZvbnQtc2l6ZToxLjI1cmVtO2xpbmUtaGVpZ2h0OjEuNX0ucGFnaW5hdGlvbi1sZyAucGFnZS1pdGVtOmZpcnN0LWNoaWxkIC5wYWdlLWxpbmt7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czouM3JlbTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOi4zcmVtfS5wYWdpbmF0aW9uLWxnIC5wYWdlLWl0ZW06bGFzdC1jaGlsZCAucGFnZS1saW5re2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOi4zcmVtO2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOi4zcmVtfS5wYWdpbmF0aW9uLXNtIC5wYWdlLWxpbmt7cGFkZGluZzouMjVyZW0gLjVyZW07Zm9udC1zaXplOi44NzVyZW07bGluZS1oZWlnaHQ6MS41fS5wYWdpbmF0aW9uLXNtIC5wYWdlLWl0ZW06Zmlyc3QtY2hpbGQgLnBhZ2UtbGlua3tib3JkZXItdG9wLWxlZnQtcmFkaXVzOi4ycmVtO2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6LjJyZW19LnBhZ2luYXRpb24tc20gLnBhZ2UtaXRlbTpsYXN0LWNoaWxkIC5wYWdlLWxpbmt7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6LjJyZW07Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6LjJyZW19LmJhZGdle2Rpc3BsYXk6aW5saW5lLWJsb2NrO3BhZGRpbmc6LjI1ZW0gLjRlbTtmb250LXNpemU6NzUlO2ZvbnQtd2VpZ2h0OjcwMDtsaW5lLWhlaWdodDoxO3RleHQtYWxpZ246Y2VudGVyO3doaXRlLXNwYWNlOm5vd3JhcDt2ZXJ0aWNhbC1hbGlnbjpiYXNlbGluZTtib3JkZXItcmFkaXVzOi4yNXJlbTt0cmFuc2l0aW9uOmNvbG9yIC4xNXMgZWFzZS1pbi1vdXQsYmFja2dyb3VuZC1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJvcmRlci1jb2xvciAuMTVzIGVhc2UtaW4tb3V0LGJveC1zaGFkb3cgLjE1cyBlYXNlLWluLW91dH1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuYmFkZ2V7dHJhbnNpdGlvbjpub25lfX1hLmJhZGdlOmZvY3VzLGEuYmFkZ2U6aG92ZXJ7dGV4dC1kZWNvcmF0aW9uOm5vbmV9LmJhZGdlOmVtcHR5e2Rpc3BsYXk6bm9uZX0uYnRuIC5iYWRnZXtwb3NpdGlvbjpyZWxhdGl2ZTt0b3A6LTFweH0uYmFkZ2UtcGlsbHtwYWRkaW5nLXJpZ2h0Oi42ZW07cGFkZGluZy1sZWZ0Oi42ZW07Ym9yZGVyLXJhZGl1czoxMHJlbX0uYmFkZ2UtcHJpbWFyeXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzAwN2JmZn1hLmJhZGdlLXByaW1hcnk6Zm9jdXMsYS5iYWRnZS1wcmltYXJ5OmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMDA2MmNjfWEuYmFkZ2UtcHJpbWFyeS5mb2N1cyxhLmJhZGdlLXByaW1hcnk6Zm9jdXN7b3V0bGluZTowO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgwLDEyMywyNTUsLjUpfS5iYWRnZS1zZWNvbmRhcnl7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM2Yzc1N2R9YS5iYWRnZS1zZWNvbmRhcnk6Zm9jdXMsYS5iYWRnZS1zZWNvbmRhcnk6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM1NDViNjJ9YS5iYWRnZS1zZWNvbmRhcnkuZm9jdXMsYS5iYWRnZS1zZWNvbmRhcnk6Zm9jdXN7b3V0bGluZTowO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgxMDgsMTE3LDEyNSwuNSl9LmJhZGdlLXN1Y2Nlc3N7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMyOGE3NDV9YS5iYWRnZS1zdWNjZXNzOmZvY3VzLGEuYmFkZ2Utc3VjY2Vzczpob3Zlcntjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzFlN2UzNH1hLmJhZGdlLXN1Y2Nlc3MuZm9jdXMsYS5iYWRnZS1zdWNjZXNzOmZvY3Vze291dGxpbmU6MDtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoNDAsMTY3LDY5LC41KX0uYmFkZ2UtaW5mb3tjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzE3YTJiOH1hLmJhZGdlLWluZm86Zm9jdXMsYS5iYWRnZS1pbmZvOmhvdmVye2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMTE3YThifWEuYmFkZ2UtaW5mby5mb2N1cyxhLmJhZGdlLWluZm86Zm9jdXN7b3V0bGluZTowO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMywxNjIsMTg0LC41KX0uYmFkZ2Utd2FybmluZ3tjb2xvcjojMjEyNTI5O2JhY2tncm91bmQtY29sb3I6I2ZmYzEwN31hLmJhZGdlLXdhcm5pbmc6Zm9jdXMsYS5iYWRnZS13YXJuaW5nOmhvdmVye2NvbG9yOiMyMTI1Mjk7YmFja2dyb3VuZC1jb2xvcjojZDM5ZTAwfWEuYmFkZ2Utd2FybmluZy5mb2N1cyxhLmJhZGdlLXdhcm5pbmc6Zm9jdXN7b3V0bGluZTowO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyNTUsMTkzLDcsLjUpfS5iYWRnZS1kYW5nZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiNkYzM1NDV9YS5iYWRnZS1kYW5nZXI6Zm9jdXMsYS5iYWRnZS1kYW5nZXI6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiNiZDIxMzB9YS5iYWRnZS1kYW5nZXIuZm9jdXMsYS5iYWRnZS1kYW5nZXI6Zm9jdXN7b3V0bGluZTowO2JveC1zaGFkb3c6MCAwIDAgLjJyZW0gcmdiYSgyMjAsNTMsNjksLjUpfS5iYWRnZS1saWdodHtjb2xvcjojMjEyNTI5O2JhY2tncm91bmQtY29sb3I6I2Y4ZjlmYX1hLmJhZGdlLWxpZ2h0OmZvY3VzLGEuYmFkZ2UtbGlnaHQ6aG92ZXJ7Y29sb3I6IzIxMjUyOTtiYWNrZ3JvdW5kLWNvbG9yOiNkYWUwZTV9YS5iYWRnZS1saWdodC5mb2N1cyxhLmJhZGdlLWxpZ2h0OmZvY3Vze291dGxpbmU6MDtib3gtc2hhZG93OjAgMCAwIC4ycmVtIHJnYmEoMjQ4LDI0OSwyNTAsLjUpfS5iYWRnZS1kYXJre2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMzQzYTQwfWEuYmFkZ2UtZGFyazpmb2N1cyxhLmJhZGdlLWRhcms6aG92ZXJ7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMxZDIxMjR9YS5iYWRnZS1kYXJrLmZvY3VzLGEuYmFkZ2UtZGFyazpmb2N1c3tvdXRsaW5lOjA7Ym94LXNoYWRvdzowIDAgMCAuMnJlbSByZ2JhKDUyLDU4LDY0LC41KX0uanVtYm90cm9ue3BhZGRpbmc6MnJlbSAxcmVtO21hcmdpbi1ib3R0b206MnJlbTtiYWNrZ3JvdW5kLWNvbG9yOiNlOWVjZWY7Ym9yZGVyLXJhZGl1czouM3JlbX1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7Lmp1bWJvdHJvbntwYWRkaW5nOjRyZW0gMnJlbX19Lmp1bWJvdHJvbi1mbHVpZHtwYWRkaW5nLXJpZ2h0OjA7cGFkZGluZy1sZWZ0OjA7Ym9yZGVyLXJhZGl1czowfS5hbGVydHtwb3NpdGlvbjpyZWxhdGl2ZTtwYWRkaW5nOi43NXJlbSAxLjI1cmVtO21hcmdpbi1ib3R0b206MXJlbTtib3JkZXI6MXB4IHNvbGlkIHRyYW5zcGFyZW50O2JvcmRlci1yYWRpdXM6LjI1cmVtfS5hbGVydC1oZWFkaW5ne2NvbG9yOmluaGVyaXR9LmFsZXJ0LWxpbmt7Zm9udC13ZWlnaHQ6NzAwfS5hbGVydC1kaXNtaXNzaWJsZXtwYWRkaW5nLXJpZ2h0OjRyZW19LmFsZXJ0LWRpc21pc3NpYmxlIC5jbG9zZXtwb3NpdGlvbjphYnNvbHV0ZTt0b3A6MDtyaWdodDowO3BhZGRpbmc6Ljc1cmVtIDEuMjVyZW07Y29sb3I6aW5oZXJpdH0uYWxlcnQtcHJpbWFyeXtjb2xvcjojMDA0MDg1O2JhY2tncm91bmQtY29sb3I6I2NjZTVmZjtib3JkZXItY29sb3I6I2I4ZGFmZn0uYWxlcnQtcHJpbWFyeSBocntib3JkZXItdG9wLWNvbG9yOiM5ZmNkZmZ9LmFsZXJ0LXByaW1hcnkgLmFsZXJ0LWxpbmt7Y29sb3I6IzAwMjc1Mn0uYWxlcnQtc2Vjb25kYXJ5e2NvbG9yOiMzODNkNDE7YmFja2dyb3VuZC1jb2xvcjojZTJlM2U1O2JvcmRlci1jb2xvcjojZDZkOGRifS5hbGVydC1zZWNvbmRhcnkgaHJ7Ym9yZGVyLXRvcC1jb2xvcjojYzhjYmNmfS5hbGVydC1zZWNvbmRhcnkgLmFsZXJ0LWxpbmt7Y29sb3I6IzIwMjMyNn0uYWxlcnQtc3VjY2Vzc3tjb2xvcjojMTU1NzI0O2JhY2tncm91bmQtY29sb3I6I2Q0ZWRkYTtib3JkZXItY29sb3I6I2MzZTZjYn0uYWxlcnQtc3VjY2VzcyBocntib3JkZXItdG9wLWNvbG9yOiNiMWRmYmJ9LmFsZXJ0LXN1Y2Nlc3MgLmFsZXJ0LWxpbmt7Y29sb3I6IzBiMmUxM30uYWxlcnQtaW5mb3tjb2xvcjojMGM1NDYwO2JhY2tncm91bmQtY29sb3I6I2QxZWNmMTtib3JkZXItY29sb3I6I2JlZTVlYn0uYWxlcnQtaW5mbyBocntib3JkZXItdG9wLWNvbG9yOiNhYmRkZTV9LmFsZXJ0LWluZm8gLmFsZXJ0LWxpbmt7Y29sb3I6IzA2MmMzM30uYWxlcnQtd2FybmluZ3tjb2xvcjojODU2NDA0O2JhY2tncm91bmQtY29sb3I6I2ZmZjNjZDtib3JkZXItY29sb3I6I2ZmZWViYX0uYWxlcnQtd2FybmluZyBocntib3JkZXItdG9wLWNvbG9yOiNmZmU4YTF9LmFsZXJ0LXdhcm5pbmcgLmFsZXJ0LWxpbmt7Y29sb3I6IzUzM2YwM30uYWxlcnQtZGFuZ2Vye2NvbG9yOiM3MjFjMjQ7YmFja2dyb3VuZC1jb2xvcjojZjhkN2RhO2JvcmRlci1jb2xvcjojZjVjNmNifS5hbGVydC1kYW5nZXIgaHJ7Ym9yZGVyLXRvcC1jb2xvcjojZjFiMGI3fS5hbGVydC1kYW5nZXIgLmFsZXJ0LWxpbmt7Y29sb3I6IzQ5MTIxN30uYWxlcnQtbGlnaHR7Y29sb3I6IzgxODE4MjtiYWNrZ3JvdW5kLWNvbG9yOiNmZWZlZmU7Ym9yZGVyLWNvbG9yOiNmZGZkZmV9LmFsZXJ0LWxpZ2h0IGhye2JvcmRlci10b3AtY29sb3I6I2VjZWNmNn0uYWxlcnQtbGlnaHQgLmFsZXJ0LWxpbmt7Y29sb3I6IzY4Njg2OH0uYWxlcnQtZGFya3tjb2xvcjojMWIxZTIxO2JhY2tncm91bmQtY29sb3I6I2Q2ZDhkOTtib3JkZXItY29sb3I6I2M2YzhjYX0uYWxlcnQtZGFyayBocntib3JkZXItdG9wLWNvbG9yOiNiOWJiYmV9LmFsZXJ0LWRhcmsgLmFsZXJ0LWxpbmt7Y29sb3I6IzA0MDUwNX1ALXdlYmtpdC1rZXlmcmFtZXMgcHJvZ3Jlc3MtYmFyLXN0cmlwZXN7ZnJvbXtiYWNrZ3JvdW5kLXBvc2l0aW9uOjFyZW0gMH10b3tiYWNrZ3JvdW5kLXBvc2l0aW9uOjAgMH19QGtleWZyYW1lcyBwcm9ncmVzcy1iYXItc3RyaXBlc3tmcm9te2JhY2tncm91bmQtcG9zaXRpb246MXJlbSAwfXRve2JhY2tncm91bmQtcG9zaXRpb246MCAwfX0ucHJvZ3Jlc3N7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7aGVpZ2h0OjFyZW07b3ZlcmZsb3c6aGlkZGVuO2ZvbnQtc2l6ZTouNzVyZW07YmFja2dyb3VuZC1jb2xvcjojZTllY2VmO2JvcmRlci1yYWRpdXM6LjI1cmVtfS5wcm9ncmVzcy1iYXJ7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbjtmbGV4LWRpcmVjdGlvbjpjb2x1bW47LW1zLWZsZXgtcGFjazpjZW50ZXI7anVzdGlmeS1jb250ZW50OmNlbnRlcjtvdmVyZmxvdzpoaWRkZW47Y29sb3I6I2ZmZjt0ZXh0LWFsaWduOmNlbnRlcjt3aGl0ZS1zcGFjZTpub3dyYXA7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmO3RyYW5zaXRpb246d2lkdGggLjZzIGVhc2V9QG1lZGlhIChwcmVmZXJzLXJlZHVjZWQtbW90aW9uOnJlZHVjZSl7LnByb2dyZXNzLWJhcnt0cmFuc2l0aW9uOm5vbmV9fS5wcm9ncmVzcy1iYXItc3RyaXBlZHtiYWNrZ3JvdW5kLWltYWdlOmxpbmVhci1ncmFkaWVudCg0NWRlZyxyZ2JhKDI1NSwyNTUsMjU1LC4xNSkgMjUlLHRyYW5zcGFyZW50IDI1JSx0cmFuc3BhcmVudCA1MCUscmdiYSgyNTUsMjU1LDI1NSwuMTUpIDUwJSxyZ2JhKDI1NSwyNTUsMjU1LC4xNSkgNzUlLHRyYW5zcGFyZW50IDc1JSx0cmFuc3BhcmVudCk7YmFja2dyb3VuZC1zaXplOjFyZW0gMXJlbX0ucHJvZ3Jlc3MtYmFyLWFuaW1hdGVkey13ZWJraXQtYW5pbWF0aW9uOnByb2dyZXNzLWJhci1zdHJpcGVzIDFzIGxpbmVhciBpbmZpbml0ZTthbmltYXRpb246cHJvZ3Jlc3MtYmFyLXN0cmlwZXMgMXMgbGluZWFyIGluZmluaXRlfUBtZWRpYSAocHJlZmVycy1yZWR1Y2VkLW1vdGlvbjpyZWR1Y2Upey5wcm9ncmVzcy1iYXItYW5pbWF0ZWR7LXdlYmtpdC1hbmltYXRpb246bm9uZTthbmltYXRpb246bm9uZX19Lm1lZGlhe2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWFsaWduOnN0YXJ0O2FsaWduLWl0ZW1zOmZsZXgtc3RhcnR9Lm1lZGlhLWJvZHl7LW1zLWZsZXg6MTtmbGV4OjF9Lmxpc3QtZ3JvdXB7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbjtmbGV4LWRpcmVjdGlvbjpjb2x1bW47cGFkZGluZy1sZWZ0OjA7bWFyZ2luLWJvdHRvbTowfS5saXN0LWdyb3VwLWl0ZW0tYWN0aW9ue3dpZHRoOjEwMCU7Y29sb3I6IzQ5NTA1Nzt0ZXh0LWFsaWduOmluaGVyaXR9Lmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246Zm9jdXMsLmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246aG92ZXJ7ei1pbmRleDoxO2NvbG9yOiM0OTUwNTc7dGV4dC1kZWNvcmF0aW9uOm5vbmU7YmFja2dyb3VuZC1jb2xvcjojZjhmOWZhfS5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmFjdGl2ZXtjb2xvcjojMjEyNTI5O2JhY2tncm91bmQtY29sb3I6I2U5ZWNlZn0ubGlzdC1ncm91cC1pdGVte3Bvc2l0aW9uOnJlbGF0aXZlO2Rpc3BsYXk6YmxvY2s7cGFkZGluZzouNzVyZW0gMS4yNXJlbTtiYWNrZ3JvdW5kLWNvbG9yOiNmZmY7Ym9yZGVyOjFweCBzb2xpZCByZ2JhKDAsMCwwLC4xMjUpfS5saXN0LWdyb3VwLWl0ZW06Zmlyc3QtY2hpbGR7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czouMjVyZW07Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6LjI1cmVtfS5saXN0LWdyb3VwLWl0ZW06bGFzdC1jaGlsZHtib3JkZXItYm90dG9tLXJpZ2h0LXJhZGl1czouMjVyZW07Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czouMjVyZW19Lmxpc3QtZ3JvdXAtaXRlbS5kaXNhYmxlZCwubGlzdC1ncm91cC1pdGVtOmRpc2FibGVke2NvbG9yOiM2Yzc1N2Q7cG9pbnRlci1ldmVudHM6bm9uZTtiYWNrZ3JvdW5kLWNvbG9yOiNmZmZ9Lmxpc3QtZ3JvdXAtaXRlbS5hY3RpdmV7ei1pbmRleDoyO2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmO2JvcmRlci1jb2xvcjojMDA3YmZmfS5saXN0LWdyb3VwLWl0ZW0rLmxpc3QtZ3JvdXAtaXRlbXtib3JkZXItdG9wLXdpZHRoOjB9Lmxpc3QtZ3JvdXAtaXRlbSsubGlzdC1ncm91cC1pdGVtLmFjdGl2ZXttYXJnaW4tdG9wOi0xcHg7Ym9yZGVyLXRvcC13aWR0aDoxcHh9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbHstbXMtZmxleC1kaXJlY3Rpb246cm93O2ZsZXgtZGlyZWN0aW9uOnJvd30ubGlzdC1ncm91cC1ob3Jpem9udGFsIC5saXN0LWdyb3VwLWl0ZW06Zmlyc3QtY2hpbGR7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czouMjVyZW07Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6MH0ubGlzdC1ncm91cC1ob3Jpem9udGFsIC5saXN0LWdyb3VwLWl0ZW06bGFzdC1jaGlsZHtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czouMjVyZW07Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czowfS5saXN0LWdyb3VwLWhvcml6b250YWwgLmxpc3QtZ3JvdXAtaXRlbS5hY3RpdmV7bWFyZ2luLXRvcDowfS5saXN0LWdyb3VwLWhvcml6b250YWwgLmxpc3QtZ3JvdXAtaXRlbSsubGlzdC1ncm91cC1pdGVte2JvcmRlci10b3Atd2lkdGg6MXB4O2JvcmRlci1sZWZ0LXdpZHRoOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbCAubGlzdC1ncm91cC1pdGVtKy5saXN0LWdyb3VwLWl0ZW0uYWN0aXZle21hcmdpbi1sZWZ0Oi0xcHg7Ym9yZGVyLWxlZnQtd2lkdGg6MXB4fUBtZWRpYSAobWluLXdpZHRoOjU3NnB4KXsubGlzdC1ncm91cC1ob3Jpem9udGFsLXNtey1tcy1mbGV4LWRpcmVjdGlvbjpyb3c7ZmxleC1kaXJlY3Rpb246cm93fS5saXN0LWdyb3VwLWhvcml6b250YWwtc20gLmxpc3QtZ3JvdXAtaXRlbTpmaXJzdC1jaGlsZHtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOi4yNXJlbTtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czowfS5saXN0LWdyb3VwLWhvcml6b250YWwtc20gLmxpc3QtZ3JvdXAtaXRlbTpsYXN0LWNoaWxke2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOi4yNXJlbTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC1zbSAubGlzdC1ncm91cC1pdGVtLmFjdGl2ZXttYXJnaW4tdG9wOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC1zbSAubGlzdC1ncm91cC1pdGVtKy5saXN0LWdyb3VwLWl0ZW17Ym9yZGVyLXRvcC13aWR0aDoxcHg7Ym9yZGVyLWxlZnQtd2lkdGg6MH0ubGlzdC1ncm91cC1ob3Jpem9udGFsLXNtIC5saXN0LWdyb3VwLWl0ZW0rLmxpc3QtZ3JvdXAtaXRlbS5hY3RpdmV7bWFyZ2luLWxlZnQ6LTFweDtib3JkZXItbGVmdC13aWR0aDoxcHh9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsubGlzdC1ncm91cC1ob3Jpem9udGFsLW1key1tcy1mbGV4LWRpcmVjdGlvbjpyb3c7ZmxleC1kaXJlY3Rpb246cm93fS5saXN0LWdyb3VwLWhvcml6b250YWwtbWQgLmxpc3QtZ3JvdXAtaXRlbTpmaXJzdC1jaGlsZHtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOi4yNXJlbTtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czowfS5saXN0LWdyb3VwLWhvcml6b250YWwtbWQgLmxpc3QtZ3JvdXAtaXRlbTpsYXN0LWNoaWxke2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOi4yNXJlbTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC1tZCAubGlzdC1ncm91cC1pdGVtLmFjdGl2ZXttYXJnaW4tdG9wOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC1tZCAubGlzdC1ncm91cC1pdGVtKy5saXN0LWdyb3VwLWl0ZW17Ym9yZGVyLXRvcC13aWR0aDoxcHg7Ym9yZGVyLWxlZnQtd2lkdGg6MH0ubGlzdC1ncm91cC1ob3Jpem9udGFsLW1kIC5saXN0LWdyb3VwLWl0ZW0rLmxpc3QtZ3JvdXAtaXRlbS5hY3RpdmV7bWFyZ2luLWxlZnQ6LTFweDtib3JkZXItbGVmdC13aWR0aDoxcHh9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsubGlzdC1ncm91cC1ob3Jpem9udGFsLWxney1tcy1mbGV4LWRpcmVjdGlvbjpyb3c7ZmxleC1kaXJlY3Rpb246cm93fS5saXN0LWdyb3VwLWhvcml6b250YWwtbGcgLmxpc3QtZ3JvdXAtaXRlbTpmaXJzdC1jaGlsZHtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOi4yNXJlbTtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czowfS5saXN0LWdyb3VwLWhvcml6b250YWwtbGcgLmxpc3QtZ3JvdXAtaXRlbTpsYXN0LWNoaWxke2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOi4yNXJlbTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC1sZyAubGlzdC1ncm91cC1pdGVtLmFjdGl2ZXttYXJnaW4tdG9wOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC1sZyAubGlzdC1ncm91cC1pdGVtKy5saXN0LWdyb3VwLWl0ZW17Ym9yZGVyLXRvcC13aWR0aDoxcHg7Ym9yZGVyLWxlZnQtd2lkdGg6MH0ubGlzdC1ncm91cC1ob3Jpem9udGFsLWxnIC5saXN0LWdyb3VwLWl0ZW0rLmxpc3QtZ3JvdXAtaXRlbS5hY3RpdmV7bWFyZ2luLWxlZnQ6LTFweDtib3JkZXItbGVmdC13aWR0aDoxcHh9fUBtZWRpYSAobWluLXdpZHRoOjEyMDBweCl7Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC14bHstbXMtZmxleC1kaXJlY3Rpb246cm93O2ZsZXgtZGlyZWN0aW9uOnJvd30ubGlzdC1ncm91cC1ob3Jpem9udGFsLXhsIC5saXN0LWdyb3VwLWl0ZW06Zmlyc3QtY2hpbGR7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czouMjVyZW07Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6MH0ubGlzdC1ncm91cC1ob3Jpem9udGFsLXhsIC5saXN0LWdyb3VwLWl0ZW06bGFzdC1jaGlsZHtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czouMjVyZW07Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czowfS5saXN0LWdyb3VwLWhvcml6b250YWwteGwgLmxpc3QtZ3JvdXAtaXRlbS5hY3RpdmV7bWFyZ2luLXRvcDowfS5saXN0LWdyb3VwLWhvcml6b250YWwteGwgLmxpc3QtZ3JvdXAtaXRlbSsubGlzdC1ncm91cC1pdGVte2JvcmRlci10b3Atd2lkdGg6MXB4O2JvcmRlci1sZWZ0LXdpZHRoOjB9Lmxpc3QtZ3JvdXAtaG9yaXpvbnRhbC14bCAubGlzdC1ncm91cC1pdGVtKy5saXN0LWdyb3VwLWl0ZW0uYWN0aXZle21hcmdpbi1sZWZ0Oi0xcHg7Ym9yZGVyLWxlZnQtd2lkdGg6MXB4fX0ubGlzdC1ncm91cC1mbHVzaCAubGlzdC1ncm91cC1pdGVte2JvcmRlci1yaWdodC13aWR0aDowO2JvcmRlci1sZWZ0LXdpZHRoOjA7Ym9yZGVyLXJhZGl1czowfS5saXN0LWdyb3VwLWZsdXNoIC5saXN0LWdyb3VwLWl0ZW06Zmlyc3QtY2hpbGR7Ym9yZGVyLXRvcC13aWR0aDowfS5saXN0LWdyb3VwLWZsdXNoOmxhc3QtY2hpbGQgLmxpc3QtZ3JvdXAtaXRlbTpsYXN0LWNoaWxke2JvcmRlci1ib3R0b20td2lkdGg6MH0ubGlzdC1ncm91cC1pdGVtLXByaW1hcnl7Y29sb3I6IzAwNDA4NTtiYWNrZ3JvdW5kLWNvbG9yOiNiOGRhZmZ9Lmxpc3QtZ3JvdXAtaXRlbS1wcmltYXJ5Lmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246Zm9jdXMsLmxpc3QtZ3JvdXAtaXRlbS1wcmltYXJ5Lmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246aG92ZXJ7Y29sb3I6IzAwNDA4NTtiYWNrZ3JvdW5kLWNvbG9yOiM5ZmNkZmZ9Lmxpc3QtZ3JvdXAtaXRlbS1wcmltYXJ5Lmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb24uYWN0aXZle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMDA0MDg1O2JvcmRlci1jb2xvcjojMDA0MDg1fS5saXN0LWdyb3VwLWl0ZW0tc2Vjb25kYXJ5e2NvbG9yOiMzODNkNDE7YmFja2dyb3VuZC1jb2xvcjojZDZkOGRifS5saXN0LWdyb3VwLWl0ZW0tc2Vjb25kYXJ5Lmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246Zm9jdXMsLmxpc3QtZ3JvdXAtaXRlbS1zZWNvbmRhcnkubGlzdC1ncm91cC1pdGVtLWFjdGlvbjpob3Zlcntjb2xvcjojMzgzZDQxO2JhY2tncm91bmQtY29sb3I6I2M4Y2JjZn0ubGlzdC1ncm91cC1pdGVtLXNlY29uZGFyeS5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uLmFjdGl2ZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzM4M2Q0MTtib3JkZXItY29sb3I6IzM4M2Q0MX0ubGlzdC1ncm91cC1pdGVtLXN1Y2Nlc3N7Y29sb3I6IzE1NTcyNDtiYWNrZ3JvdW5kLWNvbG9yOiNjM2U2Y2J9Lmxpc3QtZ3JvdXAtaXRlbS1zdWNjZXNzLmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246Zm9jdXMsLmxpc3QtZ3JvdXAtaXRlbS1zdWNjZXNzLmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246aG92ZXJ7Y29sb3I6IzE1NTcyNDtiYWNrZ3JvdW5kLWNvbG9yOiNiMWRmYmJ9Lmxpc3QtZ3JvdXAtaXRlbS1zdWNjZXNzLmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb24uYWN0aXZle2NvbG9yOiNmZmY7YmFja2dyb3VuZC1jb2xvcjojMTU1NzI0O2JvcmRlci1jb2xvcjojMTU1NzI0fS5saXN0LWdyb3VwLWl0ZW0taW5mb3tjb2xvcjojMGM1NDYwO2JhY2tncm91bmQtY29sb3I6I2JlZTVlYn0ubGlzdC1ncm91cC1pdGVtLWluZm8ubGlzdC1ncm91cC1pdGVtLWFjdGlvbjpmb2N1cywubGlzdC1ncm91cC1pdGVtLWluZm8ubGlzdC1ncm91cC1pdGVtLWFjdGlvbjpob3Zlcntjb2xvcjojMGM1NDYwO2JhY2tncm91bmQtY29sb3I6I2FiZGRlNX0ubGlzdC1ncm91cC1pdGVtLWluZm8ubGlzdC1ncm91cC1pdGVtLWFjdGlvbi5hY3RpdmV7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMwYzU0NjA7Ym9yZGVyLWNvbG9yOiMwYzU0NjB9Lmxpc3QtZ3JvdXAtaXRlbS13YXJuaW5ne2NvbG9yOiM4NTY0MDQ7YmFja2dyb3VuZC1jb2xvcjojZmZlZWJhfS5saXN0LWdyb3VwLWl0ZW0td2FybmluZy5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmZvY3VzLC5saXN0LWdyb3VwLWl0ZW0td2FybmluZy5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmhvdmVye2NvbG9yOiM4NTY0MDQ7YmFja2dyb3VuZC1jb2xvcjojZmZlOGExfS5saXN0LWdyb3VwLWl0ZW0td2FybmluZy5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uLmFjdGl2ZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6Izg1NjQwNDtib3JkZXItY29sb3I6Izg1NjQwNH0ubGlzdC1ncm91cC1pdGVtLWRhbmdlcntjb2xvcjojNzIxYzI0O2JhY2tncm91bmQtY29sb3I6I2Y1YzZjYn0ubGlzdC1ncm91cC1pdGVtLWRhbmdlci5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmZvY3VzLC5saXN0LWdyb3VwLWl0ZW0tZGFuZ2VyLmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246aG92ZXJ7Y29sb3I6IzcyMWMyNDtiYWNrZ3JvdW5kLWNvbG9yOiNmMWIwYjd9Lmxpc3QtZ3JvdXAtaXRlbS1kYW5nZXIubGlzdC1ncm91cC1pdGVtLWFjdGlvbi5hY3RpdmV7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM3MjFjMjQ7Ym9yZGVyLWNvbG9yOiM3MjFjMjR9Lmxpc3QtZ3JvdXAtaXRlbS1saWdodHtjb2xvcjojODE4MTgyO2JhY2tncm91bmQtY29sb3I6I2ZkZmRmZX0ubGlzdC1ncm91cC1pdGVtLWxpZ2h0Lmxpc3QtZ3JvdXAtaXRlbS1hY3Rpb246Zm9jdXMsLmxpc3QtZ3JvdXAtaXRlbS1saWdodC5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmhvdmVye2NvbG9yOiM4MTgxODI7YmFja2dyb3VuZC1jb2xvcjojZWNlY2Y2fS5saXN0LWdyb3VwLWl0ZW0tbGlnaHQubGlzdC1ncm91cC1pdGVtLWFjdGlvbi5hY3RpdmV7Y29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNvbG9yOiM4MTgxODI7Ym9yZGVyLWNvbG9yOiM4MTgxODJ9Lmxpc3QtZ3JvdXAtaXRlbS1kYXJre2NvbG9yOiMxYjFlMjE7YmFja2dyb3VuZC1jb2xvcjojYzZjOGNhfS5saXN0LWdyb3VwLWl0ZW0tZGFyay5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmZvY3VzLC5saXN0LWdyb3VwLWl0ZW0tZGFyay5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uOmhvdmVye2NvbG9yOiMxYjFlMjE7YmFja2dyb3VuZC1jb2xvcjojYjliYmJlfS5saXN0LWdyb3VwLWl0ZW0tZGFyay5saXN0LWdyb3VwLWl0ZW0tYWN0aW9uLmFjdGl2ZXtjb2xvcjojZmZmO2JhY2tncm91bmQtY29sb3I6IzFiMWUyMTtib3JkZXItY29sb3I6IzFiMWUyMX0uY2xvc2V7ZmxvYXQ6cmlnaHQ7Zm9udC1zaXplOjEuNXJlbTtmb250LXdlaWdodDo3MDA7bGluZS1oZWlnaHQ6MTtjb2xvcjojMDAwO3RleHQtc2hhZG93OjAgMXB4IDAgI2ZmZjtvcGFjaXR5Oi41fS5jbG9zZTpob3Zlcntjb2xvcjojMDAwO3RleHQtZGVjb3JhdGlvbjpub25lfS5jbG9zZTpub3QoOmRpc2FibGVkKTpub3QoLmRpc2FibGVkKTpmb2N1cywuY2xvc2U6bm90KDpkaXNhYmxlZCk6bm90KC5kaXNhYmxlZCk6aG92ZXJ7b3BhY2l0eTouNzV9YnV0dG9uLmNsb3Nle3BhZGRpbmc6MDtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlcjowOy13ZWJraXQtYXBwZWFyYW5jZTpub25lOy1tb3otYXBwZWFyYW5jZTpub25lO2FwcGVhcmFuY2U6bm9uZX1hLmNsb3NlLmRpc2FibGVke3BvaW50ZXItZXZlbnRzOm5vbmV9LnRvYXN0e21heC13aWR0aDozNTBweDtvdmVyZmxvdzpoaWRkZW47Zm9udC1zaXplOi44NzVyZW07YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDI1NSwyNTUsMjU1LC44NSk7YmFja2dyb3VuZC1jbGlwOnBhZGRpbmctYm94O2JvcmRlcjoxcHggc29saWQgcmdiYSgwLDAsMCwuMSk7Ym94LXNoYWRvdzowIC4yNXJlbSAuNzVyZW0gcmdiYSgwLDAsMCwuMSk7LXdlYmtpdC1iYWNrZHJvcC1maWx0ZXI6Ymx1cigxMHB4KTtiYWNrZHJvcC1maWx0ZXI6Ymx1cigxMHB4KTtvcGFjaXR5OjA7Ym9yZGVyLXJhZGl1czouMjVyZW19LnRvYXN0Om5vdCg6bGFzdC1jaGlsZCl7bWFyZ2luLWJvdHRvbTouNzVyZW19LnRvYXN0LnNob3dpbmd7b3BhY2l0eToxfS50b2FzdC5zaG93e2Rpc3BsYXk6YmxvY2s7b3BhY2l0eToxfS50b2FzdC5oaWRle2Rpc3BsYXk6bm9uZX0udG9hc3QtaGVhZGVye2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7cGFkZGluZzouMjVyZW0gLjc1cmVtO2NvbG9yOiM2Yzc1N2Q7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDI1NSwyNTUsMjU1LC44NSk7YmFja2dyb3VuZC1jbGlwOnBhZGRpbmctYm94O2JvcmRlci1ib3R0b206MXB4IHNvbGlkIHJnYmEoMCwwLDAsLjA1KX0udG9hc3QtYm9keXtwYWRkaW5nOi43NXJlbX0ubW9kYWwtb3BlbntvdmVyZmxvdzpoaWRkZW59Lm1vZGFsLW9wZW4gLm1vZGFse292ZXJmbG93LXg6aGlkZGVuO292ZXJmbG93LXk6YXV0b30ubW9kYWx7cG9zaXRpb246Zml4ZWQ7dG9wOjA7bGVmdDowO3otaW5kZXg6MTA1MDtkaXNwbGF5Om5vbmU7d2lkdGg6MTAwJTtoZWlnaHQ6MTAwJTtvdmVyZmxvdzpoaWRkZW47b3V0bGluZTowfS5tb2RhbC1kaWFsb2d7cG9zaXRpb246cmVsYXRpdmU7d2lkdGg6YXV0bzttYXJnaW46LjVyZW07cG9pbnRlci1ldmVudHM6bm9uZX0ubW9kYWwuZmFkZSAubW9kYWwtZGlhbG9ne3RyYW5zaXRpb246LXdlYmtpdC10cmFuc2Zvcm0gLjNzIGVhc2Utb3V0O3RyYW5zaXRpb246dHJhbnNmb3JtIC4zcyBlYXNlLW91dDt0cmFuc2l0aW9uOnRyYW5zZm9ybSAuM3MgZWFzZS1vdXQsLXdlYmtpdC10cmFuc2Zvcm0gLjNzIGVhc2Utb3V0Oy13ZWJraXQtdHJhbnNmb3JtOnRyYW5zbGF0ZSgwLC01MHB4KTt0cmFuc2Zvcm06dHJhbnNsYXRlKDAsLTUwcHgpfUBtZWRpYSAocHJlZmVycy1yZWR1Y2VkLW1vdGlvbjpyZWR1Y2Upey5tb2RhbC5mYWRlIC5tb2RhbC1kaWFsb2d7dHJhbnNpdGlvbjpub25lfX0ubW9kYWwuc2hvdyAubW9kYWwtZGlhbG9ney13ZWJraXQtdHJhbnNmb3JtOm5vbmU7dHJhbnNmb3JtOm5vbmV9Lm1vZGFsLm1vZGFsLXN0YXRpYyAubW9kYWwtZGlhbG9ney13ZWJraXQtdHJhbnNmb3JtOnNjYWxlKDEuMDIpO3RyYW5zZm9ybTpzY2FsZSgxLjAyKX0ubW9kYWwtZGlhbG9nLXNjcm9sbGFibGV7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7bWF4LWhlaWdodDpjYWxjKDEwMCUgLSAxcmVtKX0ubW9kYWwtZGlhbG9nLXNjcm9sbGFibGUgLm1vZGFsLWNvbnRlbnR7bWF4LWhlaWdodDpjYWxjKDEwMHZoIC0gMXJlbSk7b3ZlcmZsb3c6aGlkZGVufS5tb2RhbC1kaWFsb2ctc2Nyb2xsYWJsZSAubW9kYWwtZm9vdGVyLC5tb2RhbC1kaWFsb2ctc2Nyb2xsYWJsZSAubW9kYWwtaGVhZGVyey1tcy1mbGV4LW5lZ2F0aXZlOjA7ZmxleC1zaHJpbms6MH0ubW9kYWwtZGlhbG9nLXNjcm9sbGFibGUgLm1vZGFsLWJvZHl7b3ZlcmZsb3cteTphdXRvfS5tb2RhbC1kaWFsb2ctY2VudGVyZWR7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtYWxpZ246Y2VudGVyO2FsaWduLWl0ZW1zOmNlbnRlcjttaW4taGVpZ2h0OmNhbGMoMTAwJSAtIDFyZW0pfS5tb2RhbC1kaWFsb2ctY2VudGVyZWQ6OmJlZm9yZXtkaXNwbGF5OmJsb2NrO2hlaWdodDpjYWxjKDEwMHZoIC0gMXJlbSk7Y29udGVudDoiIn0ubW9kYWwtZGlhbG9nLWNlbnRlcmVkLm1vZGFsLWRpYWxvZy1zY3JvbGxhYmxley1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW47ZmxleC1kaXJlY3Rpb246Y29sdW1uOy1tcy1mbGV4LXBhY2s6Y2VudGVyO2p1c3RpZnktY29udGVudDpjZW50ZXI7aGVpZ2h0OjEwMCV9Lm1vZGFsLWRpYWxvZy1jZW50ZXJlZC5tb2RhbC1kaWFsb2ctc2Nyb2xsYWJsZSAubW9kYWwtY29udGVudHttYXgtaGVpZ2h0Om5vbmV9Lm1vZGFsLWRpYWxvZy1jZW50ZXJlZC5tb2RhbC1kaWFsb2ctc2Nyb2xsYWJsZTo6YmVmb3Jle2NvbnRlbnQ6bm9uZX0ubW9kYWwtY29udGVudHtwb3NpdGlvbjpyZWxhdGl2ZTtkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uO2ZsZXgtZGlyZWN0aW9uOmNvbHVtbjt3aWR0aDoxMDAlO3BvaW50ZXItZXZlbnRzOmF1dG87YmFja2dyb3VuZC1jb2xvcjojZmZmO2JhY2tncm91bmQtY2xpcDpwYWRkaW5nLWJveDtib3JkZXI6MXB4IHNvbGlkIHJnYmEoMCwwLDAsLjIpO2JvcmRlci1yYWRpdXM6LjNyZW07b3V0bGluZTowfS5tb2RhbC1iYWNrZHJvcHtwb3NpdGlvbjpmaXhlZDt0b3A6MDtsZWZ0OjA7ei1pbmRleDoxMDQwO3dpZHRoOjEwMHZ3O2hlaWdodDoxMDB2aDtiYWNrZ3JvdW5kLWNvbG9yOiMwMDB9Lm1vZGFsLWJhY2tkcm9wLmZhZGV7b3BhY2l0eTowfS5tb2RhbC1iYWNrZHJvcC5zaG93e29wYWNpdHk6LjV9Lm1vZGFsLWhlYWRlcntkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC1hbGlnbjpzdGFydDthbGlnbi1pdGVtczpmbGV4LXN0YXJ0Oy1tcy1mbGV4LXBhY2s6anVzdGlmeTtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYmV0d2VlbjtwYWRkaW5nOjFyZW0gMXJlbTtib3JkZXItYm90dG9tOjFweCBzb2xpZCAjZGVlMmU2O2JvcmRlci10b3AtbGVmdC1yYWRpdXM6Y2FsYyguM3JlbSAtIDFweCk7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6Y2FsYyguM3JlbSAtIDFweCl9Lm1vZGFsLWhlYWRlciAuY2xvc2V7cGFkZGluZzoxcmVtIDFyZW07bWFyZ2luOi0xcmVtIC0xcmVtIC0xcmVtIGF1dG99Lm1vZGFsLXRpdGxle21hcmdpbi1ib3R0b206MDtsaW5lLWhlaWdodDoxLjV9Lm1vZGFsLWJvZHl7cG9zaXRpb246cmVsYXRpdmU7LW1zLWZsZXg6MSAxIGF1dG87ZmxleDoxIDEgYXV0bztwYWRkaW5nOjFyZW19Lm1vZGFsLWZvb3RlcntkaXNwbGF5Oi1tcy1mbGV4Ym94O2Rpc3BsYXk6ZmxleDstbXMtZmxleC13cmFwOndyYXA7ZmxleC13cmFwOndyYXA7LW1zLWZsZXgtYWxpZ246Y2VudGVyO2FsaWduLWl0ZW1zOmNlbnRlcjstbXMtZmxleC1wYWNrOmVuZDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1lbmQ7cGFkZGluZzouNzVyZW07Ym9yZGVyLXRvcDoxcHggc29saWQgI2RlZTJlNjtib3JkZXItYm90dG9tLXJpZ2h0LXJhZGl1czpjYWxjKC4zcmVtIC0gMXB4KTtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOmNhbGMoLjNyZW0gLSAxcHgpfS5tb2RhbC1mb290ZXI+KnttYXJnaW46LjI1cmVtfS5tb2RhbC1zY3JvbGxiYXItbWVhc3VyZXtwb3NpdGlvbjphYnNvbHV0ZTt0b3A6LTk5OTlweDt3aWR0aDo1MHB4O2hlaWdodDo1MHB4O292ZXJmbG93OnNjcm9sbH1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7Lm1vZGFsLWRpYWxvZ3ttYXgtd2lkdGg6NTAwcHg7bWFyZ2luOjEuNzVyZW0gYXV0b30ubW9kYWwtZGlhbG9nLXNjcm9sbGFibGV7bWF4LWhlaWdodDpjYWxjKDEwMCUgLSAzLjVyZW0pfS5tb2RhbC1kaWFsb2ctc2Nyb2xsYWJsZSAubW9kYWwtY29udGVudHttYXgtaGVpZ2h0OmNhbGMoMTAwdmggLSAzLjVyZW0pfS5tb2RhbC1kaWFsb2ctY2VudGVyZWR7bWluLWhlaWdodDpjYWxjKDEwMCUgLSAzLjVyZW0pfS5tb2RhbC1kaWFsb2ctY2VudGVyZWQ6OmJlZm9yZXtoZWlnaHQ6Y2FsYygxMDB2aCAtIDMuNXJlbSl9Lm1vZGFsLXNte21heC13aWR0aDozMDBweH19QG1lZGlhIChtaW4td2lkdGg6OTkycHgpey5tb2RhbC1sZywubW9kYWwteGx7bWF4LXdpZHRoOjgwMHB4fX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5tb2RhbC14bHttYXgtd2lkdGg6MTE0MHB4fX0udG9vbHRpcHtwb3NpdGlvbjphYnNvbHV0ZTt6LWluZGV4OjEwNzA7ZGlzcGxheTpibG9jazttYXJnaW46MDtmb250LWZhbWlseTotYXBwbGUtc3lzdGVtLEJsaW5rTWFjU3lzdGVtRm9udCwiU2Vnb2UgVUkiLFJvYm90bywiSGVsdmV0aWNhIE5ldWUiLEFyaWFsLCJOb3RvIFNhbnMiLHNhbnMtc2VyaWYsIkFwcGxlIENvbG9yIEVtb2ppIiwiU2Vnb2UgVUkgRW1vamkiLCJTZWdvZSBVSSBTeW1ib2wiLCJOb3RvIENvbG9yIEVtb2ppIjtmb250LXN0eWxlOm5vcm1hbDtmb250LXdlaWdodDo0MDA7bGluZS1oZWlnaHQ6MS41O3RleHQtYWxpZ246bGVmdDt0ZXh0LWFsaWduOnN0YXJ0O3RleHQtZGVjb3JhdGlvbjpub25lO3RleHQtc2hhZG93Om5vbmU7dGV4dC10cmFuc2Zvcm06bm9uZTtsZXR0ZXItc3BhY2luZzpub3JtYWw7d29yZC1icmVhazpub3JtYWw7d29yZC1zcGFjaW5nOm5vcm1hbDt3aGl0ZS1zcGFjZTpub3JtYWw7bGluZS1icmVhazphdXRvO2ZvbnQtc2l6ZTouODc1cmVtO3dvcmQtd3JhcDpicmVhay13b3JkO29wYWNpdHk6MH0udG9vbHRpcC5zaG93e29wYWNpdHk6Ljl9LnRvb2x0aXAgLmFycm93e3Bvc2l0aW9uOmFic29sdXRlO2Rpc3BsYXk6YmxvY2s7d2lkdGg6LjhyZW07aGVpZ2h0Oi40cmVtfS50b29sdGlwIC5hcnJvdzo6YmVmb3Jle3Bvc2l0aW9uOmFic29sdXRlO2NvbnRlbnQ6IiI7Ym9yZGVyLWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlci1zdHlsZTpzb2xpZH0uYnMtdG9vbHRpcC1hdXRvW3gtcGxhY2VtZW50Xj10b3BdLC5icy10b29sdGlwLXRvcHtwYWRkaW5nOi40cmVtIDB9LmJzLXRvb2x0aXAtYXV0b1t4LXBsYWNlbWVudF49dG9wXSAuYXJyb3csLmJzLXRvb2x0aXAtdG9wIC5hcnJvd3tib3R0b206MH0uYnMtdG9vbHRpcC1hdXRvW3gtcGxhY2VtZW50Xj10b3BdIC5hcnJvdzo6YmVmb3JlLC5icy10b29sdGlwLXRvcCAuYXJyb3c6OmJlZm9yZXt0b3A6MDtib3JkZXItd2lkdGg6LjRyZW0gLjRyZW0gMDtib3JkZXItdG9wLWNvbG9yOiMwMDB9LmJzLXRvb2x0aXAtYXV0b1t4LXBsYWNlbWVudF49cmlnaHRdLC5icy10b29sdGlwLXJpZ2h0e3BhZGRpbmc6MCAuNHJlbX0uYnMtdG9vbHRpcC1hdXRvW3gtcGxhY2VtZW50Xj1yaWdodF0gLmFycm93LC5icy10b29sdGlwLXJpZ2h0IC5hcnJvd3tsZWZ0OjA7d2lkdGg6LjRyZW07aGVpZ2h0Oi44cmVtfS5icy10b29sdGlwLWF1dG9beC1wbGFjZW1lbnRePXJpZ2h0XSAuYXJyb3c6OmJlZm9yZSwuYnMtdG9vbHRpcC1yaWdodCAuYXJyb3c6OmJlZm9yZXtyaWdodDowO2JvcmRlci13aWR0aDouNHJlbSAuNHJlbSAuNHJlbSAwO2JvcmRlci1yaWdodC1jb2xvcjojMDAwfS5icy10b29sdGlwLWF1dG9beC1wbGFjZW1lbnRePWJvdHRvbV0sLmJzLXRvb2x0aXAtYm90dG9te3BhZGRpbmc6LjRyZW0gMH0uYnMtdG9vbHRpcC1hdXRvW3gtcGxhY2VtZW50Xj1ib3R0b21dIC5hcnJvdywuYnMtdG9vbHRpcC1ib3R0b20gLmFycm93e3RvcDowfS5icy10b29sdGlwLWF1dG9beC1wbGFjZW1lbnRePWJvdHRvbV0gLmFycm93OjpiZWZvcmUsLmJzLXRvb2x0aXAtYm90dG9tIC5hcnJvdzo6YmVmb3Jle2JvdHRvbTowO2JvcmRlci13aWR0aDowIC40cmVtIC40cmVtO2JvcmRlci1ib3R0b20tY29sb3I6IzAwMH0uYnMtdG9vbHRpcC1hdXRvW3gtcGxhY2VtZW50Xj1sZWZ0XSwuYnMtdG9vbHRpcC1sZWZ0e3BhZGRpbmc6MCAuNHJlbX0uYnMtdG9vbHRpcC1hdXRvW3gtcGxhY2VtZW50Xj1sZWZ0XSAuYXJyb3csLmJzLXRvb2x0aXAtbGVmdCAuYXJyb3d7cmlnaHQ6MDt3aWR0aDouNHJlbTtoZWlnaHQ6LjhyZW19LmJzLXRvb2x0aXAtYXV0b1t4LXBsYWNlbWVudF49bGVmdF0gLmFycm93OjpiZWZvcmUsLmJzLXRvb2x0aXAtbGVmdCAuYXJyb3c6OmJlZm9yZXtsZWZ0OjA7Ym9yZGVyLXdpZHRoOi40cmVtIDAgLjRyZW0gLjRyZW07Ym9yZGVyLWxlZnQtY29sb3I6IzAwMH0udG9vbHRpcC1pbm5lcnttYXgtd2lkdGg6MjAwcHg7cGFkZGluZzouMjVyZW0gLjVyZW07Y29sb3I6I2ZmZjt0ZXh0LWFsaWduOmNlbnRlcjtiYWNrZ3JvdW5kLWNvbG9yOiMwMDA7Ym9yZGVyLXJhZGl1czouMjVyZW19LnBvcG92ZXJ7cG9zaXRpb246YWJzb2x1dGU7dG9wOjA7bGVmdDowO3otaW5kZXg6MTA2MDtkaXNwbGF5OmJsb2NrO21heC13aWR0aDoyNzZweDtmb250LWZhbWlseTotYXBwbGUtc3lzdGVtLEJsaW5rTWFjU3lzdGVtRm9udCwiU2Vnb2UgVUkiLFJvYm90bywiSGVsdmV0aWNhIE5ldWUiLEFyaWFsLCJOb3RvIFNhbnMiLHNhbnMtc2VyaWYsIkFwcGxlIENvbG9yIEVtb2ppIiwiU2Vnb2UgVUkgRW1vamkiLCJTZWdvZSBVSSBTeW1ib2wiLCJOb3RvIENvbG9yIEVtb2ppIjtmb250LXN0eWxlOm5vcm1hbDtmb250LXdlaWdodDo0MDA7bGluZS1oZWlnaHQ6MS41O3RleHQtYWxpZ246bGVmdDt0ZXh0LWFsaWduOnN0YXJ0O3RleHQtZGVjb3JhdGlvbjpub25lO3RleHQtc2hhZG93Om5vbmU7dGV4dC10cmFuc2Zvcm06bm9uZTtsZXR0ZXItc3BhY2luZzpub3JtYWw7d29yZC1icmVhazpub3JtYWw7d29yZC1zcGFjaW5nOm5vcm1hbDt3aGl0ZS1zcGFjZTpub3JtYWw7bGluZS1icmVhazphdXRvO2ZvbnQtc2l6ZTouODc1cmVtO3dvcmQtd3JhcDpicmVhay13b3JkO2JhY2tncm91bmQtY29sb3I6I2ZmZjtiYWNrZ3JvdW5kLWNsaXA6cGFkZGluZy1ib3g7Ym9yZGVyOjFweCBzb2xpZCByZ2JhKDAsMCwwLC4yKTtib3JkZXItcmFkaXVzOi4zcmVtfS5wb3BvdmVyIC5hcnJvd3twb3NpdGlvbjphYnNvbHV0ZTtkaXNwbGF5OmJsb2NrO3dpZHRoOjFyZW07aGVpZ2h0Oi41cmVtO21hcmdpbjowIC4zcmVtfS5wb3BvdmVyIC5hcnJvdzo6YWZ0ZXIsLnBvcG92ZXIgLmFycm93OjpiZWZvcmV7cG9zaXRpb246YWJzb2x1dGU7ZGlzcGxheTpibG9jaztjb250ZW50OiIiO2JvcmRlci1jb2xvcjp0cmFuc3BhcmVudDtib3JkZXItc3R5bGU6c29saWR9LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49dG9wXSwuYnMtcG9wb3Zlci10b3B7bWFyZ2luLWJvdHRvbTouNXJlbX0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj10b3BdPi5hcnJvdywuYnMtcG9wb3Zlci10b3A+LmFycm93e2JvdHRvbTpjYWxjKC0uNXJlbSAtIDFweCl9LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49dG9wXT4uYXJyb3c6OmJlZm9yZSwuYnMtcG9wb3Zlci10b3A+LmFycm93OjpiZWZvcmV7Ym90dG9tOjA7Ym9yZGVyLXdpZHRoOi41cmVtIC41cmVtIDA7Ym9yZGVyLXRvcC1jb2xvcjpyZ2JhKDAsMCwwLC4yNSl9LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49dG9wXT4uYXJyb3c6OmFmdGVyLC5icy1wb3BvdmVyLXRvcD4uYXJyb3c6OmFmdGVye2JvdHRvbToxcHg7Ym9yZGVyLXdpZHRoOi41cmVtIC41cmVtIDA7Ym9yZGVyLXRvcC1jb2xvcjojZmZmfS5icy1wb3BvdmVyLWF1dG9beC1wbGFjZW1lbnRePXJpZ2h0XSwuYnMtcG9wb3Zlci1yaWdodHttYXJnaW4tbGVmdDouNXJlbX0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj1yaWdodF0+LmFycm93LC5icy1wb3BvdmVyLXJpZ2h0Pi5hcnJvd3tsZWZ0OmNhbGMoLS41cmVtIC0gMXB4KTt3aWR0aDouNXJlbTtoZWlnaHQ6MXJlbTttYXJnaW46LjNyZW0gMH0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj1yaWdodF0+LmFycm93OjpiZWZvcmUsLmJzLXBvcG92ZXItcmlnaHQ+LmFycm93OjpiZWZvcmV7bGVmdDowO2JvcmRlci13aWR0aDouNXJlbSAuNXJlbSAuNXJlbSAwO2JvcmRlci1yaWdodC1jb2xvcjpyZ2JhKDAsMCwwLC4yNSl9LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49cmlnaHRdPi5hcnJvdzo6YWZ0ZXIsLmJzLXBvcG92ZXItcmlnaHQ+LmFycm93OjphZnRlcntsZWZ0OjFweDtib3JkZXItd2lkdGg6LjVyZW0gLjVyZW0gLjVyZW0gMDtib3JkZXItcmlnaHQtY29sb3I6I2ZmZn0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj1ib3R0b21dLC5icy1wb3BvdmVyLWJvdHRvbXttYXJnaW4tdG9wOi41cmVtfS5icy1wb3BvdmVyLWF1dG9beC1wbGFjZW1lbnRePWJvdHRvbV0+LmFycm93LC5icy1wb3BvdmVyLWJvdHRvbT4uYXJyb3d7dG9wOmNhbGMoLS41cmVtIC0gMXB4KX0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj1ib3R0b21dPi5hcnJvdzo6YmVmb3JlLC5icy1wb3BvdmVyLWJvdHRvbT4uYXJyb3c6OmJlZm9yZXt0b3A6MDtib3JkZXItd2lkdGg6MCAuNXJlbSAuNXJlbSAuNXJlbTtib3JkZXItYm90dG9tLWNvbG9yOnJnYmEoMCwwLDAsLjI1KX0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj1ib3R0b21dPi5hcnJvdzo6YWZ0ZXIsLmJzLXBvcG92ZXItYm90dG9tPi5hcnJvdzo6YWZ0ZXJ7dG9wOjFweDtib3JkZXItd2lkdGg6MCAuNXJlbSAuNXJlbSAuNXJlbTtib3JkZXItYm90dG9tLWNvbG9yOiNmZmZ9LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49Ym90dG9tXSAucG9wb3Zlci1oZWFkZXI6OmJlZm9yZSwuYnMtcG9wb3Zlci1ib3R0b20gLnBvcG92ZXItaGVhZGVyOjpiZWZvcmV7cG9zaXRpb246YWJzb2x1dGU7dG9wOjA7bGVmdDo1MCU7ZGlzcGxheTpibG9jazt3aWR0aDoxcmVtO21hcmdpbi1sZWZ0Oi0uNXJlbTtjb250ZW50OiIiO2JvcmRlci1ib3R0b206MXB4IHNvbGlkICNmN2Y3Zjd9LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49bGVmdF0sLmJzLXBvcG92ZXItbGVmdHttYXJnaW4tcmlnaHQ6LjVyZW19LmJzLXBvcG92ZXItYXV0b1t4LXBsYWNlbWVudF49bGVmdF0+LmFycm93LC5icy1wb3BvdmVyLWxlZnQ+LmFycm93e3JpZ2h0OmNhbGMoLS41cmVtIC0gMXB4KTt3aWR0aDouNXJlbTtoZWlnaHQ6MXJlbTttYXJnaW46LjNyZW0gMH0uYnMtcG9wb3Zlci1hdXRvW3gtcGxhY2VtZW50Xj1sZWZ0XT4uYXJyb3c6OmJlZm9yZSwuYnMtcG9wb3Zlci1sZWZ0Pi5hcnJvdzo6YmVmb3Jle3JpZ2h0OjA7Ym9yZGVyLXdpZHRoOi41cmVtIDAgLjVyZW0gLjVyZW07Ym9yZGVyLWxlZnQtY29sb3I6cmdiYSgwLDAsMCwuMjUpfS5icy1wb3BvdmVyLWF1dG9beC1wbGFjZW1lbnRePWxlZnRdPi5hcnJvdzo6YWZ0ZXIsLmJzLXBvcG92ZXItbGVmdD4uYXJyb3c6OmFmdGVye3JpZ2h0OjFweDtib3JkZXItd2lkdGg6LjVyZW0gMCAuNXJlbSAuNXJlbTtib3JkZXItbGVmdC1jb2xvcjojZmZmfS5wb3BvdmVyLWhlYWRlcntwYWRkaW5nOi41cmVtIC43NXJlbTttYXJnaW4tYm90dG9tOjA7Zm9udC1zaXplOjFyZW07YmFja2dyb3VuZC1jb2xvcjojZjdmN2Y3O2JvcmRlci1ib3R0b206MXB4IHNvbGlkICNlYmViZWI7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czpjYWxjKC4zcmVtIC0gMXB4KTtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czpjYWxjKC4zcmVtIC0gMXB4KX0ucG9wb3Zlci1oZWFkZXI6ZW1wdHl7ZGlzcGxheTpub25lfS5wb3BvdmVyLWJvZHl7cGFkZGluZzouNXJlbSAuNzVyZW07Y29sb3I6IzIxMjUyOX0uY2Fyb3VzZWx7cG9zaXRpb246cmVsYXRpdmV9LmNhcm91c2VsLnBvaW50ZXItZXZlbnR7LW1zLXRvdWNoLWFjdGlvbjpwYW4teTt0b3VjaC1hY3Rpb246cGFuLXl9LmNhcm91c2VsLWlubmVye3Bvc2l0aW9uOnJlbGF0aXZlO3dpZHRoOjEwMCU7b3ZlcmZsb3c6aGlkZGVufS5jYXJvdXNlbC1pbm5lcjo6YWZ0ZXJ7ZGlzcGxheTpibG9jaztjbGVhcjpib3RoO2NvbnRlbnQ6IiJ9LmNhcm91c2VsLWl0ZW17cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTpub25lO2Zsb2F0OmxlZnQ7d2lkdGg6MTAwJTttYXJnaW4tcmlnaHQ6LTEwMCU7LXdlYmtpdC1iYWNrZmFjZS12aXNpYmlsaXR5OmhpZGRlbjtiYWNrZmFjZS12aXNpYmlsaXR5OmhpZGRlbjt0cmFuc2l0aW9uOi13ZWJraXQtdHJhbnNmb3JtIC42cyBlYXNlLWluLW91dDt0cmFuc2l0aW9uOnRyYW5zZm9ybSAuNnMgZWFzZS1pbi1vdXQ7dHJhbnNpdGlvbjp0cmFuc2Zvcm0gLjZzIGVhc2UtaW4tb3V0LC13ZWJraXQtdHJhbnNmb3JtIC42cyBlYXNlLWluLW91dH1AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuY2Fyb3VzZWwtaXRlbXt0cmFuc2l0aW9uOm5vbmV9fS5jYXJvdXNlbC1pdGVtLW5leHQsLmNhcm91c2VsLWl0ZW0tcHJldiwuY2Fyb3VzZWwtaXRlbS5hY3RpdmV7ZGlzcGxheTpibG9ja30uYWN0aXZlLmNhcm91c2VsLWl0ZW0tcmlnaHQsLmNhcm91c2VsLWl0ZW0tbmV4dDpub3QoLmNhcm91c2VsLWl0ZW0tbGVmdCl7LXdlYmtpdC10cmFuc2Zvcm06dHJhbnNsYXRlWCgxMDAlKTt0cmFuc2Zvcm06dHJhbnNsYXRlWCgxMDAlKX0uYWN0aXZlLmNhcm91c2VsLWl0ZW0tbGVmdCwuY2Fyb3VzZWwtaXRlbS1wcmV2Om5vdCguY2Fyb3VzZWwtaXRlbS1yaWdodCl7LXdlYmtpdC10cmFuc2Zvcm06dHJhbnNsYXRlWCgtMTAwJSk7dHJhbnNmb3JtOnRyYW5zbGF0ZVgoLTEwMCUpfS5jYXJvdXNlbC1mYWRlIC5jYXJvdXNlbC1pdGVte29wYWNpdHk6MDt0cmFuc2l0aW9uLXByb3BlcnR5Om9wYWNpdHk7LXdlYmtpdC10cmFuc2Zvcm06bm9uZTt0cmFuc2Zvcm06bm9uZX0uY2Fyb3VzZWwtZmFkZSAuY2Fyb3VzZWwtaXRlbS1uZXh0LmNhcm91c2VsLWl0ZW0tbGVmdCwuY2Fyb3VzZWwtZmFkZSAuY2Fyb3VzZWwtaXRlbS1wcmV2LmNhcm91c2VsLWl0ZW0tcmlnaHQsLmNhcm91c2VsLWZhZGUgLmNhcm91c2VsLWl0ZW0uYWN0aXZle3otaW5kZXg6MTtvcGFjaXR5OjF9LmNhcm91c2VsLWZhZGUgLmFjdGl2ZS5jYXJvdXNlbC1pdGVtLWxlZnQsLmNhcm91c2VsLWZhZGUgLmFjdGl2ZS5jYXJvdXNlbC1pdGVtLXJpZ2h0e3otaW5kZXg6MDtvcGFjaXR5OjA7dHJhbnNpdGlvbjpvcGFjaXR5IDBzIC42c31AbWVkaWEgKHByZWZlcnMtcmVkdWNlZC1tb3Rpb246cmVkdWNlKXsuY2Fyb3VzZWwtZmFkZSAuYWN0aXZlLmNhcm91c2VsLWl0ZW0tbGVmdCwuY2Fyb3VzZWwtZmFkZSAuYWN0aXZlLmNhcm91c2VsLWl0ZW0tcmlnaHR7dHJhbnNpdGlvbjpub25lfX0uY2Fyb3VzZWwtY29udHJvbC1uZXh0LC5jYXJvdXNlbC1jb250cm9sLXByZXZ7cG9zaXRpb246YWJzb2x1dGU7dG9wOjA7Ym90dG9tOjA7ei1pbmRleDoxO2Rpc3BsYXk6LW1zLWZsZXhib3g7ZGlzcGxheTpmbGV4Oy1tcy1mbGV4LWFsaWduOmNlbnRlcjthbGlnbi1pdGVtczpjZW50ZXI7LW1zLWZsZXgtcGFjazpjZW50ZXI7anVzdGlmeS1jb250ZW50OmNlbnRlcjt3aWR0aDoxNSU7Y29sb3I6I2ZmZjt0ZXh0LWFsaWduOmNlbnRlcjtvcGFjaXR5Oi41O3RyYW5zaXRpb246b3BhY2l0eSAuMTVzIGVhc2V9QG1lZGlhIChwcmVmZXJzLXJlZHVjZWQtbW90aW9uOnJlZHVjZSl7LmNhcm91c2VsLWNvbnRyb2wtbmV4dCwuY2Fyb3VzZWwtY29udHJvbC1wcmV2e3RyYW5zaXRpb246bm9uZX19LmNhcm91c2VsLWNvbnRyb2wtbmV4dDpmb2N1cywuY2Fyb3VzZWwtY29udHJvbC1uZXh0OmhvdmVyLC5jYXJvdXNlbC1jb250cm9sLXByZXY6Zm9jdXMsLmNhcm91c2VsLWNvbnRyb2wtcHJldjpob3Zlcntjb2xvcjojZmZmO3RleHQtZGVjb3JhdGlvbjpub25lO291dGxpbmU6MDtvcGFjaXR5Oi45fS5jYXJvdXNlbC1jb250cm9sLXByZXZ7bGVmdDowfS5jYXJvdXNlbC1jb250cm9sLW5leHR7cmlnaHQ6MH0uY2Fyb3VzZWwtY29udHJvbC1uZXh0LWljb24sLmNhcm91c2VsLWNvbnRyb2wtcHJldi1pY29ue2Rpc3BsYXk6aW5saW5lLWJsb2NrO3dpZHRoOjIwcHg7aGVpZ2h0OjIwcHg7YmFja2dyb3VuZDpuby1yZXBlYXQgNTAlLzEwMCUgMTAwJX0uY2Fyb3VzZWwtY29udHJvbC1wcmV2LWljb257YmFja2dyb3VuZC1pbWFnZTp1cmwoImRhdGE6aW1hZ2Uvc3ZnK3htbCwlM2NzdmcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyBmaWxsPSclMjNmZmYnIHdpZHRoPSc4JyBoZWlnaHQ9JzgnIHZpZXdCb3g9JzAgMCA4IDgnJTNlJTNjcGF0aCBkPSdNNS4yNSAwbC00IDQgNCA0IDEuNS0xLjVMNC4yNSA0bDIuNS0yLjVMNS4yNSAweicvJTNlJTNjL3N2ZyUzZSIpfS5jYXJvdXNlbC1jb250cm9sLW5leHQtaWNvbntiYWNrZ3JvdW5kLWltYWdlOnVybCgiZGF0YTppbWFnZS9zdmcreG1sLCUzY3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIGZpbGw9JyUyM2ZmZicgd2lkdGg9JzgnIGhlaWdodD0nOCcgdmlld0JveD0nMCAwIDggOCclM2UlM2NwYXRoIGQ9J00yLjc1IDBsLTEuNSAxLjVMMy43NSA0bC0yLjUgMi41TDIuNzUgOGw0LTQtNC00eicvJTNlJTNjL3N2ZyUzZSIpfS5jYXJvdXNlbC1pbmRpY2F0b3Jze3Bvc2l0aW9uOmFic29sdXRlO3JpZ2h0OjA7Ym90dG9tOjA7bGVmdDowO3otaW5kZXg6MTU7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtcGFjazpjZW50ZXI7anVzdGlmeS1jb250ZW50OmNlbnRlcjtwYWRkaW5nLWxlZnQ6MDttYXJnaW4tcmlnaHQ6MTUlO21hcmdpbi1sZWZ0OjE1JTtsaXN0LXN0eWxlOm5vbmV9LmNhcm91c2VsLWluZGljYXRvcnMgbGl7Ym94LXNpemluZzpjb250ZW50LWJveDstbXMtZmxleDowIDEgYXV0bztmbGV4OjAgMSBhdXRvO3dpZHRoOjMwcHg7aGVpZ2h0OjNweDttYXJnaW4tcmlnaHQ6M3B4O21hcmdpbi1sZWZ0OjNweDt0ZXh0LWluZGVudDotOTk5cHg7Y3Vyc29yOnBvaW50ZXI7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JhY2tncm91bmQtY2xpcDpwYWRkaW5nLWJveDtib3JkZXItdG9wOjEwcHggc29saWQgdHJhbnNwYXJlbnQ7Ym9yZGVyLWJvdHRvbToxMHB4IHNvbGlkIHRyYW5zcGFyZW50O29wYWNpdHk6LjU7dHJhbnNpdGlvbjpvcGFjaXR5IC42cyBlYXNlfUBtZWRpYSAocHJlZmVycy1yZWR1Y2VkLW1vdGlvbjpyZWR1Y2Upey5jYXJvdXNlbC1pbmRpY2F0b3JzIGxpe3RyYW5zaXRpb246bm9uZX19LmNhcm91c2VsLWluZGljYXRvcnMgLmFjdGl2ZXtvcGFjaXR5OjF9LmNhcm91c2VsLWNhcHRpb257cG9zaXRpb246YWJzb2x1dGU7cmlnaHQ6MTUlO2JvdHRvbToyMHB4O2xlZnQ6MTUlO3otaW5kZXg6MTA7cGFkZGluZy10b3A6MjBweDtwYWRkaW5nLWJvdHRvbToyMHB4O2NvbG9yOiNmZmY7dGV4dC1hbGlnbjpjZW50ZXJ9QC13ZWJraXQta2V5ZnJhbWVzIHNwaW5uZXItYm9yZGVye3Rvey13ZWJraXQtdHJhbnNmb3JtOnJvdGF0ZSgzNjBkZWcpO3RyYW5zZm9ybTpyb3RhdGUoMzYwZGVnKX19QGtleWZyYW1lcyBzcGlubmVyLWJvcmRlcnt0b3std2Via2l0LXRyYW5zZm9ybTpyb3RhdGUoMzYwZGVnKTt0cmFuc2Zvcm06cm90YXRlKDM2MGRlZyl9fS5zcGlubmVyLWJvcmRlcntkaXNwbGF5OmlubGluZS1ibG9jazt3aWR0aDoycmVtO2hlaWdodDoycmVtO3ZlcnRpY2FsLWFsaWduOnRleHQtYm90dG9tO2JvcmRlcjouMjVlbSBzb2xpZCBjdXJyZW50Q29sb3I7Ym9yZGVyLXJpZ2h0LWNvbG9yOnRyYW5zcGFyZW50O2JvcmRlci1yYWRpdXM6NTAlOy13ZWJraXQtYW5pbWF0aW9uOnNwaW5uZXItYm9yZGVyIC43NXMgbGluZWFyIGluZmluaXRlO2FuaW1hdGlvbjpzcGlubmVyLWJvcmRlciAuNzVzIGxpbmVhciBpbmZpbml0ZX0uc3Bpbm5lci1ib3JkZXItc217d2lkdGg6MXJlbTtoZWlnaHQ6MXJlbTtib3JkZXItd2lkdGg6LjJlbX1ALXdlYmtpdC1rZXlmcmFtZXMgc3Bpbm5lci1ncm93ezAley13ZWJraXQtdHJhbnNmb3JtOnNjYWxlKDApO3RyYW5zZm9ybTpzY2FsZSgwKX01MCV7b3BhY2l0eToxfX1Aa2V5ZnJhbWVzIHNwaW5uZXItZ3Jvd3swJXstd2Via2l0LXRyYW5zZm9ybTpzY2FsZSgwKTt0cmFuc2Zvcm06c2NhbGUoMCl9NTAle29wYWNpdHk6MX19LnNwaW5uZXItZ3Jvd3tkaXNwbGF5OmlubGluZS1ibG9jazt3aWR0aDoycmVtO2hlaWdodDoycmVtO3ZlcnRpY2FsLWFsaWduOnRleHQtYm90dG9tO2JhY2tncm91bmQtY29sb3I6Y3VycmVudENvbG9yO2JvcmRlci1yYWRpdXM6NTAlO29wYWNpdHk6MDstd2Via2l0LWFuaW1hdGlvbjpzcGlubmVyLWdyb3cgLjc1cyBsaW5lYXIgaW5maW5pdGU7YW5pbWF0aW9uOnNwaW5uZXItZ3JvdyAuNzVzIGxpbmVhciBpbmZpbml0ZX0uc3Bpbm5lci1ncm93LXNte3dpZHRoOjFyZW07aGVpZ2h0OjFyZW19LmFsaWduLWJhc2VsaW5le3ZlcnRpY2FsLWFsaWduOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24tdG9we3ZlcnRpY2FsLWFsaWduOnRvcCFpbXBvcnRhbnR9LmFsaWduLW1pZGRsZXt2ZXJ0aWNhbC1hbGlnbjptaWRkbGUhaW1wb3J0YW50fS5hbGlnbi1ib3R0b217dmVydGljYWwtYWxpZ246Ym90dG9tIWltcG9ydGFudH0uYWxpZ24tdGV4dC1ib3R0b217dmVydGljYWwtYWxpZ246dGV4dC1ib3R0b20haW1wb3J0YW50fS5hbGlnbi10ZXh0LXRvcHt2ZXJ0aWNhbC1hbGlnbjp0ZXh0LXRvcCFpbXBvcnRhbnR9LmJnLXByaW1hcnl7YmFja2dyb3VuZC1jb2xvcjojMDA3YmZmIWltcG9ydGFudH1hLmJnLXByaW1hcnk6Zm9jdXMsYS5iZy1wcmltYXJ5OmhvdmVyLGJ1dHRvbi5iZy1wcmltYXJ5OmZvY3VzLGJ1dHRvbi5iZy1wcmltYXJ5OmhvdmVye2JhY2tncm91bmQtY29sb3I6IzAwNjJjYyFpbXBvcnRhbnR9LmJnLXNlY29uZGFyeXtiYWNrZ3JvdW5kLWNvbG9yOiM2Yzc1N2QhaW1wb3J0YW50fWEuYmctc2Vjb25kYXJ5OmZvY3VzLGEuYmctc2Vjb25kYXJ5OmhvdmVyLGJ1dHRvbi5iZy1zZWNvbmRhcnk6Zm9jdXMsYnV0dG9uLmJnLXNlY29uZGFyeTpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiM1NDViNjIhaW1wb3J0YW50fS5iZy1zdWNjZXNze2JhY2tncm91bmQtY29sb3I6IzI4YTc0NSFpbXBvcnRhbnR9YS5iZy1zdWNjZXNzOmZvY3VzLGEuYmctc3VjY2Vzczpob3ZlcixidXR0b24uYmctc3VjY2Vzczpmb2N1cyxidXR0b24uYmctc3VjY2Vzczpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiMxZTdlMzQhaW1wb3J0YW50fS5iZy1pbmZve2JhY2tncm91bmQtY29sb3I6IzE3YTJiOCFpbXBvcnRhbnR9YS5iZy1pbmZvOmZvY3VzLGEuYmctaW5mbzpob3ZlcixidXR0b24uYmctaW5mbzpmb2N1cyxidXR0b24uYmctaW5mbzpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiMxMTdhOGIhaW1wb3J0YW50fS5iZy13YXJuaW5ne2JhY2tncm91bmQtY29sb3I6I2ZmYzEwNyFpbXBvcnRhbnR9YS5iZy13YXJuaW5nOmZvY3VzLGEuYmctd2FybmluZzpob3ZlcixidXR0b24uYmctd2FybmluZzpmb2N1cyxidXR0b24uYmctd2FybmluZzpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiNkMzllMDAhaW1wb3J0YW50fS5iZy1kYW5nZXJ7YmFja2dyb3VuZC1jb2xvcjojZGMzNTQ1IWltcG9ydGFudH1hLmJnLWRhbmdlcjpmb2N1cyxhLmJnLWRhbmdlcjpob3ZlcixidXR0b24uYmctZGFuZ2VyOmZvY3VzLGJ1dHRvbi5iZy1kYW5nZXI6aG92ZXJ7YmFja2dyb3VuZC1jb2xvcjojYmQyMTMwIWltcG9ydGFudH0uYmctbGlnaHR7YmFja2dyb3VuZC1jb2xvcjojZjhmOWZhIWltcG9ydGFudH1hLmJnLWxpZ2h0OmZvY3VzLGEuYmctbGlnaHQ6aG92ZXIsYnV0dG9uLmJnLWxpZ2h0OmZvY3VzLGJ1dHRvbi5iZy1saWdodDpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiNkYWUwZTUhaW1wb3J0YW50fS5iZy1kYXJre2JhY2tncm91bmQtY29sb3I6IzM0M2E0MCFpbXBvcnRhbnR9YS5iZy1kYXJrOmZvY3VzLGEuYmctZGFyazpob3ZlcixidXR0b24uYmctZGFyazpmb2N1cyxidXR0b24uYmctZGFyazpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOiMxZDIxMjQhaW1wb3J0YW50fS5iZy13aGl0ZXtiYWNrZ3JvdW5kLWNvbG9yOiNmZmYhaW1wb3J0YW50fS5iZy10cmFuc3BhcmVudHtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50IWltcG9ydGFudH0uYm9yZGVye2JvcmRlcjoxcHggc29saWQgI2RlZTJlNiFpbXBvcnRhbnR9LmJvcmRlci10b3B7Ym9yZGVyLXRvcDoxcHggc29saWQgI2RlZTJlNiFpbXBvcnRhbnR9LmJvcmRlci1yaWdodHtib3JkZXItcmlnaHQ6MXB4IHNvbGlkICNkZWUyZTYhaW1wb3J0YW50fS5ib3JkZXItYm90dG9te2JvcmRlci1ib3R0b206MXB4IHNvbGlkICNkZWUyZTYhaW1wb3J0YW50fS5ib3JkZXItbGVmdHtib3JkZXItbGVmdDoxcHggc29saWQgI2RlZTJlNiFpbXBvcnRhbnR9LmJvcmRlci0we2JvcmRlcjowIWltcG9ydGFudH0uYm9yZGVyLXRvcC0we2JvcmRlci10b3A6MCFpbXBvcnRhbnR9LmJvcmRlci1yaWdodC0we2JvcmRlci1yaWdodDowIWltcG9ydGFudH0uYm9yZGVyLWJvdHRvbS0we2JvcmRlci1ib3R0b206MCFpbXBvcnRhbnR9LmJvcmRlci1sZWZ0LTB7Ym9yZGVyLWxlZnQ6MCFpbXBvcnRhbnR9LmJvcmRlci1wcmltYXJ5e2JvcmRlci1jb2xvcjojMDA3YmZmIWltcG9ydGFudH0uYm9yZGVyLXNlY29uZGFyeXtib3JkZXItY29sb3I6IzZjNzU3ZCFpbXBvcnRhbnR9LmJvcmRlci1zdWNjZXNze2JvcmRlci1jb2xvcjojMjhhNzQ1IWltcG9ydGFudH0uYm9yZGVyLWluZm97Ym9yZGVyLWNvbG9yOiMxN2EyYjghaW1wb3J0YW50fS5ib3JkZXItd2FybmluZ3tib3JkZXItY29sb3I6I2ZmYzEwNyFpbXBvcnRhbnR9LmJvcmRlci1kYW5nZXJ7Ym9yZGVyLWNvbG9yOiNkYzM1NDUhaW1wb3J0YW50fS5ib3JkZXItbGlnaHR7Ym9yZGVyLWNvbG9yOiNmOGY5ZmEhaW1wb3J0YW50fS5ib3JkZXItZGFya3tib3JkZXItY29sb3I6IzM0M2E0MCFpbXBvcnRhbnR9LmJvcmRlci13aGl0ZXtib3JkZXItY29sb3I6I2ZmZiFpbXBvcnRhbnR9LnJvdW5kZWQtc217Ym9yZGVyLXJhZGl1czouMnJlbSFpbXBvcnRhbnR9LnJvdW5kZWR7Ym9yZGVyLXJhZGl1czouMjVyZW0haW1wb3J0YW50fS5yb3VuZGVkLXRvcHtib3JkZXItdG9wLWxlZnQtcmFkaXVzOi4yNXJlbSFpbXBvcnRhbnQ7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6LjI1cmVtIWltcG9ydGFudH0ucm91bmRlZC1yaWdodHtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czouMjVyZW0haW1wb3J0YW50O2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOi4yNXJlbSFpbXBvcnRhbnR9LnJvdW5kZWQtYm90dG9te2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOi4yNXJlbSFpbXBvcnRhbnQ7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czouMjVyZW0haW1wb3J0YW50fS5yb3VuZGVkLWxlZnR7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czouMjVyZW0haW1wb3J0YW50O2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6LjI1cmVtIWltcG9ydGFudH0ucm91bmRlZC1sZ3tib3JkZXItcmFkaXVzOi4zcmVtIWltcG9ydGFudH0ucm91bmRlZC1jaXJjbGV7Ym9yZGVyLXJhZGl1czo1MCUhaW1wb3J0YW50fS5yb3VuZGVkLXBpbGx7Ym9yZGVyLXJhZGl1czo1MHJlbSFpbXBvcnRhbnR9LnJvdW5kZWQtMHtib3JkZXItcmFkaXVzOjAhaW1wb3J0YW50fS5jbGVhcmZpeDo6YWZ0ZXJ7ZGlzcGxheTpibG9jaztjbGVhcjpib3RoO2NvbnRlbnQ6IiJ9LmQtbm9uZXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5kLWlubGluZXtkaXNwbGF5OmlubGluZSFpbXBvcnRhbnR9LmQtaW5saW5lLWJsb2Nre2Rpc3BsYXk6aW5saW5lLWJsb2NrIWltcG9ydGFudH0uZC1ibG9ja3tkaXNwbGF5OmJsb2NrIWltcG9ydGFudH0uZC10YWJsZXtkaXNwbGF5OnRhYmxlIWltcG9ydGFudH0uZC10YWJsZS1yb3d7ZGlzcGxheTp0YWJsZS1yb3chaW1wb3J0YW50fS5kLXRhYmxlLWNlbGx7ZGlzcGxheTp0YWJsZS1jZWxsIWltcG9ydGFudH0uZC1mbGV4e2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnR9LmQtaW5saW5lLWZsZXh7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6aW5saW5lLWZsZXghaW1wb3J0YW50fUBtZWRpYSAobWluLXdpZHRoOjU3NnB4KXsuZC1zbS1ub25le2Rpc3BsYXk6bm9uZSFpbXBvcnRhbnR9LmQtc20taW5saW5le2Rpc3BsYXk6aW5saW5lIWltcG9ydGFudH0uZC1zbS1pbmxpbmUtYmxvY2t7ZGlzcGxheTppbmxpbmUtYmxvY2shaW1wb3J0YW50fS5kLXNtLWJsb2Nre2Rpc3BsYXk6YmxvY2shaW1wb3J0YW50fS5kLXNtLXRhYmxle2Rpc3BsYXk6dGFibGUhaW1wb3J0YW50fS5kLXNtLXRhYmxlLXJvd3tkaXNwbGF5OnRhYmxlLXJvdyFpbXBvcnRhbnR9LmQtc20tdGFibGUtY2VsbHtkaXNwbGF5OnRhYmxlLWNlbGwhaW1wb3J0YW50fS5kLXNtLWZsZXh7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudH0uZC1zbS1pbmxpbmUtZmxleHtkaXNwbGF5Oi1tcy1pbmxpbmUtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTppbmxpbmUtZmxleCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsuZC1tZC1ub25le2Rpc3BsYXk6bm9uZSFpbXBvcnRhbnR9LmQtbWQtaW5saW5le2Rpc3BsYXk6aW5saW5lIWltcG9ydGFudH0uZC1tZC1pbmxpbmUtYmxvY2t7ZGlzcGxheTppbmxpbmUtYmxvY2shaW1wb3J0YW50fS5kLW1kLWJsb2Nre2Rpc3BsYXk6YmxvY2shaW1wb3J0YW50fS5kLW1kLXRhYmxle2Rpc3BsYXk6dGFibGUhaW1wb3J0YW50fS5kLW1kLXRhYmxlLXJvd3tkaXNwbGF5OnRhYmxlLXJvdyFpbXBvcnRhbnR9LmQtbWQtdGFibGUtY2VsbHtkaXNwbGF5OnRhYmxlLWNlbGwhaW1wb3J0YW50fS5kLW1kLWZsZXh7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudH0uZC1tZC1pbmxpbmUtZmxleHtkaXNwbGF5Oi1tcy1pbmxpbmUtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTppbmxpbmUtZmxleCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuZC1sZy1ub25le2Rpc3BsYXk6bm9uZSFpbXBvcnRhbnR9LmQtbGctaW5saW5le2Rpc3BsYXk6aW5saW5lIWltcG9ydGFudH0uZC1sZy1pbmxpbmUtYmxvY2t7ZGlzcGxheTppbmxpbmUtYmxvY2shaW1wb3J0YW50fS5kLWxnLWJsb2Nre2Rpc3BsYXk6YmxvY2shaW1wb3J0YW50fS5kLWxnLXRhYmxle2Rpc3BsYXk6dGFibGUhaW1wb3J0YW50fS5kLWxnLXRhYmxlLXJvd3tkaXNwbGF5OnRhYmxlLXJvdyFpbXBvcnRhbnR9LmQtbGctdGFibGUtY2VsbHtkaXNwbGF5OnRhYmxlLWNlbGwhaW1wb3J0YW50fS5kLWxnLWZsZXh7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudH0uZC1sZy1pbmxpbmUtZmxleHtkaXNwbGF5Oi1tcy1pbmxpbmUtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTppbmxpbmUtZmxleCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjEyMDBweCl7LmQteGwtbm9uZXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5kLXhsLWlubGluZXtkaXNwbGF5OmlubGluZSFpbXBvcnRhbnR9LmQteGwtaW5saW5lLWJsb2Nre2Rpc3BsYXk6aW5saW5lLWJsb2NrIWltcG9ydGFudH0uZC14bC1ibG9ja3tkaXNwbGF5OmJsb2NrIWltcG9ydGFudH0uZC14bC10YWJsZXtkaXNwbGF5OnRhYmxlIWltcG9ydGFudH0uZC14bC10YWJsZS1yb3d7ZGlzcGxheTp0YWJsZS1yb3chaW1wb3J0YW50fS5kLXhsLXRhYmxlLWNlbGx7ZGlzcGxheTp0YWJsZS1jZWxsIWltcG9ydGFudH0uZC14bC1mbGV4e2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnR9LmQteGwtaW5saW5lLWZsZXh7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6aW5saW5lLWZsZXghaW1wb3J0YW50fX1AbWVkaWEgcHJpbnR7LmQtcHJpbnQtbm9uZXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5kLXByaW50LWlubGluZXtkaXNwbGF5OmlubGluZSFpbXBvcnRhbnR9LmQtcHJpbnQtaW5saW5lLWJsb2Nre2Rpc3BsYXk6aW5saW5lLWJsb2NrIWltcG9ydGFudH0uZC1wcmludC1ibG9ja3tkaXNwbGF5OmJsb2NrIWltcG9ydGFudH0uZC1wcmludC10YWJsZXtkaXNwbGF5OnRhYmxlIWltcG9ydGFudH0uZC1wcmludC10YWJsZS1yb3d7ZGlzcGxheTp0YWJsZS1yb3chaW1wb3J0YW50fS5kLXByaW50LXRhYmxlLWNlbGx7ZGlzcGxheTp0YWJsZS1jZWxsIWltcG9ydGFudH0uZC1wcmludC1mbGV4e2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnR9LmQtcHJpbnQtaW5saW5lLWZsZXh7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6aW5saW5lLWZsZXghaW1wb3J0YW50fX0uZW1iZWQtcmVzcG9uc2l2ZXtwb3NpdGlvbjpyZWxhdGl2ZTtkaXNwbGF5OmJsb2NrO3dpZHRoOjEwMCU7cGFkZGluZzowO292ZXJmbG93OmhpZGRlbn0uZW1iZWQtcmVzcG9uc2l2ZTo6YmVmb3Jle2Rpc3BsYXk6YmxvY2s7Y29udGVudDoiIn0uZW1iZWQtcmVzcG9uc2l2ZSAuZW1iZWQtcmVzcG9uc2l2ZS1pdGVtLC5lbWJlZC1yZXNwb25zaXZlIGVtYmVkLC5lbWJlZC1yZXNwb25zaXZlIGlmcmFtZSwuZW1iZWQtcmVzcG9uc2l2ZSBvYmplY3QsLmVtYmVkLXJlc3BvbnNpdmUgdmlkZW97cG9zaXRpb246YWJzb2x1dGU7dG9wOjA7Ym90dG9tOjA7bGVmdDowO3dpZHRoOjEwMCU7aGVpZ2h0OjEwMCU7Ym9yZGVyOjB9LmVtYmVkLXJlc3BvbnNpdmUtMjFieTk6OmJlZm9yZXtwYWRkaW5nLXRvcDo0Mi44NTcxNDMlfS5lbWJlZC1yZXNwb25zaXZlLTE2Ynk5OjpiZWZvcmV7cGFkZGluZy10b3A6NTYuMjUlfS5lbWJlZC1yZXNwb25zaXZlLTRieTM6OmJlZm9yZXtwYWRkaW5nLXRvcDo3NSV9LmVtYmVkLXJlc3BvbnNpdmUtMWJ5MTo6YmVmb3Jle3BhZGRpbmctdG9wOjEwMCV9LmZsZXgtcm93ey1tcy1mbGV4LWRpcmVjdGlvbjpyb3chaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnR9LmZsZXgtY29sdW1uey1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW4haW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnR9LmZsZXgtcm93LXJldmVyc2V7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpyb3ctcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtY29sdW1uLXJldmVyc2V7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpjb2x1bW4tcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtd3JhcHstbXMtZmxleC13cmFwOndyYXAhaW1wb3J0YW50O2ZsZXgtd3JhcDp3cmFwIWltcG9ydGFudH0uZmxleC1ub3dyYXB7LW1zLWZsZXgtd3JhcDpub3dyYXAhaW1wb3J0YW50O2ZsZXgtd3JhcDpub3dyYXAhaW1wb3J0YW50fS5mbGV4LXdyYXAtcmV2ZXJzZXstbXMtZmxleC13cmFwOndyYXAtcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAtcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtZmlsbHstbXMtZmxleDoxIDEgYXV0byFpbXBvcnRhbnQ7ZmxleDoxIDEgYXV0byFpbXBvcnRhbnR9LmZsZXgtZ3Jvdy0wey1tcy1mbGV4LXBvc2l0aXZlOjAhaW1wb3J0YW50O2ZsZXgtZ3JvdzowIWltcG9ydGFudH0uZmxleC1ncm93LTF7LW1zLWZsZXgtcG9zaXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1ncm93OjEhaW1wb3J0YW50fS5mbGV4LXNocmluay0wey1tcy1mbGV4LW5lZ2F0aXZlOjAhaW1wb3J0YW50O2ZsZXgtc2hyaW5rOjAhaW1wb3J0YW50fS5mbGV4LXNocmluay0xey1tcy1mbGV4LW5lZ2F0aXZlOjEhaW1wb3J0YW50O2ZsZXgtc2hyaW5rOjEhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtc3RhcnR7LW1zLWZsZXgtcGFjazpzdGFydCFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmZsZXgtc3RhcnQhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtZW5key1tcy1mbGV4LXBhY2s6ZW5kIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtY2VudGVyey1tcy1mbGV4LXBhY2s6Y2VudGVyIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6Y2VudGVyIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LWJldHdlZW57LW1zLWZsZXgtcGFjazpqdXN0aWZ5IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYmV0d2VlbiFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1hcm91bmR7LW1zLWZsZXgtcGFjazpkaXN0cmlidXRlIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMtc3RhcnR7LW1zLWZsZXgtYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1lbmR7LW1zLWZsZXgtYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1pdGVtczpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLWNlbnRlcnstbXMtZmxleC1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLWJhc2VsaW5ley1tcy1mbGV4LWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1pdGVtczpiYXNlbGluZSFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXN0cmV0Y2h7LW1zLWZsZXgtYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6c3RyZXRjaCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtc3RhcnR7LW1zLWZsZXgtbGluZS1wYWNrOnN0YXJ0IWltcG9ydGFudDthbGlnbi1jb250ZW50OmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWVuZHstbXMtZmxleC1saW5lLXBhY2s6ZW5kIWltcG9ydGFudDthbGlnbi1jb250ZW50OmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1jZW50ZXJ7LW1zLWZsZXgtbGluZS1wYWNrOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWJldHdlZW57LW1zLWZsZXgtbGluZS1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYmV0d2VlbiFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtYXJvdW5key1tcy1mbGV4LWxpbmUtcGFjazpkaXN0cmlidXRlIWltcG9ydGFudDthbGlnbi1jb250ZW50OnNwYWNlLWFyb3VuZCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtc3RyZXRjaHstbXMtZmxleC1saW5lLXBhY2s6c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tc2VsZi1hdXRvey1tcy1mbGV4LWl0ZW0tYWxpZ246YXV0byFpbXBvcnRhbnQ7YWxpZ24tc2VsZjphdXRvIWltcG9ydGFudH0uYWxpZ24tc2VsZi1zdGFydHstbXMtZmxleC1pdGVtLWFsaWduOnN0YXJ0IWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1zZWxmLWVuZHstbXMtZmxleC1pdGVtLWFsaWduOmVuZCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtY2VudGVyey1tcy1mbGV4LWl0ZW0tYWxpZ246Y2VudGVyIWltcG9ydGFudDthbGlnbi1zZWxmOmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLXNlbGYtYmFzZWxpbmV7LW1zLWZsZXgtaXRlbS1hbGlnbjpiYXNlbGluZSFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpiYXNlbGluZSFpbXBvcnRhbnR9LmFsaWduLXNlbGYtc3RyZXRjaHstbXMtZmxleC1pdGVtLWFsaWduOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLXNlbGY6c3RyZXRjaCFpbXBvcnRhbnR9QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey5mbGV4LXNtLXJvd3stbXMtZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpyb3chaW1wb3J0YW50fS5mbGV4LXNtLWNvbHVtbnstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpjb2x1bW4haW1wb3J0YW50fS5mbGV4LXNtLXJvdy1yZXZlcnNley1tcy1mbGV4LWRpcmVjdGlvbjpyb3ctcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LXNtLWNvbHVtbi1yZXZlcnNley1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW4tcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LXNtLXdyYXB7LW1zLWZsZXgtd3JhcDp3cmFwIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnR9LmZsZXgtc20tbm93cmFwey1tcy1mbGV4LXdyYXA6bm93cmFwIWltcG9ydGFudDtmbGV4LXdyYXA6bm93cmFwIWltcG9ydGFudH0uZmxleC1zbS13cmFwLXJldmVyc2V7LW1zLWZsZXgtd3JhcDp3cmFwLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtd3JhcDp3cmFwLXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LXNtLWZpbGx7LW1zLWZsZXg6MSAxIGF1dG8haW1wb3J0YW50O2ZsZXg6MSAxIGF1dG8haW1wb3J0YW50fS5mbGV4LXNtLWdyb3ctMHstbXMtZmxleC1wb3NpdGl2ZTowIWltcG9ydGFudDtmbGV4LWdyb3c6MCFpbXBvcnRhbnR9LmZsZXgtc20tZ3Jvdy0xey1tcy1mbGV4LXBvc2l0aXZlOjEhaW1wb3J0YW50O2ZsZXgtZ3JvdzoxIWltcG9ydGFudH0uZmxleC1zbS1zaHJpbmstMHstbXMtZmxleC1uZWdhdGl2ZTowIWltcG9ydGFudDtmbGV4LXNocmluazowIWltcG9ydGFudH0uZmxleC1zbS1zaHJpbmstMXstbXMtZmxleC1uZWdhdGl2ZToxIWltcG9ydGFudDtmbGV4LXNocmluazoxIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXNtLXN0YXJ0ey1tcy1mbGV4LXBhY2s6c3RhcnQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LXN0YXJ0IWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXNtLWVuZHstbXMtZmxleC1wYWNrOmVuZCFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmZsZXgtZW5kIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXNtLWNlbnRlcnstbXMtZmxleC1wYWNrOmNlbnRlciFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1zbS1iZXR3ZWVuey1tcy1mbGV4LXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OnNwYWNlLWJldHdlZW4haW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtc20tYXJvdW5key1tcy1mbGV4LXBhY2s6ZGlzdHJpYnV0ZSFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OnNwYWNlLWFyb3VuZCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXNtLXN0YXJ0ey1tcy1mbGV4LWFsaWduOnN0YXJ0IWltcG9ydGFudDthbGlnbi1pdGVtczpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24taXRlbXMtc20tZW5key1tcy1mbGV4LWFsaWduOmVuZCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1zbS1jZW50ZXJ7LW1zLWZsZXgtYWxpZ246Y2VudGVyIWltcG9ydGFudDthbGlnbi1pdGVtczpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1zbS1iYXNlbGluZXstbXMtZmxleC1hbGlnbjpiYXNlbGluZSFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6YmFzZWxpbmUhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1zbS1zdHJldGNoey1tcy1mbGV4LWFsaWduOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLWl0ZW1zOnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXNtLXN0YXJ0ey1tcy1mbGV4LWxpbmUtcGFjazpzdGFydCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24tY29udGVudC1zbS1lbmR7LW1zLWZsZXgtbGluZS1wYWNrOmVuZCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtc20tY2VudGVyey1tcy1mbGV4LWxpbmUtcGFjazpjZW50ZXIhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1zbS1iZXR3ZWVuey1tcy1mbGV4LWxpbmUtcGFjazpqdXN0aWZ5IWltcG9ydGFudDthbGlnbi1jb250ZW50OnNwYWNlLWJldHdlZW4haW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXNtLWFyb3VuZHstbXMtZmxleC1saW5lLXBhY2s6ZGlzdHJpYnV0ZSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXNtLXN0cmV0Y2h7LW1zLWZsZXgtbGluZS1wYWNrOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3RyZXRjaCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtc20tYXV0b3stbXMtZmxleC1pdGVtLWFsaWduOmF1dG8haW1wb3J0YW50O2FsaWduLXNlbGY6YXV0byFpbXBvcnRhbnR9LmFsaWduLXNlbGYtc20tc3RhcnR7LW1zLWZsZXgtaXRlbS1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24tc2VsZi1zbS1lbmR7LW1zLWZsZXgtaXRlbS1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXNtLWNlbnRlcnstbXMtZmxleC1pdGVtLWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXNtLWJhc2VsaW5ley1tcy1mbGV4LWl0ZW0tYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLXNlbGY6YmFzZWxpbmUhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXNtLXN0cmV0Y2h7LW1zLWZsZXgtaXRlbS1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1zZWxmOnN0cmV0Y2ghaW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7LmZsZXgtbWQtcm93ey1tcy1mbGV4LWRpcmVjdGlvbjpyb3chaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnR9LmZsZXgtbWQtY29sdW1uey1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW4haW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnR9LmZsZXgtbWQtcm93LXJldmVyc2V7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpyb3ctcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtbWQtY29sdW1uLXJldmVyc2V7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpjb2x1bW4tcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtbWQtd3JhcHstbXMtZmxleC13cmFwOndyYXAhaW1wb3J0YW50O2ZsZXgtd3JhcDp3cmFwIWltcG9ydGFudH0uZmxleC1tZC1ub3dyYXB7LW1zLWZsZXgtd3JhcDpub3dyYXAhaW1wb3J0YW50O2ZsZXgtd3JhcDpub3dyYXAhaW1wb3J0YW50fS5mbGV4LW1kLXdyYXAtcmV2ZXJzZXstbXMtZmxleC13cmFwOndyYXAtcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAtcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtbWQtZmlsbHstbXMtZmxleDoxIDEgYXV0byFpbXBvcnRhbnQ7ZmxleDoxIDEgYXV0byFpbXBvcnRhbnR9LmZsZXgtbWQtZ3Jvdy0wey1tcy1mbGV4LXBvc2l0aXZlOjAhaW1wb3J0YW50O2ZsZXgtZ3JvdzowIWltcG9ydGFudH0uZmxleC1tZC1ncm93LTF7LW1zLWZsZXgtcG9zaXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1ncm93OjEhaW1wb3J0YW50fS5mbGV4LW1kLXNocmluay0wey1tcy1mbGV4LW5lZ2F0aXZlOjAhaW1wb3J0YW50O2ZsZXgtc2hyaW5rOjAhaW1wb3J0YW50fS5mbGV4LW1kLXNocmluay0xey1tcy1mbGV4LW5lZ2F0aXZlOjEhaW1wb3J0YW50O2ZsZXgtc2hyaW5rOjEhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtbWQtc3RhcnR7LW1zLWZsZXgtcGFjazpzdGFydCFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmZsZXgtc3RhcnQhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtbWQtZW5key1tcy1mbGV4LXBhY2s6ZW5kIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtbWQtY2VudGVyey1tcy1mbGV4LXBhY2s6Y2VudGVyIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6Y2VudGVyIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LW1kLWJldHdlZW57LW1zLWZsZXgtcGFjazpqdXN0aWZ5IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYmV0d2VlbiFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1tZC1hcm91bmR7LW1zLWZsZXgtcGFjazpkaXN0cmlidXRlIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbWQtc3RhcnR7LW1zLWZsZXgtYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1tZC1lbmR7LW1zLWZsZXgtYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1pdGVtczpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLW1kLWNlbnRlcnstbXMtZmxleC1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLW1kLWJhc2VsaW5ley1tcy1mbGV4LWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1pdGVtczpiYXNlbGluZSFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLW1kLXN0cmV0Y2h7LW1zLWZsZXgtYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6c3RyZXRjaCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbWQtc3RhcnR7LW1zLWZsZXgtbGluZS1wYWNrOnN0YXJ0IWltcG9ydGFudDthbGlnbi1jb250ZW50OmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LW1kLWVuZHstbXMtZmxleC1saW5lLXBhY2s6ZW5kIWltcG9ydGFudDthbGlnbi1jb250ZW50OmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1tZC1jZW50ZXJ7LW1zLWZsZXgtbGluZS1wYWNrOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LW1kLWJldHdlZW57LW1zLWZsZXgtbGluZS1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYmV0d2VlbiFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbWQtYXJvdW5key1tcy1mbGV4LWxpbmUtcGFjazpkaXN0cmlidXRlIWltcG9ydGFudDthbGlnbi1jb250ZW50OnNwYWNlLWFyb3VuZCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbWQtc3RyZXRjaHstbXMtZmxleC1saW5lLXBhY2s6c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tc2VsZi1tZC1hdXRvey1tcy1mbGV4LWl0ZW0tYWxpZ246YXV0byFpbXBvcnRhbnQ7YWxpZ24tc2VsZjphdXRvIWltcG9ydGFudH0uYWxpZ24tc2VsZi1tZC1zdGFydHstbXMtZmxleC1pdGVtLWFsaWduOnN0YXJ0IWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1zZWxmLW1kLWVuZHstbXMtZmxleC1pdGVtLWFsaWduOmVuZCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbWQtY2VudGVyey1tcy1mbGV4LWl0ZW0tYWxpZ246Y2VudGVyIWltcG9ydGFudDthbGlnbi1zZWxmOmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbWQtYmFzZWxpbmV7LW1zLWZsZXgtaXRlbS1hbGlnbjpiYXNlbGluZSFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpiYXNlbGluZSFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbWQtc3RyZXRjaHstbXMtZmxleC1pdGVtLWFsaWduOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLXNlbGY6c3RyZXRjaCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuZmxleC1sZy1yb3d7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudH0uZmxleC1sZy1jb2x1bW57LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudH0uZmxleC1sZy1yb3ctcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1sZy1jb2x1bW4tcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1sZy13cmFwey1tcy1mbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAhaW1wb3J0YW50fS5mbGV4LWxnLW5vd3JhcHstbXMtZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnR9LmZsZXgtbGctd3JhcC1yZXZlcnNley1tcy1mbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1sZy1maWxsey1tcy1mbGV4OjEgMSBhdXRvIWltcG9ydGFudDtmbGV4OjEgMSBhdXRvIWltcG9ydGFudH0uZmxleC1sZy1ncm93LTB7LW1zLWZsZXgtcG9zaXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1ncm93OjAhaW1wb3J0YW50fS5mbGV4LWxnLWdyb3ctMXstbXMtZmxleC1wb3NpdGl2ZToxIWltcG9ydGFudDtmbGV4LWdyb3c6MSFpbXBvcnRhbnR9LmZsZXgtbGctc2hyaW5rLTB7LW1zLWZsZXgtbmVnYXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MCFpbXBvcnRhbnR9LmZsZXgtbGctc2hyaW5rLTF7LW1zLWZsZXgtbmVnYXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MSFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1sZy1zdGFydHstbXMtZmxleC1wYWNrOnN0YXJ0IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1sZy1lbmR7LW1zLWZsZXgtcGFjazplbmQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1sZy1jZW50ZXJ7LW1zLWZsZXgtcGFjazpjZW50ZXIhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtbGctYmV0d2VlbnstbXMtZmxleC1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LWxnLWFyb3VuZHstbXMtZmxleC1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1sZy1zdGFydHstbXMtZmxleC1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLWxnLWVuZHstbXMtZmxleC1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbGctY2VudGVyey1tcy1mbGV4LWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbGctYmFzZWxpbmV7LW1zLWZsZXgtYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbGctc3RyZXRjaHstbXMtZmxleC1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1pdGVtczpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1sZy1zdGFydHstbXMtZmxleC1saW5lLXBhY2s6c3RhcnQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbGctZW5key1tcy1mbGV4LWxpbmUtcGFjazplbmQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWxnLWNlbnRlcnstbXMtZmxleC1saW5lLXBhY2s6Y2VudGVyIWltcG9ydGFudDthbGlnbi1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbGctYmV0d2VlbnstbXMtZmxleC1saW5lLXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1sZy1hcm91bmR7LW1zLWZsZXgtbGluZS1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1sZy1zdHJldGNoey1tcy1mbGV4LWxpbmUtcGFjazpzdHJldGNoIWltcG9ydGFudDthbGlnbi1jb250ZW50OnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1zZWxmLWxnLWF1dG97LW1zLWZsZXgtaXRlbS1hbGlnbjphdXRvIWltcG9ydGFudDthbGlnbi1zZWxmOmF1dG8haW1wb3J0YW50fS5hbGlnbi1zZWxmLWxnLXN0YXJ0ey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbGctZW5key1tcy1mbGV4LWl0ZW0tYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tc2VsZi1sZy1jZW50ZXJ7LW1zLWZsZXgtaXRlbS1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLXNlbGY6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tc2VsZi1sZy1iYXNlbGluZXstbXMtZmxleC1pdGVtLWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1zZWxmOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24tc2VsZi1sZy1zdHJldGNoey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpzdHJldGNoIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6MTIwMHB4KXsuZmxleC14bC1yb3d7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudH0uZmxleC14bC1jb2x1bW57LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudH0uZmxleC14bC1yb3ctcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudH0uZmxleC14bC1jb2x1bW4tcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudH0uZmxleC14bC13cmFwey1tcy1mbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAhaW1wb3J0YW50fS5mbGV4LXhsLW5vd3JhcHstbXMtZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnR9LmZsZXgteGwtd3JhcC1yZXZlcnNley1tcy1mbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudH0uZmxleC14bC1maWxsey1tcy1mbGV4OjEgMSBhdXRvIWltcG9ydGFudDtmbGV4OjEgMSBhdXRvIWltcG9ydGFudH0uZmxleC14bC1ncm93LTB7LW1zLWZsZXgtcG9zaXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1ncm93OjAhaW1wb3J0YW50fS5mbGV4LXhsLWdyb3ctMXstbXMtZmxleC1wb3NpdGl2ZToxIWltcG9ydGFudDtmbGV4LWdyb3c6MSFpbXBvcnRhbnR9LmZsZXgteGwtc2hyaW5rLTB7LW1zLWZsZXgtbmVnYXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MCFpbXBvcnRhbnR9LmZsZXgteGwtc2hyaW5rLTF7LW1zLWZsZXgtbmVnYXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MSFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC14bC1zdGFydHstbXMtZmxleC1wYWNrOnN0YXJ0IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC14bC1lbmR7LW1zLWZsZXgtcGFjazplbmQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC14bC1jZW50ZXJ7LW1zLWZsZXgtcGFjazpjZW50ZXIhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQteGwtYmV0d2VlbnstbXMtZmxleC1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXhsLWFyb3VuZHstbXMtZmxleC1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy14bC1zdGFydHstbXMtZmxleC1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXhsLWVuZHstbXMtZmxleC1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMteGwtY2VudGVyey1tcy1mbGV4LWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24taXRlbXMteGwtYmFzZWxpbmV7LW1zLWZsZXgtYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24taXRlbXMteGwtc3RyZXRjaHstbXMtZmxleC1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1pdGVtczpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tY29udGVudC14bC1zdGFydHstbXMtZmxleC1saW5lLXBhY2s6c3RhcnQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQteGwtZW5key1tcy1mbGV4LWxpbmUtcGFjazplbmQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXhsLWNlbnRlcnstbXMtZmxleC1saW5lLXBhY2s6Y2VudGVyIWltcG9ydGFudDthbGlnbi1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQteGwtYmV0d2VlbnstbXMtZmxleC1saW5lLXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uYWxpZ24tY29udGVudC14bC1hcm91bmR7LW1zLWZsZXgtbGluZS1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC14bC1zdHJldGNoey1tcy1mbGV4LWxpbmUtcGFjazpzdHJldGNoIWltcG9ydGFudDthbGlnbi1jb250ZW50OnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1zZWxmLXhsLWF1dG97LW1zLWZsZXgtaXRlbS1hbGlnbjphdXRvIWltcG9ydGFudDthbGlnbi1zZWxmOmF1dG8haW1wb3J0YW50fS5hbGlnbi1zZWxmLXhsLXN0YXJ0ey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLXNlbGYteGwtZW5key1tcy1mbGV4LWl0ZW0tYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tc2VsZi14bC1jZW50ZXJ7LW1zLWZsZXgtaXRlbS1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLXNlbGY6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tc2VsZi14bC1iYXNlbGluZXstbXMtZmxleC1pdGVtLWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1zZWxmOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24tc2VsZi14bC1zdHJldGNoey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpzdHJldGNoIWltcG9ydGFudH19LmZsb2F0LWxlZnR7ZmxvYXQ6bGVmdCFpbXBvcnRhbnR9LmZsb2F0LXJpZ2h0e2Zsb2F0OnJpZ2h0IWltcG9ydGFudH0uZmxvYXQtbm9uZXtmbG9hdDpub25lIWltcG9ydGFudH1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmZsb2F0LXNtLWxlZnR7ZmxvYXQ6bGVmdCFpbXBvcnRhbnR9LmZsb2F0LXNtLXJpZ2h0e2Zsb2F0OnJpZ2h0IWltcG9ydGFudH0uZmxvYXQtc20tbm9uZXtmbG9hdDpub25lIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6NzY4cHgpey5mbG9hdC1tZC1sZWZ0e2Zsb2F0OmxlZnQhaW1wb3J0YW50fS5mbG9hdC1tZC1yaWdodHtmbG9hdDpyaWdodCFpbXBvcnRhbnR9LmZsb2F0LW1kLW5vbmV7ZmxvYXQ6bm9uZSFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuZmxvYXQtbGctbGVmdHtmbG9hdDpsZWZ0IWltcG9ydGFudH0uZmxvYXQtbGctcmlnaHR7ZmxvYXQ6cmlnaHQhaW1wb3J0YW50fS5mbG9hdC1sZy1ub25le2Zsb2F0Om5vbmUhaW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5mbG9hdC14bC1sZWZ0e2Zsb2F0OmxlZnQhaW1wb3J0YW50fS5mbG9hdC14bC1yaWdodHtmbG9hdDpyaWdodCFpbXBvcnRhbnR9LmZsb2F0LXhsLW5vbmV7ZmxvYXQ6bm9uZSFpbXBvcnRhbnR9fS5vdmVyZmxvdy1hdXRve292ZXJmbG93OmF1dG8haW1wb3J0YW50fS5vdmVyZmxvdy1oaWRkZW57b3ZlcmZsb3c6aGlkZGVuIWltcG9ydGFudH0ucG9zaXRpb24tc3RhdGlje3Bvc2l0aW9uOnN0YXRpYyFpbXBvcnRhbnR9LnBvc2l0aW9uLXJlbGF0aXZle3Bvc2l0aW9uOnJlbGF0aXZlIWltcG9ydGFudH0ucG9zaXRpb24tYWJzb2x1dGV7cG9zaXRpb246YWJzb2x1dGUhaW1wb3J0YW50fS5wb3NpdGlvbi1maXhlZHtwb3NpdGlvbjpmaXhlZCFpbXBvcnRhbnR9LnBvc2l0aW9uLXN0aWNreXtwb3NpdGlvbjotd2Via2l0LXN0aWNreSFpbXBvcnRhbnQ7cG9zaXRpb246c3RpY2t5IWltcG9ydGFudH0uZml4ZWQtdG9we3Bvc2l0aW9uOmZpeGVkO3RvcDowO3JpZ2h0OjA7bGVmdDowO3otaW5kZXg6MTAzMH0uZml4ZWQtYm90dG9te3Bvc2l0aW9uOmZpeGVkO3JpZ2h0OjA7Ym90dG9tOjA7bGVmdDowO3otaW5kZXg6MTAzMH1Ac3VwcG9ydHMgKChwb3NpdGlvbjotd2Via2l0LXN0aWNreSkgb3IgKHBvc2l0aW9uOnN0aWNreSkpey5zdGlja3ktdG9we3Bvc2l0aW9uOi13ZWJraXQtc3RpY2t5O3Bvc2l0aW9uOnN0aWNreTt0b3A6MDt6LWluZGV4OjEwMjB9fS5zci1vbmx5e3Bvc2l0aW9uOmFic29sdXRlO3dpZHRoOjFweDtoZWlnaHQ6MXB4O3BhZGRpbmc6MDttYXJnaW46LTFweDtvdmVyZmxvdzpoaWRkZW47Y2xpcDpyZWN0KDAsMCwwLDApO3doaXRlLXNwYWNlOm5vd3JhcDtib3JkZXI6MH0uc3Itb25seS1mb2N1c2FibGU6YWN0aXZlLC5zci1vbmx5LWZvY3VzYWJsZTpmb2N1c3twb3NpdGlvbjpzdGF0aWM7d2lkdGg6YXV0bztoZWlnaHQ6YXV0bztvdmVyZmxvdzp2aXNpYmxlO2NsaXA6YXV0bzt3aGl0ZS1zcGFjZTpub3JtYWx9LnNoYWRvdy1zbXtib3gtc2hhZG93OjAgLjEyNXJlbSAuMjVyZW0gcmdiYSgwLDAsMCwuMDc1KSFpbXBvcnRhbnR9LnNoYWRvd3tib3gtc2hhZG93OjAgLjVyZW0gMXJlbSByZ2JhKDAsMCwwLC4xNSkhaW1wb3J0YW50fS5zaGFkb3ctbGd7Ym94LXNoYWRvdzowIDFyZW0gM3JlbSByZ2JhKDAsMCwwLC4xNzUpIWltcG9ydGFudH0uc2hhZG93LW5vbmV7Ym94LXNoYWRvdzpub25lIWltcG9ydGFudH0udy0yNXt3aWR0aDoyNSUhaW1wb3J0YW50fS53LTUwe3dpZHRoOjUwJSFpbXBvcnRhbnR9LnctNzV7d2lkdGg6NzUlIWltcG9ydGFudH0udy0xMDB7d2lkdGg6MTAwJSFpbXBvcnRhbnR9LnctYXV0b3t3aWR0aDphdXRvIWltcG9ydGFudH0uaC0yNXtoZWlnaHQ6MjUlIWltcG9ydGFudH0uaC01MHtoZWlnaHQ6NTAlIWltcG9ydGFudH0uaC03NXtoZWlnaHQ6NzUlIWltcG9ydGFudH0uaC0xMDB7aGVpZ2h0OjEwMCUhaW1wb3J0YW50fS5oLWF1dG97aGVpZ2h0OmF1dG8haW1wb3J0YW50fS5tdy0xMDB7bWF4LXdpZHRoOjEwMCUhaW1wb3J0YW50fS5taC0xMDB7bWF4LWhlaWdodDoxMDAlIWltcG9ydGFudH0ubWluLXZ3LTEwMHttaW4td2lkdGg6MTAwdnchaW1wb3J0YW50fS5taW4tdmgtMTAwe21pbi1oZWlnaHQ6MTAwdmghaW1wb3J0YW50fS52dy0xMDB7d2lkdGg6MTAwdnchaW1wb3J0YW50fS52aC0xMDB7aGVpZ2h0OjEwMHZoIWltcG9ydGFudH0uc3RyZXRjaGVkLWxpbms6OmFmdGVye3Bvc2l0aW9uOmFic29sdXRlO3RvcDowO3JpZ2h0OjA7Ym90dG9tOjA7bGVmdDowO3otaW5kZXg6MTtwb2ludGVyLWV2ZW50czphdXRvO2NvbnRlbnQ6IiI7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLDApfS5tLTB7bWFyZ2luOjAhaW1wb3J0YW50fS5tdC0wLC5teS0we21hcmdpbi10b3A6MCFpbXBvcnRhbnR9Lm1yLTAsLm14LTB7bWFyZ2luLXJpZ2h0OjAhaW1wb3J0YW50fS5tYi0wLC5teS0we21hcmdpbi1ib3R0b206MCFpbXBvcnRhbnR9Lm1sLTAsLm14LTB7bWFyZ2luLWxlZnQ6MCFpbXBvcnRhbnR9Lm0tMXttYXJnaW46LjI1cmVtIWltcG9ydGFudH0ubXQtMSwubXktMXttYXJnaW4tdG9wOi4yNXJlbSFpbXBvcnRhbnR9Lm1yLTEsLm14LTF7bWFyZ2luLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9Lm1iLTEsLm15LTF7bWFyZ2luLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5tbC0xLC5teC0xe21hcmdpbi1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9Lm0tMnttYXJnaW46LjVyZW0haW1wb3J0YW50fS5tdC0yLC5teS0ye21hcmdpbi10b3A6LjVyZW0haW1wb3J0YW50fS5tci0yLC5teC0ye21hcmdpbi1yaWdodDouNXJlbSFpbXBvcnRhbnR9Lm1iLTIsLm15LTJ7bWFyZ2luLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9Lm1sLTIsLm14LTJ7bWFyZ2luLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5tLTN7bWFyZ2luOjFyZW0haW1wb3J0YW50fS5tdC0zLC5teS0ze21hcmdpbi10b3A6MXJlbSFpbXBvcnRhbnR9Lm1yLTMsLm14LTN7bWFyZ2luLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5tYi0zLC5teS0ze21hcmdpbi1ib3R0b206MXJlbSFpbXBvcnRhbnR9Lm1sLTMsLm14LTN7bWFyZ2luLWxlZnQ6MXJlbSFpbXBvcnRhbnR9Lm0tNHttYXJnaW46MS41cmVtIWltcG9ydGFudH0ubXQtNCwubXktNHttYXJnaW4tdG9wOjEuNXJlbSFpbXBvcnRhbnR9Lm1yLTQsLm14LTR7bWFyZ2luLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9Lm1iLTQsLm15LTR7bWFyZ2luLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5tbC00LC5teC00e21hcmdpbi1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9Lm0tNXttYXJnaW46M3JlbSFpbXBvcnRhbnR9Lm10LTUsLm15LTV7bWFyZ2luLXRvcDozcmVtIWltcG9ydGFudH0ubXItNSwubXgtNXttYXJnaW4tcmlnaHQ6M3JlbSFpbXBvcnRhbnR9Lm1iLTUsLm15LTV7bWFyZ2luLWJvdHRvbTozcmVtIWltcG9ydGFudH0ubWwtNSwubXgtNXttYXJnaW4tbGVmdDozcmVtIWltcG9ydGFudH0ucC0we3BhZGRpbmc6MCFpbXBvcnRhbnR9LnB0LTAsLnB5LTB7cGFkZGluZy10b3A6MCFpbXBvcnRhbnR9LnByLTAsLnB4LTB7cGFkZGluZy1yaWdodDowIWltcG9ydGFudH0ucGItMCwucHktMHtwYWRkaW5nLWJvdHRvbTowIWltcG9ydGFudH0ucGwtMCwucHgtMHtwYWRkaW5nLWxlZnQ6MCFpbXBvcnRhbnR9LnAtMXtwYWRkaW5nOi4yNXJlbSFpbXBvcnRhbnR9LnB0LTEsLnB5LTF7cGFkZGluZy10b3A6LjI1cmVtIWltcG9ydGFudH0ucHItMSwucHgtMXtwYWRkaW5nLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9LnBiLTEsLnB5LTF7cGFkZGluZy1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ucGwtMSwucHgtMXtwYWRkaW5nLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ucC0ye3BhZGRpbmc6LjVyZW0haW1wb3J0YW50fS5wdC0yLC5weS0ye3BhZGRpbmctdG9wOi41cmVtIWltcG9ydGFudH0ucHItMiwucHgtMntwYWRkaW5nLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ucGItMiwucHktMntwYWRkaW5nLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9LnBsLTIsLnB4LTJ7cGFkZGluZy1sZWZ0Oi41cmVtIWltcG9ydGFudH0ucC0ze3BhZGRpbmc6MXJlbSFpbXBvcnRhbnR9LnB0LTMsLnB5LTN7cGFkZGluZy10b3A6MXJlbSFpbXBvcnRhbnR9LnByLTMsLnB4LTN7cGFkZGluZy1yaWdodDoxcmVtIWltcG9ydGFudH0ucGItMywucHktM3twYWRkaW5nLWJvdHRvbToxcmVtIWltcG9ydGFudH0ucGwtMywucHgtM3twYWRkaW5nLWxlZnQ6MXJlbSFpbXBvcnRhbnR9LnAtNHtwYWRkaW5nOjEuNXJlbSFpbXBvcnRhbnR9LnB0LTQsLnB5LTR7cGFkZGluZy10b3A6MS41cmVtIWltcG9ydGFudH0ucHItNCwucHgtNHtwYWRkaW5nLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9LnBiLTQsLnB5LTR7cGFkZGluZy1ib3R0b206MS41cmVtIWltcG9ydGFudH0ucGwtNCwucHgtNHtwYWRkaW5nLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ucC01e3BhZGRpbmc6M3JlbSFpbXBvcnRhbnR9LnB0LTUsLnB5LTV7cGFkZGluZy10b3A6M3JlbSFpbXBvcnRhbnR9LnByLTUsLnB4LTV7cGFkZGluZy1yaWdodDozcmVtIWltcG9ydGFudH0ucGItNSwucHktNXtwYWRkaW5nLWJvdHRvbTozcmVtIWltcG9ydGFudH0ucGwtNSwucHgtNXtwYWRkaW5nLWxlZnQ6M3JlbSFpbXBvcnRhbnR9Lm0tbjF7bWFyZ2luOi0uMjVyZW0haW1wb3J0YW50fS5tdC1uMSwubXktbjF7bWFyZ2luLXRvcDotLjI1cmVtIWltcG9ydGFudH0ubXItbjEsLm14LW4xe21hcmdpbi1yaWdodDotLjI1cmVtIWltcG9ydGFudH0ubWItbjEsLm15LW4xe21hcmdpbi1ib3R0b206LS4yNXJlbSFpbXBvcnRhbnR9Lm1sLW4xLC5teC1uMXttYXJnaW4tbGVmdDotLjI1cmVtIWltcG9ydGFudH0ubS1uMnttYXJnaW46LS41cmVtIWltcG9ydGFudH0ubXQtbjIsLm15LW4ye21hcmdpbi10b3A6LS41cmVtIWltcG9ydGFudH0ubXItbjIsLm14LW4ye21hcmdpbi1yaWdodDotLjVyZW0haW1wb3J0YW50fS5tYi1uMiwubXktbjJ7bWFyZ2luLWJvdHRvbTotLjVyZW0haW1wb3J0YW50fS5tbC1uMiwubXgtbjJ7bWFyZ2luLWxlZnQ6LS41cmVtIWltcG9ydGFudH0ubS1uM3ttYXJnaW46LTFyZW0haW1wb3J0YW50fS5tdC1uMywubXktbjN7bWFyZ2luLXRvcDotMXJlbSFpbXBvcnRhbnR9Lm1yLW4zLC5teC1uM3ttYXJnaW4tcmlnaHQ6LTFyZW0haW1wb3J0YW50fS5tYi1uMywubXktbjN7bWFyZ2luLWJvdHRvbTotMXJlbSFpbXBvcnRhbnR9Lm1sLW4zLC5teC1uM3ttYXJnaW4tbGVmdDotMXJlbSFpbXBvcnRhbnR9Lm0tbjR7bWFyZ2luOi0xLjVyZW0haW1wb3J0YW50fS5tdC1uNCwubXktbjR7bWFyZ2luLXRvcDotMS41cmVtIWltcG9ydGFudH0ubXItbjQsLm14LW40e21hcmdpbi1yaWdodDotMS41cmVtIWltcG9ydGFudH0ubWItbjQsLm15LW40e21hcmdpbi1ib3R0b206LTEuNXJlbSFpbXBvcnRhbnR9Lm1sLW40LC5teC1uNHttYXJnaW4tbGVmdDotMS41cmVtIWltcG9ydGFudH0ubS1uNXttYXJnaW46LTNyZW0haW1wb3J0YW50fS5tdC1uNSwubXktbjV7bWFyZ2luLXRvcDotM3JlbSFpbXBvcnRhbnR9Lm1yLW41LC5teC1uNXttYXJnaW4tcmlnaHQ6LTNyZW0haW1wb3J0YW50fS5tYi1uNSwubXktbjV7bWFyZ2luLWJvdHRvbTotM3JlbSFpbXBvcnRhbnR9Lm1sLW41LC5teC1uNXttYXJnaW4tbGVmdDotM3JlbSFpbXBvcnRhbnR9Lm0tYXV0b3ttYXJnaW46YXV0byFpbXBvcnRhbnR9Lm10LWF1dG8sLm15LWF1dG97bWFyZ2luLXRvcDphdXRvIWltcG9ydGFudH0ubXItYXV0bywubXgtYXV0b3ttYXJnaW4tcmlnaHQ6YXV0byFpbXBvcnRhbnR9Lm1iLWF1dG8sLm15LWF1dG97bWFyZ2luLWJvdHRvbTphdXRvIWltcG9ydGFudH0ubWwtYXV0bywubXgtYXV0b3ttYXJnaW4tbGVmdDphdXRvIWltcG9ydGFudH1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7Lm0tc20tMHttYXJnaW46MCFpbXBvcnRhbnR9Lm10LXNtLTAsLm15LXNtLTB7bWFyZ2luLXRvcDowIWltcG9ydGFudH0ubXItc20tMCwubXgtc20tMHttYXJnaW4tcmlnaHQ6MCFpbXBvcnRhbnR9Lm1iLXNtLTAsLm15LXNtLTB7bWFyZ2luLWJvdHRvbTowIWltcG9ydGFudH0ubWwtc20tMCwubXgtc20tMHttYXJnaW4tbGVmdDowIWltcG9ydGFudH0ubS1zbS0xe21hcmdpbjouMjVyZW0haW1wb3J0YW50fS5tdC1zbS0xLC5teS1zbS0xe21hcmdpbi10b3A6LjI1cmVtIWltcG9ydGFudH0ubXItc20tMSwubXgtc20tMXttYXJnaW4tcmlnaHQ6LjI1cmVtIWltcG9ydGFudH0ubWItc20tMSwubXktc20tMXttYXJnaW4tYm90dG9tOi4yNXJlbSFpbXBvcnRhbnR9Lm1sLXNtLTEsLm14LXNtLTF7bWFyZ2luLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ubS1zbS0ye21hcmdpbjouNXJlbSFpbXBvcnRhbnR9Lm10LXNtLTIsLm15LXNtLTJ7bWFyZ2luLXRvcDouNXJlbSFpbXBvcnRhbnR9Lm1yLXNtLTIsLm14LXNtLTJ7bWFyZ2luLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ubWItc20tMiwubXktc20tMnttYXJnaW4tYm90dG9tOi41cmVtIWltcG9ydGFudH0ubWwtc20tMiwubXgtc20tMnttYXJnaW4tbGVmdDouNXJlbSFpbXBvcnRhbnR9Lm0tc20tM3ttYXJnaW46MXJlbSFpbXBvcnRhbnR9Lm10LXNtLTMsLm15LXNtLTN7bWFyZ2luLXRvcDoxcmVtIWltcG9ydGFudH0ubXItc20tMywubXgtc20tM3ttYXJnaW4tcmlnaHQ6MXJlbSFpbXBvcnRhbnR9Lm1iLXNtLTMsLm15LXNtLTN7bWFyZ2luLWJvdHRvbToxcmVtIWltcG9ydGFudH0ubWwtc20tMywubXgtc20tM3ttYXJnaW4tbGVmdDoxcmVtIWltcG9ydGFudH0ubS1zbS00e21hcmdpbjoxLjVyZW0haW1wb3J0YW50fS5tdC1zbS00LC5teS1zbS00e21hcmdpbi10b3A6MS41cmVtIWltcG9ydGFudH0ubXItc20tNCwubXgtc20tNHttYXJnaW4tcmlnaHQ6MS41cmVtIWltcG9ydGFudH0ubWItc20tNCwubXktc20tNHttYXJnaW4tYm90dG9tOjEuNXJlbSFpbXBvcnRhbnR9Lm1sLXNtLTQsLm14LXNtLTR7bWFyZ2luLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ubS1zbS01e21hcmdpbjozcmVtIWltcG9ydGFudH0ubXQtc20tNSwubXktc20tNXttYXJnaW4tdG9wOjNyZW0haW1wb3J0YW50fS5tci1zbS01LC5teC1zbS01e21hcmdpbi1yaWdodDozcmVtIWltcG9ydGFudH0ubWItc20tNSwubXktc20tNXttYXJnaW4tYm90dG9tOjNyZW0haW1wb3J0YW50fS5tbC1zbS01LC5teC1zbS01e21hcmdpbi1sZWZ0OjNyZW0haW1wb3J0YW50fS5wLXNtLTB7cGFkZGluZzowIWltcG9ydGFudH0ucHQtc20tMCwucHktc20tMHtwYWRkaW5nLXRvcDowIWltcG9ydGFudH0ucHItc20tMCwucHgtc20tMHtwYWRkaW5nLXJpZ2h0OjAhaW1wb3J0YW50fS5wYi1zbS0wLC5weS1zbS0we3BhZGRpbmctYm90dG9tOjAhaW1wb3J0YW50fS5wbC1zbS0wLC5weC1zbS0we3BhZGRpbmctbGVmdDowIWltcG9ydGFudH0ucC1zbS0xe3BhZGRpbmc6LjI1cmVtIWltcG9ydGFudH0ucHQtc20tMSwucHktc20tMXtwYWRkaW5nLXRvcDouMjVyZW0haW1wb3J0YW50fS5wci1zbS0xLC5weC1zbS0xe3BhZGRpbmctcmlnaHQ6LjI1cmVtIWltcG9ydGFudH0ucGItc20tMSwucHktc20tMXtwYWRkaW5nLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5wbC1zbS0xLC5weC1zbS0xe3BhZGRpbmctbGVmdDouMjVyZW0haW1wb3J0YW50fS5wLXNtLTJ7cGFkZGluZzouNXJlbSFpbXBvcnRhbnR9LnB0LXNtLTIsLnB5LXNtLTJ7cGFkZGluZy10b3A6LjVyZW0haW1wb3J0YW50fS5wci1zbS0yLC5weC1zbS0ye3BhZGRpbmctcmlnaHQ6LjVyZW0haW1wb3J0YW50fS5wYi1zbS0yLC5weS1zbS0ye3BhZGRpbmctYm90dG9tOi41cmVtIWltcG9ydGFudH0ucGwtc20tMiwucHgtc20tMntwYWRkaW5nLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5wLXNtLTN7cGFkZGluZzoxcmVtIWltcG9ydGFudH0ucHQtc20tMywucHktc20tM3twYWRkaW5nLXRvcDoxcmVtIWltcG9ydGFudH0ucHItc20tMywucHgtc20tM3twYWRkaW5nLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5wYi1zbS0zLC5weS1zbS0ze3BhZGRpbmctYm90dG9tOjFyZW0haW1wb3J0YW50fS5wbC1zbS0zLC5weC1zbS0ze3BhZGRpbmctbGVmdDoxcmVtIWltcG9ydGFudH0ucC1zbS00e3BhZGRpbmc6MS41cmVtIWltcG9ydGFudH0ucHQtc20tNCwucHktc20tNHtwYWRkaW5nLXRvcDoxLjVyZW0haW1wb3J0YW50fS5wci1zbS00LC5weC1zbS00e3BhZGRpbmctcmlnaHQ6MS41cmVtIWltcG9ydGFudH0ucGItc20tNCwucHktc20tNHtwYWRkaW5nLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5wbC1zbS00LC5weC1zbS00e3BhZGRpbmctbGVmdDoxLjVyZW0haW1wb3J0YW50fS5wLXNtLTV7cGFkZGluZzozcmVtIWltcG9ydGFudH0ucHQtc20tNSwucHktc20tNXtwYWRkaW5nLXRvcDozcmVtIWltcG9ydGFudH0ucHItc20tNSwucHgtc20tNXtwYWRkaW5nLXJpZ2h0OjNyZW0haW1wb3J0YW50fS5wYi1zbS01LC5weS1zbS01e3BhZGRpbmctYm90dG9tOjNyZW0haW1wb3J0YW50fS5wbC1zbS01LC5weC1zbS01e3BhZGRpbmctbGVmdDozcmVtIWltcG9ydGFudH0ubS1zbS1uMXttYXJnaW46LS4yNXJlbSFpbXBvcnRhbnR9Lm10LXNtLW4xLC5teS1zbS1uMXttYXJnaW4tdG9wOi0uMjVyZW0haW1wb3J0YW50fS5tci1zbS1uMSwubXgtc20tbjF7bWFyZ2luLXJpZ2h0Oi0uMjVyZW0haW1wb3J0YW50fS5tYi1zbS1uMSwubXktc20tbjF7bWFyZ2luLWJvdHRvbTotLjI1cmVtIWltcG9ydGFudH0ubWwtc20tbjEsLm14LXNtLW4xe21hcmdpbi1sZWZ0Oi0uMjVyZW0haW1wb3J0YW50fS5tLXNtLW4ye21hcmdpbjotLjVyZW0haW1wb3J0YW50fS5tdC1zbS1uMiwubXktc20tbjJ7bWFyZ2luLXRvcDotLjVyZW0haW1wb3J0YW50fS5tci1zbS1uMiwubXgtc20tbjJ7bWFyZ2luLXJpZ2h0Oi0uNXJlbSFpbXBvcnRhbnR9Lm1iLXNtLW4yLC5teS1zbS1uMnttYXJnaW4tYm90dG9tOi0uNXJlbSFpbXBvcnRhbnR9Lm1sLXNtLW4yLC5teC1zbS1uMnttYXJnaW4tbGVmdDotLjVyZW0haW1wb3J0YW50fS5tLXNtLW4ze21hcmdpbjotMXJlbSFpbXBvcnRhbnR9Lm10LXNtLW4zLC5teS1zbS1uM3ttYXJnaW4tdG9wOi0xcmVtIWltcG9ydGFudH0ubXItc20tbjMsLm14LXNtLW4ze21hcmdpbi1yaWdodDotMXJlbSFpbXBvcnRhbnR9Lm1iLXNtLW4zLC5teS1zbS1uM3ttYXJnaW4tYm90dG9tOi0xcmVtIWltcG9ydGFudH0ubWwtc20tbjMsLm14LXNtLW4ze21hcmdpbi1sZWZ0Oi0xcmVtIWltcG9ydGFudH0ubS1zbS1uNHttYXJnaW46LTEuNXJlbSFpbXBvcnRhbnR9Lm10LXNtLW40LC5teS1zbS1uNHttYXJnaW4tdG9wOi0xLjVyZW0haW1wb3J0YW50fS5tci1zbS1uNCwubXgtc20tbjR7bWFyZ2luLXJpZ2h0Oi0xLjVyZW0haW1wb3J0YW50fS5tYi1zbS1uNCwubXktc20tbjR7bWFyZ2luLWJvdHRvbTotMS41cmVtIWltcG9ydGFudH0ubWwtc20tbjQsLm14LXNtLW40e21hcmdpbi1sZWZ0Oi0xLjVyZW0haW1wb3J0YW50fS5tLXNtLW41e21hcmdpbjotM3JlbSFpbXBvcnRhbnR9Lm10LXNtLW41LC5teS1zbS1uNXttYXJnaW4tdG9wOi0zcmVtIWltcG9ydGFudH0ubXItc20tbjUsLm14LXNtLW41e21hcmdpbi1yaWdodDotM3JlbSFpbXBvcnRhbnR9Lm1iLXNtLW41LC5teS1zbS1uNXttYXJnaW4tYm90dG9tOi0zcmVtIWltcG9ydGFudH0ubWwtc20tbjUsLm14LXNtLW41e21hcmdpbi1sZWZ0Oi0zcmVtIWltcG9ydGFudH0ubS1zbS1hdXRve21hcmdpbjphdXRvIWltcG9ydGFudH0ubXQtc20tYXV0bywubXktc20tYXV0b3ttYXJnaW4tdG9wOmF1dG8haW1wb3J0YW50fS5tci1zbS1hdXRvLC5teC1zbS1hdXRve21hcmdpbi1yaWdodDphdXRvIWltcG9ydGFudH0ubWItc20tYXV0bywubXktc20tYXV0b3ttYXJnaW4tYm90dG9tOmF1dG8haW1wb3J0YW50fS5tbC1zbS1hdXRvLC5teC1zbS1hdXRve21hcmdpbi1sZWZ0OmF1dG8haW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7Lm0tbWQtMHttYXJnaW46MCFpbXBvcnRhbnR9Lm10LW1kLTAsLm15LW1kLTB7bWFyZ2luLXRvcDowIWltcG9ydGFudH0ubXItbWQtMCwubXgtbWQtMHttYXJnaW4tcmlnaHQ6MCFpbXBvcnRhbnR9Lm1iLW1kLTAsLm15LW1kLTB7bWFyZ2luLWJvdHRvbTowIWltcG9ydGFudH0ubWwtbWQtMCwubXgtbWQtMHttYXJnaW4tbGVmdDowIWltcG9ydGFudH0ubS1tZC0xe21hcmdpbjouMjVyZW0haW1wb3J0YW50fS5tdC1tZC0xLC5teS1tZC0xe21hcmdpbi10b3A6LjI1cmVtIWltcG9ydGFudH0ubXItbWQtMSwubXgtbWQtMXttYXJnaW4tcmlnaHQ6LjI1cmVtIWltcG9ydGFudH0ubWItbWQtMSwubXktbWQtMXttYXJnaW4tYm90dG9tOi4yNXJlbSFpbXBvcnRhbnR9Lm1sLW1kLTEsLm14LW1kLTF7bWFyZ2luLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ubS1tZC0ye21hcmdpbjouNXJlbSFpbXBvcnRhbnR9Lm10LW1kLTIsLm15LW1kLTJ7bWFyZ2luLXRvcDouNXJlbSFpbXBvcnRhbnR9Lm1yLW1kLTIsLm14LW1kLTJ7bWFyZ2luLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ubWItbWQtMiwubXktbWQtMnttYXJnaW4tYm90dG9tOi41cmVtIWltcG9ydGFudH0ubWwtbWQtMiwubXgtbWQtMnttYXJnaW4tbGVmdDouNXJlbSFpbXBvcnRhbnR9Lm0tbWQtM3ttYXJnaW46MXJlbSFpbXBvcnRhbnR9Lm10LW1kLTMsLm15LW1kLTN7bWFyZ2luLXRvcDoxcmVtIWltcG9ydGFudH0ubXItbWQtMywubXgtbWQtM3ttYXJnaW4tcmlnaHQ6MXJlbSFpbXBvcnRhbnR9Lm1iLW1kLTMsLm15LW1kLTN7bWFyZ2luLWJvdHRvbToxcmVtIWltcG9ydGFudH0ubWwtbWQtMywubXgtbWQtM3ttYXJnaW4tbGVmdDoxcmVtIWltcG9ydGFudH0ubS1tZC00e21hcmdpbjoxLjVyZW0haW1wb3J0YW50fS5tdC1tZC00LC5teS1tZC00e21hcmdpbi10b3A6MS41cmVtIWltcG9ydGFudH0ubXItbWQtNCwubXgtbWQtNHttYXJnaW4tcmlnaHQ6MS41cmVtIWltcG9ydGFudH0ubWItbWQtNCwubXktbWQtNHttYXJnaW4tYm90dG9tOjEuNXJlbSFpbXBvcnRhbnR9Lm1sLW1kLTQsLm14LW1kLTR7bWFyZ2luLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ubS1tZC01e21hcmdpbjozcmVtIWltcG9ydGFudH0ubXQtbWQtNSwubXktbWQtNXttYXJnaW4tdG9wOjNyZW0haW1wb3J0YW50fS5tci1tZC01LC5teC1tZC01e21hcmdpbi1yaWdodDozcmVtIWltcG9ydGFudH0ubWItbWQtNSwubXktbWQtNXttYXJnaW4tYm90dG9tOjNyZW0haW1wb3J0YW50fS5tbC1tZC01LC5teC1tZC01e21hcmdpbi1sZWZ0OjNyZW0haW1wb3J0YW50fS5wLW1kLTB7cGFkZGluZzowIWltcG9ydGFudH0ucHQtbWQtMCwucHktbWQtMHtwYWRkaW5nLXRvcDowIWltcG9ydGFudH0ucHItbWQtMCwucHgtbWQtMHtwYWRkaW5nLXJpZ2h0OjAhaW1wb3J0YW50fS5wYi1tZC0wLC5weS1tZC0we3BhZGRpbmctYm90dG9tOjAhaW1wb3J0YW50fS5wbC1tZC0wLC5weC1tZC0we3BhZGRpbmctbGVmdDowIWltcG9ydGFudH0ucC1tZC0xe3BhZGRpbmc6LjI1cmVtIWltcG9ydGFudH0ucHQtbWQtMSwucHktbWQtMXtwYWRkaW5nLXRvcDouMjVyZW0haW1wb3J0YW50fS5wci1tZC0xLC5weC1tZC0xe3BhZGRpbmctcmlnaHQ6LjI1cmVtIWltcG9ydGFudH0ucGItbWQtMSwucHktbWQtMXtwYWRkaW5nLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5wbC1tZC0xLC5weC1tZC0xe3BhZGRpbmctbGVmdDouMjVyZW0haW1wb3J0YW50fS5wLW1kLTJ7cGFkZGluZzouNXJlbSFpbXBvcnRhbnR9LnB0LW1kLTIsLnB5LW1kLTJ7cGFkZGluZy10b3A6LjVyZW0haW1wb3J0YW50fS5wci1tZC0yLC5weC1tZC0ye3BhZGRpbmctcmlnaHQ6LjVyZW0haW1wb3J0YW50fS5wYi1tZC0yLC5weS1tZC0ye3BhZGRpbmctYm90dG9tOi41cmVtIWltcG9ydGFudH0ucGwtbWQtMiwucHgtbWQtMntwYWRkaW5nLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5wLW1kLTN7cGFkZGluZzoxcmVtIWltcG9ydGFudH0ucHQtbWQtMywucHktbWQtM3twYWRkaW5nLXRvcDoxcmVtIWltcG9ydGFudH0ucHItbWQtMywucHgtbWQtM3twYWRkaW5nLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5wYi1tZC0zLC5weS1tZC0ze3BhZGRpbmctYm90dG9tOjFyZW0haW1wb3J0YW50fS5wbC1tZC0zLC5weC1tZC0ze3BhZGRpbmctbGVmdDoxcmVtIWltcG9ydGFudH0ucC1tZC00e3BhZGRpbmc6MS41cmVtIWltcG9ydGFudH0ucHQtbWQtNCwucHktbWQtNHtwYWRkaW5nLXRvcDoxLjVyZW0haW1wb3J0YW50fS5wci1tZC00LC5weC1tZC00e3BhZGRpbmctcmlnaHQ6MS41cmVtIWltcG9ydGFudH0ucGItbWQtNCwucHktbWQtNHtwYWRkaW5nLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5wbC1tZC00LC5weC1tZC00e3BhZGRpbmctbGVmdDoxLjVyZW0haW1wb3J0YW50fS5wLW1kLTV7cGFkZGluZzozcmVtIWltcG9ydGFudH0ucHQtbWQtNSwucHktbWQtNXtwYWRkaW5nLXRvcDozcmVtIWltcG9ydGFudH0ucHItbWQtNSwucHgtbWQtNXtwYWRkaW5nLXJpZ2h0OjNyZW0haW1wb3J0YW50fS5wYi1tZC01LC5weS1tZC01e3BhZGRpbmctYm90dG9tOjNyZW0haW1wb3J0YW50fS5wbC1tZC01LC5weC1tZC01e3BhZGRpbmctbGVmdDozcmVtIWltcG9ydGFudH0ubS1tZC1uMXttYXJnaW46LS4yNXJlbSFpbXBvcnRhbnR9Lm10LW1kLW4xLC5teS1tZC1uMXttYXJnaW4tdG9wOi0uMjVyZW0haW1wb3J0YW50fS5tci1tZC1uMSwubXgtbWQtbjF7bWFyZ2luLXJpZ2h0Oi0uMjVyZW0haW1wb3J0YW50fS5tYi1tZC1uMSwubXktbWQtbjF7bWFyZ2luLWJvdHRvbTotLjI1cmVtIWltcG9ydGFudH0ubWwtbWQtbjEsLm14LW1kLW4xe21hcmdpbi1sZWZ0Oi0uMjVyZW0haW1wb3J0YW50fS5tLW1kLW4ye21hcmdpbjotLjVyZW0haW1wb3J0YW50fS5tdC1tZC1uMiwubXktbWQtbjJ7bWFyZ2luLXRvcDotLjVyZW0haW1wb3J0YW50fS5tci1tZC1uMiwubXgtbWQtbjJ7bWFyZ2luLXJpZ2h0Oi0uNXJlbSFpbXBvcnRhbnR9Lm1iLW1kLW4yLC5teS1tZC1uMnttYXJnaW4tYm90dG9tOi0uNXJlbSFpbXBvcnRhbnR9Lm1sLW1kLW4yLC5teC1tZC1uMnttYXJnaW4tbGVmdDotLjVyZW0haW1wb3J0YW50fS5tLW1kLW4ze21hcmdpbjotMXJlbSFpbXBvcnRhbnR9Lm10LW1kLW4zLC5teS1tZC1uM3ttYXJnaW4tdG9wOi0xcmVtIWltcG9ydGFudH0ubXItbWQtbjMsLm14LW1kLW4ze21hcmdpbi1yaWdodDotMXJlbSFpbXBvcnRhbnR9Lm1iLW1kLW4zLC5teS1tZC1uM3ttYXJnaW4tYm90dG9tOi0xcmVtIWltcG9ydGFudH0ubWwtbWQtbjMsLm14LW1kLW4ze21hcmdpbi1sZWZ0Oi0xcmVtIWltcG9ydGFudH0ubS1tZC1uNHttYXJnaW46LTEuNXJlbSFpbXBvcnRhbnR9Lm10LW1kLW40LC5teS1tZC1uNHttYXJnaW4tdG9wOi0xLjVyZW0haW1wb3J0YW50fS5tci1tZC1uNCwubXgtbWQtbjR7bWFyZ2luLXJpZ2h0Oi0xLjVyZW0haW1wb3J0YW50fS5tYi1tZC1uNCwubXktbWQtbjR7bWFyZ2luLWJvdHRvbTotMS41cmVtIWltcG9ydGFudH0ubWwtbWQtbjQsLm14LW1kLW40e21hcmdpbi1sZWZ0Oi0xLjVyZW0haW1wb3J0YW50fS5tLW1kLW41e21hcmdpbjotM3JlbSFpbXBvcnRhbnR9Lm10LW1kLW41LC5teS1tZC1uNXttYXJnaW4tdG9wOi0zcmVtIWltcG9ydGFudH0ubXItbWQtbjUsLm14LW1kLW41e21hcmdpbi1yaWdodDotM3JlbSFpbXBvcnRhbnR9Lm1iLW1kLW41LC5teS1tZC1uNXttYXJnaW4tYm90dG9tOi0zcmVtIWltcG9ydGFudH0ubWwtbWQtbjUsLm14LW1kLW41e21hcmdpbi1sZWZ0Oi0zcmVtIWltcG9ydGFudH0ubS1tZC1hdXRve21hcmdpbjphdXRvIWltcG9ydGFudH0ubXQtbWQtYXV0bywubXktbWQtYXV0b3ttYXJnaW4tdG9wOmF1dG8haW1wb3J0YW50fS5tci1tZC1hdXRvLC5teC1tZC1hdXRve21hcmdpbi1yaWdodDphdXRvIWltcG9ydGFudH0ubWItbWQtYXV0bywubXktbWQtYXV0b3ttYXJnaW4tYm90dG9tOmF1dG8haW1wb3J0YW50fS5tbC1tZC1hdXRvLC5teC1tZC1hdXRve21hcmdpbi1sZWZ0OmF1dG8haW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDo5OTJweCl7Lm0tbGctMHttYXJnaW46MCFpbXBvcnRhbnR9Lm10LWxnLTAsLm15LWxnLTB7bWFyZ2luLXRvcDowIWltcG9ydGFudH0ubXItbGctMCwubXgtbGctMHttYXJnaW4tcmlnaHQ6MCFpbXBvcnRhbnR9Lm1iLWxnLTAsLm15LWxnLTB7bWFyZ2luLWJvdHRvbTowIWltcG9ydGFudH0ubWwtbGctMCwubXgtbGctMHttYXJnaW4tbGVmdDowIWltcG9ydGFudH0ubS1sZy0xe21hcmdpbjouMjVyZW0haW1wb3J0YW50fS5tdC1sZy0xLC5teS1sZy0xe21hcmdpbi10b3A6LjI1cmVtIWltcG9ydGFudH0ubXItbGctMSwubXgtbGctMXttYXJnaW4tcmlnaHQ6LjI1cmVtIWltcG9ydGFudH0ubWItbGctMSwubXktbGctMXttYXJnaW4tYm90dG9tOi4yNXJlbSFpbXBvcnRhbnR9Lm1sLWxnLTEsLm14LWxnLTF7bWFyZ2luLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ubS1sZy0ye21hcmdpbjouNXJlbSFpbXBvcnRhbnR9Lm10LWxnLTIsLm15LWxnLTJ7bWFyZ2luLXRvcDouNXJlbSFpbXBvcnRhbnR9Lm1yLWxnLTIsLm14LWxnLTJ7bWFyZ2luLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ubWItbGctMiwubXktbGctMnttYXJnaW4tYm90dG9tOi41cmVtIWltcG9ydGFudH0ubWwtbGctMiwubXgtbGctMnttYXJnaW4tbGVmdDouNXJlbSFpbXBvcnRhbnR9Lm0tbGctM3ttYXJnaW46MXJlbSFpbXBvcnRhbnR9Lm10LWxnLTMsLm15LWxnLTN7bWFyZ2luLXRvcDoxcmVtIWltcG9ydGFudH0ubXItbGctMywubXgtbGctM3ttYXJnaW4tcmlnaHQ6MXJlbSFpbXBvcnRhbnR9Lm1iLWxnLTMsLm15LWxnLTN7bWFyZ2luLWJvdHRvbToxcmVtIWltcG9ydGFudH0ubWwtbGctMywubXgtbGctM3ttYXJnaW4tbGVmdDoxcmVtIWltcG9ydGFudH0ubS1sZy00e21hcmdpbjoxLjVyZW0haW1wb3J0YW50fS5tdC1sZy00LC5teS1sZy00e21hcmdpbi10b3A6MS41cmVtIWltcG9ydGFudH0ubXItbGctNCwubXgtbGctNHttYXJnaW4tcmlnaHQ6MS41cmVtIWltcG9ydGFudH0ubWItbGctNCwubXktbGctNHttYXJnaW4tYm90dG9tOjEuNXJlbSFpbXBvcnRhbnR9Lm1sLWxnLTQsLm14LWxnLTR7bWFyZ2luLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ubS1sZy01e21hcmdpbjozcmVtIWltcG9ydGFudH0ubXQtbGctNSwubXktbGctNXttYXJnaW4tdG9wOjNyZW0haW1wb3J0YW50fS5tci1sZy01LC5teC1sZy01e21hcmdpbi1yaWdodDozcmVtIWltcG9ydGFudH0ubWItbGctNSwubXktbGctNXttYXJnaW4tYm90dG9tOjNyZW0haW1wb3J0YW50fS5tbC1sZy01LC5teC1sZy01e21hcmdpbi1sZWZ0OjNyZW0haW1wb3J0YW50fS5wLWxnLTB7cGFkZGluZzowIWltcG9ydGFudH0ucHQtbGctMCwucHktbGctMHtwYWRkaW5nLXRvcDowIWltcG9ydGFudH0ucHItbGctMCwucHgtbGctMHtwYWRkaW5nLXJpZ2h0OjAhaW1wb3J0YW50fS5wYi1sZy0wLC5weS1sZy0we3BhZGRpbmctYm90dG9tOjAhaW1wb3J0YW50fS5wbC1sZy0wLC5weC1sZy0we3BhZGRpbmctbGVmdDowIWltcG9ydGFudH0ucC1sZy0xe3BhZGRpbmc6LjI1cmVtIWltcG9ydGFudH0ucHQtbGctMSwucHktbGctMXtwYWRkaW5nLXRvcDouMjVyZW0haW1wb3J0YW50fS5wci1sZy0xLC5weC1sZy0xe3BhZGRpbmctcmlnaHQ6LjI1cmVtIWltcG9ydGFudH0ucGItbGctMSwucHktbGctMXtwYWRkaW5nLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5wbC1sZy0xLC5weC1sZy0xe3BhZGRpbmctbGVmdDouMjVyZW0haW1wb3J0YW50fS5wLWxnLTJ7cGFkZGluZzouNXJlbSFpbXBvcnRhbnR9LnB0LWxnLTIsLnB5LWxnLTJ7cGFkZGluZy10b3A6LjVyZW0haW1wb3J0YW50fS5wci1sZy0yLC5weC1sZy0ye3BhZGRpbmctcmlnaHQ6LjVyZW0haW1wb3J0YW50fS5wYi1sZy0yLC5weS1sZy0ye3BhZGRpbmctYm90dG9tOi41cmVtIWltcG9ydGFudH0ucGwtbGctMiwucHgtbGctMntwYWRkaW5nLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5wLWxnLTN7cGFkZGluZzoxcmVtIWltcG9ydGFudH0ucHQtbGctMywucHktbGctM3twYWRkaW5nLXRvcDoxcmVtIWltcG9ydGFudH0ucHItbGctMywucHgtbGctM3twYWRkaW5nLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5wYi1sZy0zLC5weS1sZy0ze3BhZGRpbmctYm90dG9tOjFyZW0haW1wb3J0YW50fS5wbC1sZy0zLC5weC1sZy0ze3BhZGRpbmctbGVmdDoxcmVtIWltcG9ydGFudH0ucC1sZy00e3BhZGRpbmc6MS41cmVtIWltcG9ydGFudH0ucHQtbGctNCwucHktbGctNHtwYWRkaW5nLXRvcDoxLjVyZW0haW1wb3J0YW50fS5wci1sZy00LC5weC1sZy00e3BhZGRpbmctcmlnaHQ6MS41cmVtIWltcG9ydGFudH0ucGItbGctNCwucHktbGctNHtwYWRkaW5nLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5wbC1sZy00LC5weC1sZy00e3BhZGRpbmctbGVmdDoxLjVyZW0haW1wb3J0YW50fS5wLWxnLTV7cGFkZGluZzozcmVtIWltcG9ydGFudH0ucHQtbGctNSwucHktbGctNXtwYWRkaW5nLXRvcDozcmVtIWltcG9ydGFudH0ucHItbGctNSwucHgtbGctNXtwYWRkaW5nLXJpZ2h0OjNyZW0haW1wb3J0YW50fS5wYi1sZy01LC5weS1sZy01e3BhZGRpbmctYm90dG9tOjNyZW0haW1wb3J0YW50fS5wbC1sZy01LC5weC1sZy01e3BhZGRpbmctbGVmdDozcmVtIWltcG9ydGFudH0ubS1sZy1uMXttYXJnaW46LS4yNXJlbSFpbXBvcnRhbnR9Lm10LWxnLW4xLC5teS1sZy1uMXttYXJnaW4tdG9wOi0uMjVyZW0haW1wb3J0YW50fS5tci1sZy1uMSwubXgtbGctbjF7bWFyZ2luLXJpZ2h0Oi0uMjVyZW0haW1wb3J0YW50fS5tYi1sZy1uMSwubXktbGctbjF7bWFyZ2luLWJvdHRvbTotLjI1cmVtIWltcG9ydGFudH0ubWwtbGctbjEsLm14LWxnLW4xe21hcmdpbi1sZWZ0Oi0uMjVyZW0haW1wb3J0YW50fS5tLWxnLW4ye21hcmdpbjotLjVyZW0haW1wb3J0YW50fS5tdC1sZy1uMiwubXktbGctbjJ7bWFyZ2luLXRvcDotLjVyZW0haW1wb3J0YW50fS5tci1sZy1uMiwubXgtbGctbjJ7bWFyZ2luLXJpZ2h0Oi0uNXJlbSFpbXBvcnRhbnR9Lm1iLWxnLW4yLC5teS1sZy1uMnttYXJnaW4tYm90dG9tOi0uNXJlbSFpbXBvcnRhbnR9Lm1sLWxnLW4yLC5teC1sZy1uMnttYXJnaW4tbGVmdDotLjVyZW0haW1wb3J0YW50fS5tLWxnLW4ze21hcmdpbjotMXJlbSFpbXBvcnRhbnR9Lm10LWxnLW4zLC5teS1sZy1uM3ttYXJnaW4tdG9wOi0xcmVtIWltcG9ydGFudH0ubXItbGctbjMsLm14LWxnLW4ze21hcmdpbi1yaWdodDotMXJlbSFpbXBvcnRhbnR9Lm1iLWxnLW4zLC5teS1sZy1uM3ttYXJnaW4tYm90dG9tOi0xcmVtIWltcG9ydGFudH0ubWwtbGctbjMsLm14LWxnLW4ze21hcmdpbi1sZWZ0Oi0xcmVtIWltcG9ydGFudH0ubS1sZy1uNHttYXJnaW46LTEuNXJlbSFpbXBvcnRhbnR9Lm10LWxnLW40LC5teS1sZy1uNHttYXJnaW4tdG9wOi0xLjVyZW0haW1wb3J0YW50fS5tci1sZy1uNCwubXgtbGctbjR7bWFyZ2luLXJpZ2h0Oi0xLjVyZW0haW1wb3J0YW50fS5tYi1sZy1uNCwubXktbGctbjR7bWFyZ2luLWJvdHRvbTotMS41cmVtIWltcG9ydGFudH0ubWwtbGctbjQsLm14LWxnLW40e21hcmdpbi1sZWZ0Oi0xLjVyZW0haW1wb3J0YW50fS5tLWxnLW41e21hcmdpbjotM3JlbSFpbXBvcnRhbnR9Lm10LWxnLW41LC5teS1sZy1uNXttYXJnaW4tdG9wOi0zcmVtIWltcG9ydGFudH0ubXItbGctbjUsLm14LWxnLW41e21hcmdpbi1yaWdodDotM3JlbSFpbXBvcnRhbnR9Lm1iLWxnLW41LC5teS1sZy1uNXttYXJnaW4tYm90dG9tOi0zcmVtIWltcG9ydGFudH0ubWwtbGctbjUsLm14LWxnLW41e21hcmdpbi1sZWZ0Oi0zcmVtIWltcG9ydGFudH0ubS1sZy1hdXRve21hcmdpbjphdXRvIWltcG9ydGFudH0ubXQtbGctYXV0bywubXktbGctYXV0b3ttYXJnaW4tdG9wOmF1dG8haW1wb3J0YW50fS5tci1sZy1hdXRvLC5teC1sZy1hdXRve21hcmdpbi1yaWdodDphdXRvIWltcG9ydGFudH0ubWItbGctYXV0bywubXktbGctYXV0b3ttYXJnaW4tYm90dG9tOmF1dG8haW1wb3J0YW50fS5tbC1sZy1hdXRvLC5teC1sZy1hdXRve21hcmdpbi1sZWZ0OmF1dG8haW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5tLXhsLTB7bWFyZ2luOjAhaW1wb3J0YW50fS5tdC14bC0wLC5teS14bC0we21hcmdpbi10b3A6MCFpbXBvcnRhbnR9Lm1yLXhsLTAsLm14LXhsLTB7bWFyZ2luLXJpZ2h0OjAhaW1wb3J0YW50fS5tYi14bC0wLC5teS14bC0we21hcmdpbi1ib3R0b206MCFpbXBvcnRhbnR9Lm1sLXhsLTAsLm14LXhsLTB7bWFyZ2luLWxlZnQ6MCFpbXBvcnRhbnR9Lm0teGwtMXttYXJnaW46LjI1cmVtIWltcG9ydGFudH0ubXQteGwtMSwubXkteGwtMXttYXJnaW4tdG9wOi4yNXJlbSFpbXBvcnRhbnR9Lm1yLXhsLTEsLm14LXhsLTF7bWFyZ2luLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9Lm1iLXhsLTEsLm15LXhsLTF7bWFyZ2luLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5tbC14bC0xLC5teC14bC0xe21hcmdpbi1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9Lm0teGwtMnttYXJnaW46LjVyZW0haW1wb3J0YW50fS5tdC14bC0yLC5teS14bC0ye21hcmdpbi10b3A6LjVyZW0haW1wb3J0YW50fS5tci14bC0yLC5teC14bC0ye21hcmdpbi1yaWdodDouNXJlbSFpbXBvcnRhbnR9Lm1iLXhsLTIsLm15LXhsLTJ7bWFyZ2luLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9Lm1sLXhsLTIsLm14LXhsLTJ7bWFyZ2luLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5tLXhsLTN7bWFyZ2luOjFyZW0haW1wb3J0YW50fS5tdC14bC0zLC5teS14bC0ze21hcmdpbi10b3A6MXJlbSFpbXBvcnRhbnR9Lm1yLXhsLTMsLm14LXhsLTN7bWFyZ2luLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5tYi14bC0zLC5teS14bC0ze21hcmdpbi1ib3R0b206MXJlbSFpbXBvcnRhbnR9Lm1sLXhsLTMsLm14LXhsLTN7bWFyZ2luLWxlZnQ6MXJlbSFpbXBvcnRhbnR9Lm0teGwtNHttYXJnaW46MS41cmVtIWltcG9ydGFudH0ubXQteGwtNCwubXkteGwtNHttYXJnaW4tdG9wOjEuNXJlbSFpbXBvcnRhbnR9Lm1yLXhsLTQsLm14LXhsLTR7bWFyZ2luLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9Lm1iLXhsLTQsLm15LXhsLTR7bWFyZ2luLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5tbC14bC00LC5teC14bC00e21hcmdpbi1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9Lm0teGwtNXttYXJnaW46M3JlbSFpbXBvcnRhbnR9Lm10LXhsLTUsLm15LXhsLTV7bWFyZ2luLXRvcDozcmVtIWltcG9ydGFudH0ubXIteGwtNSwubXgteGwtNXttYXJnaW4tcmlnaHQ6M3JlbSFpbXBvcnRhbnR9Lm1iLXhsLTUsLm15LXhsLTV7bWFyZ2luLWJvdHRvbTozcmVtIWltcG9ydGFudH0ubWwteGwtNSwubXgteGwtNXttYXJnaW4tbGVmdDozcmVtIWltcG9ydGFudH0ucC14bC0we3BhZGRpbmc6MCFpbXBvcnRhbnR9LnB0LXhsLTAsLnB5LXhsLTB7cGFkZGluZy10b3A6MCFpbXBvcnRhbnR9LnByLXhsLTAsLnB4LXhsLTB7cGFkZGluZy1yaWdodDowIWltcG9ydGFudH0ucGIteGwtMCwucHkteGwtMHtwYWRkaW5nLWJvdHRvbTowIWltcG9ydGFudH0ucGwteGwtMCwucHgteGwtMHtwYWRkaW5nLWxlZnQ6MCFpbXBvcnRhbnR9LnAteGwtMXtwYWRkaW5nOi4yNXJlbSFpbXBvcnRhbnR9LnB0LXhsLTEsLnB5LXhsLTF7cGFkZGluZy10b3A6LjI1cmVtIWltcG9ydGFudH0ucHIteGwtMSwucHgteGwtMXtwYWRkaW5nLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9LnBiLXhsLTEsLnB5LXhsLTF7cGFkZGluZy1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ucGwteGwtMSwucHgteGwtMXtwYWRkaW5nLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ucC14bC0ye3BhZGRpbmc6LjVyZW0haW1wb3J0YW50fS5wdC14bC0yLC5weS14bC0ye3BhZGRpbmctdG9wOi41cmVtIWltcG9ydGFudH0ucHIteGwtMiwucHgteGwtMntwYWRkaW5nLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ucGIteGwtMiwucHkteGwtMntwYWRkaW5nLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9LnBsLXhsLTIsLnB4LXhsLTJ7cGFkZGluZy1sZWZ0Oi41cmVtIWltcG9ydGFudH0ucC14bC0ze3BhZGRpbmc6MXJlbSFpbXBvcnRhbnR9LnB0LXhsLTMsLnB5LXhsLTN7cGFkZGluZy10b3A6MXJlbSFpbXBvcnRhbnR9LnByLXhsLTMsLnB4LXhsLTN7cGFkZGluZy1yaWdodDoxcmVtIWltcG9ydGFudH0ucGIteGwtMywucHkteGwtM3twYWRkaW5nLWJvdHRvbToxcmVtIWltcG9ydGFudH0ucGwteGwtMywucHgteGwtM3twYWRkaW5nLWxlZnQ6MXJlbSFpbXBvcnRhbnR9LnAteGwtNHtwYWRkaW5nOjEuNXJlbSFpbXBvcnRhbnR9LnB0LXhsLTQsLnB5LXhsLTR7cGFkZGluZy10b3A6MS41cmVtIWltcG9ydGFudH0ucHIteGwtNCwucHgteGwtNHtwYWRkaW5nLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9LnBiLXhsLTQsLnB5LXhsLTR7cGFkZGluZy1ib3R0b206MS41cmVtIWltcG9ydGFudH0ucGwteGwtNCwucHgteGwtNHtwYWRkaW5nLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ucC14bC01e3BhZGRpbmc6M3JlbSFpbXBvcnRhbnR9LnB0LXhsLTUsLnB5LXhsLTV7cGFkZGluZy10b3A6M3JlbSFpbXBvcnRhbnR9LnByLXhsLTUsLnB4LXhsLTV7cGFkZGluZy1yaWdodDozcmVtIWltcG9ydGFudH0ucGIteGwtNSwucHkteGwtNXtwYWRkaW5nLWJvdHRvbTozcmVtIWltcG9ydGFudH0ucGwteGwtNSwucHgteGwtNXtwYWRkaW5nLWxlZnQ6M3JlbSFpbXBvcnRhbnR9Lm0teGwtbjF7bWFyZ2luOi0uMjVyZW0haW1wb3J0YW50fS5tdC14bC1uMSwubXkteGwtbjF7bWFyZ2luLXRvcDotLjI1cmVtIWltcG9ydGFudH0ubXIteGwtbjEsLm14LXhsLW4xe21hcmdpbi1yaWdodDotLjI1cmVtIWltcG9ydGFudH0ubWIteGwtbjEsLm15LXhsLW4xe21hcmdpbi1ib3R0b206LS4yNXJlbSFpbXBvcnRhbnR9Lm1sLXhsLW4xLC5teC14bC1uMXttYXJnaW4tbGVmdDotLjI1cmVtIWltcG9ydGFudH0ubS14bC1uMnttYXJnaW46LS41cmVtIWltcG9ydGFudH0ubXQteGwtbjIsLm15LXhsLW4ye21hcmdpbi10b3A6LS41cmVtIWltcG9ydGFudH0ubXIteGwtbjIsLm14LXhsLW4ye21hcmdpbi1yaWdodDotLjVyZW0haW1wb3J0YW50fS5tYi14bC1uMiwubXkteGwtbjJ7bWFyZ2luLWJvdHRvbTotLjVyZW0haW1wb3J0YW50fS5tbC14bC1uMiwubXgteGwtbjJ7bWFyZ2luLWxlZnQ6LS41cmVtIWltcG9ydGFudH0ubS14bC1uM3ttYXJnaW46LTFyZW0haW1wb3J0YW50fS5tdC14bC1uMywubXkteGwtbjN7bWFyZ2luLXRvcDotMXJlbSFpbXBvcnRhbnR9Lm1yLXhsLW4zLC5teC14bC1uM3ttYXJnaW4tcmlnaHQ6LTFyZW0haW1wb3J0YW50fS5tYi14bC1uMywubXkteGwtbjN7bWFyZ2luLWJvdHRvbTotMXJlbSFpbXBvcnRhbnR9Lm1sLXhsLW4zLC5teC14bC1uM3ttYXJnaW4tbGVmdDotMXJlbSFpbXBvcnRhbnR9Lm0teGwtbjR7bWFyZ2luOi0xLjVyZW0haW1wb3J0YW50fS5tdC14bC1uNCwubXkteGwtbjR7bWFyZ2luLXRvcDotMS41cmVtIWltcG9ydGFudH0ubXIteGwtbjQsLm14LXhsLW40e21hcmdpbi1yaWdodDotMS41cmVtIWltcG9ydGFudH0ubWIteGwtbjQsLm15LXhsLW40e21hcmdpbi1ib3R0b206LTEuNXJlbSFpbXBvcnRhbnR9Lm1sLXhsLW40LC5teC14bC1uNHttYXJnaW4tbGVmdDotMS41cmVtIWltcG9ydGFudH0ubS14bC1uNXttYXJnaW46LTNyZW0haW1wb3J0YW50fS5tdC14bC1uNSwubXkteGwtbjV7bWFyZ2luLXRvcDotM3JlbSFpbXBvcnRhbnR9Lm1yLXhsLW41LC5teC14bC1uNXttYXJnaW4tcmlnaHQ6LTNyZW0haW1wb3J0YW50fS5tYi14bC1uNSwubXkteGwtbjV7bWFyZ2luLWJvdHRvbTotM3JlbSFpbXBvcnRhbnR9Lm1sLXhsLW41LC5teC14bC1uNXttYXJnaW4tbGVmdDotM3JlbSFpbXBvcnRhbnR9Lm0teGwtYXV0b3ttYXJnaW46YXV0byFpbXBvcnRhbnR9Lm10LXhsLWF1dG8sLm15LXhsLWF1dG97bWFyZ2luLXRvcDphdXRvIWltcG9ydGFudH0ubXIteGwtYXV0bywubXgteGwtYXV0b3ttYXJnaW4tcmlnaHQ6YXV0byFpbXBvcnRhbnR9Lm1iLXhsLWF1dG8sLm15LXhsLWF1dG97bWFyZ2luLWJvdHRvbTphdXRvIWltcG9ydGFudH0ubWwteGwtYXV0bywubXgteGwtYXV0b3ttYXJnaW4tbGVmdDphdXRvIWltcG9ydGFudH19LnRleHQtbW9ub3NwYWNle2ZvbnQtZmFtaWx5OlNGTW9uby1SZWd1bGFyLE1lbmxvLE1vbmFjbyxDb25zb2xhcywiTGliZXJhdGlvbiBNb25vIiwiQ291cmllciBOZXciLG1vbm9zcGFjZSFpbXBvcnRhbnR9LnRleHQtanVzdGlmeXt0ZXh0LWFsaWduOmp1c3RpZnkhaW1wb3J0YW50fS50ZXh0LXdyYXB7d2hpdGUtc3BhY2U6bm9ybWFsIWltcG9ydGFudH0udGV4dC1ub3dyYXB7d2hpdGUtc3BhY2U6bm93cmFwIWltcG9ydGFudH0udGV4dC10cnVuY2F0ZXtvdmVyZmxvdzpoaWRkZW47dGV4dC1vdmVyZmxvdzplbGxpcHNpczt3aGl0ZS1zcGFjZTpub3dyYXB9LnRleHQtbGVmdHt0ZXh0LWFsaWduOmxlZnQhaW1wb3J0YW50fS50ZXh0LXJpZ2h0e3RleHQtYWxpZ246cmlnaHQhaW1wb3J0YW50fS50ZXh0LWNlbnRlcnt0ZXh0LWFsaWduOmNlbnRlciFpbXBvcnRhbnR9QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey50ZXh0LXNtLWxlZnR7dGV4dC1hbGlnbjpsZWZ0IWltcG9ydGFudH0udGV4dC1zbS1yaWdodHt0ZXh0LWFsaWduOnJpZ2h0IWltcG9ydGFudH0udGV4dC1zbS1jZW50ZXJ7dGV4dC1hbGlnbjpjZW50ZXIhaW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7LnRleHQtbWQtbGVmdHt0ZXh0LWFsaWduOmxlZnQhaW1wb3J0YW50fS50ZXh0LW1kLXJpZ2h0e3RleHQtYWxpZ246cmlnaHQhaW1wb3J0YW50fS50ZXh0LW1kLWNlbnRlcnt0ZXh0LWFsaWduOmNlbnRlciFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsudGV4dC1sZy1sZWZ0e3RleHQtYWxpZ246bGVmdCFpbXBvcnRhbnR9LnRleHQtbGctcmlnaHR7dGV4dC1hbGlnbjpyaWdodCFpbXBvcnRhbnR9LnRleHQtbGctY2VudGVye3RleHQtYWxpZ246Y2VudGVyIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6MTIwMHB4KXsudGV4dC14bC1sZWZ0e3RleHQtYWxpZ246bGVmdCFpbXBvcnRhbnR9LnRleHQteGwtcmlnaHR7dGV4dC1hbGlnbjpyaWdodCFpbXBvcnRhbnR9LnRleHQteGwtY2VudGVye3RleHQtYWxpZ246Y2VudGVyIWltcG9ydGFudH19LnRleHQtbG93ZXJjYXNle3RleHQtdHJhbnNmb3JtOmxvd2VyY2FzZSFpbXBvcnRhbnR9LnRleHQtdXBwZXJjYXNle3RleHQtdHJhbnNmb3JtOnVwcGVyY2FzZSFpbXBvcnRhbnR9LnRleHQtY2FwaXRhbGl6ZXt0ZXh0LXRyYW5zZm9ybTpjYXBpdGFsaXplIWltcG9ydGFudH0uZm9udC13ZWlnaHQtbGlnaHR7Zm9udC13ZWlnaHQ6MzAwIWltcG9ydGFudH0uZm9udC13ZWlnaHQtbGlnaHRlcntmb250LXdlaWdodDpsaWdodGVyIWltcG9ydGFudH0uZm9udC13ZWlnaHQtbm9ybWFse2ZvbnQtd2VpZ2h0OjQwMCFpbXBvcnRhbnR9LmZvbnQtd2VpZ2h0LWJvbGR7Zm9udC13ZWlnaHQ6NzAwIWltcG9ydGFudH0uZm9udC13ZWlnaHQtYm9sZGVye2ZvbnQtd2VpZ2h0OmJvbGRlciFpbXBvcnRhbnR9LmZvbnQtaXRhbGlje2ZvbnQtc3R5bGU6aXRhbGljIWltcG9ydGFudH0udGV4dC13aGl0ZXtjb2xvcjojZmZmIWltcG9ydGFudH0udGV4dC1wcmltYXJ5e2NvbG9yOiMwMDdiZmYhaW1wb3J0YW50fWEudGV4dC1wcmltYXJ5OmZvY3VzLGEudGV4dC1wcmltYXJ5OmhvdmVye2NvbG9yOiMwMDU2YjMhaW1wb3J0YW50fS50ZXh0LXNlY29uZGFyeXtjb2xvcjojNmM3NTdkIWltcG9ydGFudH1hLnRleHQtc2Vjb25kYXJ5OmZvY3VzLGEudGV4dC1zZWNvbmRhcnk6aG92ZXJ7Y29sb3I6IzQ5NGY1NCFpbXBvcnRhbnR9LnRleHQtc3VjY2Vzc3tjb2xvcjojMjhhNzQ1IWltcG9ydGFudH1hLnRleHQtc3VjY2Vzczpmb2N1cyxhLnRleHQtc3VjY2Vzczpob3Zlcntjb2xvcjojMTk2OTJjIWltcG9ydGFudH0udGV4dC1pbmZve2NvbG9yOiMxN2EyYjghaW1wb3J0YW50fWEudGV4dC1pbmZvOmZvY3VzLGEudGV4dC1pbmZvOmhvdmVye2NvbG9yOiMwZjY2NzQhaW1wb3J0YW50fS50ZXh0LXdhcm5pbmd7Y29sb3I6I2ZmYzEwNyFpbXBvcnRhbnR9YS50ZXh0LXdhcm5pbmc6Zm9jdXMsYS50ZXh0LXdhcm5pbmc6aG92ZXJ7Y29sb3I6I2JhOGIwMCFpbXBvcnRhbnR9LnRleHQtZGFuZ2Vye2NvbG9yOiNkYzM1NDUhaW1wb3J0YW50fWEudGV4dC1kYW5nZXI6Zm9jdXMsYS50ZXh0LWRhbmdlcjpob3Zlcntjb2xvcjojYTcxZDJhIWltcG9ydGFudH0udGV4dC1saWdodHtjb2xvcjojZjhmOWZhIWltcG9ydGFudH1hLnRleHQtbGlnaHQ6Zm9jdXMsYS50ZXh0LWxpZ2h0OmhvdmVye2NvbG9yOiNjYmQzZGEhaW1wb3J0YW50fS50ZXh0LWRhcmt7Y29sb3I6IzM0M2E0MCFpbXBvcnRhbnR9YS50ZXh0LWRhcms6Zm9jdXMsYS50ZXh0LWRhcms6aG92ZXJ7Y29sb3I6IzEyMTQxNiFpbXBvcnRhbnR9LnRleHQtYm9keXtjb2xvcjojMjEyNTI5IWltcG9ydGFudH0udGV4dC1tdXRlZHtjb2xvcjojNmM3NTdkIWltcG9ydGFudH0udGV4dC1ibGFjay01MHtjb2xvcjpyZ2JhKDAsMCwwLC41KSFpbXBvcnRhbnR9LnRleHQtd2hpdGUtNTB7Y29sb3I6cmdiYSgyNTUsMjU1LDI1NSwuNSkhaW1wb3J0YW50fS50ZXh0LWhpZGV7Zm9udDowLzAgYTtjb2xvcjp0cmFuc3BhcmVudDt0ZXh0LXNoYWRvdzpub25lO2JhY2tncm91bmQtY29sb3I6dHJhbnNwYXJlbnQ7Ym9yZGVyOjB9LnRleHQtZGVjb3JhdGlvbi1ub25le3RleHQtZGVjb3JhdGlvbjpub25lIWltcG9ydGFudH0udGV4dC1icmVha3t3b3JkLWJyZWFrOmJyZWFrLXdvcmQhaW1wb3J0YW50O292ZXJmbG93LXdyYXA6YnJlYWstd29yZCFpbXBvcnRhbnR9LnRleHQtcmVzZXR7Y29sb3I6aW5oZXJpdCFpbXBvcnRhbnR9LnZpc2libGV7dmlzaWJpbGl0eTp2aXNpYmxlIWltcG9ydGFudH0uaW52aXNpYmxle3Zpc2liaWxpdHk6aGlkZGVuIWltcG9ydGFudH1AbWVkaWEgcHJpbnR7Kiw6OmFmdGVyLDo6YmVmb3Jle3RleHQtc2hhZG93Om5vbmUhaW1wb3J0YW50O2JveC1zaGFkb3c6bm9uZSFpbXBvcnRhbnR9YTpub3QoLmJ0bil7dGV4dC1kZWNvcmF0aW9uOnVuZGVybGluZX1hYmJyW3RpdGxlXTo6YWZ0ZXJ7Y29udGVudDoiICgiIGF0dHIodGl0bGUpICIpIn1wcmV7d2hpdGUtc3BhY2U6cHJlLXdyYXAhaW1wb3J0YW50fWJsb2NrcXVvdGUscHJle2JvcmRlcjoxcHggc29saWQgI2FkYjViZDtwYWdlLWJyZWFrLWluc2lkZTphdm9pZH10aGVhZHtkaXNwbGF5OnRhYmxlLWhlYWRlci1ncm91cH1pbWcsdHJ7cGFnZS1icmVhay1pbnNpZGU6YXZvaWR9aDIsaDMscHtvcnBoYW5zOjM7d2lkb3dzOjN9aDIsaDN7cGFnZS1icmVhay1hZnRlcjphdm9pZH1AcGFnZXtzaXplOmEzfWJvZHl7bWluLXdpZHRoOjk5MnB4IWltcG9ydGFudH0uY29udGFpbmVye21pbi13aWR0aDo5OTJweCFpbXBvcnRhbnR9Lm5hdmJhcntkaXNwbGF5Om5vbmV9LmJhZGdle2JvcmRlcjoxcHggc29saWQgIzAwMH0udGFibGV7Ym9yZGVyLWNvbGxhcHNlOmNvbGxhcHNlIWltcG9ydGFudH0udGFibGUgdGQsLnRhYmxlIHRoe2JhY2tncm91bmQtY29sb3I6I2ZmZiFpbXBvcnRhbnR9LnRhYmxlLWJvcmRlcmVkIHRkLC50YWJsZS1ib3JkZXJlZCB0aHtib3JkZXI6MXB4IHNvbGlkICNkZWUyZTYhaW1wb3J0YW50fS50YWJsZS1kYXJre2NvbG9yOmluaGVyaXR9LnRhYmxlLWRhcmsgdGJvZHkrdGJvZHksLnRhYmxlLWRhcmsgdGQsLnRhYmxlLWRhcmsgdGgsLnRhYmxlLWRhcmsgdGhlYWQgdGh7Ym9yZGVyLWNvbG9yOiNkZWUyZTZ9LnRhYmxlIC50aGVhZC1kYXJrIHRoe2NvbG9yOmluaGVyaXQ7Ym9yZGVyLWNvbG9yOiNkZWUyZTZ9fQovKiMgc291cmNlTWFwcGluZ1VSTD1ib290c3RyYXAubWluLmNzcy5tYXAgKi8='); }
if($path == 'bootstrap4/css/bootstrap-grid.min.css'){ return base64_decode('LyohCiAqIEJvb3RzdHJhcCBHcmlkIHY0LjQuMSAoaHR0cHM6Ly9nZXRib290c3RyYXAuY29tLykKICogQ29weXJpZ2h0IDIwMTEtMjAxOSBUaGUgQm9vdHN0cmFwIEF1dGhvcnMKICogQ29weXJpZ2h0IDIwMTEtMjAxOSBUd2l0dGVyLCBJbmMuCiAqIExpY2Vuc2VkIHVuZGVyIE1JVCAoaHR0cHM6Ly9naXRodWIuY29tL3R3YnMvYm9vdHN0cmFwL2Jsb2IvbWFzdGVyL0xJQ0VOU0UpCiAqL2h0bWx7Ym94LXNpemluZzpib3JkZXItYm94Oy1tcy1vdmVyZmxvdy1zdHlsZTpzY3JvbGxiYXJ9Kiw6OmFmdGVyLDo6YmVmb3Jle2JveC1zaXppbmc6aW5oZXJpdH0uY29udGFpbmVye3dpZHRoOjEwMCU7cGFkZGluZy1yaWdodDoxNXB4O3BhZGRpbmctbGVmdDoxNXB4O21hcmdpbi1yaWdodDphdXRvO21hcmdpbi1sZWZ0OmF1dG99QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey5jb250YWluZXJ7bWF4LXdpZHRoOjU0MHB4fX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7LmNvbnRhaW5lcnttYXgtd2lkdGg6NzIwcHh9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuY29udGFpbmVye21heC13aWR0aDo5NjBweH19QG1lZGlhIChtaW4td2lkdGg6MTIwMHB4KXsuY29udGFpbmVye21heC13aWR0aDoxMTQwcHh9fS5jb250YWluZXItZmx1aWQsLmNvbnRhaW5lci1sZywuY29udGFpbmVyLW1kLC5jb250YWluZXItc20sLmNvbnRhaW5lci14bHt3aWR0aDoxMDAlO3BhZGRpbmctcmlnaHQ6MTVweDtwYWRkaW5nLWxlZnQ6MTVweDttYXJnaW4tcmlnaHQ6YXV0bzttYXJnaW4tbGVmdDphdXRvfUBtZWRpYSAobWluLXdpZHRoOjU3NnB4KXsuY29udGFpbmVyLC5jb250YWluZXItc217bWF4LXdpZHRoOjU0MHB4fX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7LmNvbnRhaW5lciwuY29udGFpbmVyLW1kLC5jb250YWluZXItc217bWF4LXdpZHRoOjcyMHB4fX1AbWVkaWEgKG1pbi13aWR0aDo5OTJweCl7LmNvbnRhaW5lciwuY29udGFpbmVyLWxnLC5jb250YWluZXItbWQsLmNvbnRhaW5lci1zbXttYXgtd2lkdGg6OTYwcHh9fUBtZWRpYSAobWluLXdpZHRoOjEyMDBweCl7LmNvbnRhaW5lciwuY29udGFpbmVyLWxnLC5jb250YWluZXItbWQsLmNvbnRhaW5lci1zbSwuY29udGFpbmVyLXhse21heC13aWR0aDoxMTQwcHh9fS5yb3d7ZGlzcGxheTotbXMtZmxleGJveDtkaXNwbGF5OmZsZXg7LW1zLWZsZXgtd3JhcDp3cmFwO2ZsZXgtd3JhcDp3cmFwO21hcmdpbi1yaWdodDotMTVweDttYXJnaW4tbGVmdDotMTVweH0ubm8tZ3V0dGVyc3ttYXJnaW4tcmlnaHQ6MDttYXJnaW4tbGVmdDowfS5uby1ndXR0ZXJzPi5jb2wsLm5vLWd1dHRlcnM+W2NsYXNzKj1jb2wtXXtwYWRkaW5nLXJpZ2h0OjA7cGFkZGluZy1sZWZ0OjB9LmNvbCwuY29sLTEsLmNvbC0xMCwuY29sLTExLC5jb2wtMTIsLmNvbC0yLC5jb2wtMywuY29sLTQsLmNvbC01LC5jb2wtNiwuY29sLTcsLmNvbC04LC5jb2wtOSwuY29sLWF1dG8sLmNvbC1sZywuY29sLWxnLTEsLmNvbC1sZy0xMCwuY29sLWxnLTExLC5jb2wtbGctMTIsLmNvbC1sZy0yLC5jb2wtbGctMywuY29sLWxnLTQsLmNvbC1sZy01LC5jb2wtbGctNiwuY29sLWxnLTcsLmNvbC1sZy04LC5jb2wtbGctOSwuY29sLWxnLWF1dG8sLmNvbC1tZCwuY29sLW1kLTEsLmNvbC1tZC0xMCwuY29sLW1kLTExLC5jb2wtbWQtMTIsLmNvbC1tZC0yLC5jb2wtbWQtMywuY29sLW1kLTQsLmNvbC1tZC01LC5jb2wtbWQtNiwuY29sLW1kLTcsLmNvbC1tZC04LC5jb2wtbWQtOSwuY29sLW1kLWF1dG8sLmNvbC1zbSwuY29sLXNtLTEsLmNvbC1zbS0xMCwuY29sLXNtLTExLC5jb2wtc20tMTIsLmNvbC1zbS0yLC5jb2wtc20tMywuY29sLXNtLTQsLmNvbC1zbS01LC5jb2wtc20tNiwuY29sLXNtLTcsLmNvbC1zbS04LC5jb2wtc20tOSwuY29sLXNtLWF1dG8sLmNvbC14bCwuY29sLXhsLTEsLmNvbC14bC0xMCwuY29sLXhsLTExLC5jb2wteGwtMTIsLmNvbC14bC0yLC5jb2wteGwtMywuY29sLXhsLTQsLmNvbC14bC01LC5jb2wteGwtNiwuY29sLXhsLTcsLmNvbC14bC04LC5jb2wteGwtOSwuY29sLXhsLWF1dG97cG9zaXRpb246cmVsYXRpdmU7d2lkdGg6MTAwJTtwYWRkaW5nLXJpZ2h0OjE1cHg7cGFkZGluZy1sZWZ0OjE1cHh9LmNvbHstbXMtZmxleC1wcmVmZXJyZWQtc2l6ZTowO2ZsZXgtYmFzaXM6MDstbXMtZmxleC1wb3NpdGl2ZToxO2ZsZXgtZ3JvdzoxO21heC13aWR0aDoxMDAlfS5yb3ctY29scy0xPip7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ucm93LWNvbHMtMj4qey1tcy1mbGV4OjAgMCA1MCU7ZmxleDowIDAgNTAlO21heC13aWR0aDo1MCV9LnJvdy1jb2xzLTM+KnstbXMtZmxleDowIDAgMzMuMzMzMzMzJTtmbGV4OjAgMCAzMy4zMzMzMzMlO21heC13aWR0aDozMy4zMzMzMzMlfS5yb3ctY29scy00Pip7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0ucm93LWNvbHMtNT4qey1tcy1mbGV4OjAgMCAyMCU7ZmxleDowIDAgMjAlO21heC13aWR0aDoyMCV9LnJvdy1jb2xzLTY+KnstbXMtZmxleDowIDAgMTYuNjY2NjY3JTtmbGV4OjAgMCAxNi42NjY2NjclO21heC13aWR0aDoxNi42NjY2NjclfS5jb2wtYXV0b3stbXMtZmxleDowIDAgYXV0bztmbGV4OjAgMCBhdXRvO3dpZHRoOmF1dG87bWF4LXdpZHRoOjEwMCV9LmNvbC0xey1tcy1mbGV4OjAgMCA4LjMzMzMzMyU7ZmxleDowIDAgOC4zMzMzMzMlO21heC13aWR0aDo4LjMzMzMzMyV9LmNvbC0yey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC0zey1tcy1mbGV4OjAgMCAyNSU7ZmxleDowIDAgMjUlO21heC13aWR0aDoyNSV9LmNvbC00ey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LmNvbC01ey1tcy1mbGV4OjAgMCA0MS42NjY2NjclO2ZsZXg6MCAwIDQxLjY2NjY2NyU7bWF4LXdpZHRoOjQxLjY2NjY2NyV9LmNvbC02ey1tcy1mbGV4OjAgMCA1MCU7ZmxleDowIDAgNTAlO21heC13aWR0aDo1MCV9LmNvbC03ey1tcy1mbGV4OjAgMCA1OC4zMzMzMzMlO2ZsZXg6MCAwIDU4LjMzMzMzMyU7bWF4LXdpZHRoOjU4LjMzMzMzMyV9LmNvbC04ey1tcy1mbGV4OjAgMCA2Ni42NjY2NjclO2ZsZXg6MCAwIDY2LjY2NjY2NyU7bWF4LXdpZHRoOjY2LjY2NjY2NyV9LmNvbC05ey1tcy1mbGV4OjAgMCA3NSU7ZmxleDowIDAgNzUlO21heC13aWR0aDo3NSV9LmNvbC0xMHstbXMtZmxleDowIDAgODMuMzMzMzMzJTtmbGV4OjAgMCA4My4zMzMzMzMlO21heC13aWR0aDo4My4zMzMzMzMlfS5jb2wtMTF7LW1zLWZsZXg6MCAwIDkxLjY2NjY2NyU7ZmxleDowIDAgOTEuNjY2NjY3JTttYXgtd2lkdGg6OTEuNjY2NjY3JX0uY29sLTEyey1tcy1mbGV4OjAgMCAxMDAlO2ZsZXg6MCAwIDEwMCU7bWF4LXdpZHRoOjEwMCV9Lm9yZGVyLWZpcnN0ey1tcy1mbGV4LW9yZGVyOi0xO29yZGVyOi0xfS5vcmRlci1sYXN0ey1tcy1mbGV4LW9yZGVyOjEzO29yZGVyOjEzfS5vcmRlci0wey1tcy1mbGV4LW9yZGVyOjA7b3JkZXI6MH0ub3JkZXItMXstbXMtZmxleC1vcmRlcjoxO29yZGVyOjF9Lm9yZGVyLTJ7LW1zLWZsZXgtb3JkZXI6MjtvcmRlcjoyfS5vcmRlci0zey1tcy1mbGV4LW9yZGVyOjM7b3JkZXI6M30ub3JkZXItNHstbXMtZmxleC1vcmRlcjo0O29yZGVyOjR9Lm9yZGVyLTV7LW1zLWZsZXgtb3JkZXI6NTtvcmRlcjo1fS5vcmRlci02ey1tcy1mbGV4LW9yZGVyOjY7b3JkZXI6Nn0ub3JkZXItN3stbXMtZmxleC1vcmRlcjo3O29yZGVyOjd9Lm9yZGVyLTh7LW1zLWZsZXgtb3JkZXI6ODtvcmRlcjo4fS5vcmRlci05ey1tcy1mbGV4LW9yZGVyOjk7b3JkZXI6OX0ub3JkZXItMTB7LW1zLWZsZXgtb3JkZXI6MTA7b3JkZXI6MTB9Lm9yZGVyLTExey1tcy1mbGV4LW9yZGVyOjExO29yZGVyOjExfS5vcmRlci0xMnstbXMtZmxleC1vcmRlcjoxMjtvcmRlcjoxMn0ub2Zmc2V0LTF7bWFyZ2luLWxlZnQ6OC4zMzMzMzMlfS5vZmZzZXQtMnttYXJnaW4tbGVmdDoxNi42NjY2NjclfS5vZmZzZXQtM3ttYXJnaW4tbGVmdDoyNSV9Lm9mZnNldC00e21hcmdpbi1sZWZ0OjMzLjMzMzMzMyV9Lm9mZnNldC01e21hcmdpbi1sZWZ0OjQxLjY2NjY2NyV9Lm9mZnNldC02e21hcmdpbi1sZWZ0OjUwJX0ub2Zmc2V0LTd7bWFyZ2luLWxlZnQ6NTguMzMzMzMzJX0ub2Zmc2V0LTh7bWFyZ2luLWxlZnQ6NjYuNjY2NjY3JX0ub2Zmc2V0LTl7bWFyZ2luLWxlZnQ6NzUlfS5vZmZzZXQtMTB7bWFyZ2luLWxlZnQ6ODMuMzMzMzMzJX0ub2Zmc2V0LTExe21hcmdpbi1sZWZ0OjkxLjY2NjY2NyV9QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey5jb2wtc217LW1zLWZsZXgtcHJlZmVycmVkLXNpemU6MDtmbGV4LWJhc2lzOjA7LW1zLWZsZXgtcG9zaXRpdmU6MTtmbGV4LWdyb3c6MTttYXgtd2lkdGg6MTAwJX0ucm93LWNvbHMtc20tMT4qey1tcy1mbGV4OjAgMCAxMDAlO2ZsZXg6MCAwIDEwMCU7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLXNtLTI+KnstbXMtZmxleDowIDAgNTAlO2ZsZXg6MCAwIDUwJTttYXgtd2lkdGg6NTAlfS5yb3ctY29scy1zbS0zPip7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0ucm93LWNvbHMtc20tND4qey1tcy1mbGV4OjAgMCAyNSU7ZmxleDowIDAgMjUlO21heC13aWR0aDoyNSV9LnJvdy1jb2xzLXNtLTU+KnstbXMtZmxleDowIDAgMjAlO2ZsZXg6MCAwIDIwJTttYXgtd2lkdGg6MjAlfS5yb3ctY29scy1zbS02Pip7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLXNtLWF1dG97LW1zLWZsZXg6MCAwIGF1dG87ZmxleDowIDAgYXV0bzt3aWR0aDphdXRvO21heC13aWR0aDoxMDAlfS5jb2wtc20tMXstbXMtZmxleDowIDAgOC4zMzMzMzMlO2ZsZXg6MCAwIDguMzMzMzMzJTttYXgtd2lkdGg6OC4zMzMzMzMlfS5jb2wtc20tMnstbXMtZmxleDowIDAgMTYuNjY2NjY3JTtmbGV4OjAgMCAxNi42NjY2NjclO21heC13aWR0aDoxNi42NjY2NjclfS5jb2wtc20tM3stbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5jb2wtc20tNHstbXMtZmxleDowIDAgMzMuMzMzMzMzJTtmbGV4OjAgMCAzMy4zMzMzMzMlO21heC13aWR0aDozMy4zMzMzMzMlfS5jb2wtc20tNXstbXMtZmxleDowIDAgNDEuNjY2NjY3JTtmbGV4OjAgMCA0MS42NjY2NjclO21heC13aWR0aDo0MS42NjY2NjclfS5jb2wtc20tNnstbXMtZmxleDowIDAgNTAlO2ZsZXg6MCAwIDUwJTttYXgtd2lkdGg6NTAlfS5jb2wtc20tN3stbXMtZmxleDowIDAgNTguMzMzMzMzJTtmbGV4OjAgMCA1OC4zMzMzMzMlO21heC13aWR0aDo1OC4zMzMzMzMlfS5jb2wtc20tOHstbXMtZmxleDowIDAgNjYuNjY2NjY3JTtmbGV4OjAgMCA2Ni42NjY2NjclO21heC13aWR0aDo2Ni42NjY2NjclfS5jb2wtc20tOXstbXMtZmxleDowIDAgNzUlO2ZsZXg6MCAwIDc1JTttYXgtd2lkdGg6NzUlfS5jb2wtc20tMTB7LW1zLWZsZXg6MCAwIDgzLjMzMzMzMyU7ZmxleDowIDAgODMuMzMzMzMzJTttYXgtd2lkdGg6ODMuMzMzMzMzJX0uY29sLXNtLTExey1tcy1mbGV4OjAgMCA5MS42NjY2NjclO2ZsZXg6MCAwIDkxLjY2NjY2NyU7bWF4LXdpZHRoOjkxLjY2NjY2NyV9LmNvbC1zbS0xMnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5vcmRlci1zbS1maXJzdHstbXMtZmxleC1vcmRlcjotMTtvcmRlcjotMX0ub3JkZXItc20tbGFzdHstbXMtZmxleC1vcmRlcjoxMztvcmRlcjoxM30ub3JkZXItc20tMHstbXMtZmxleC1vcmRlcjowO29yZGVyOjB9Lm9yZGVyLXNtLTF7LW1zLWZsZXgtb3JkZXI6MTtvcmRlcjoxfS5vcmRlci1zbS0yey1tcy1mbGV4LW9yZGVyOjI7b3JkZXI6Mn0ub3JkZXItc20tM3stbXMtZmxleC1vcmRlcjozO29yZGVyOjN9Lm9yZGVyLXNtLTR7LW1zLWZsZXgtb3JkZXI6NDtvcmRlcjo0fS5vcmRlci1zbS01ey1tcy1mbGV4LW9yZGVyOjU7b3JkZXI6NX0ub3JkZXItc20tNnstbXMtZmxleC1vcmRlcjo2O29yZGVyOjZ9Lm9yZGVyLXNtLTd7LW1zLWZsZXgtb3JkZXI6NztvcmRlcjo3fS5vcmRlci1zbS04ey1tcy1mbGV4LW9yZGVyOjg7b3JkZXI6OH0ub3JkZXItc20tOXstbXMtZmxleC1vcmRlcjo5O29yZGVyOjl9Lm9yZGVyLXNtLTEwey1tcy1mbGV4LW9yZGVyOjEwO29yZGVyOjEwfS5vcmRlci1zbS0xMXstbXMtZmxleC1vcmRlcjoxMTtvcmRlcjoxMX0ub3JkZXItc20tMTJ7LW1zLWZsZXgtb3JkZXI6MTI7b3JkZXI6MTJ9Lm9mZnNldC1zbS0we21hcmdpbi1sZWZ0OjB9Lm9mZnNldC1zbS0xe21hcmdpbi1sZWZ0OjguMzMzMzMzJX0ub2Zmc2V0LXNtLTJ7bWFyZ2luLWxlZnQ6MTYuNjY2NjY3JX0ub2Zmc2V0LXNtLTN7bWFyZ2luLWxlZnQ6MjUlfS5vZmZzZXQtc20tNHttYXJnaW4tbGVmdDozMy4zMzMzMzMlfS5vZmZzZXQtc20tNXttYXJnaW4tbGVmdDo0MS42NjY2NjclfS5vZmZzZXQtc20tNnttYXJnaW4tbGVmdDo1MCV9Lm9mZnNldC1zbS03e21hcmdpbi1sZWZ0OjU4LjMzMzMzMyV9Lm9mZnNldC1zbS04e21hcmdpbi1sZWZ0OjY2LjY2NjY2NyV9Lm9mZnNldC1zbS05e21hcmdpbi1sZWZ0Ojc1JX0ub2Zmc2V0LXNtLTEwe21hcmdpbi1sZWZ0OjgzLjMzMzMzMyV9Lm9mZnNldC1zbS0xMXttYXJnaW4tbGVmdDo5MS42NjY2NjclfX1AbWVkaWEgKG1pbi13aWR0aDo3NjhweCl7LmNvbC1tZHstbXMtZmxleC1wcmVmZXJyZWQtc2l6ZTowO2ZsZXgtYmFzaXM6MDstbXMtZmxleC1wb3NpdGl2ZToxO2ZsZXgtZ3JvdzoxO21heC13aWR0aDoxMDAlfS5yb3ctY29scy1tZC0xPip7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ucm93LWNvbHMtbWQtMj4qey1tcy1mbGV4OjAgMCA1MCU7ZmxleDowIDAgNTAlO21heC13aWR0aDo1MCV9LnJvdy1jb2xzLW1kLTM+KnstbXMtZmxleDowIDAgMzMuMzMzMzMzJTtmbGV4OjAgMCAzMy4zMzMzMzMlO21heC13aWR0aDozMy4zMzMzMzMlfS5yb3ctY29scy1tZC00Pip7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0ucm93LWNvbHMtbWQtNT4qey1tcy1mbGV4OjAgMCAyMCU7ZmxleDowIDAgMjAlO21heC13aWR0aDoyMCV9LnJvdy1jb2xzLW1kLTY+KnstbXMtZmxleDowIDAgMTYuNjY2NjY3JTtmbGV4OjAgMCAxNi42NjY2NjclO21heC13aWR0aDoxNi42NjY2NjclfS5jb2wtbWQtYXV0b3stbXMtZmxleDowIDAgYXV0bztmbGV4OjAgMCBhdXRvO3dpZHRoOmF1dG87bWF4LXdpZHRoOjEwMCV9LmNvbC1tZC0xey1tcy1mbGV4OjAgMCA4LjMzMzMzMyU7ZmxleDowIDAgOC4zMzMzMzMlO21heC13aWR0aDo4LjMzMzMzMyV9LmNvbC1tZC0yey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC1tZC0zey1tcy1mbGV4OjAgMCAyNSU7ZmxleDowIDAgMjUlO21heC13aWR0aDoyNSV9LmNvbC1tZC00ey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LmNvbC1tZC01ey1tcy1mbGV4OjAgMCA0MS42NjY2NjclO2ZsZXg6MCAwIDQxLjY2NjY2NyU7bWF4LXdpZHRoOjQxLjY2NjY2NyV9LmNvbC1tZC02ey1tcy1mbGV4OjAgMCA1MCU7ZmxleDowIDAgNTAlO21heC13aWR0aDo1MCV9LmNvbC1tZC03ey1tcy1mbGV4OjAgMCA1OC4zMzMzMzMlO2ZsZXg6MCAwIDU4LjMzMzMzMyU7bWF4LXdpZHRoOjU4LjMzMzMzMyV9LmNvbC1tZC04ey1tcy1mbGV4OjAgMCA2Ni42NjY2NjclO2ZsZXg6MCAwIDY2LjY2NjY2NyU7bWF4LXdpZHRoOjY2LjY2NjY2NyV9LmNvbC1tZC05ey1tcy1mbGV4OjAgMCA3NSU7ZmxleDowIDAgNzUlO21heC13aWR0aDo3NSV9LmNvbC1tZC0xMHstbXMtZmxleDowIDAgODMuMzMzMzMzJTtmbGV4OjAgMCA4My4zMzMzMzMlO21heC13aWR0aDo4My4zMzMzMzMlfS5jb2wtbWQtMTF7LW1zLWZsZXg6MCAwIDkxLjY2NjY2NyU7ZmxleDowIDAgOTEuNjY2NjY3JTttYXgtd2lkdGg6OTEuNjY2NjY3JX0uY29sLW1kLTEyey1tcy1mbGV4OjAgMCAxMDAlO2ZsZXg6MCAwIDEwMCU7bWF4LXdpZHRoOjEwMCV9Lm9yZGVyLW1kLWZpcnN0ey1tcy1mbGV4LW9yZGVyOi0xO29yZGVyOi0xfS5vcmRlci1tZC1sYXN0ey1tcy1mbGV4LW9yZGVyOjEzO29yZGVyOjEzfS5vcmRlci1tZC0wey1tcy1mbGV4LW9yZGVyOjA7b3JkZXI6MH0ub3JkZXItbWQtMXstbXMtZmxleC1vcmRlcjoxO29yZGVyOjF9Lm9yZGVyLW1kLTJ7LW1zLWZsZXgtb3JkZXI6MjtvcmRlcjoyfS5vcmRlci1tZC0zey1tcy1mbGV4LW9yZGVyOjM7b3JkZXI6M30ub3JkZXItbWQtNHstbXMtZmxleC1vcmRlcjo0O29yZGVyOjR9Lm9yZGVyLW1kLTV7LW1zLWZsZXgtb3JkZXI6NTtvcmRlcjo1fS5vcmRlci1tZC02ey1tcy1mbGV4LW9yZGVyOjY7b3JkZXI6Nn0ub3JkZXItbWQtN3stbXMtZmxleC1vcmRlcjo3O29yZGVyOjd9Lm9yZGVyLW1kLTh7LW1zLWZsZXgtb3JkZXI6ODtvcmRlcjo4fS5vcmRlci1tZC05ey1tcy1mbGV4LW9yZGVyOjk7b3JkZXI6OX0ub3JkZXItbWQtMTB7LW1zLWZsZXgtb3JkZXI6MTA7b3JkZXI6MTB9Lm9yZGVyLW1kLTExey1tcy1mbGV4LW9yZGVyOjExO29yZGVyOjExfS5vcmRlci1tZC0xMnstbXMtZmxleC1vcmRlcjoxMjtvcmRlcjoxMn0ub2Zmc2V0LW1kLTB7bWFyZ2luLWxlZnQ6MH0ub2Zmc2V0LW1kLTF7bWFyZ2luLWxlZnQ6OC4zMzMzMzMlfS5vZmZzZXQtbWQtMnttYXJnaW4tbGVmdDoxNi42NjY2NjclfS5vZmZzZXQtbWQtM3ttYXJnaW4tbGVmdDoyNSV9Lm9mZnNldC1tZC00e21hcmdpbi1sZWZ0OjMzLjMzMzMzMyV9Lm9mZnNldC1tZC01e21hcmdpbi1sZWZ0OjQxLjY2NjY2NyV9Lm9mZnNldC1tZC02e21hcmdpbi1sZWZ0OjUwJX0ub2Zmc2V0LW1kLTd7bWFyZ2luLWxlZnQ6NTguMzMzMzMzJX0ub2Zmc2V0LW1kLTh7bWFyZ2luLWxlZnQ6NjYuNjY2NjY3JX0ub2Zmc2V0LW1kLTl7bWFyZ2luLWxlZnQ6NzUlfS5vZmZzZXQtbWQtMTB7bWFyZ2luLWxlZnQ6ODMuMzMzMzMzJX0ub2Zmc2V0LW1kLTExe21hcmdpbi1sZWZ0OjkxLjY2NjY2NyV9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuY29sLWxney1tcy1mbGV4LXByZWZlcnJlZC1zaXplOjA7ZmxleC1iYXNpczowOy1tcy1mbGV4LXBvc2l0aXZlOjE7ZmxleC1ncm93OjE7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLWxnLTE+KnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5yb3ctY29scy1sZy0yPip7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0ucm93LWNvbHMtbGctMz4qey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LnJvdy1jb2xzLWxnLTQ+KnstbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5yb3ctY29scy1sZy01Pip7LW1zLWZsZXg6MCAwIDIwJTtmbGV4OjAgMCAyMCU7bWF4LXdpZHRoOjIwJX0ucm93LWNvbHMtbGctNj4qey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC1sZy1hdXRvey1tcy1mbGV4OjAgMCBhdXRvO2ZsZXg6MCAwIGF1dG87d2lkdGg6YXV0bzttYXgtd2lkdGg6MTAwJX0uY29sLWxnLTF7LW1zLWZsZXg6MCAwIDguMzMzMzMzJTtmbGV4OjAgMCA4LjMzMzMzMyU7bWF4LXdpZHRoOjguMzMzMzMzJX0uY29sLWxnLTJ7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLWxnLTN7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0uY29sLWxnLTR7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0uY29sLWxnLTV7LW1zLWZsZXg6MCAwIDQxLjY2NjY2NyU7ZmxleDowIDAgNDEuNjY2NjY3JTttYXgtd2lkdGg6NDEuNjY2NjY3JX0uY29sLWxnLTZ7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0uY29sLWxnLTd7LW1zLWZsZXg6MCAwIDU4LjMzMzMzMyU7ZmxleDowIDAgNTguMzMzMzMzJTttYXgtd2lkdGg6NTguMzMzMzMzJX0uY29sLWxnLTh7LW1zLWZsZXg6MCAwIDY2LjY2NjY2NyU7ZmxleDowIDAgNjYuNjY2NjY3JTttYXgtd2lkdGg6NjYuNjY2NjY3JX0uY29sLWxnLTl7LW1zLWZsZXg6MCAwIDc1JTtmbGV4OjAgMCA3NSU7bWF4LXdpZHRoOjc1JX0uY29sLWxnLTEwey1tcy1mbGV4OjAgMCA4My4zMzMzMzMlO2ZsZXg6MCAwIDgzLjMzMzMzMyU7bWF4LXdpZHRoOjgzLjMzMzMzMyV9LmNvbC1sZy0xMXstbXMtZmxleDowIDAgOTEuNjY2NjY3JTtmbGV4OjAgMCA5MS42NjY2NjclO21heC13aWR0aDo5MS42NjY2NjclfS5jb2wtbGctMTJ7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ub3JkZXItbGctZmlyc3R7LW1zLWZsZXgtb3JkZXI6LTE7b3JkZXI6LTF9Lm9yZGVyLWxnLWxhc3R7LW1zLWZsZXgtb3JkZXI6MTM7b3JkZXI6MTN9Lm9yZGVyLWxnLTB7LW1zLWZsZXgtb3JkZXI6MDtvcmRlcjowfS5vcmRlci1sZy0xey1tcy1mbGV4LW9yZGVyOjE7b3JkZXI6MX0ub3JkZXItbGctMnstbXMtZmxleC1vcmRlcjoyO29yZGVyOjJ9Lm9yZGVyLWxnLTN7LW1zLWZsZXgtb3JkZXI6MztvcmRlcjozfS5vcmRlci1sZy00ey1tcy1mbGV4LW9yZGVyOjQ7b3JkZXI6NH0ub3JkZXItbGctNXstbXMtZmxleC1vcmRlcjo1O29yZGVyOjV9Lm9yZGVyLWxnLTZ7LW1zLWZsZXgtb3JkZXI6NjtvcmRlcjo2fS5vcmRlci1sZy03ey1tcy1mbGV4LW9yZGVyOjc7b3JkZXI6N30ub3JkZXItbGctOHstbXMtZmxleC1vcmRlcjo4O29yZGVyOjh9Lm9yZGVyLWxnLTl7LW1zLWZsZXgtb3JkZXI6OTtvcmRlcjo5fS5vcmRlci1sZy0xMHstbXMtZmxleC1vcmRlcjoxMDtvcmRlcjoxMH0ub3JkZXItbGctMTF7LW1zLWZsZXgtb3JkZXI6MTE7b3JkZXI6MTF9Lm9yZGVyLWxnLTEyey1tcy1mbGV4LW9yZGVyOjEyO29yZGVyOjEyfS5vZmZzZXQtbGctMHttYXJnaW4tbGVmdDowfS5vZmZzZXQtbGctMXttYXJnaW4tbGVmdDo4LjMzMzMzMyV9Lm9mZnNldC1sZy0ye21hcmdpbi1sZWZ0OjE2LjY2NjY2NyV9Lm9mZnNldC1sZy0ze21hcmdpbi1sZWZ0OjI1JX0ub2Zmc2V0LWxnLTR7bWFyZ2luLWxlZnQ6MzMuMzMzMzMzJX0ub2Zmc2V0LWxnLTV7bWFyZ2luLWxlZnQ6NDEuNjY2NjY3JX0ub2Zmc2V0LWxnLTZ7bWFyZ2luLWxlZnQ6NTAlfS5vZmZzZXQtbGctN3ttYXJnaW4tbGVmdDo1OC4zMzMzMzMlfS5vZmZzZXQtbGctOHttYXJnaW4tbGVmdDo2Ni42NjY2NjclfS5vZmZzZXQtbGctOXttYXJnaW4tbGVmdDo3NSV9Lm9mZnNldC1sZy0xMHttYXJnaW4tbGVmdDo4My4zMzMzMzMlfS5vZmZzZXQtbGctMTF7bWFyZ2luLWxlZnQ6OTEuNjY2NjY3JX19QG1lZGlhIChtaW4td2lkdGg6MTIwMHB4KXsuY29sLXhsey1tcy1mbGV4LXByZWZlcnJlZC1zaXplOjA7ZmxleC1iYXNpczowOy1tcy1mbGV4LXBvc2l0aXZlOjE7ZmxleC1ncm93OjE7bWF4LXdpZHRoOjEwMCV9LnJvdy1jb2xzLXhsLTE+KnstbXMtZmxleDowIDAgMTAwJTtmbGV4OjAgMCAxMDAlO21heC13aWR0aDoxMDAlfS5yb3ctY29scy14bC0yPip7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0ucm93LWNvbHMteGwtMz4qey1tcy1mbGV4OjAgMCAzMy4zMzMzMzMlO2ZsZXg6MCAwIDMzLjMzMzMzMyU7bWF4LXdpZHRoOjMzLjMzMzMzMyV9LnJvdy1jb2xzLXhsLTQ+KnstbXMtZmxleDowIDAgMjUlO2ZsZXg6MCAwIDI1JTttYXgtd2lkdGg6MjUlfS5yb3ctY29scy14bC01Pip7LW1zLWZsZXg6MCAwIDIwJTtmbGV4OjAgMCAyMCU7bWF4LXdpZHRoOjIwJX0ucm93LWNvbHMteGwtNj4qey1tcy1mbGV4OjAgMCAxNi42NjY2NjclO2ZsZXg6MCAwIDE2LjY2NjY2NyU7bWF4LXdpZHRoOjE2LjY2NjY2NyV9LmNvbC14bC1hdXRvey1tcy1mbGV4OjAgMCBhdXRvO2ZsZXg6MCAwIGF1dG87d2lkdGg6YXV0bzttYXgtd2lkdGg6MTAwJX0uY29sLXhsLTF7LW1zLWZsZXg6MCAwIDguMzMzMzMzJTtmbGV4OjAgMCA4LjMzMzMzMyU7bWF4LXdpZHRoOjguMzMzMzMzJX0uY29sLXhsLTJ7LW1zLWZsZXg6MCAwIDE2LjY2NjY2NyU7ZmxleDowIDAgMTYuNjY2NjY3JTttYXgtd2lkdGg6MTYuNjY2NjY3JX0uY29sLXhsLTN7LW1zLWZsZXg6MCAwIDI1JTtmbGV4OjAgMCAyNSU7bWF4LXdpZHRoOjI1JX0uY29sLXhsLTR7LW1zLWZsZXg6MCAwIDMzLjMzMzMzMyU7ZmxleDowIDAgMzMuMzMzMzMzJTttYXgtd2lkdGg6MzMuMzMzMzMzJX0uY29sLXhsLTV7LW1zLWZsZXg6MCAwIDQxLjY2NjY2NyU7ZmxleDowIDAgNDEuNjY2NjY3JTttYXgtd2lkdGg6NDEuNjY2NjY3JX0uY29sLXhsLTZ7LW1zLWZsZXg6MCAwIDUwJTtmbGV4OjAgMCA1MCU7bWF4LXdpZHRoOjUwJX0uY29sLXhsLTd7LW1zLWZsZXg6MCAwIDU4LjMzMzMzMyU7ZmxleDowIDAgNTguMzMzMzMzJTttYXgtd2lkdGg6NTguMzMzMzMzJX0uY29sLXhsLTh7LW1zLWZsZXg6MCAwIDY2LjY2NjY2NyU7ZmxleDowIDAgNjYuNjY2NjY3JTttYXgtd2lkdGg6NjYuNjY2NjY3JX0uY29sLXhsLTl7LW1zLWZsZXg6MCAwIDc1JTtmbGV4OjAgMCA3NSU7bWF4LXdpZHRoOjc1JX0uY29sLXhsLTEwey1tcy1mbGV4OjAgMCA4My4zMzMzMzMlO2ZsZXg6MCAwIDgzLjMzMzMzMyU7bWF4LXdpZHRoOjgzLjMzMzMzMyV9LmNvbC14bC0xMXstbXMtZmxleDowIDAgOTEuNjY2NjY3JTtmbGV4OjAgMCA5MS42NjY2NjclO21heC13aWR0aDo5MS42NjY2NjclfS5jb2wteGwtMTJ7LW1zLWZsZXg6MCAwIDEwMCU7ZmxleDowIDAgMTAwJTttYXgtd2lkdGg6MTAwJX0ub3JkZXIteGwtZmlyc3R7LW1zLWZsZXgtb3JkZXI6LTE7b3JkZXI6LTF9Lm9yZGVyLXhsLWxhc3R7LW1zLWZsZXgtb3JkZXI6MTM7b3JkZXI6MTN9Lm9yZGVyLXhsLTB7LW1zLWZsZXgtb3JkZXI6MDtvcmRlcjowfS5vcmRlci14bC0xey1tcy1mbGV4LW9yZGVyOjE7b3JkZXI6MX0ub3JkZXIteGwtMnstbXMtZmxleC1vcmRlcjoyO29yZGVyOjJ9Lm9yZGVyLXhsLTN7LW1zLWZsZXgtb3JkZXI6MztvcmRlcjozfS5vcmRlci14bC00ey1tcy1mbGV4LW9yZGVyOjQ7b3JkZXI6NH0ub3JkZXIteGwtNXstbXMtZmxleC1vcmRlcjo1O29yZGVyOjV9Lm9yZGVyLXhsLTZ7LW1zLWZsZXgtb3JkZXI6NjtvcmRlcjo2fS5vcmRlci14bC03ey1tcy1mbGV4LW9yZGVyOjc7b3JkZXI6N30ub3JkZXIteGwtOHstbXMtZmxleC1vcmRlcjo4O29yZGVyOjh9Lm9yZGVyLXhsLTl7LW1zLWZsZXgtb3JkZXI6OTtvcmRlcjo5fS5vcmRlci14bC0xMHstbXMtZmxleC1vcmRlcjoxMDtvcmRlcjoxMH0ub3JkZXIteGwtMTF7LW1zLWZsZXgtb3JkZXI6MTE7b3JkZXI6MTF9Lm9yZGVyLXhsLTEyey1tcy1mbGV4LW9yZGVyOjEyO29yZGVyOjEyfS5vZmZzZXQteGwtMHttYXJnaW4tbGVmdDowfS5vZmZzZXQteGwtMXttYXJnaW4tbGVmdDo4LjMzMzMzMyV9Lm9mZnNldC14bC0ye21hcmdpbi1sZWZ0OjE2LjY2NjY2NyV9Lm9mZnNldC14bC0ze21hcmdpbi1sZWZ0OjI1JX0ub2Zmc2V0LXhsLTR7bWFyZ2luLWxlZnQ6MzMuMzMzMzMzJX0ub2Zmc2V0LXhsLTV7bWFyZ2luLWxlZnQ6NDEuNjY2NjY3JX0ub2Zmc2V0LXhsLTZ7bWFyZ2luLWxlZnQ6NTAlfS5vZmZzZXQteGwtN3ttYXJnaW4tbGVmdDo1OC4zMzMzMzMlfS5vZmZzZXQteGwtOHttYXJnaW4tbGVmdDo2Ni42NjY2NjclfS5vZmZzZXQteGwtOXttYXJnaW4tbGVmdDo3NSV9Lm9mZnNldC14bC0xMHttYXJnaW4tbGVmdDo4My4zMzMzMzMlfS5vZmZzZXQteGwtMTF7bWFyZ2luLWxlZnQ6OTEuNjY2NjY3JX19LmQtbm9uZXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5kLWlubGluZXtkaXNwbGF5OmlubGluZSFpbXBvcnRhbnR9LmQtaW5saW5lLWJsb2Nre2Rpc3BsYXk6aW5saW5lLWJsb2NrIWltcG9ydGFudH0uZC1ibG9ja3tkaXNwbGF5OmJsb2NrIWltcG9ydGFudH0uZC10YWJsZXtkaXNwbGF5OnRhYmxlIWltcG9ydGFudH0uZC10YWJsZS1yb3d7ZGlzcGxheTp0YWJsZS1yb3chaW1wb3J0YW50fS5kLXRhYmxlLWNlbGx7ZGlzcGxheTp0YWJsZS1jZWxsIWltcG9ydGFudH0uZC1mbGV4e2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnR9LmQtaW5saW5lLWZsZXh7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6aW5saW5lLWZsZXghaW1wb3J0YW50fUBtZWRpYSAobWluLXdpZHRoOjU3NnB4KXsuZC1zbS1ub25le2Rpc3BsYXk6bm9uZSFpbXBvcnRhbnR9LmQtc20taW5saW5le2Rpc3BsYXk6aW5saW5lIWltcG9ydGFudH0uZC1zbS1pbmxpbmUtYmxvY2t7ZGlzcGxheTppbmxpbmUtYmxvY2shaW1wb3J0YW50fS5kLXNtLWJsb2Nre2Rpc3BsYXk6YmxvY2shaW1wb3J0YW50fS5kLXNtLXRhYmxle2Rpc3BsYXk6dGFibGUhaW1wb3J0YW50fS5kLXNtLXRhYmxlLXJvd3tkaXNwbGF5OnRhYmxlLXJvdyFpbXBvcnRhbnR9LmQtc20tdGFibGUtY2VsbHtkaXNwbGF5OnRhYmxlLWNlbGwhaW1wb3J0YW50fS5kLXNtLWZsZXh7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudH0uZC1zbS1pbmxpbmUtZmxleHtkaXNwbGF5Oi1tcy1pbmxpbmUtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTppbmxpbmUtZmxleCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsuZC1tZC1ub25le2Rpc3BsYXk6bm9uZSFpbXBvcnRhbnR9LmQtbWQtaW5saW5le2Rpc3BsYXk6aW5saW5lIWltcG9ydGFudH0uZC1tZC1pbmxpbmUtYmxvY2t7ZGlzcGxheTppbmxpbmUtYmxvY2shaW1wb3J0YW50fS5kLW1kLWJsb2Nre2Rpc3BsYXk6YmxvY2shaW1wb3J0YW50fS5kLW1kLXRhYmxle2Rpc3BsYXk6dGFibGUhaW1wb3J0YW50fS5kLW1kLXRhYmxlLXJvd3tkaXNwbGF5OnRhYmxlLXJvdyFpbXBvcnRhbnR9LmQtbWQtdGFibGUtY2VsbHtkaXNwbGF5OnRhYmxlLWNlbGwhaW1wb3J0YW50fS5kLW1kLWZsZXh7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudH0uZC1tZC1pbmxpbmUtZmxleHtkaXNwbGF5Oi1tcy1pbmxpbmUtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTppbmxpbmUtZmxleCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjk5MnB4KXsuZC1sZy1ub25le2Rpc3BsYXk6bm9uZSFpbXBvcnRhbnR9LmQtbGctaW5saW5le2Rpc3BsYXk6aW5saW5lIWltcG9ydGFudH0uZC1sZy1pbmxpbmUtYmxvY2t7ZGlzcGxheTppbmxpbmUtYmxvY2shaW1wb3J0YW50fS5kLWxnLWJsb2Nre2Rpc3BsYXk6YmxvY2shaW1wb3J0YW50fS5kLWxnLXRhYmxle2Rpc3BsYXk6dGFibGUhaW1wb3J0YW50fS5kLWxnLXRhYmxlLXJvd3tkaXNwbGF5OnRhYmxlLXJvdyFpbXBvcnRhbnR9LmQtbGctdGFibGUtY2VsbHtkaXNwbGF5OnRhYmxlLWNlbGwhaW1wb3J0YW50fS5kLWxnLWZsZXh7ZGlzcGxheTotbXMtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTpmbGV4IWltcG9ydGFudH0uZC1sZy1pbmxpbmUtZmxleHtkaXNwbGF5Oi1tcy1pbmxpbmUtZmxleGJveCFpbXBvcnRhbnQ7ZGlzcGxheTppbmxpbmUtZmxleCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjEyMDBweCl7LmQteGwtbm9uZXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5kLXhsLWlubGluZXtkaXNwbGF5OmlubGluZSFpbXBvcnRhbnR9LmQteGwtaW5saW5lLWJsb2Nre2Rpc3BsYXk6aW5saW5lLWJsb2NrIWltcG9ydGFudH0uZC14bC1ibG9ja3tkaXNwbGF5OmJsb2NrIWltcG9ydGFudH0uZC14bC10YWJsZXtkaXNwbGF5OnRhYmxlIWltcG9ydGFudH0uZC14bC10YWJsZS1yb3d7ZGlzcGxheTp0YWJsZS1yb3chaW1wb3J0YW50fS5kLXhsLXRhYmxlLWNlbGx7ZGlzcGxheTp0YWJsZS1jZWxsIWltcG9ydGFudH0uZC14bC1mbGV4e2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnR9LmQteGwtaW5saW5lLWZsZXh7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6aW5saW5lLWZsZXghaW1wb3J0YW50fX1AbWVkaWEgcHJpbnR7LmQtcHJpbnQtbm9uZXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fS5kLXByaW50LWlubGluZXtkaXNwbGF5OmlubGluZSFpbXBvcnRhbnR9LmQtcHJpbnQtaW5saW5lLWJsb2Nre2Rpc3BsYXk6aW5saW5lLWJsb2NrIWltcG9ydGFudH0uZC1wcmludC1ibG9ja3tkaXNwbGF5OmJsb2NrIWltcG9ydGFudH0uZC1wcmludC10YWJsZXtkaXNwbGF5OnRhYmxlIWltcG9ydGFudH0uZC1wcmludC10YWJsZS1yb3d7ZGlzcGxheTp0YWJsZS1yb3chaW1wb3J0YW50fS5kLXByaW50LXRhYmxlLWNlbGx7ZGlzcGxheTp0YWJsZS1jZWxsIWltcG9ydGFudH0uZC1wcmludC1mbGV4e2Rpc3BsYXk6LW1zLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6ZmxleCFpbXBvcnRhbnR9LmQtcHJpbnQtaW5saW5lLWZsZXh7ZGlzcGxheTotbXMtaW5saW5lLWZsZXhib3ghaW1wb3J0YW50O2Rpc3BsYXk6aW5saW5lLWZsZXghaW1wb3J0YW50fX0uZmxleC1yb3d7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudH0uZmxleC1jb2x1bW57LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudH0uZmxleC1yb3ctcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1jb2x1bW4tcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudH0uZmxleC13cmFwey1tcy1mbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAhaW1wb3J0YW50fS5mbGV4LW5vd3JhcHstbXMtZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnR9LmZsZXgtd3JhcC1yZXZlcnNley1tcy1mbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1maWxsey1tcy1mbGV4OjEgMSBhdXRvIWltcG9ydGFudDtmbGV4OjEgMSBhdXRvIWltcG9ydGFudH0uZmxleC1ncm93LTB7LW1zLWZsZXgtcG9zaXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1ncm93OjAhaW1wb3J0YW50fS5mbGV4LWdyb3ctMXstbXMtZmxleC1wb3NpdGl2ZToxIWltcG9ydGFudDtmbGV4LWdyb3c6MSFpbXBvcnRhbnR9LmZsZXgtc2hyaW5rLTB7LW1zLWZsZXgtbmVnYXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MCFpbXBvcnRhbnR9LmZsZXgtc2hyaW5rLTF7LW1zLWZsZXgtbmVnYXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MSFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1zdGFydHstbXMtZmxleC1wYWNrOnN0YXJ0IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1lbmR7LW1zLWZsZXgtcGFjazplbmQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1jZW50ZXJ7LW1zLWZsZXgtcGFjazpjZW50ZXIhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtYmV0d2VlbnstbXMtZmxleC1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LWFyb3VuZHstbXMtZmxleC1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1zdGFydHstbXMtZmxleC1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLWVuZHstbXMtZmxleC1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMtY2VudGVyey1tcy1mbGV4LWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24taXRlbXMtYmFzZWxpbmV7LW1zLWZsZXgtYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24taXRlbXMtc3RyZXRjaHstbXMtZmxleC1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1pdGVtczpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1zdGFydHstbXMtZmxleC1saW5lLXBhY2s6c3RhcnQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtZW5key1tcy1mbGV4LWxpbmUtcGFjazplbmQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWNlbnRlcnstbXMtZmxleC1saW5lLXBhY2s6Y2VudGVyIWltcG9ydGFudDthbGlnbi1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtYmV0d2VlbnstbXMtZmxleC1saW5lLXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1hcm91bmR7LW1zLWZsZXgtbGluZS1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1zdHJldGNoey1tcy1mbGV4LWxpbmUtcGFjazpzdHJldGNoIWltcG9ydGFudDthbGlnbi1jb250ZW50OnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1zZWxmLWF1dG97LW1zLWZsZXgtaXRlbS1hbGlnbjphdXRvIWltcG9ydGFudDthbGlnbi1zZWxmOmF1dG8haW1wb3J0YW50fS5hbGlnbi1zZWxmLXN0YXJ0ey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtZW5key1tcy1mbGV4LWl0ZW0tYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tc2VsZi1jZW50ZXJ7LW1zLWZsZXgtaXRlbS1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLXNlbGY6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tc2VsZi1iYXNlbGluZXstbXMtZmxleC1pdGVtLWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1zZWxmOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24tc2VsZi1zdHJldGNoey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpzdHJldGNoIWltcG9ydGFudH1AbWVkaWEgKG1pbi13aWR0aDo1NzZweCl7LmZsZXgtc20tcm93ey1tcy1mbGV4LWRpcmVjdGlvbjpyb3chaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnR9LmZsZXgtc20tY29sdW1uey1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW4haW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnR9LmZsZXgtc20tcm93LXJldmVyc2V7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpyb3ctcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtc20tY29sdW1uLXJldmVyc2V7LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpjb2x1bW4tcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtc20td3JhcHstbXMtZmxleC13cmFwOndyYXAhaW1wb3J0YW50O2ZsZXgtd3JhcDp3cmFwIWltcG9ydGFudH0uZmxleC1zbS1ub3dyYXB7LW1zLWZsZXgtd3JhcDpub3dyYXAhaW1wb3J0YW50O2ZsZXgtd3JhcDpub3dyYXAhaW1wb3J0YW50fS5mbGV4LXNtLXdyYXAtcmV2ZXJzZXstbXMtZmxleC13cmFwOndyYXAtcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAtcmV2ZXJzZSFpbXBvcnRhbnR9LmZsZXgtc20tZmlsbHstbXMtZmxleDoxIDEgYXV0byFpbXBvcnRhbnQ7ZmxleDoxIDEgYXV0byFpbXBvcnRhbnR9LmZsZXgtc20tZ3Jvdy0wey1tcy1mbGV4LXBvc2l0aXZlOjAhaW1wb3J0YW50O2ZsZXgtZ3JvdzowIWltcG9ydGFudH0uZmxleC1zbS1ncm93LTF7LW1zLWZsZXgtcG9zaXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1ncm93OjEhaW1wb3J0YW50fS5mbGV4LXNtLXNocmluay0wey1tcy1mbGV4LW5lZ2F0aXZlOjAhaW1wb3J0YW50O2ZsZXgtc2hyaW5rOjAhaW1wb3J0YW50fS5mbGV4LXNtLXNocmluay0xey1tcy1mbGV4LW5lZ2F0aXZlOjEhaW1wb3J0YW50O2ZsZXgtc2hyaW5rOjEhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtc20tc3RhcnR7LW1zLWZsZXgtcGFjazpzdGFydCFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmZsZXgtc3RhcnQhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtc20tZW5key1tcy1mbGV4LXBhY2s6ZW5kIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtc20tY2VudGVyey1tcy1mbGV4LXBhY2s6Y2VudGVyIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6Y2VudGVyIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXNtLWJldHdlZW57LW1zLWZsZXgtcGFjazpqdXN0aWZ5IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYmV0d2VlbiFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1zbS1hcm91bmR7LW1zLWZsZXgtcGFjazpkaXN0cmlidXRlIWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMtc20tc3RhcnR7LW1zLWZsZXgtYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1zbS1lbmR7LW1zLWZsZXgtYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1pdGVtczpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXNtLWNlbnRlcnstbXMtZmxleC1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXNtLWJhc2VsaW5ley1tcy1mbGV4LWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1pdGVtczpiYXNlbGluZSFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXNtLXN0cmV0Y2h7LW1zLWZsZXgtYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6c3RyZXRjaCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtc20tc3RhcnR7LW1zLWZsZXgtbGluZS1wYWNrOnN0YXJ0IWltcG9ydGFudDthbGlnbi1jb250ZW50OmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXNtLWVuZHstbXMtZmxleC1saW5lLXBhY2s6ZW5kIWltcG9ydGFudDthbGlnbi1jb250ZW50OmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1zbS1jZW50ZXJ7LW1zLWZsZXgtbGluZS1wYWNrOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXNtLWJldHdlZW57LW1zLWZsZXgtbGluZS1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYmV0d2VlbiFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtc20tYXJvdW5key1tcy1mbGV4LWxpbmUtcGFjazpkaXN0cmlidXRlIWltcG9ydGFudDthbGlnbi1jb250ZW50OnNwYWNlLWFyb3VuZCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtc20tc3RyZXRjaHstbXMtZmxleC1saW5lLXBhY2s6c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tc2VsZi1zbS1hdXRvey1tcy1mbGV4LWl0ZW0tYWxpZ246YXV0byFpbXBvcnRhbnQ7YWxpZ24tc2VsZjphdXRvIWltcG9ydGFudH0uYWxpZ24tc2VsZi1zbS1zdGFydHstbXMtZmxleC1pdGVtLWFsaWduOnN0YXJ0IWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtc3RhcnQhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXNtLWVuZHstbXMtZmxleC1pdGVtLWFsaWduOmVuZCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtc20tY2VudGVyey1tcy1mbGV4LWl0ZW0tYWxpZ246Y2VudGVyIWltcG9ydGFudDthbGlnbi1zZWxmOmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLXNlbGYtc20tYmFzZWxpbmV7LW1zLWZsZXgtaXRlbS1hbGlnbjpiYXNlbGluZSFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpiYXNlbGluZSFpbXBvcnRhbnR9LmFsaWduLXNlbGYtc20tc3RyZXRjaHstbXMtZmxleC1pdGVtLWFsaWduOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLXNlbGY6c3RyZXRjaCFpbXBvcnRhbnR9fUBtZWRpYSAobWluLXdpZHRoOjc2OHB4KXsuZmxleC1tZC1yb3d7LW1zLWZsZXgtZGlyZWN0aW9uOnJvdyFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudH0uZmxleC1tZC1jb2x1bW57LW1zLWZsZXgtZGlyZWN0aW9uOmNvbHVtbiFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudH0uZmxleC1tZC1yb3ctcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOnJvdy1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1tZC1jb2x1bW4tcmV2ZXJzZXstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtZGlyZWN0aW9uOmNvbHVtbi1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1tZC13cmFwey1tcy1mbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOndyYXAhaW1wb3J0YW50fS5mbGV4LW1kLW5vd3JhcHstbXMtZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnQ7ZmxleC13cmFwOm5vd3JhcCFpbXBvcnRhbnR9LmZsZXgtbWQtd3JhcC1yZXZlcnNley1tcy1mbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcC1yZXZlcnNlIWltcG9ydGFudH0uZmxleC1tZC1maWxsey1tcy1mbGV4OjEgMSBhdXRvIWltcG9ydGFudDtmbGV4OjEgMSBhdXRvIWltcG9ydGFudH0uZmxleC1tZC1ncm93LTB7LW1zLWZsZXgtcG9zaXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1ncm93OjAhaW1wb3J0YW50fS5mbGV4LW1kLWdyb3ctMXstbXMtZmxleC1wb3NpdGl2ZToxIWltcG9ydGFudDtmbGV4LWdyb3c6MSFpbXBvcnRhbnR9LmZsZXgtbWQtc2hyaW5rLTB7LW1zLWZsZXgtbmVnYXRpdmU6MCFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MCFpbXBvcnRhbnR9LmZsZXgtbWQtc2hyaW5rLTF7LW1zLWZsZXgtbmVnYXRpdmU6MSFpbXBvcnRhbnQ7ZmxleC1zaHJpbms6MSFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1tZC1zdGFydHstbXMtZmxleC1wYWNrOnN0YXJ0IWltcG9ydGFudDtqdXN0aWZ5LWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1tZC1lbmR7LW1zLWZsZXgtcGFjazplbmQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1tZC1jZW50ZXJ7LW1zLWZsZXgtcGFjazpjZW50ZXIhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpjZW50ZXIhaW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtbWQtYmV0d2VlbnstbXMtZmxleC1wYWNrOmp1c3RpZnkhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LW1kLWFyb3VuZHstbXMtZmxleC1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1tZC1zdGFydHstbXMtZmxleC1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLW1kLWVuZHstbXMtZmxleC1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbWQtY2VudGVyey1tcy1mbGV4LWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbWQtYmFzZWxpbmV7LW1zLWZsZXgtYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLWl0ZW1zOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24taXRlbXMtbWQtc3RyZXRjaHstbXMtZmxleC1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1pdGVtczpzdHJldGNoIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1tZC1zdGFydHstbXMtZmxleC1saW5lLXBhY2s6c3RhcnQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbWQtZW5key1tcy1mbGV4LWxpbmUtcGFjazplbmQhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LW1kLWNlbnRlcnstbXMtZmxleC1saW5lLXBhY2s6Y2VudGVyIWltcG9ydGFudDthbGlnbi1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbWQtYmV0d2VlbnstbXMtZmxleC1saW5lLXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1iZXR3ZWVuIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1tZC1hcm91bmR7LW1zLWZsZXgtbGluZS1wYWNrOmRpc3RyaWJ1dGUhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3BhY2UtYXJvdW5kIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1tZC1zdHJldGNoey1tcy1mbGV4LWxpbmUtcGFjazpzdHJldGNoIWltcG9ydGFudDthbGlnbi1jb250ZW50OnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1zZWxmLW1kLWF1dG97LW1zLWZsZXgtaXRlbS1hbGlnbjphdXRvIWltcG9ydGFudDthbGlnbi1zZWxmOmF1dG8haW1wb3J0YW50fS5hbGlnbi1zZWxmLW1kLXN0YXJ0ey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RhcnQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1zdGFydCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbWQtZW5key1tcy1mbGV4LWl0ZW0tYWxpZ246ZW5kIWltcG9ydGFudDthbGlnbi1zZWxmOmZsZXgtZW5kIWltcG9ydGFudH0uYWxpZ24tc2VsZi1tZC1jZW50ZXJ7LW1zLWZsZXgtaXRlbS1hbGlnbjpjZW50ZXIhaW1wb3J0YW50O2FsaWduLXNlbGY6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tc2VsZi1tZC1iYXNlbGluZXstbXMtZmxleC1pdGVtLWFsaWduOmJhc2VsaW5lIWltcG9ydGFudDthbGlnbi1zZWxmOmJhc2VsaW5lIWltcG9ydGFudH0uYWxpZ24tc2VsZi1tZC1zdHJldGNoey1tcy1mbGV4LWl0ZW0tYWxpZ246c3RyZXRjaCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpzdHJldGNoIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6OTkycHgpey5mbGV4LWxnLXJvd3stbXMtZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpyb3chaW1wb3J0YW50fS5mbGV4LWxnLWNvbHVtbnstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpjb2x1bW4haW1wb3J0YW50fS5mbGV4LWxnLXJvdy1yZXZlcnNley1tcy1mbGV4LWRpcmVjdGlvbjpyb3ctcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LWxnLWNvbHVtbi1yZXZlcnNley1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW4tcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LWxnLXdyYXB7LW1zLWZsZXgtd3JhcDp3cmFwIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnR9LmZsZXgtbGctbm93cmFwey1tcy1mbGV4LXdyYXA6bm93cmFwIWltcG9ydGFudDtmbGV4LXdyYXA6bm93cmFwIWltcG9ydGFudH0uZmxleC1sZy13cmFwLXJldmVyc2V7LW1zLWZsZXgtd3JhcDp3cmFwLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtd3JhcDp3cmFwLXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LWxnLWZpbGx7LW1zLWZsZXg6MSAxIGF1dG8haW1wb3J0YW50O2ZsZXg6MSAxIGF1dG8haW1wb3J0YW50fS5mbGV4LWxnLWdyb3ctMHstbXMtZmxleC1wb3NpdGl2ZTowIWltcG9ydGFudDtmbGV4LWdyb3c6MCFpbXBvcnRhbnR9LmZsZXgtbGctZ3Jvdy0xey1tcy1mbGV4LXBvc2l0aXZlOjEhaW1wb3J0YW50O2ZsZXgtZ3JvdzoxIWltcG9ydGFudH0uZmxleC1sZy1zaHJpbmstMHstbXMtZmxleC1uZWdhdGl2ZTowIWltcG9ydGFudDtmbGV4LXNocmluazowIWltcG9ydGFudH0uZmxleC1sZy1zaHJpbmstMXstbXMtZmxleC1uZWdhdGl2ZToxIWltcG9ydGFudDtmbGV4LXNocmluazoxIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LWxnLXN0YXJ0ey1tcy1mbGV4LXBhY2s6c3RhcnQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LXN0YXJ0IWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LWxnLWVuZHstbXMtZmxleC1wYWNrOmVuZCFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmZsZXgtZW5kIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LWxnLWNlbnRlcnstbXMtZmxleC1wYWNrOmNlbnRlciFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC1sZy1iZXR3ZWVuey1tcy1mbGV4LXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OnNwYWNlLWJldHdlZW4haW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQtbGctYXJvdW5key1tcy1mbGV4LXBhY2s6ZGlzdHJpYnV0ZSFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OnNwYWNlLWFyb3VuZCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLWxnLXN0YXJ0ey1tcy1mbGV4LWFsaWduOnN0YXJ0IWltcG9ydGFudDthbGlnbi1pdGVtczpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24taXRlbXMtbGctZW5key1tcy1mbGV4LWFsaWduOmVuZCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1sZy1jZW50ZXJ7LW1zLWZsZXgtYWxpZ246Y2VudGVyIWltcG9ydGFudDthbGlnbi1pdGVtczpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1sZy1iYXNlbGluZXstbXMtZmxleC1hbGlnbjpiYXNlbGluZSFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6YmFzZWxpbmUhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy1sZy1zdHJldGNoey1tcy1mbGV4LWFsaWduOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLWl0ZW1zOnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWxnLXN0YXJ0ey1tcy1mbGV4LWxpbmUtcGFjazpzdGFydCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24tY29udGVudC1sZy1lbmR7LW1zLWZsZXgtbGluZS1wYWNrOmVuZCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQtbGctY2VudGVyey1tcy1mbGV4LWxpbmUtcGFjazpjZW50ZXIhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tY29udGVudC1sZy1iZXR3ZWVuey1tcy1mbGV4LWxpbmUtcGFjazpqdXN0aWZ5IWltcG9ydGFudDthbGlnbi1jb250ZW50OnNwYWNlLWJldHdlZW4haW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWxnLWFyb3VuZHstbXMtZmxleC1saW5lLXBhY2s6ZGlzdHJpYnV0ZSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LWxnLXN0cmV0Y2h7LW1zLWZsZXgtbGluZS1wYWNrOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3RyZXRjaCFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbGctYXV0b3stbXMtZmxleC1pdGVtLWFsaWduOmF1dG8haW1wb3J0YW50O2FsaWduLXNlbGY6YXV0byFpbXBvcnRhbnR9LmFsaWduLXNlbGYtbGctc3RhcnR7LW1zLWZsZXgtaXRlbS1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24tc2VsZi1sZy1lbmR7LW1zLWZsZXgtaXRlbS1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1zZWxmLWxnLWNlbnRlcnstbXMtZmxleC1pdGVtLWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1zZWxmLWxnLWJhc2VsaW5ley1tcy1mbGV4LWl0ZW0tYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLXNlbGY6YmFzZWxpbmUhaW1wb3J0YW50fS5hbGlnbi1zZWxmLWxnLXN0cmV0Y2h7LW1zLWZsZXgtaXRlbS1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1zZWxmOnN0cmV0Y2ghaW1wb3J0YW50fX1AbWVkaWEgKG1pbi13aWR0aDoxMjAwcHgpey5mbGV4LXhsLXJvd3stbXMtZmxleC1kaXJlY3Rpb246cm93IWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpyb3chaW1wb3J0YW50fS5mbGV4LXhsLWNvbHVtbnstbXMtZmxleC1kaXJlY3Rpb246Y29sdW1uIWltcG9ydGFudDtmbGV4LWRpcmVjdGlvbjpjb2x1bW4haW1wb3J0YW50fS5mbGV4LXhsLXJvdy1yZXZlcnNley1tcy1mbGV4LWRpcmVjdGlvbjpyb3ctcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246cm93LXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LXhsLWNvbHVtbi1yZXZlcnNley1tcy1mbGV4LWRpcmVjdGlvbjpjb2x1bW4tcmV2ZXJzZSFpbXBvcnRhbnQ7ZmxleC1kaXJlY3Rpb246Y29sdW1uLXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LXhsLXdyYXB7LW1zLWZsZXgtd3JhcDp3cmFwIWltcG9ydGFudDtmbGV4LXdyYXA6d3JhcCFpbXBvcnRhbnR9LmZsZXgteGwtbm93cmFwey1tcy1mbGV4LXdyYXA6bm93cmFwIWltcG9ydGFudDtmbGV4LXdyYXA6bm93cmFwIWltcG9ydGFudH0uZmxleC14bC13cmFwLXJldmVyc2V7LW1zLWZsZXgtd3JhcDp3cmFwLXJldmVyc2UhaW1wb3J0YW50O2ZsZXgtd3JhcDp3cmFwLXJldmVyc2UhaW1wb3J0YW50fS5mbGV4LXhsLWZpbGx7LW1zLWZsZXg6MSAxIGF1dG8haW1wb3J0YW50O2ZsZXg6MSAxIGF1dG8haW1wb3J0YW50fS5mbGV4LXhsLWdyb3ctMHstbXMtZmxleC1wb3NpdGl2ZTowIWltcG9ydGFudDtmbGV4LWdyb3c6MCFpbXBvcnRhbnR9LmZsZXgteGwtZ3Jvdy0xey1tcy1mbGV4LXBvc2l0aXZlOjEhaW1wb3J0YW50O2ZsZXgtZ3JvdzoxIWltcG9ydGFudH0uZmxleC14bC1zaHJpbmstMHstbXMtZmxleC1uZWdhdGl2ZTowIWltcG9ydGFudDtmbGV4LXNocmluazowIWltcG9ydGFudH0uZmxleC14bC1zaHJpbmstMXstbXMtZmxleC1uZWdhdGl2ZToxIWltcG9ydGFudDtmbGV4LXNocmluazoxIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXhsLXN0YXJ0ey1tcy1mbGV4LXBhY2s6c3RhcnQhaW1wb3J0YW50O2p1c3RpZnktY29udGVudDpmbGV4LXN0YXJ0IWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXhsLWVuZHstbXMtZmxleC1wYWNrOmVuZCFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmZsZXgtZW5kIWltcG9ydGFudH0uanVzdGlmeS1jb250ZW50LXhsLWNlbnRlcnstbXMtZmxleC1wYWNrOmNlbnRlciFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OmNlbnRlciFpbXBvcnRhbnR9Lmp1c3RpZnktY29udGVudC14bC1iZXR3ZWVuey1tcy1mbGV4LXBhY2s6anVzdGlmeSFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OnNwYWNlLWJldHdlZW4haW1wb3J0YW50fS5qdXN0aWZ5LWNvbnRlbnQteGwtYXJvdW5key1tcy1mbGV4LXBhY2s6ZGlzdHJpYnV0ZSFpbXBvcnRhbnQ7anVzdGlmeS1jb250ZW50OnNwYWNlLWFyb3VuZCFpbXBvcnRhbnR9LmFsaWduLWl0ZW1zLXhsLXN0YXJ0ey1tcy1mbGV4LWFsaWduOnN0YXJ0IWltcG9ydGFudDthbGlnbi1pdGVtczpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24taXRlbXMteGwtZW5key1tcy1mbGV4LWFsaWduOmVuZCFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy14bC1jZW50ZXJ7LW1zLWZsZXgtYWxpZ246Y2VudGVyIWltcG9ydGFudDthbGlnbi1pdGVtczpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy14bC1iYXNlbGluZXstbXMtZmxleC1hbGlnbjpiYXNlbGluZSFpbXBvcnRhbnQ7YWxpZ24taXRlbXM6YmFzZWxpbmUhaW1wb3J0YW50fS5hbGlnbi1pdGVtcy14bC1zdHJldGNoey1tcy1mbGV4LWFsaWduOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLWl0ZW1zOnN0cmV0Y2ghaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXhsLXN0YXJ0ey1tcy1mbGV4LWxpbmUtcGFjazpzdGFydCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24tY29udGVudC14bC1lbmR7LW1zLWZsZXgtbGluZS1wYWNrOmVuZCFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpmbGV4LWVuZCFpbXBvcnRhbnR9LmFsaWduLWNvbnRlbnQteGwtY2VudGVyey1tcy1mbGV4LWxpbmUtcGFjazpjZW50ZXIhaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6Y2VudGVyIWltcG9ydGFudH0uYWxpZ24tY29udGVudC14bC1iZXR3ZWVuey1tcy1mbGV4LWxpbmUtcGFjazpqdXN0aWZ5IWltcG9ydGFudDthbGlnbi1jb250ZW50OnNwYWNlLWJldHdlZW4haW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXhsLWFyb3VuZHstbXMtZmxleC1saW5lLXBhY2s6ZGlzdHJpYnV0ZSFpbXBvcnRhbnQ7YWxpZ24tY29udGVudDpzcGFjZS1hcm91bmQhaW1wb3J0YW50fS5hbGlnbi1jb250ZW50LXhsLXN0cmV0Y2h7LW1zLWZsZXgtbGluZS1wYWNrOnN0cmV0Y2ghaW1wb3J0YW50O2FsaWduLWNvbnRlbnQ6c3RyZXRjaCFpbXBvcnRhbnR9LmFsaWduLXNlbGYteGwtYXV0b3stbXMtZmxleC1pdGVtLWFsaWduOmF1dG8haW1wb3J0YW50O2FsaWduLXNlbGY6YXV0byFpbXBvcnRhbnR9LmFsaWduLXNlbGYteGwtc3RhcnR7LW1zLWZsZXgtaXRlbS1hbGlnbjpzdGFydCFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpmbGV4LXN0YXJ0IWltcG9ydGFudH0uYWxpZ24tc2VsZi14bC1lbmR7LW1zLWZsZXgtaXRlbS1hbGlnbjplbmQhaW1wb3J0YW50O2FsaWduLXNlbGY6ZmxleC1lbmQhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXhsLWNlbnRlcnstbXMtZmxleC1pdGVtLWFsaWduOmNlbnRlciFpbXBvcnRhbnQ7YWxpZ24tc2VsZjpjZW50ZXIhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXhsLWJhc2VsaW5ley1tcy1mbGV4LWl0ZW0tYWxpZ246YmFzZWxpbmUhaW1wb3J0YW50O2FsaWduLXNlbGY6YmFzZWxpbmUhaW1wb3J0YW50fS5hbGlnbi1zZWxmLXhsLXN0cmV0Y2h7LW1zLWZsZXgtaXRlbS1hbGlnbjpzdHJldGNoIWltcG9ydGFudDthbGlnbi1zZWxmOnN0cmV0Y2ghaW1wb3J0YW50fX0ubS0we21hcmdpbjowIWltcG9ydGFudH0ubXQtMCwubXktMHttYXJnaW4tdG9wOjAhaW1wb3J0YW50fS5tci0wLC5teC0we21hcmdpbi1yaWdodDowIWltcG9ydGFudH0ubWItMCwubXktMHttYXJnaW4tYm90dG9tOjAhaW1wb3J0YW50fS5tbC0wLC5teC0we21hcmdpbi1sZWZ0OjAhaW1wb3J0YW50fS5tLTF7bWFyZ2luOi4yNXJlbSFpbXBvcnRhbnR9Lm10LTEsLm15LTF7bWFyZ2luLXRvcDouMjVyZW0haW1wb3J0YW50fS5tci0xLC5teC0xe21hcmdpbi1yaWdodDouMjVyZW0haW1wb3J0YW50fS5tYi0xLC5teS0xe21hcmdpbi1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ubWwtMSwubXgtMXttYXJnaW4tbGVmdDouMjVyZW0haW1wb3J0YW50fS5tLTJ7bWFyZ2luOi41cmVtIWltcG9ydGFudH0ubXQtMiwubXktMnttYXJnaW4tdG9wOi41cmVtIWltcG9ydGFudH0ubXItMiwubXgtMnttYXJnaW4tcmlnaHQ6LjVyZW0haW1wb3J0YW50fS5tYi0yLC5teS0ye21hcmdpbi1ib3R0b206LjVyZW0haW1wb3J0YW50fS5tbC0yLC5teC0ye21hcmdpbi1sZWZ0Oi41cmVtIWltcG9ydGFudH0ubS0ze21hcmdpbjoxcmVtIWltcG9ydGFudH0ubXQtMywubXktM3ttYXJnaW4tdG9wOjFyZW0haW1wb3J0YW50fS5tci0zLC5teC0ze21hcmdpbi1yaWdodDoxcmVtIWltcG9ydGFudH0ubWItMywubXktM3ttYXJnaW4tYm90dG9tOjFyZW0haW1wb3J0YW50fS5tbC0zLC5teC0ze21hcmdpbi1sZWZ0OjFyZW0haW1wb3J0YW50fS5tLTR7bWFyZ2luOjEuNXJlbSFpbXBvcnRhbnR9Lm10LTQsLm15LTR7bWFyZ2luLXRvcDoxLjVyZW0haW1wb3J0YW50fS5tci00LC5teC00e21hcmdpbi1yaWdodDoxLjVyZW0haW1wb3J0YW50fS5tYi00LC5teS00e21hcmdpbi1ib3R0b206MS41cmVtIWltcG9ydGFudH0ubWwtNCwubXgtNHttYXJnaW4tbGVmdDoxLjVyZW0haW1wb3J0YW50fS5tLTV7bWFyZ2luOjNyZW0haW1wb3J0YW50fS5tdC01LC5teS01e21hcmdpbi10b3A6M3JlbSFpbXBvcnRhbnR9Lm1yLTUsLm14LTV7bWFyZ2luLXJpZ2h0OjNyZW0haW1wb3J0YW50fS5tYi01LC5teS01e21hcmdpbi1ib3R0b206M3JlbSFpbXBvcnRhbnR9Lm1sLTUsLm14LTV7bWFyZ2luLWxlZnQ6M3JlbSFpbXBvcnRhbnR9LnAtMHtwYWRkaW5nOjAhaW1wb3J0YW50fS5wdC0wLC5weS0we3BhZGRpbmctdG9wOjAhaW1wb3J0YW50fS5wci0wLC5weC0we3BhZGRpbmctcmlnaHQ6MCFpbXBvcnRhbnR9LnBiLTAsLnB5LTB7cGFkZGluZy1ib3R0b206MCFpbXBvcnRhbnR9LnBsLTAsLnB4LTB7cGFkZGluZy1sZWZ0OjAhaW1wb3J0YW50fS5wLTF7cGFkZGluZzouMjVyZW0haW1wb3J0YW50fS5wdC0xLC5weS0xe3BhZGRpbmctdG9wOi4yNXJlbSFpbXBvcnRhbnR9LnByLTEsLnB4LTF7cGFkZGluZy1yaWdodDouMjVyZW0haW1wb3J0YW50fS5wYi0xLC5weS0xe3BhZGRpbmctYm90dG9tOi4yNXJlbSFpbXBvcnRhbnR9LnBsLTEsLnB4LTF7cGFkZGluZy1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9LnAtMntwYWRkaW5nOi41cmVtIWltcG9ydGFudH0ucHQtMiwucHktMntwYWRkaW5nLXRvcDouNXJlbSFpbXBvcnRhbnR9LnByLTIsLnB4LTJ7cGFkZGluZy1yaWdodDouNXJlbSFpbXBvcnRhbnR9LnBiLTIsLnB5LTJ7cGFkZGluZy1ib3R0b206LjVyZW0haW1wb3J0YW50fS5wbC0yLC5weC0ye3BhZGRpbmctbGVmdDouNXJlbSFpbXBvcnRhbnR9LnAtM3twYWRkaW5nOjFyZW0haW1wb3J0YW50fS5wdC0zLC5weS0ze3BhZGRpbmctdG9wOjFyZW0haW1wb3J0YW50fS5wci0zLC5weC0ze3BhZGRpbmctcmlnaHQ6MXJlbSFpbXBvcnRhbnR9LnBiLTMsLnB5LTN7cGFkZGluZy1ib3R0b206MXJlbSFpbXBvcnRhbnR9LnBsLTMsLnB4LTN7cGFkZGluZy1sZWZ0OjFyZW0haW1wb3J0YW50fS5wLTR7cGFkZGluZzoxLjVyZW0haW1wb3J0YW50fS5wdC00LC5weS00e3BhZGRpbmctdG9wOjEuNXJlbSFpbXBvcnRhbnR9LnByLTQsLnB4LTR7cGFkZGluZy1yaWdodDoxLjVyZW0haW1wb3J0YW50fS5wYi00LC5weS00e3BhZGRpbmctYm90dG9tOjEuNXJlbSFpbXBvcnRhbnR9LnBsLTQsLnB4LTR7cGFkZGluZy1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9LnAtNXtwYWRkaW5nOjNyZW0haW1wb3J0YW50fS5wdC01LC5weS01e3BhZGRpbmctdG9wOjNyZW0haW1wb3J0YW50fS5wci01LC5weC01e3BhZGRpbmctcmlnaHQ6M3JlbSFpbXBvcnRhbnR9LnBiLTUsLnB5LTV7cGFkZGluZy1ib3R0b206M3JlbSFpbXBvcnRhbnR9LnBsLTUsLnB4LTV7cGFkZGluZy1sZWZ0OjNyZW0haW1wb3J0YW50fS5tLW4xe21hcmdpbjotLjI1cmVtIWltcG9ydGFudH0ubXQtbjEsLm15LW4xe21hcmdpbi10b3A6LS4yNXJlbSFpbXBvcnRhbnR9Lm1yLW4xLC5teC1uMXttYXJnaW4tcmlnaHQ6LS4yNXJlbSFpbXBvcnRhbnR9Lm1iLW4xLC5teS1uMXttYXJnaW4tYm90dG9tOi0uMjVyZW0haW1wb3J0YW50fS5tbC1uMSwubXgtbjF7bWFyZ2luLWxlZnQ6LS4yNXJlbSFpbXBvcnRhbnR9Lm0tbjJ7bWFyZ2luOi0uNXJlbSFpbXBvcnRhbnR9Lm10LW4yLC5teS1uMnttYXJnaW4tdG9wOi0uNXJlbSFpbXBvcnRhbnR9Lm1yLW4yLC5teC1uMnttYXJnaW4tcmlnaHQ6LS41cmVtIWltcG9ydGFudH0ubWItbjIsLm15LW4ye21hcmdpbi1ib3R0b206LS41cmVtIWltcG9ydGFudH0ubWwtbjIsLm14LW4ye21hcmdpbi1sZWZ0Oi0uNXJlbSFpbXBvcnRhbnR9Lm0tbjN7bWFyZ2luOi0xcmVtIWltcG9ydGFudH0ubXQtbjMsLm15LW4ze21hcmdpbi10b3A6LTFyZW0haW1wb3J0YW50fS5tci1uMywubXgtbjN7bWFyZ2luLXJpZ2h0Oi0xcmVtIWltcG9ydGFudH0ubWItbjMsLm15LW4ze21hcmdpbi1ib3R0b206LTFyZW0haW1wb3J0YW50fS5tbC1uMywubXgtbjN7bWFyZ2luLWxlZnQ6LTFyZW0haW1wb3J0YW50fS5tLW40e21hcmdpbjotMS41cmVtIWltcG9ydGFudH0ubXQtbjQsLm15LW40e21hcmdpbi10b3A6LTEuNXJlbSFpbXBvcnRhbnR9Lm1yLW40LC5teC1uNHttYXJnaW4tcmlnaHQ6LTEuNXJlbSFpbXBvcnRhbnR9Lm1iLW40LC5teS1uNHttYXJnaW4tYm90dG9tOi0xLjVyZW0haW1wb3J0YW50fS5tbC1uNCwubXgtbjR7bWFyZ2luLWxlZnQ6LTEuNXJlbSFpbXBvcnRhbnR9Lm0tbjV7bWFyZ2luOi0zcmVtIWltcG9ydGFudH0ubXQtbjUsLm15LW41e21hcmdpbi10b3A6LTNyZW0haW1wb3J0YW50fS5tci1uNSwubXgtbjV7bWFyZ2luLXJpZ2h0Oi0zcmVtIWltcG9ydGFudH0ubWItbjUsLm15LW41e21hcmdpbi1ib3R0b206LTNyZW0haW1wb3J0YW50fS5tbC1uNSwubXgtbjV7bWFyZ2luLWxlZnQ6LTNyZW0haW1wb3J0YW50fS5tLWF1dG97bWFyZ2luOmF1dG8haW1wb3J0YW50fS5tdC1hdXRvLC5teS1hdXRve21hcmdpbi10b3A6YXV0byFpbXBvcnRhbnR9Lm1yLWF1dG8sLm14LWF1dG97bWFyZ2luLXJpZ2h0OmF1dG8haW1wb3J0YW50fS5tYi1hdXRvLC5teS1hdXRve21hcmdpbi1ib3R0b206YXV0byFpbXBvcnRhbnR9Lm1sLWF1dG8sLm14LWF1dG97bWFyZ2luLWxlZnQ6YXV0byFpbXBvcnRhbnR9QG1lZGlhIChtaW4td2lkdGg6NTc2cHgpey5tLXNtLTB7bWFyZ2luOjAhaW1wb3J0YW50fS5tdC1zbS0wLC5teS1zbS0we21hcmdpbi10b3A6MCFpbXBvcnRhbnR9Lm1yLXNtLTAsLm14LXNtLTB7bWFyZ2luLXJpZ2h0OjAhaW1wb3J0YW50fS5tYi1zbS0wLC5teS1zbS0we21hcmdpbi1ib3R0b206MCFpbXBvcnRhbnR9Lm1sLXNtLTAsLm14LXNtLTB7bWFyZ2luLWxlZnQ6MCFpbXBvcnRhbnR9Lm0tc20tMXttYXJnaW46LjI1cmVtIWltcG9ydGFudH0ubXQtc20tMSwubXktc20tMXttYXJnaW4tdG9wOi4yNXJlbSFpbXBvcnRhbnR9Lm1yLXNtLTEsLm14LXNtLTF7bWFyZ2luLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9Lm1iLXNtLTEsLm15LXNtLTF7bWFyZ2luLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5tbC1zbS0xLC5teC1zbS0xe21hcmdpbi1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9Lm0tc20tMnttYXJnaW46LjVyZW0haW1wb3J0YW50fS5tdC1zbS0yLC5teS1zbS0ye21hcmdpbi10b3A6LjVyZW0haW1wb3J0YW50fS5tci1zbS0yLC5teC1zbS0ye21hcmdpbi1yaWdodDouNXJlbSFpbXBvcnRhbnR9Lm1iLXNtLTIsLm15LXNtLTJ7bWFyZ2luLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9Lm1sLXNtLTIsLm14LXNtLTJ7bWFyZ2luLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5tLXNtLTN7bWFyZ2luOjFyZW0haW1wb3J0YW50fS5tdC1zbS0zLC5teS1zbS0ze21hcmdpbi10b3A6MXJlbSFpbXBvcnRhbnR9Lm1yLXNtLTMsLm14LXNtLTN7bWFyZ2luLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5tYi1zbS0zLC5teS1zbS0ze21hcmdpbi1ib3R0b206MXJlbSFpbXBvcnRhbnR9Lm1sLXNtLTMsLm14LXNtLTN7bWFyZ2luLWxlZnQ6MXJlbSFpbXBvcnRhbnR9Lm0tc20tNHttYXJnaW46MS41cmVtIWltcG9ydGFudH0ubXQtc20tNCwubXktc20tNHttYXJnaW4tdG9wOjEuNXJlbSFpbXBvcnRhbnR9Lm1yLXNtLTQsLm14LXNtLTR7bWFyZ2luLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9Lm1iLXNtLTQsLm15LXNtLTR7bWFyZ2luLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5tbC1zbS00LC5teC1zbS00e21hcmdpbi1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9Lm0tc20tNXttYXJnaW46M3JlbSFpbXBvcnRhbnR9Lm10LXNtLTUsLm15LXNtLTV7bWFyZ2luLXRvcDozcmVtIWltcG9ydGFudH0ubXItc20tNSwubXgtc20tNXttYXJnaW4tcmlnaHQ6M3JlbSFpbXBvcnRhbnR9Lm1iLXNtLTUsLm15LXNtLTV7bWFyZ2luLWJvdHRvbTozcmVtIWltcG9ydGFudH0ubWwtc20tNSwubXgtc20tNXttYXJnaW4tbGVmdDozcmVtIWltcG9ydGFudH0ucC1zbS0we3BhZGRpbmc6MCFpbXBvcnRhbnR9LnB0LXNtLTAsLnB5LXNtLTB7cGFkZGluZy10b3A6MCFpbXBvcnRhbnR9LnByLXNtLTAsLnB4LXNtLTB7cGFkZGluZy1yaWdodDowIWltcG9ydGFudH0ucGItc20tMCwucHktc20tMHtwYWRkaW5nLWJvdHRvbTowIWltcG9ydGFudH0ucGwtc20tMCwucHgtc20tMHtwYWRkaW5nLWxlZnQ6MCFpbXBvcnRhbnR9LnAtc20tMXtwYWRkaW5nOi4yNXJlbSFpbXBvcnRhbnR9LnB0LXNtLTEsLnB5LXNtLTF7cGFkZGluZy10b3A6LjI1cmVtIWltcG9ydGFudH0ucHItc20tMSwucHgtc20tMXtwYWRkaW5nLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9LnBiLXNtLTEsLnB5LXNtLTF7cGFkZGluZy1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ucGwtc20tMSwucHgtc20tMXtwYWRkaW5nLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ucC1zbS0ye3BhZGRpbmc6LjVyZW0haW1wb3J0YW50fS5wdC1zbS0yLC5weS1zbS0ye3BhZGRpbmctdG9wOi41cmVtIWltcG9ydGFudH0ucHItc20tMiwucHgtc20tMntwYWRkaW5nLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ucGItc20tMiwucHktc20tMntwYWRkaW5nLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9LnBsLXNtLTIsLnB4LXNtLTJ7cGFkZGluZy1sZWZ0Oi41cmVtIWltcG9ydGFudH0ucC1zbS0ze3BhZGRpbmc6MXJlbSFpbXBvcnRhbnR9LnB0LXNtLTMsLnB5LXNtLTN7cGFkZGluZy10b3A6MXJlbSFpbXBvcnRhbnR9LnByLXNtLTMsLnB4LXNtLTN7cGFkZGluZy1yaWdodDoxcmVtIWltcG9ydGFudH0ucGItc20tMywucHktc20tM3twYWRkaW5nLWJvdHRvbToxcmVtIWltcG9ydGFudH0ucGwtc20tMywucHgtc20tM3twYWRkaW5nLWxlZnQ6MXJlbSFpbXBvcnRhbnR9LnAtc20tNHtwYWRkaW5nOjEuNXJlbSFpbXBvcnRhbnR9LnB0LXNtLTQsLnB5LXNtLTR7cGFkZGluZy10b3A6MS41cmVtIWltcG9ydGFudH0ucHItc20tNCwucHgtc20tNHtwYWRkaW5nLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9LnBiLXNtLTQsLnB5LXNtLTR7cGFkZGluZy1ib3R0b206MS41cmVtIWltcG9ydGFudH0ucGwtc20tNCwucHgtc20tNHtwYWRkaW5nLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ucC1zbS01e3BhZGRpbmc6M3JlbSFpbXBvcnRhbnR9LnB0LXNtLTUsLnB5LXNtLTV7cGFkZGluZy10b3A6M3JlbSFpbXBvcnRhbnR9LnByLXNtLTUsLnB4LXNtLTV7cGFkZGluZy1yaWdodDozcmVtIWltcG9ydGFudH0ucGItc20tNSwucHktc20tNXtwYWRkaW5nLWJvdHRvbTozcmVtIWltcG9ydGFudH0ucGwtc20tNSwucHgtc20tNXtwYWRkaW5nLWxlZnQ6M3JlbSFpbXBvcnRhbnR9Lm0tc20tbjF7bWFyZ2luOi0uMjVyZW0haW1wb3J0YW50fS5tdC1zbS1uMSwubXktc20tbjF7bWFyZ2luLXRvcDotLjI1cmVtIWltcG9ydGFudH0ubXItc20tbjEsLm14LXNtLW4xe21hcmdpbi1yaWdodDotLjI1cmVtIWltcG9ydGFudH0ubWItc20tbjEsLm15LXNtLW4xe21hcmdpbi1ib3R0b206LS4yNXJlbSFpbXBvcnRhbnR9Lm1sLXNtLW4xLC5teC1zbS1uMXttYXJnaW4tbGVmdDotLjI1cmVtIWltcG9ydGFudH0ubS1zbS1uMnttYXJnaW46LS41cmVtIWltcG9ydGFudH0ubXQtc20tbjIsLm15LXNtLW4ye21hcmdpbi10b3A6LS41cmVtIWltcG9ydGFudH0ubXItc20tbjIsLm14LXNtLW4ye21hcmdpbi1yaWdodDotLjVyZW0haW1wb3J0YW50fS5tYi1zbS1uMiwubXktc20tbjJ7bWFyZ2luLWJvdHRvbTotLjVyZW0haW1wb3J0YW50fS5tbC1zbS1uMiwubXgtc20tbjJ7bWFyZ2luLWxlZnQ6LS41cmVtIWltcG9ydGFudH0ubS1zbS1uM3ttYXJnaW46LTFyZW0haW1wb3J0YW50fS5tdC1zbS1uMywubXktc20tbjN7bWFyZ2luLXRvcDotMXJlbSFpbXBvcnRhbnR9Lm1yLXNtLW4zLC5teC1zbS1uM3ttYXJnaW4tcmlnaHQ6LTFyZW0haW1wb3J0YW50fS5tYi1zbS1uMywubXktc20tbjN7bWFyZ2luLWJvdHRvbTotMXJlbSFpbXBvcnRhbnR9Lm1sLXNtLW4zLC5teC1zbS1uM3ttYXJnaW4tbGVmdDotMXJlbSFpbXBvcnRhbnR9Lm0tc20tbjR7bWFyZ2luOi0xLjVyZW0haW1wb3J0YW50fS5tdC1zbS1uNCwubXktc20tbjR7bWFyZ2luLXRvcDotMS41cmVtIWltcG9ydGFudH0ubXItc20tbjQsLm14LXNtLW40e21hcmdpbi1yaWdodDotMS41cmVtIWltcG9ydGFudH0ubWItc20tbjQsLm15LXNtLW40e21hcmdpbi1ib3R0b206LTEuNXJlbSFpbXBvcnRhbnR9Lm1sLXNtLW40LC5teC1zbS1uNHttYXJnaW4tbGVmdDotMS41cmVtIWltcG9ydGFudH0ubS1zbS1uNXttYXJnaW46LTNyZW0haW1wb3J0YW50fS5tdC1zbS1uNSwubXktc20tbjV7bWFyZ2luLXRvcDotM3JlbSFpbXBvcnRhbnR9Lm1yLXNtLW41LC5teC1zbS1uNXttYXJnaW4tcmlnaHQ6LTNyZW0haW1wb3J0YW50fS5tYi1zbS1uNSwubXktc20tbjV7bWFyZ2luLWJvdHRvbTotM3JlbSFpbXBvcnRhbnR9Lm1sLXNtLW41LC5teC1zbS1uNXttYXJnaW4tbGVmdDotM3JlbSFpbXBvcnRhbnR9Lm0tc20tYXV0b3ttYXJnaW46YXV0byFpbXBvcnRhbnR9Lm10LXNtLWF1dG8sLm15LXNtLWF1dG97bWFyZ2luLXRvcDphdXRvIWltcG9ydGFudH0ubXItc20tYXV0bywubXgtc20tYXV0b3ttYXJnaW4tcmlnaHQ6YXV0byFpbXBvcnRhbnR9Lm1iLXNtLWF1dG8sLm15LXNtLWF1dG97bWFyZ2luLWJvdHRvbTphdXRvIWltcG9ydGFudH0ubWwtc20tYXV0bywubXgtc20tYXV0b3ttYXJnaW4tbGVmdDphdXRvIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6NzY4cHgpey5tLW1kLTB7bWFyZ2luOjAhaW1wb3J0YW50fS5tdC1tZC0wLC5teS1tZC0we21hcmdpbi10b3A6MCFpbXBvcnRhbnR9Lm1yLW1kLTAsLm14LW1kLTB7bWFyZ2luLXJpZ2h0OjAhaW1wb3J0YW50fS5tYi1tZC0wLC5teS1tZC0we21hcmdpbi1ib3R0b206MCFpbXBvcnRhbnR9Lm1sLW1kLTAsLm14LW1kLTB7bWFyZ2luLWxlZnQ6MCFpbXBvcnRhbnR9Lm0tbWQtMXttYXJnaW46LjI1cmVtIWltcG9ydGFudH0ubXQtbWQtMSwubXktbWQtMXttYXJnaW4tdG9wOi4yNXJlbSFpbXBvcnRhbnR9Lm1yLW1kLTEsLm14LW1kLTF7bWFyZ2luLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9Lm1iLW1kLTEsLm15LW1kLTF7bWFyZ2luLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5tbC1tZC0xLC5teC1tZC0xe21hcmdpbi1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9Lm0tbWQtMnttYXJnaW46LjVyZW0haW1wb3J0YW50fS5tdC1tZC0yLC5teS1tZC0ye21hcmdpbi10b3A6LjVyZW0haW1wb3J0YW50fS5tci1tZC0yLC5teC1tZC0ye21hcmdpbi1yaWdodDouNXJlbSFpbXBvcnRhbnR9Lm1iLW1kLTIsLm15LW1kLTJ7bWFyZ2luLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9Lm1sLW1kLTIsLm14LW1kLTJ7bWFyZ2luLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5tLW1kLTN7bWFyZ2luOjFyZW0haW1wb3J0YW50fS5tdC1tZC0zLC5teS1tZC0ze21hcmdpbi10b3A6MXJlbSFpbXBvcnRhbnR9Lm1yLW1kLTMsLm14LW1kLTN7bWFyZ2luLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5tYi1tZC0zLC5teS1tZC0ze21hcmdpbi1ib3R0b206MXJlbSFpbXBvcnRhbnR9Lm1sLW1kLTMsLm14LW1kLTN7bWFyZ2luLWxlZnQ6MXJlbSFpbXBvcnRhbnR9Lm0tbWQtNHttYXJnaW46MS41cmVtIWltcG9ydGFudH0ubXQtbWQtNCwubXktbWQtNHttYXJnaW4tdG9wOjEuNXJlbSFpbXBvcnRhbnR9Lm1yLW1kLTQsLm14LW1kLTR7bWFyZ2luLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9Lm1iLW1kLTQsLm15LW1kLTR7bWFyZ2luLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5tbC1tZC00LC5teC1tZC00e21hcmdpbi1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9Lm0tbWQtNXttYXJnaW46M3JlbSFpbXBvcnRhbnR9Lm10LW1kLTUsLm15LW1kLTV7bWFyZ2luLXRvcDozcmVtIWltcG9ydGFudH0ubXItbWQtNSwubXgtbWQtNXttYXJnaW4tcmlnaHQ6M3JlbSFpbXBvcnRhbnR9Lm1iLW1kLTUsLm15LW1kLTV7bWFyZ2luLWJvdHRvbTozcmVtIWltcG9ydGFudH0ubWwtbWQtNSwubXgtbWQtNXttYXJnaW4tbGVmdDozcmVtIWltcG9ydGFudH0ucC1tZC0we3BhZGRpbmc6MCFpbXBvcnRhbnR9LnB0LW1kLTAsLnB5LW1kLTB7cGFkZGluZy10b3A6MCFpbXBvcnRhbnR9LnByLW1kLTAsLnB4LW1kLTB7cGFkZGluZy1yaWdodDowIWltcG9ydGFudH0ucGItbWQtMCwucHktbWQtMHtwYWRkaW5nLWJvdHRvbTowIWltcG9ydGFudH0ucGwtbWQtMCwucHgtbWQtMHtwYWRkaW5nLWxlZnQ6MCFpbXBvcnRhbnR9LnAtbWQtMXtwYWRkaW5nOi4yNXJlbSFpbXBvcnRhbnR9LnB0LW1kLTEsLnB5LW1kLTF7cGFkZGluZy10b3A6LjI1cmVtIWltcG9ydGFudH0ucHItbWQtMSwucHgtbWQtMXtwYWRkaW5nLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9LnBiLW1kLTEsLnB5LW1kLTF7cGFkZGluZy1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ucGwtbWQtMSwucHgtbWQtMXtwYWRkaW5nLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ucC1tZC0ye3BhZGRpbmc6LjVyZW0haW1wb3J0YW50fS5wdC1tZC0yLC5weS1tZC0ye3BhZGRpbmctdG9wOi41cmVtIWltcG9ydGFudH0ucHItbWQtMiwucHgtbWQtMntwYWRkaW5nLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ucGItbWQtMiwucHktbWQtMntwYWRkaW5nLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9LnBsLW1kLTIsLnB4LW1kLTJ7cGFkZGluZy1sZWZ0Oi41cmVtIWltcG9ydGFudH0ucC1tZC0ze3BhZGRpbmc6MXJlbSFpbXBvcnRhbnR9LnB0LW1kLTMsLnB5LW1kLTN7cGFkZGluZy10b3A6MXJlbSFpbXBvcnRhbnR9LnByLW1kLTMsLnB4LW1kLTN7cGFkZGluZy1yaWdodDoxcmVtIWltcG9ydGFudH0ucGItbWQtMywucHktbWQtM3twYWRkaW5nLWJvdHRvbToxcmVtIWltcG9ydGFudH0ucGwtbWQtMywucHgtbWQtM3twYWRkaW5nLWxlZnQ6MXJlbSFpbXBvcnRhbnR9LnAtbWQtNHtwYWRkaW5nOjEuNXJlbSFpbXBvcnRhbnR9LnB0LW1kLTQsLnB5LW1kLTR7cGFkZGluZy10b3A6MS41cmVtIWltcG9ydGFudH0ucHItbWQtNCwucHgtbWQtNHtwYWRkaW5nLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9LnBiLW1kLTQsLnB5LW1kLTR7cGFkZGluZy1ib3R0b206MS41cmVtIWltcG9ydGFudH0ucGwtbWQtNCwucHgtbWQtNHtwYWRkaW5nLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ucC1tZC01e3BhZGRpbmc6M3JlbSFpbXBvcnRhbnR9LnB0LW1kLTUsLnB5LW1kLTV7cGFkZGluZy10b3A6M3JlbSFpbXBvcnRhbnR9LnByLW1kLTUsLnB4LW1kLTV7cGFkZGluZy1yaWdodDozcmVtIWltcG9ydGFudH0ucGItbWQtNSwucHktbWQtNXtwYWRkaW5nLWJvdHRvbTozcmVtIWltcG9ydGFudH0ucGwtbWQtNSwucHgtbWQtNXtwYWRkaW5nLWxlZnQ6M3JlbSFpbXBvcnRhbnR9Lm0tbWQtbjF7bWFyZ2luOi0uMjVyZW0haW1wb3J0YW50fS5tdC1tZC1uMSwubXktbWQtbjF7bWFyZ2luLXRvcDotLjI1cmVtIWltcG9ydGFudH0ubXItbWQtbjEsLm14LW1kLW4xe21hcmdpbi1yaWdodDotLjI1cmVtIWltcG9ydGFudH0ubWItbWQtbjEsLm15LW1kLW4xe21hcmdpbi1ib3R0b206LS4yNXJlbSFpbXBvcnRhbnR9Lm1sLW1kLW4xLC5teC1tZC1uMXttYXJnaW4tbGVmdDotLjI1cmVtIWltcG9ydGFudH0ubS1tZC1uMnttYXJnaW46LS41cmVtIWltcG9ydGFudH0ubXQtbWQtbjIsLm15LW1kLW4ye21hcmdpbi10b3A6LS41cmVtIWltcG9ydGFudH0ubXItbWQtbjIsLm14LW1kLW4ye21hcmdpbi1yaWdodDotLjVyZW0haW1wb3J0YW50fS5tYi1tZC1uMiwubXktbWQtbjJ7bWFyZ2luLWJvdHRvbTotLjVyZW0haW1wb3J0YW50fS5tbC1tZC1uMiwubXgtbWQtbjJ7bWFyZ2luLWxlZnQ6LS41cmVtIWltcG9ydGFudH0ubS1tZC1uM3ttYXJnaW46LTFyZW0haW1wb3J0YW50fS5tdC1tZC1uMywubXktbWQtbjN7bWFyZ2luLXRvcDotMXJlbSFpbXBvcnRhbnR9Lm1yLW1kLW4zLC5teC1tZC1uM3ttYXJnaW4tcmlnaHQ6LTFyZW0haW1wb3J0YW50fS5tYi1tZC1uMywubXktbWQtbjN7bWFyZ2luLWJvdHRvbTotMXJlbSFpbXBvcnRhbnR9Lm1sLW1kLW4zLC5teC1tZC1uM3ttYXJnaW4tbGVmdDotMXJlbSFpbXBvcnRhbnR9Lm0tbWQtbjR7bWFyZ2luOi0xLjVyZW0haW1wb3J0YW50fS5tdC1tZC1uNCwubXktbWQtbjR7bWFyZ2luLXRvcDotMS41cmVtIWltcG9ydGFudH0ubXItbWQtbjQsLm14LW1kLW40e21hcmdpbi1yaWdodDotMS41cmVtIWltcG9ydGFudH0ubWItbWQtbjQsLm15LW1kLW40e21hcmdpbi1ib3R0b206LTEuNXJlbSFpbXBvcnRhbnR9Lm1sLW1kLW40LC5teC1tZC1uNHttYXJnaW4tbGVmdDotMS41cmVtIWltcG9ydGFudH0ubS1tZC1uNXttYXJnaW46LTNyZW0haW1wb3J0YW50fS5tdC1tZC1uNSwubXktbWQtbjV7bWFyZ2luLXRvcDotM3JlbSFpbXBvcnRhbnR9Lm1yLW1kLW41LC5teC1tZC1uNXttYXJnaW4tcmlnaHQ6LTNyZW0haW1wb3J0YW50fS5tYi1tZC1uNSwubXktbWQtbjV7bWFyZ2luLWJvdHRvbTotM3JlbSFpbXBvcnRhbnR9Lm1sLW1kLW41LC5teC1tZC1uNXttYXJnaW4tbGVmdDotM3JlbSFpbXBvcnRhbnR9Lm0tbWQtYXV0b3ttYXJnaW46YXV0byFpbXBvcnRhbnR9Lm10LW1kLWF1dG8sLm15LW1kLWF1dG97bWFyZ2luLXRvcDphdXRvIWltcG9ydGFudH0ubXItbWQtYXV0bywubXgtbWQtYXV0b3ttYXJnaW4tcmlnaHQ6YXV0byFpbXBvcnRhbnR9Lm1iLW1kLWF1dG8sLm15LW1kLWF1dG97bWFyZ2luLWJvdHRvbTphdXRvIWltcG9ydGFudH0ubWwtbWQtYXV0bywubXgtbWQtYXV0b3ttYXJnaW4tbGVmdDphdXRvIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6OTkycHgpey5tLWxnLTB7bWFyZ2luOjAhaW1wb3J0YW50fS5tdC1sZy0wLC5teS1sZy0we21hcmdpbi10b3A6MCFpbXBvcnRhbnR9Lm1yLWxnLTAsLm14LWxnLTB7bWFyZ2luLXJpZ2h0OjAhaW1wb3J0YW50fS5tYi1sZy0wLC5teS1sZy0we21hcmdpbi1ib3R0b206MCFpbXBvcnRhbnR9Lm1sLWxnLTAsLm14LWxnLTB7bWFyZ2luLWxlZnQ6MCFpbXBvcnRhbnR9Lm0tbGctMXttYXJnaW46LjI1cmVtIWltcG9ydGFudH0ubXQtbGctMSwubXktbGctMXttYXJnaW4tdG9wOi4yNXJlbSFpbXBvcnRhbnR9Lm1yLWxnLTEsLm14LWxnLTF7bWFyZ2luLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9Lm1iLWxnLTEsLm15LWxnLTF7bWFyZ2luLWJvdHRvbTouMjVyZW0haW1wb3J0YW50fS5tbC1sZy0xLC5teC1sZy0xe21hcmdpbi1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9Lm0tbGctMnttYXJnaW46LjVyZW0haW1wb3J0YW50fS5tdC1sZy0yLC5teS1sZy0ye21hcmdpbi10b3A6LjVyZW0haW1wb3J0YW50fS5tci1sZy0yLC5teC1sZy0ye21hcmdpbi1yaWdodDouNXJlbSFpbXBvcnRhbnR9Lm1iLWxnLTIsLm15LWxnLTJ7bWFyZ2luLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9Lm1sLWxnLTIsLm14LWxnLTJ7bWFyZ2luLWxlZnQ6LjVyZW0haW1wb3J0YW50fS5tLWxnLTN7bWFyZ2luOjFyZW0haW1wb3J0YW50fS5tdC1sZy0zLC5teS1sZy0ze21hcmdpbi10b3A6MXJlbSFpbXBvcnRhbnR9Lm1yLWxnLTMsLm14LWxnLTN7bWFyZ2luLXJpZ2h0OjFyZW0haW1wb3J0YW50fS5tYi1sZy0zLC5teS1sZy0ze21hcmdpbi1ib3R0b206MXJlbSFpbXBvcnRhbnR9Lm1sLWxnLTMsLm14LWxnLTN7bWFyZ2luLWxlZnQ6MXJlbSFpbXBvcnRhbnR9Lm0tbGctNHttYXJnaW46MS41cmVtIWltcG9ydGFudH0ubXQtbGctNCwubXktbGctNHttYXJnaW4tdG9wOjEuNXJlbSFpbXBvcnRhbnR9Lm1yLWxnLTQsLm14LWxnLTR7bWFyZ2luLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9Lm1iLWxnLTQsLm15LWxnLTR7bWFyZ2luLWJvdHRvbToxLjVyZW0haW1wb3J0YW50fS5tbC1sZy00LC5teC1sZy00e21hcmdpbi1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9Lm0tbGctNXttYXJnaW46M3JlbSFpbXBvcnRhbnR9Lm10LWxnLTUsLm15LWxnLTV7bWFyZ2luLXRvcDozcmVtIWltcG9ydGFudH0ubXItbGctNSwubXgtbGctNXttYXJnaW4tcmlnaHQ6M3JlbSFpbXBvcnRhbnR9Lm1iLWxnLTUsLm15LWxnLTV7bWFyZ2luLWJvdHRvbTozcmVtIWltcG9ydGFudH0ubWwtbGctNSwubXgtbGctNXttYXJnaW4tbGVmdDozcmVtIWltcG9ydGFudH0ucC1sZy0we3BhZGRpbmc6MCFpbXBvcnRhbnR9LnB0LWxnLTAsLnB5LWxnLTB7cGFkZGluZy10b3A6MCFpbXBvcnRhbnR9LnByLWxnLTAsLnB4LWxnLTB7cGFkZGluZy1yaWdodDowIWltcG9ydGFudH0ucGItbGctMCwucHktbGctMHtwYWRkaW5nLWJvdHRvbTowIWltcG9ydGFudH0ucGwtbGctMCwucHgtbGctMHtwYWRkaW5nLWxlZnQ6MCFpbXBvcnRhbnR9LnAtbGctMXtwYWRkaW5nOi4yNXJlbSFpbXBvcnRhbnR9LnB0LWxnLTEsLnB5LWxnLTF7cGFkZGluZy10b3A6LjI1cmVtIWltcG9ydGFudH0ucHItbGctMSwucHgtbGctMXtwYWRkaW5nLXJpZ2h0Oi4yNXJlbSFpbXBvcnRhbnR9LnBiLWxnLTEsLnB5LWxnLTF7cGFkZGluZy1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ucGwtbGctMSwucHgtbGctMXtwYWRkaW5nLWxlZnQ6LjI1cmVtIWltcG9ydGFudH0ucC1sZy0ye3BhZGRpbmc6LjVyZW0haW1wb3J0YW50fS5wdC1sZy0yLC5weS1sZy0ye3BhZGRpbmctdG9wOi41cmVtIWltcG9ydGFudH0ucHItbGctMiwucHgtbGctMntwYWRkaW5nLXJpZ2h0Oi41cmVtIWltcG9ydGFudH0ucGItbGctMiwucHktbGctMntwYWRkaW5nLWJvdHRvbTouNXJlbSFpbXBvcnRhbnR9LnBsLWxnLTIsLnB4LWxnLTJ7cGFkZGluZy1sZWZ0Oi41cmVtIWltcG9ydGFudH0ucC1sZy0ze3BhZGRpbmc6MXJlbSFpbXBvcnRhbnR9LnB0LWxnLTMsLnB5LWxnLTN7cGFkZGluZy10b3A6MXJlbSFpbXBvcnRhbnR9LnByLWxnLTMsLnB4LWxnLTN7cGFkZGluZy1yaWdodDoxcmVtIWltcG9ydGFudH0ucGItbGctMywucHktbGctM3twYWRkaW5nLWJvdHRvbToxcmVtIWltcG9ydGFudH0ucGwtbGctMywucHgtbGctM3twYWRkaW5nLWxlZnQ6MXJlbSFpbXBvcnRhbnR9LnAtbGctNHtwYWRkaW5nOjEuNXJlbSFpbXBvcnRhbnR9LnB0LWxnLTQsLnB5LWxnLTR7cGFkZGluZy10b3A6MS41cmVtIWltcG9ydGFudH0ucHItbGctNCwucHgtbGctNHtwYWRkaW5nLXJpZ2h0OjEuNXJlbSFpbXBvcnRhbnR9LnBiLWxnLTQsLnB5LWxnLTR7cGFkZGluZy1ib3R0b206MS41cmVtIWltcG9ydGFudH0ucGwtbGctNCwucHgtbGctNHtwYWRkaW5nLWxlZnQ6MS41cmVtIWltcG9ydGFudH0ucC1sZy01e3BhZGRpbmc6M3JlbSFpbXBvcnRhbnR9LnB0LWxnLTUsLnB5LWxnLTV7cGFkZGluZy10b3A6M3JlbSFpbXBvcnRhbnR9LnByLWxnLTUsLnB4LWxnLTV7cGFkZGluZy1yaWdodDozcmVtIWltcG9ydGFudH0ucGItbGctNSwucHktbGctNXtwYWRkaW5nLWJvdHRvbTozcmVtIWltcG9ydGFudH0ucGwtbGctNSwucHgtbGctNXtwYWRkaW5nLWxlZnQ6M3JlbSFpbXBvcnRhbnR9Lm0tbGctbjF7bWFyZ2luOi0uMjVyZW0haW1wb3J0YW50fS5tdC1sZy1uMSwubXktbGctbjF7bWFyZ2luLXRvcDotLjI1cmVtIWltcG9ydGFudH0ubXItbGctbjEsLm14LWxnLW4xe21hcmdpbi1yaWdodDotLjI1cmVtIWltcG9ydGFudH0ubWItbGctbjEsLm15LWxnLW4xe21hcmdpbi1ib3R0b206LS4yNXJlbSFpbXBvcnRhbnR9Lm1sLWxnLW4xLC5teC1sZy1uMXttYXJnaW4tbGVmdDotLjI1cmVtIWltcG9ydGFudH0ubS1sZy1uMnttYXJnaW46LS41cmVtIWltcG9ydGFudH0ubXQtbGctbjIsLm15LWxnLW4ye21hcmdpbi10b3A6LS41cmVtIWltcG9ydGFudH0ubXItbGctbjIsLm14LWxnLW4ye21hcmdpbi1yaWdodDotLjVyZW0haW1wb3J0YW50fS5tYi1sZy1uMiwubXktbGctbjJ7bWFyZ2luLWJvdHRvbTotLjVyZW0haW1wb3J0YW50fS5tbC1sZy1uMiwubXgtbGctbjJ7bWFyZ2luLWxlZnQ6LS41cmVtIWltcG9ydGFudH0ubS1sZy1uM3ttYXJnaW46LTFyZW0haW1wb3J0YW50fS5tdC1sZy1uMywubXktbGctbjN7bWFyZ2luLXRvcDotMXJlbSFpbXBvcnRhbnR9Lm1yLWxnLW4zLC5teC1sZy1uM3ttYXJnaW4tcmlnaHQ6LTFyZW0haW1wb3J0YW50fS5tYi1sZy1uMywubXktbGctbjN7bWFyZ2luLWJvdHRvbTotMXJlbSFpbXBvcnRhbnR9Lm1sLWxnLW4zLC5teC1sZy1uM3ttYXJnaW4tbGVmdDotMXJlbSFpbXBvcnRhbnR9Lm0tbGctbjR7bWFyZ2luOi0xLjVyZW0haW1wb3J0YW50fS5tdC1sZy1uNCwubXktbGctbjR7bWFyZ2luLXRvcDotMS41cmVtIWltcG9ydGFudH0ubXItbGctbjQsLm14LWxnLW40e21hcmdpbi1yaWdodDotMS41cmVtIWltcG9ydGFudH0ubWItbGctbjQsLm15LWxnLW40e21hcmdpbi1ib3R0b206LTEuNXJlbSFpbXBvcnRhbnR9Lm1sLWxnLW40LC5teC1sZy1uNHttYXJnaW4tbGVmdDotMS41cmVtIWltcG9ydGFudH0ubS1sZy1uNXttYXJnaW46LTNyZW0haW1wb3J0YW50fS5tdC1sZy1uNSwubXktbGctbjV7bWFyZ2luLXRvcDotM3JlbSFpbXBvcnRhbnR9Lm1yLWxnLW41LC5teC1sZy1uNXttYXJnaW4tcmlnaHQ6LTNyZW0haW1wb3J0YW50fS5tYi1sZy1uNSwubXktbGctbjV7bWFyZ2luLWJvdHRvbTotM3JlbSFpbXBvcnRhbnR9Lm1sLWxnLW41LC5teC1sZy1uNXttYXJnaW4tbGVmdDotM3JlbSFpbXBvcnRhbnR9Lm0tbGctYXV0b3ttYXJnaW46YXV0byFpbXBvcnRhbnR9Lm10LWxnLWF1dG8sLm15LWxnLWF1dG97bWFyZ2luLXRvcDphdXRvIWltcG9ydGFudH0ubXItbGctYXV0bywubXgtbGctYXV0b3ttYXJnaW4tcmlnaHQ6YXV0byFpbXBvcnRhbnR9Lm1iLWxnLWF1dG8sLm15LWxnLWF1dG97bWFyZ2luLWJvdHRvbTphdXRvIWltcG9ydGFudH0ubWwtbGctYXV0bywubXgtbGctYXV0b3ttYXJnaW4tbGVmdDphdXRvIWltcG9ydGFudH19QG1lZGlhIChtaW4td2lkdGg6MTIwMHB4KXsubS14bC0we21hcmdpbjowIWltcG9ydGFudH0ubXQteGwtMCwubXkteGwtMHttYXJnaW4tdG9wOjAhaW1wb3J0YW50fS5tci14bC0wLC5teC14bC0we21hcmdpbi1yaWdodDowIWltcG9ydGFudH0ubWIteGwtMCwubXkteGwtMHttYXJnaW4tYm90dG9tOjAhaW1wb3J0YW50fS5tbC14bC0wLC5teC14bC0we21hcmdpbi1sZWZ0OjAhaW1wb3J0YW50fS5tLXhsLTF7bWFyZ2luOi4yNXJlbSFpbXBvcnRhbnR9Lm10LXhsLTEsLm15LXhsLTF7bWFyZ2luLXRvcDouMjVyZW0haW1wb3J0YW50fS5tci14bC0xLC5teC14bC0xe21hcmdpbi1yaWdodDouMjVyZW0haW1wb3J0YW50fS5tYi14bC0xLC5teS14bC0xe21hcmdpbi1ib3R0b206LjI1cmVtIWltcG9ydGFudH0ubWwteGwtMSwubXgteGwtMXttYXJnaW4tbGVmdDouMjVyZW0haW1wb3J0YW50fS5tLXhsLTJ7bWFyZ2luOi41cmVtIWltcG9ydGFudH0ubXQteGwtMiwubXkteGwtMnttYXJnaW4tdG9wOi41cmVtIWltcG9ydGFudH0ubXIteGwtMiwubXgteGwtMnttYXJnaW4tcmlnaHQ6LjVyZW0haW1wb3J0YW50fS5tYi14bC0yLC5teS14bC0ye21hcmdpbi1ib3R0b206LjVyZW0haW1wb3J0YW50fS5tbC14bC0yLC5teC14bC0ye21hcmdpbi1sZWZ0Oi41cmVtIWltcG9ydGFudH0ubS14bC0ze21hcmdpbjoxcmVtIWltcG9ydGFudH0ubXQteGwtMywubXkteGwtM3ttYXJnaW4tdG9wOjFyZW0haW1wb3J0YW50fS5tci14bC0zLC5teC14bC0ze21hcmdpbi1yaWdodDoxcmVtIWltcG9ydGFudH0ubWIteGwtMywubXkteGwtM3ttYXJnaW4tYm90dG9tOjFyZW0haW1wb3J0YW50fS5tbC14bC0zLC5teC14bC0ze21hcmdpbi1sZWZ0OjFyZW0haW1wb3J0YW50fS5tLXhsLTR7bWFyZ2luOjEuNXJlbSFpbXBvcnRhbnR9Lm10LXhsLTQsLm15LXhsLTR7bWFyZ2luLXRvcDoxLjVyZW0haW1wb3J0YW50fS5tci14bC00LC5teC14bC00e21hcmdpbi1yaWdodDoxLjVyZW0haW1wb3J0YW50fS5tYi14bC00LC5teS14bC00e21hcmdpbi1ib3R0b206MS41cmVtIWltcG9ydGFudH0ubWwteGwtNCwubXgteGwtNHttYXJnaW4tbGVmdDoxLjVyZW0haW1wb3J0YW50fS5tLXhsLTV7bWFyZ2luOjNyZW0haW1wb3J0YW50fS5tdC14bC01LC5teS14bC01e21hcmdpbi10b3A6M3JlbSFpbXBvcnRhbnR9Lm1yLXhsLTUsLm14LXhsLTV7bWFyZ2luLXJpZ2h0OjNyZW0haW1wb3J0YW50fS5tYi14bC01LC5teS14bC01e21hcmdpbi1ib3R0b206M3JlbSFpbXBvcnRhbnR9Lm1sLXhsLTUsLm14LXhsLTV7bWFyZ2luLWxlZnQ6M3JlbSFpbXBvcnRhbnR9LnAteGwtMHtwYWRkaW5nOjAhaW1wb3J0YW50fS5wdC14bC0wLC5weS14bC0we3BhZGRpbmctdG9wOjAhaW1wb3J0YW50fS5wci14bC0wLC5weC14bC0we3BhZGRpbmctcmlnaHQ6MCFpbXBvcnRhbnR9LnBiLXhsLTAsLnB5LXhsLTB7cGFkZGluZy1ib3R0b206MCFpbXBvcnRhbnR9LnBsLXhsLTAsLnB4LXhsLTB7cGFkZGluZy1sZWZ0OjAhaW1wb3J0YW50fS5wLXhsLTF7cGFkZGluZzouMjVyZW0haW1wb3J0YW50fS5wdC14bC0xLC5weS14bC0xe3BhZGRpbmctdG9wOi4yNXJlbSFpbXBvcnRhbnR9LnByLXhsLTEsLnB4LXhsLTF7cGFkZGluZy1yaWdodDouMjVyZW0haW1wb3J0YW50fS5wYi14bC0xLC5weS14bC0xe3BhZGRpbmctYm90dG9tOi4yNXJlbSFpbXBvcnRhbnR9LnBsLXhsLTEsLnB4LXhsLTF7cGFkZGluZy1sZWZ0Oi4yNXJlbSFpbXBvcnRhbnR9LnAteGwtMntwYWRkaW5nOi41cmVtIWltcG9ydGFudH0ucHQteGwtMiwucHkteGwtMntwYWRkaW5nLXRvcDouNXJlbSFpbXBvcnRhbnR9LnByLXhsLTIsLnB4LXhsLTJ7cGFkZGluZy1yaWdodDouNXJlbSFpbXBvcnRhbnR9LnBiLXhsLTIsLnB5LXhsLTJ7cGFkZGluZy1ib3R0b206LjVyZW0haW1wb3J0YW50fS5wbC14bC0yLC5weC14bC0ye3BhZGRpbmctbGVmdDouNXJlbSFpbXBvcnRhbnR9LnAteGwtM3twYWRkaW5nOjFyZW0haW1wb3J0YW50fS5wdC14bC0zLC5weS14bC0ze3BhZGRpbmctdG9wOjFyZW0haW1wb3J0YW50fS5wci14bC0zLC5weC14bC0ze3BhZGRpbmctcmlnaHQ6MXJlbSFpbXBvcnRhbnR9LnBiLXhsLTMsLnB5LXhsLTN7cGFkZGluZy1ib3R0b206MXJlbSFpbXBvcnRhbnR9LnBsLXhsLTMsLnB4LXhsLTN7cGFkZGluZy1sZWZ0OjFyZW0haW1wb3J0YW50fS5wLXhsLTR7cGFkZGluZzoxLjVyZW0haW1wb3J0YW50fS5wdC14bC00LC5weS14bC00e3BhZGRpbmctdG9wOjEuNXJlbSFpbXBvcnRhbnR9LnByLXhsLTQsLnB4LXhsLTR7cGFkZGluZy1yaWdodDoxLjVyZW0haW1wb3J0YW50fS5wYi14bC00LC5weS14bC00e3BhZGRpbmctYm90dG9tOjEuNXJlbSFpbXBvcnRhbnR9LnBsLXhsLTQsLnB4LXhsLTR7cGFkZGluZy1sZWZ0OjEuNXJlbSFpbXBvcnRhbnR9LnAteGwtNXtwYWRkaW5nOjNyZW0haW1wb3J0YW50fS5wdC14bC01LC5weS14bC01e3BhZGRpbmctdG9wOjNyZW0haW1wb3J0YW50fS5wci14bC01LC5weC14bC01e3BhZGRpbmctcmlnaHQ6M3JlbSFpbXBvcnRhbnR9LnBiLXhsLTUsLnB5LXhsLTV7cGFkZGluZy1ib3R0b206M3JlbSFpbXBvcnRhbnR9LnBsLXhsLTUsLnB4LXhsLTV7cGFkZGluZy1sZWZ0OjNyZW0haW1wb3J0YW50fS5tLXhsLW4xe21hcmdpbjotLjI1cmVtIWltcG9ydGFudH0ubXQteGwtbjEsLm15LXhsLW4xe21hcmdpbi10b3A6LS4yNXJlbSFpbXBvcnRhbnR9Lm1yLXhsLW4xLC5teC14bC1uMXttYXJnaW4tcmlnaHQ6LS4yNXJlbSFpbXBvcnRhbnR9Lm1iLXhsLW4xLC5teS14bC1uMXttYXJnaW4tYm90dG9tOi0uMjVyZW0haW1wb3J0YW50fS5tbC14bC1uMSwubXgteGwtbjF7bWFyZ2luLWxlZnQ6LS4yNXJlbSFpbXBvcnRhbnR9Lm0teGwtbjJ7bWFyZ2luOi0uNXJlbSFpbXBvcnRhbnR9Lm10LXhsLW4yLC5teS14bC1uMnttYXJnaW4tdG9wOi0uNXJlbSFpbXBvcnRhbnR9Lm1yLXhsLW4yLC5teC14bC1uMnttYXJnaW4tcmlnaHQ6LS41cmVtIWltcG9ydGFudH0ubWIteGwtbjIsLm15LXhsLW4ye21hcmdpbi1ib3R0b206LS41cmVtIWltcG9ydGFudH0ubWwteGwtbjIsLm14LXhsLW4ye21hcmdpbi1sZWZ0Oi0uNXJlbSFpbXBvcnRhbnR9Lm0teGwtbjN7bWFyZ2luOi0xcmVtIWltcG9ydGFudH0ubXQteGwtbjMsLm15LXhsLW4ze21hcmdpbi10b3A6LTFyZW0haW1wb3J0YW50fS5tci14bC1uMywubXgteGwtbjN7bWFyZ2luLXJpZ2h0Oi0xcmVtIWltcG9ydGFudH0ubWIteGwtbjMsLm15LXhsLW4ze21hcmdpbi1ib3R0b206LTFyZW0haW1wb3J0YW50fS5tbC14bC1uMywubXgteGwtbjN7bWFyZ2luLWxlZnQ6LTFyZW0haW1wb3J0YW50fS5tLXhsLW40e21hcmdpbjotMS41cmVtIWltcG9ydGFudH0ubXQteGwtbjQsLm15LXhsLW40e21hcmdpbi10b3A6LTEuNXJlbSFpbXBvcnRhbnR9Lm1yLXhsLW40LC5teC14bC1uNHttYXJnaW4tcmlnaHQ6LTEuNXJlbSFpbXBvcnRhbnR9Lm1iLXhsLW40LC5teS14bC1uNHttYXJnaW4tYm90dG9tOi0xLjVyZW0haW1wb3J0YW50fS5tbC14bC1uNCwubXgteGwtbjR7bWFyZ2luLWxlZnQ6LTEuNXJlbSFpbXBvcnRhbnR9Lm0teGwtbjV7bWFyZ2luOi0zcmVtIWltcG9ydGFudH0ubXQteGwtbjUsLm15LXhsLW41e21hcmdpbi10b3A6LTNyZW0haW1wb3J0YW50fS5tci14bC1uNSwubXgteGwtbjV7bWFyZ2luLXJpZ2h0Oi0zcmVtIWltcG9ydGFudH0ubWIteGwtbjUsLm15LXhsLW41e21hcmdpbi1ib3R0b206LTNyZW0haW1wb3J0YW50fS5tbC14bC1uNSwubXgteGwtbjV7bWFyZ2luLWxlZnQ6LTNyZW0haW1wb3J0YW50fS5tLXhsLWF1dG97bWFyZ2luOmF1dG8haW1wb3J0YW50fS5tdC14bC1hdXRvLC5teS14bC1hdXRve21hcmdpbi10b3A6YXV0byFpbXBvcnRhbnR9Lm1yLXhsLWF1dG8sLm14LXhsLWF1dG97bWFyZ2luLXJpZ2h0OmF1dG8haW1wb3J0YW50fS5tYi14bC1hdXRvLC5teS14bC1hdXRve21hcmdpbi1ib3R0b206YXV0byFpbXBvcnRhbnR9Lm1sLXhsLWF1dG8sLm14LXhsLWF1dG97bWFyZ2luLWxlZnQ6YXV0byFpbXBvcnRhbnR9fQovKiMgc291cmNlTWFwcGluZ1VSTD1ib290c3RyYXAtZ3JpZC5taW4uY3NzLm1hcCAqLw=='); }
if($path == 'bootstrap4/css/bootstrap-reboot.min.css'){ return base64_decode('LyohCiAqIEJvb3RzdHJhcCBSZWJvb3QgdjQuNC4xIChodHRwczovL2dldGJvb3RzdHJhcC5jb20vKQogKiBDb3B5cmlnaHQgMjAxMS0yMDE5IFRoZSBCb290c3RyYXAgQXV0aG9ycwogKiBDb3B5cmlnaHQgMjAxMS0yMDE5IFR3aXR0ZXIsIEluYy4KICogTGljZW5zZWQgdW5kZXIgTUlUIChodHRwczovL2dpdGh1Yi5jb20vdHdicy9ib290c3RyYXAvYmxvYi9tYXN0ZXIvTElDRU5TRSkKICogRm9ya2VkIGZyb20gTm9ybWFsaXplLmNzcywgbGljZW5zZWQgTUlUIChodHRwczovL2dpdGh1Yi5jb20vbmVjb2xhcy9ub3JtYWxpemUuY3NzL2Jsb2IvbWFzdGVyL0xJQ0VOU0UubWQpCiAqLyosOjphZnRlciw6OmJlZm9yZXtib3gtc2l6aW5nOmJvcmRlci1ib3h9aHRtbHtmb250LWZhbWlseTpzYW5zLXNlcmlmO2xpbmUtaGVpZ2h0OjEuMTU7LXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OjEwMCU7LXdlYmtpdC10YXAtaGlnaGxpZ2h0LWNvbG9yOnRyYW5zcGFyZW50fWFydGljbGUsYXNpZGUsZmlnY2FwdGlvbixmaWd1cmUsZm9vdGVyLGhlYWRlcixoZ3JvdXAsbWFpbixuYXYsc2VjdGlvbntkaXNwbGF5OmJsb2NrfWJvZHl7bWFyZ2luOjA7Zm9udC1mYW1pbHk6LWFwcGxlLXN5c3RlbSxCbGlua01hY1N5c3RlbUZvbnQsIlNlZ29lIFVJIixSb2JvdG8sIkhlbHZldGljYSBOZXVlIixBcmlhbCwiTm90byBTYW5zIixzYW5zLXNlcmlmLCJBcHBsZSBDb2xvciBFbW9qaSIsIlNlZ29lIFVJIEVtb2ppIiwiU2Vnb2UgVUkgU3ltYm9sIiwiTm90byBDb2xvciBFbW9qaSI7Zm9udC1zaXplOjFyZW07Zm9udC13ZWlnaHQ6NDAwO2xpbmUtaGVpZ2h0OjEuNTtjb2xvcjojMjEyNTI5O3RleHQtYWxpZ246bGVmdDtiYWNrZ3JvdW5kLWNvbG9yOiNmZmZ9W3RhYmluZGV4PSItMSJdOmZvY3VzOm5vdCg6Zm9jdXMtdmlzaWJsZSl7b3V0bGluZTowIWltcG9ydGFudH1ocntib3gtc2l6aW5nOmNvbnRlbnQtYm94O2hlaWdodDowO292ZXJmbG93OnZpc2libGV9aDEsaDIsaDMsaDQsaDUsaDZ7bWFyZ2luLXRvcDowO21hcmdpbi1ib3R0b206LjVyZW19cHttYXJnaW4tdG9wOjA7bWFyZ2luLWJvdHRvbToxcmVtfWFiYnJbZGF0YS1vcmlnaW5hbC10aXRsZV0sYWJiclt0aXRsZV17dGV4dC1kZWNvcmF0aW9uOnVuZGVybGluZTstd2Via2l0LXRleHQtZGVjb3JhdGlvbjp1bmRlcmxpbmUgZG90dGVkO3RleHQtZGVjb3JhdGlvbjp1bmRlcmxpbmUgZG90dGVkO2N1cnNvcjpoZWxwO2JvcmRlci1ib3R0b206MDstd2Via2l0LXRleHQtZGVjb3JhdGlvbi1za2lwLWluazpub25lO3RleHQtZGVjb3JhdGlvbi1za2lwLWluazpub25lfWFkZHJlc3N7bWFyZ2luLWJvdHRvbToxcmVtO2ZvbnQtc3R5bGU6bm9ybWFsO2xpbmUtaGVpZ2h0OmluaGVyaXR9ZGwsb2wsdWx7bWFyZ2luLXRvcDowO21hcmdpbi1ib3R0b206MXJlbX1vbCBvbCxvbCB1bCx1bCBvbCx1bCB1bHttYXJnaW4tYm90dG9tOjB9ZHR7Zm9udC13ZWlnaHQ6NzAwfWRke21hcmdpbi1ib3R0b206LjVyZW07bWFyZ2luLWxlZnQ6MH1ibG9ja3F1b3Rle21hcmdpbjowIDAgMXJlbX1iLHN0cm9uZ3tmb250LXdlaWdodDpib2xkZXJ9c21hbGx7Zm9udC1zaXplOjgwJX1zdWIsc3Vwe3Bvc2l0aW9uOnJlbGF0aXZlO2ZvbnQtc2l6ZTo3NSU7bGluZS1oZWlnaHQ6MDt2ZXJ0aWNhbC1hbGlnbjpiYXNlbGluZX1zdWJ7Ym90dG9tOi0uMjVlbX1zdXB7dG9wOi0uNWVtfWF7Y29sb3I6IzAwN2JmZjt0ZXh0LWRlY29yYXRpb246bm9uZTtiYWNrZ3JvdW5kLWNvbG9yOnRyYW5zcGFyZW50fWE6aG92ZXJ7Y29sb3I6IzAwNTZiMzt0ZXh0LWRlY29yYXRpb246dW5kZXJsaW5lfWE6bm90KFtocmVmXSl7Y29sb3I6aW5oZXJpdDt0ZXh0LWRlY29yYXRpb246bm9uZX1hOm5vdChbaHJlZl0pOmhvdmVye2NvbG9yOmluaGVyaXQ7dGV4dC1kZWNvcmF0aW9uOm5vbmV9Y29kZSxrYmQscHJlLHNhbXB7Zm9udC1mYW1pbHk6U0ZNb25vLVJlZ3VsYXIsTWVubG8sTW9uYWNvLENvbnNvbGFzLCJMaWJlcmF0aW9uIE1vbm8iLCJDb3VyaWVyIE5ldyIsbW9ub3NwYWNlO2ZvbnQtc2l6ZToxZW19cHJle21hcmdpbi10b3A6MDttYXJnaW4tYm90dG9tOjFyZW07b3ZlcmZsb3c6YXV0b31maWd1cmV7bWFyZ2luOjAgMCAxcmVtfWltZ3t2ZXJ0aWNhbC1hbGlnbjptaWRkbGU7Ym9yZGVyLXN0eWxlOm5vbmV9c3Zne292ZXJmbG93OmhpZGRlbjt2ZXJ0aWNhbC1hbGlnbjptaWRkbGV9dGFibGV7Ym9yZGVyLWNvbGxhcHNlOmNvbGxhcHNlfWNhcHRpb257cGFkZGluZy10b3A6Ljc1cmVtO3BhZGRpbmctYm90dG9tOi43NXJlbTtjb2xvcjojNmM3NTdkO3RleHQtYWxpZ246bGVmdDtjYXB0aW9uLXNpZGU6Ym90dG9tfXRoe3RleHQtYWxpZ246aW5oZXJpdH1sYWJlbHtkaXNwbGF5OmlubGluZS1ibG9jazttYXJnaW4tYm90dG9tOi41cmVtfWJ1dHRvbntib3JkZXItcmFkaXVzOjB9YnV0dG9uOmZvY3Vze291dGxpbmU6MXB4IGRvdHRlZDtvdXRsaW5lOjVweCBhdXRvIC13ZWJraXQtZm9jdXMtcmluZy1jb2xvcn1idXR0b24saW5wdXQsb3B0Z3JvdXAsc2VsZWN0LHRleHRhcmVhe21hcmdpbjowO2ZvbnQtZmFtaWx5OmluaGVyaXQ7Zm9udC1zaXplOmluaGVyaXQ7bGluZS1oZWlnaHQ6aW5oZXJpdH1idXR0b24saW5wdXR7b3ZlcmZsb3c6dmlzaWJsZX1idXR0b24sc2VsZWN0e3RleHQtdHJhbnNmb3JtOm5vbmV9c2VsZWN0e3dvcmQtd3JhcDpub3JtYWx9W3R5cGU9YnV0dG9uXSxbdHlwZT1yZXNldF0sW3R5cGU9c3VibWl0XSxidXR0b257LXdlYmtpdC1hcHBlYXJhbmNlOmJ1dHRvbn1bdHlwZT1idXR0b25dOm5vdCg6ZGlzYWJsZWQpLFt0eXBlPXJlc2V0XTpub3QoOmRpc2FibGVkKSxbdHlwZT1zdWJtaXRdOm5vdCg6ZGlzYWJsZWQpLGJ1dHRvbjpub3QoOmRpc2FibGVkKXtjdXJzb3I6cG9pbnRlcn1bdHlwZT1idXR0b25dOjotbW96LWZvY3VzLWlubmVyLFt0eXBlPXJlc2V0XTo6LW1vei1mb2N1cy1pbm5lcixbdHlwZT1zdWJtaXRdOjotbW96LWZvY3VzLWlubmVyLGJ1dHRvbjo6LW1vei1mb2N1cy1pbm5lcntwYWRkaW5nOjA7Ym9yZGVyLXN0eWxlOm5vbmV9aW5wdXRbdHlwZT1jaGVja2JveF0saW5wdXRbdHlwZT1yYWRpb117Ym94LXNpemluZzpib3JkZXItYm94O3BhZGRpbmc6MH1pbnB1dFt0eXBlPWRhdGVdLGlucHV0W3R5cGU9ZGF0ZXRpbWUtbG9jYWxdLGlucHV0W3R5cGU9bW9udGhdLGlucHV0W3R5cGU9dGltZV17LXdlYmtpdC1hcHBlYXJhbmNlOmxpc3Rib3h9dGV4dGFyZWF7b3ZlcmZsb3c6YXV0bztyZXNpemU6dmVydGljYWx9ZmllbGRzZXR7bWluLXdpZHRoOjA7cGFkZGluZzowO21hcmdpbjowO2JvcmRlcjowfWxlZ2VuZHtkaXNwbGF5OmJsb2NrO3dpZHRoOjEwMCU7bWF4LXdpZHRoOjEwMCU7cGFkZGluZzowO21hcmdpbi1ib3R0b206LjVyZW07Zm9udC1zaXplOjEuNXJlbTtsaW5lLWhlaWdodDppbmhlcml0O2NvbG9yOmluaGVyaXQ7d2hpdGUtc3BhY2U6bm9ybWFsfXByb2dyZXNze3ZlcnRpY2FsLWFsaWduOmJhc2VsaW5lfVt0eXBlPW51bWJlcl06Oi13ZWJraXQtaW5uZXItc3Bpbi1idXR0b24sW3R5cGU9bnVtYmVyXTo6LXdlYmtpdC1vdXRlci1zcGluLWJ1dHRvbntoZWlnaHQ6YXV0b31bdHlwZT1zZWFyY2hde291dGxpbmUtb2Zmc2V0Oi0ycHg7LXdlYmtpdC1hcHBlYXJhbmNlOm5vbmV9W3R5cGU9c2VhcmNoXTo6LXdlYmtpdC1zZWFyY2gtZGVjb3JhdGlvbnstd2Via2l0LWFwcGVhcmFuY2U6bm9uZX06Oi13ZWJraXQtZmlsZS11cGxvYWQtYnV0dG9ue2ZvbnQ6aW5oZXJpdDstd2Via2l0LWFwcGVhcmFuY2U6YnV0dG9ufW91dHB1dHtkaXNwbGF5OmlubGluZS1ibG9ja31zdW1tYXJ5e2Rpc3BsYXk6bGlzdC1pdGVtO2N1cnNvcjpwb2ludGVyfXRlbXBsYXRle2Rpc3BsYXk6bm9uZX1baGlkZGVuXXtkaXNwbGF5Om5vbmUhaW1wb3J0YW50fQovKiMgc291cmNlTWFwcGluZ1VSTD1ib290c3RyYXAtcmVib290Lm1pbi5jc3MubWFwICov'); }
if($path == 'bootstrap4/js/bootstrap.bundle.min.js'){ return base64_decode('LyohCiAgKiBCb290c3RyYXAgdjQuNC4xIChodHRwczovL2dldGJvb3RzdHJhcC5jb20vKQogICogQ29weXJpZ2h0IDIwMTEtMjAxOSBUaGUgQm9vdHN0cmFwIEF1dGhvcnMgKGh0dHBzOi8vZ2l0aHViLmNvbS90d2JzL2Jvb3RzdHJhcC9ncmFwaHMvY29udHJpYnV0b3JzKQogICogTGljZW5zZWQgdW5kZXIgTUlUIChodHRwczovL2dpdGh1Yi5jb20vdHdicy9ib290c3RyYXAvYmxvYi9tYXN0ZXIvTElDRU5TRSkKICAqLwohZnVuY3Rpb24oZSx0KXsib2JqZWN0Ij09dHlwZW9mIGV4cG9ydHMmJiJ1bmRlZmluZWQiIT10eXBlb2YgbW9kdWxlP3QoZXhwb3J0cyxyZXF1aXJlKCJqcXVlcnkiKSk6ImZ1bmN0aW9uIj09dHlwZW9mIGRlZmluZSYmZGVmaW5lLmFtZD9kZWZpbmUoWyJleHBvcnRzIiwianF1ZXJ5Il0sdCk6dCgoZT1lfHxzZWxmKS5ib290c3RyYXA9e30sZS5qUXVlcnkpfSh0aGlzLGZ1bmN0aW9uKGUscCl7InVzZSBzdHJpY3QiO2Z1bmN0aW9uIGkoZSx0KXtmb3IodmFyIG49MDtuPHQubGVuZ3RoO24rKyl7dmFyIGk9dFtuXTtpLmVudW1lcmFibGU9aS5lbnVtZXJhYmxlfHwhMSxpLmNvbmZpZ3VyYWJsZT0hMCwidmFsdWUiaW4gaSYmKGkud3JpdGFibGU9ITApLE9iamVjdC5kZWZpbmVQcm9wZXJ0eShlLGkua2V5LGkpfX1mdW5jdGlvbiBzKGUsdCxuKXtyZXR1cm4gdCYmaShlLnByb3RvdHlwZSx0KSxuJiZpKGUsbiksZX1mdW5jdGlvbiB0KHQsZSl7dmFyIG49T2JqZWN0LmtleXModCk7aWYoT2JqZWN0LmdldE93blByb3BlcnR5U3ltYm9scyl7dmFyIGk9T2JqZWN0LmdldE93blByb3BlcnR5U3ltYm9scyh0KTtlJiYoaT1pLmZpbHRlcihmdW5jdGlvbihlKXtyZXR1cm4gT2JqZWN0LmdldE93blByb3BlcnR5RGVzY3JpcHRvcih0LGUpLmVudW1lcmFibGV9KSksbi5wdXNoLmFwcGx5KG4saSl9cmV0dXJuIG59ZnVuY3Rpb24gbChvKXtmb3IodmFyIGU9MTtlPGFyZ3VtZW50cy5sZW5ndGg7ZSsrKXt2YXIgcj1udWxsIT1hcmd1bWVudHNbZV0/YXJndW1lbnRzW2VdOnt9O2UlMj90KE9iamVjdChyKSwhMCkuZm9yRWFjaChmdW5jdGlvbihlKXt2YXIgdCxuLGk7dD1vLGk9cltuPWVdLG4gaW4gdD9PYmplY3QuZGVmaW5lUHJvcGVydHkodCxuLHt2YWx1ZTppLGVudW1lcmFibGU6ITAsY29uZmlndXJhYmxlOiEwLHdyaXRhYmxlOiEwfSk6dFtuXT1pfSk6T2JqZWN0LmdldE93blByb3BlcnR5RGVzY3JpcHRvcnM/T2JqZWN0LmRlZmluZVByb3BlcnRpZXMobyxPYmplY3QuZ2V0T3duUHJvcGVydHlEZXNjcmlwdG9ycyhyKSk6dChPYmplY3QocikpLmZvckVhY2goZnVuY3Rpb24oZSl7T2JqZWN0LmRlZmluZVByb3BlcnR5KG8sZSxPYmplY3QuZ2V0T3duUHJvcGVydHlEZXNjcmlwdG9yKHIsZSkpfSl9cmV0dXJuIG99cD1wJiZwLmhhc093blByb3BlcnR5KCJkZWZhdWx0Iik/cC5kZWZhdWx0OnA7dmFyIG49InRyYW5zaXRpb25lbmQiO2Z1bmN0aW9uIG8oZSl7dmFyIHQ9dGhpcyxuPSExO3JldHVybiBwKHRoaXMpLm9uZShtLlRSQU5TSVRJT05fRU5ELGZ1bmN0aW9uKCl7bj0hMH0pLHNldFRpbWVvdXQoZnVuY3Rpb24oKXtufHxtLnRyaWdnZXJUcmFuc2l0aW9uRW5kKHQpfSxlKSx0aGlzfXZhciBtPXtUUkFOU0lUSU9OX0VORDoiYnNUcmFuc2l0aW9uRW5kIixnZXRVSUQ6ZnVuY3Rpb24oZSl7Zm9yKDtlKz1+figxZTYqTWF0aC5yYW5kb20oKSksZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoZSk7KTtyZXR1cm4gZX0sZ2V0U2VsZWN0b3JGcm9tRWxlbWVudDpmdW5jdGlvbihlKXt2YXIgdD1lLmdldEF0dHJpYnV0ZSgiZGF0YS10YXJnZXQiKTtpZighdHx8IiMiPT09dCl7dmFyIG49ZS5nZXRBdHRyaWJ1dGUoImhyZWYiKTt0PW4mJiIjIiE9PW4/bi50cmltKCk6IiJ9dHJ5e3JldHVybiBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKHQpP3Q6bnVsbH1jYXRjaChlKXtyZXR1cm4gbnVsbH19LGdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50OmZ1bmN0aW9uKGUpe2lmKCFlKXJldHVybiAwO3ZhciB0PXAoZSkuY3NzKCJ0cmFuc2l0aW9uLWR1cmF0aW9uIiksbj1wKGUpLmNzcygidHJhbnNpdGlvbi1kZWxheSIpLGk9cGFyc2VGbG9hdCh0KSxvPXBhcnNlRmxvYXQobik7cmV0dXJuIGl8fG8/KHQ9dC5zcGxpdCgiLCIpWzBdLG49bi5zcGxpdCgiLCIpWzBdLDFlMyoocGFyc2VGbG9hdCh0KStwYXJzZUZsb2F0KG4pKSk6MH0scmVmbG93OmZ1bmN0aW9uKGUpe3JldHVybiBlLm9mZnNldEhlaWdodH0sdHJpZ2dlclRyYW5zaXRpb25FbmQ6ZnVuY3Rpb24oZSl7cChlKS50cmlnZ2VyKG4pfSxzdXBwb3J0c1RyYW5zaXRpb25FbmQ6ZnVuY3Rpb24oKXtyZXR1cm4gQm9vbGVhbihuKX0saXNFbGVtZW50OmZ1bmN0aW9uKGUpe3JldHVybihlWzBdfHxlKS5ub2RlVHlwZX0sdHlwZUNoZWNrQ29uZmlnOmZ1bmN0aW9uKGUsdCxuKXtmb3IodmFyIGkgaW4gbilpZihPYmplY3QucHJvdG90eXBlLmhhc093blByb3BlcnR5LmNhbGwobixpKSl7dmFyIG89bltpXSxyPXRbaV0scz1yJiZtLmlzRWxlbWVudChyKT8iZWxlbWVudCI6KGE9cix7fS50b1N0cmluZy5jYWxsKGEpLm1hdGNoKC9ccyhbYS16XSspL2kpWzFdLnRvTG93ZXJDYXNlKCkpO2lmKCFuZXcgUmVnRXhwKG8pLnRlc3QocykpdGhyb3cgbmV3IEVycm9yKGUudG9VcHBlckNhc2UoKSsnOiBPcHRpb24gIicraSsnIiBwcm92aWRlZCB0eXBlICInK3MrJyIgYnV0IGV4cGVjdGVkIHR5cGUgIicrbysnIi4nKX12YXIgYX0sZmluZFNoYWRvd1Jvb3Q6ZnVuY3Rpb24oZSl7aWYoIWRvY3VtZW50LmRvY3VtZW50RWxlbWVudC5hdHRhY2hTaGFkb3cpcmV0dXJuIG51bGw7aWYoImZ1bmN0aW9uIiE9dHlwZW9mIGUuZ2V0Um9vdE5vZGUpcmV0dXJuIGUgaW5zdGFuY2VvZiBTaGFkb3dSb290P2U6ZS5wYXJlbnROb2RlP20uZmluZFNoYWRvd1Jvb3QoZS5wYXJlbnROb2RlKTpudWxsO3ZhciB0PWUuZ2V0Um9vdE5vZGUoKTtyZXR1cm4gdCBpbnN0YW5jZW9mIFNoYWRvd1Jvb3Q/dDpudWxsfSxqUXVlcnlEZXRlY3Rpb246ZnVuY3Rpb24oKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIHApdGhyb3cgbmV3IFR5cGVFcnJvcigiQm9vdHN0cmFwJ3MgSmF2YVNjcmlwdCByZXF1aXJlcyBqUXVlcnkuIGpRdWVyeSBtdXN0IGJlIGluY2x1ZGVkIGJlZm9yZSBCb290c3RyYXAncyBKYXZhU2NyaXB0LiIpO3ZhciBlPXAuZm4uanF1ZXJ5LnNwbGl0KCIgIilbMF0uc3BsaXQoIi4iKTtpZihlWzBdPDImJmVbMV08OXx8MT09PWVbMF0mJjk9PT1lWzFdJiZlWzJdPDF8fDQ8PWVbMF0pdGhyb3cgbmV3IEVycm9yKCJCb290c3RyYXAncyBKYXZhU2NyaXB0IHJlcXVpcmVzIGF0IGxlYXN0IGpRdWVyeSB2MS45LjEgYnV0IGxlc3MgdGhhbiB2NC4wLjAiKX19O20ualF1ZXJ5RGV0ZWN0aW9uKCkscC5mbi5lbXVsYXRlVHJhbnNpdGlvbkVuZD1vLHAuZXZlbnQuc3BlY2lhbFttLlRSQU5TSVRJT05fRU5EXT17YmluZFR5cGU6bixkZWxlZ2F0ZVR5cGU6bixoYW5kbGU6ZnVuY3Rpb24oZSl7aWYocChlLnRhcmdldCkuaXModGhpcykpcmV0dXJuIGUuaGFuZGxlT2JqLmhhbmRsZXIuYXBwbHkodGhpcyxhcmd1bWVudHMpfX07dmFyIHI9ImFsZXJ0IixhPSJicy5hbGVydCIsYz0iLiIrYSxoPXAuZm5bcl0sdT17Q0xPU0U6ImNsb3NlIitjLENMT1NFRDoiY2xvc2VkIitjLENMSUNLX0RBVEFfQVBJOiJjbGljayIrYysiLmRhdGEtYXBpIn0sZj0iYWxlcnQiLGQ9ImZhZGUiLGc9InNob3ciLF89ZnVuY3Rpb24oKXtmdW5jdGlvbiBpKGUpe3RoaXMuX2VsZW1lbnQ9ZX12YXIgZT1pLnByb3RvdHlwZTtyZXR1cm4gZS5jbG9zZT1mdW5jdGlvbihlKXt2YXIgdD10aGlzLl9lbGVtZW50O2UmJih0PXRoaXMuX2dldFJvb3RFbGVtZW50KGUpKSx0aGlzLl90cmlnZ2VyQ2xvc2VFdmVudCh0KS5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8dGhpcy5fcmVtb3ZlRWxlbWVudCh0KX0sZS5kaXNwb3NlPWZ1bmN0aW9uKCl7cC5yZW1vdmVEYXRhKHRoaXMuX2VsZW1lbnQsYSksdGhpcy5fZWxlbWVudD1udWxsfSxlLl9nZXRSb290RWxlbWVudD1mdW5jdGlvbihlKXt2YXIgdD1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQoZSksbj0hMTtyZXR1cm4gdCYmKG49ZG9jdW1lbnQucXVlcnlTZWxlY3Rvcih0KSksbj1ufHxwKGUpLmNsb3Nlc3QoIi4iK2YpWzBdfSxlLl90cmlnZ2VyQ2xvc2VFdmVudD1mdW5jdGlvbihlKXt2YXIgdD1wLkV2ZW50KHUuQ0xPU0UpO3JldHVybiBwKGUpLnRyaWdnZXIodCksdH0sZS5fcmVtb3ZlRWxlbWVudD1mdW5jdGlvbih0KXt2YXIgbj10aGlzO2lmKHAodCkucmVtb3ZlQ2xhc3MoZykscCh0KS5oYXNDbGFzcyhkKSl7dmFyIGU9bS5nZXRUcmFuc2l0aW9uRHVyYXRpb25Gcm9tRWxlbWVudCh0KTtwKHQpLm9uZShtLlRSQU5TSVRJT05fRU5ELGZ1bmN0aW9uKGUpe3JldHVybiBuLl9kZXN0cm95RWxlbWVudCh0LGUpfSkuZW11bGF0ZVRyYW5zaXRpb25FbmQoZSl9ZWxzZSB0aGlzLl9kZXN0cm95RWxlbWVudCh0KX0sZS5fZGVzdHJveUVsZW1lbnQ9ZnVuY3Rpb24oZSl7cChlKS5kZXRhY2goKS50cmlnZ2VyKHUuQ0xPU0VEKS5yZW1vdmUoKX0saS5falF1ZXJ5SW50ZXJmYWNlPWZ1bmN0aW9uKG4pe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgZT1wKHRoaXMpLHQ9ZS5kYXRhKGEpO3R8fCh0PW5ldyBpKHRoaXMpLGUuZGF0YShhLHQpKSwiY2xvc2UiPT09biYmdFtuXSh0aGlzKX0pfSxpLl9oYW5kbGVEaXNtaXNzPWZ1bmN0aW9uKHQpe3JldHVybiBmdW5jdGlvbihlKXtlJiZlLnByZXZlbnREZWZhdWx0KCksdC5jbG9zZSh0aGlzKX19LHMoaSxudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX1dKSxpfSgpO3AoZG9jdW1lbnQpLm9uKHUuQ0xJQ0tfREFUQV9BUEksJ1tkYXRhLWRpc21pc3M9ImFsZXJ0Il0nLF8uX2hhbmRsZURpc21pc3MobmV3IF8pKSxwLmZuW3JdPV8uX2pRdWVyeUludGVyZmFjZSxwLmZuW3JdLkNvbnN0cnVjdG9yPV8scC5mbltyXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm5bcl09aCxfLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciB2PSJidXR0b24iLHk9ImJzLmJ1dHRvbiIsRT0iLiIreSxiPSIuZGF0YS1hcGkiLHc9cC5mblt2XSxUPSJhY3RpdmUiLEM9ImJ0biIsUz0iZm9jdXMiLEQ9J1tkYXRhLXRvZ2dsZV49ImJ1dHRvbiJdJyxJPSdbZGF0YS10b2dnbGU9ImJ1dHRvbnMiXScsQT0nW2RhdGEtdG9nZ2xlPSJidXR0b24iXScsTz0nW2RhdGEtdG9nZ2xlPSJidXR0b25zIl0gLmJ0bicsTj0naW5wdXQ6bm90KFt0eXBlPSJoaWRkZW4iXSknLGs9Ii5hY3RpdmUiLEw9Ii5idG4iLFA9e0NMSUNLX0RBVEFfQVBJOiJjbGljayIrRStiLEZPQ1VTX0JMVVJfREFUQV9BUEk6ImZvY3VzIitFK2IrIiBibHVyIitFK2IsTE9BRF9EQVRBX0FQSToibG9hZCIrRStifSx4PWZ1bmN0aW9uKCl7ZnVuY3Rpb24gbihlKXt0aGlzLl9lbGVtZW50PWV9dmFyIGU9bi5wcm90b3R5cGU7cmV0dXJuIGUudG9nZ2xlPWZ1bmN0aW9uKCl7dmFyIGU9ITAsdD0hMCxuPXAodGhpcy5fZWxlbWVudCkuY2xvc2VzdChJKVswXTtpZihuKXt2YXIgaT10aGlzLl9lbGVtZW50LnF1ZXJ5U2VsZWN0b3IoTik7aWYoaSl7aWYoInJhZGlvIj09PWkudHlwZSlpZihpLmNoZWNrZWQmJnRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmNvbnRhaW5zKFQpKWU9ITE7ZWxzZXt2YXIgbz1uLnF1ZXJ5U2VsZWN0b3Ioayk7byYmcChvKS5yZW1vdmVDbGFzcyhUKX1lbHNlImNoZWNrYm94Ij09PWkudHlwZT8iTEFCRUwiPT09dGhpcy5fZWxlbWVudC50YWdOYW1lJiZpLmNoZWNrZWQ9PT10aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5jb250YWlucyhUKSYmKGU9ITEpOmU9ITE7ZSYmKGkuY2hlY2tlZD0hdGhpcy5fZWxlbWVudC5jbGFzc0xpc3QuY29udGFpbnMoVCkscChpKS50cmlnZ2VyKCJjaGFuZ2UiKSksaS5mb2N1cygpLHQ9ITF9fXRoaXMuX2VsZW1lbnQuaGFzQXR0cmlidXRlKCJkaXNhYmxlZCIpfHx0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5jb250YWlucygiZGlzYWJsZWQiKXx8KHQmJnRoaXMuX2VsZW1lbnQuc2V0QXR0cmlidXRlKCJhcmlhLXByZXNzZWQiLCF0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5jb250YWlucyhUKSksZSYmcCh0aGlzLl9lbGVtZW50KS50b2dnbGVDbGFzcyhUKSl9LGUuZGlzcG9zZT1mdW5jdGlvbigpe3AucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LHkpLHRoaXMuX2VsZW1lbnQ9bnVsbH0sbi5falF1ZXJ5SW50ZXJmYWNlPWZ1bmN0aW9uKHQpe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgZT1wKHRoaXMpLmRhdGEoeSk7ZXx8KGU9bmV3IG4odGhpcykscCh0aGlzKS5kYXRhKHksZSkpLCJ0b2dnbGUiPT09dCYmZVt0XSgpfSl9LHMobixudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX1dKSxufSgpO3AoZG9jdW1lbnQpLm9uKFAuQ0xJQ0tfREFUQV9BUEksRCxmdW5jdGlvbihlKXt2YXIgdD1lLnRhcmdldDtpZihwKHQpLmhhc0NsYXNzKEMpfHwodD1wKHQpLmNsb3Nlc3QoTClbMF0pLCF0fHx0Lmhhc0F0dHJpYnV0ZSgiZGlzYWJsZWQiKXx8dC5jbGFzc0xpc3QuY29udGFpbnMoImRpc2FibGVkIikpZS5wcmV2ZW50RGVmYXVsdCgpO2Vsc2V7dmFyIG49dC5xdWVyeVNlbGVjdG9yKE4pO2lmKG4mJihuLmhhc0F0dHJpYnV0ZSgiZGlzYWJsZWQiKXx8bi5jbGFzc0xpc3QuY29udGFpbnMoImRpc2FibGVkIikpKXJldHVybiB2b2lkIGUucHJldmVudERlZmF1bHQoKTt4Ll9qUXVlcnlJbnRlcmZhY2UuY2FsbChwKHQpLCJ0b2dnbGUiKX19KS5vbihQLkZPQ1VTX0JMVVJfREFUQV9BUEksRCxmdW5jdGlvbihlKXt2YXIgdD1wKGUudGFyZ2V0KS5jbG9zZXN0KEwpWzBdO3AodCkudG9nZ2xlQ2xhc3MoUywvXmZvY3VzKGluKT8kLy50ZXN0KGUudHlwZSkpfSkscCh3aW5kb3cpLm9uKFAuTE9BRF9EQVRBX0FQSSxmdW5jdGlvbigpe2Zvcih2YXIgZT1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoTykpLHQ9MCxuPWUubGVuZ3RoO3Q8bjt0Kyspe3ZhciBpPWVbdF0sbz1pLnF1ZXJ5U2VsZWN0b3IoTik7by5jaGVja2VkfHxvLmhhc0F0dHJpYnV0ZSgiY2hlY2tlZCIpP2kuY2xhc3NMaXN0LmFkZChUKTppLmNsYXNzTGlzdC5yZW1vdmUoVCl9Zm9yKHZhciByPTAscz0oZT1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoQSkpKS5sZW5ndGg7cjxzO3IrKyl7dmFyIGE9ZVtyXTsidHJ1ZSI9PT1hLmdldEF0dHJpYnV0ZSgiYXJpYS1wcmVzc2VkIik/YS5jbGFzc0xpc3QuYWRkKFQpOmEuY2xhc3NMaXN0LnJlbW92ZShUKX19KSxwLmZuW3ZdPXguX2pRdWVyeUludGVyZmFjZSxwLmZuW3ZdLkNvbnN0cnVjdG9yPXgscC5mblt2XS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm5bdl09dyx4Ll9qUXVlcnlJbnRlcmZhY2V9O3ZhciBqPSJjYXJvdXNlbCIsSD0iYnMuY2Fyb3VzZWwiLFI9Ii4iK0gsRj0iLmRhdGEtYXBpIixNPXAuZm5bal0sVz17aW50ZXJ2YWw6NWUzLGtleWJvYXJkOiEwLHNsaWRlOiExLHBhdXNlOiJob3ZlciIsd3JhcDohMCx0b3VjaDohMH0sVT17aW50ZXJ2YWw6IihudW1iZXJ8Ym9vbGVhbikiLGtleWJvYXJkOiJib29sZWFuIixzbGlkZToiKGJvb2xlYW58c3RyaW5nKSIscGF1c2U6IihzdHJpbmd8Ym9vbGVhbikiLHdyYXA6ImJvb2xlYW4iLHRvdWNoOiJib29sZWFuIn0sQj0ibmV4dCIscT0icHJldiIsSz0ibGVmdCIsUT0icmlnaHQiLFY9e1NMSURFOiJzbGlkZSIrUixTTElEOiJzbGlkIitSLEtFWURPV046ImtleWRvd24iK1IsTU9VU0VFTlRFUjoibW91c2VlbnRlciIrUixNT1VTRUxFQVZFOiJtb3VzZWxlYXZlIitSLFRPVUNIU1RBUlQ6InRvdWNoc3RhcnQiK1IsVE9VQ0hNT1ZFOiJ0b3VjaG1vdmUiK1IsVE9VQ0hFTkQ6InRvdWNoZW5kIitSLFBPSU5URVJET1dOOiJwb2ludGVyZG93biIrUixQT0lOVEVSVVA6InBvaW50ZXJ1cCIrUixEUkFHX1NUQVJUOiJkcmFnc3RhcnQiK1IsTE9BRF9EQVRBX0FQSToibG9hZCIrUitGLENMSUNLX0RBVEFfQVBJOiJjbGljayIrUitGfSxZPSJjYXJvdXNlbCIsej0iYWN0aXZlIixYPSJzbGlkZSIsRz0iY2Fyb3VzZWwtaXRlbS1yaWdodCIsJD0iY2Fyb3VzZWwtaXRlbS1sZWZ0IixKPSJjYXJvdXNlbC1pdGVtLW5leHQiLFo9ImNhcm91c2VsLWl0ZW0tcHJldiIsZWU9InBvaW50ZXItZXZlbnQiLHRlPSIuYWN0aXZlIixuZT0iLmFjdGl2ZS5jYXJvdXNlbC1pdGVtIixpZT0iLmNhcm91c2VsLWl0ZW0iLG9lPSIuY2Fyb3VzZWwtaXRlbSBpbWciLHJlPSIuY2Fyb3VzZWwtaXRlbS1uZXh0LCAuY2Fyb3VzZWwtaXRlbS1wcmV2IixzZT0iLmNhcm91c2VsLWluZGljYXRvcnMiLGFlPSJbZGF0YS1zbGlkZV0sIFtkYXRhLXNsaWRlLXRvXSIsbGU9J1tkYXRhLXJpZGU9ImNhcm91c2VsIl0nLGNlPXtUT1VDSDoidG91Y2giLFBFTjoicGVuIn0saGU9ZnVuY3Rpb24oKXtmdW5jdGlvbiByKGUsdCl7dGhpcy5faXRlbXM9bnVsbCx0aGlzLl9pbnRlcnZhbD1udWxsLHRoaXMuX2FjdGl2ZUVsZW1lbnQ9bnVsbCx0aGlzLl9pc1BhdXNlZD0hMSx0aGlzLl9pc1NsaWRpbmc9ITEsdGhpcy50b3VjaFRpbWVvdXQ9bnVsbCx0aGlzLnRvdWNoU3RhcnRYPTAsdGhpcy50b3VjaERlbHRhWD0wLHRoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcodCksdGhpcy5fZWxlbWVudD1lLHRoaXMuX2luZGljYXRvcnNFbGVtZW50PXRoaXMuX2VsZW1lbnQucXVlcnlTZWxlY3RvcihzZSksdGhpcy5fdG91Y2hTdXBwb3J0ZWQ9Im9udG91Y2hzdGFydCJpbiBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnR8fDA8bmF2aWdhdG9yLm1heFRvdWNoUG9pbnRzLHRoaXMuX3BvaW50ZXJFdmVudD1Cb29sZWFuKHdpbmRvdy5Qb2ludGVyRXZlbnR8fHdpbmRvdy5NU1BvaW50ZXJFdmVudCksdGhpcy5fYWRkRXZlbnRMaXN0ZW5lcnMoKX12YXIgZT1yLnByb3RvdHlwZTtyZXR1cm4gZS5uZXh0PWZ1bmN0aW9uKCl7dGhpcy5faXNTbGlkaW5nfHx0aGlzLl9zbGlkZShCKX0sZS5uZXh0V2hlblZpc2libGU9ZnVuY3Rpb24oKXshZG9jdW1lbnQuaGlkZGVuJiZwKHRoaXMuX2VsZW1lbnQpLmlzKCI6dmlzaWJsZSIpJiYiaGlkZGVuIiE9PXAodGhpcy5fZWxlbWVudCkuY3NzKCJ2aXNpYmlsaXR5IikmJnRoaXMubmV4dCgpfSxlLnByZXY9ZnVuY3Rpb24oKXt0aGlzLl9pc1NsaWRpbmd8fHRoaXMuX3NsaWRlKHEpfSxlLnBhdXNlPWZ1bmN0aW9uKGUpe2V8fCh0aGlzLl9pc1BhdXNlZD0hMCksdGhpcy5fZWxlbWVudC5xdWVyeVNlbGVjdG9yKHJlKSYmKG0udHJpZ2dlclRyYW5zaXRpb25FbmQodGhpcy5fZWxlbWVudCksdGhpcy5jeWNsZSghMCkpLGNsZWFySW50ZXJ2YWwodGhpcy5faW50ZXJ2YWwpLHRoaXMuX2ludGVydmFsPW51bGx9LGUuY3ljbGU9ZnVuY3Rpb24oZSl7ZXx8KHRoaXMuX2lzUGF1c2VkPSExKSx0aGlzLl9pbnRlcnZhbCYmKGNsZWFySW50ZXJ2YWwodGhpcy5faW50ZXJ2YWwpLHRoaXMuX2ludGVydmFsPW51bGwpLHRoaXMuX2NvbmZpZy5pbnRlcnZhbCYmIXRoaXMuX2lzUGF1c2VkJiYodGhpcy5faW50ZXJ2YWw9c2V0SW50ZXJ2YWwoKGRvY3VtZW50LnZpc2liaWxpdHlTdGF0ZT90aGlzLm5leHRXaGVuVmlzaWJsZTp0aGlzLm5leHQpLmJpbmQodGhpcyksdGhpcy5fY29uZmlnLmludGVydmFsKSl9LGUudG89ZnVuY3Rpb24oZSl7dmFyIHQ9dGhpczt0aGlzLl9hY3RpdmVFbGVtZW50PXRoaXMuX2VsZW1lbnQucXVlcnlTZWxlY3RvcihuZSk7dmFyIG49dGhpcy5fZ2V0SXRlbUluZGV4KHRoaXMuX2FjdGl2ZUVsZW1lbnQpO2lmKCEoZT50aGlzLl9pdGVtcy5sZW5ndGgtMXx8ZTwwKSlpZih0aGlzLl9pc1NsaWRpbmcpcCh0aGlzLl9lbGVtZW50KS5vbmUoVi5TTElELGZ1bmN0aW9uKCl7cmV0dXJuIHQudG8oZSl9KTtlbHNle2lmKG49PT1lKXJldHVybiB0aGlzLnBhdXNlKCksdm9pZCB0aGlzLmN5Y2xlKCk7dmFyIGk9bjxlP0I6cTt0aGlzLl9zbGlkZShpLHRoaXMuX2l0ZW1zW2VdKX19LGUuZGlzcG9zZT1mdW5jdGlvbigpe3AodGhpcy5fZWxlbWVudCkub2ZmKFIpLHAucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LEgpLHRoaXMuX2l0ZW1zPW51bGwsdGhpcy5fY29uZmlnPW51bGwsdGhpcy5fZWxlbWVudD1udWxsLHRoaXMuX2ludGVydmFsPW51bGwsdGhpcy5faXNQYXVzZWQ9bnVsbCx0aGlzLl9pc1NsaWRpbmc9bnVsbCx0aGlzLl9hY3RpdmVFbGVtZW50PW51bGwsdGhpcy5faW5kaWNhdG9yc0VsZW1lbnQ9bnVsbH0sZS5fZ2V0Q29uZmlnPWZ1bmN0aW9uKGUpe3JldHVybiBlPWwoe30sVyx7fSxlKSxtLnR5cGVDaGVja0NvbmZpZyhqLGUsVSksZX0sZS5faGFuZGxlU3dpcGU9ZnVuY3Rpb24oKXt2YXIgZT1NYXRoLmFicyh0aGlzLnRvdWNoRGVsdGFYKTtpZighKGU8PTQwKSl7dmFyIHQ9ZS90aGlzLnRvdWNoRGVsdGFYOyh0aGlzLnRvdWNoRGVsdGFYPTApPHQmJnRoaXMucHJldigpLHQ8MCYmdGhpcy5uZXh0KCl9fSxlLl9hZGRFdmVudExpc3RlbmVycz1mdW5jdGlvbigpe3ZhciB0PXRoaXM7dGhpcy5fY29uZmlnLmtleWJvYXJkJiZwKHRoaXMuX2VsZW1lbnQpLm9uKFYuS0VZRE9XTixmdW5jdGlvbihlKXtyZXR1cm4gdC5fa2V5ZG93bihlKX0pLCJob3ZlciI9PT10aGlzLl9jb25maWcucGF1c2UmJnAodGhpcy5fZWxlbWVudCkub24oVi5NT1VTRUVOVEVSLGZ1bmN0aW9uKGUpe3JldHVybiB0LnBhdXNlKGUpfSkub24oVi5NT1VTRUxFQVZFLGZ1bmN0aW9uKGUpe3JldHVybiB0LmN5Y2xlKGUpfSksdGhpcy5fY29uZmlnLnRvdWNoJiZ0aGlzLl9hZGRUb3VjaEV2ZW50TGlzdGVuZXJzKCl9LGUuX2FkZFRvdWNoRXZlbnRMaXN0ZW5lcnM9ZnVuY3Rpb24oKXt2YXIgdD10aGlzO2lmKHRoaXMuX3RvdWNoU3VwcG9ydGVkKXt2YXIgbj1mdW5jdGlvbihlKXt0Ll9wb2ludGVyRXZlbnQmJmNlW2Uub3JpZ2luYWxFdmVudC5wb2ludGVyVHlwZS50b1VwcGVyQ2FzZSgpXT90LnRvdWNoU3RhcnRYPWUub3JpZ2luYWxFdmVudC5jbGllbnRYOnQuX3BvaW50ZXJFdmVudHx8KHQudG91Y2hTdGFydFg9ZS5vcmlnaW5hbEV2ZW50LnRvdWNoZXNbMF0uY2xpZW50WCl9LGk9ZnVuY3Rpb24oZSl7dC5fcG9pbnRlckV2ZW50JiZjZVtlLm9yaWdpbmFsRXZlbnQucG9pbnRlclR5cGUudG9VcHBlckNhc2UoKV0mJih0LnRvdWNoRGVsdGFYPWUub3JpZ2luYWxFdmVudC5jbGllbnRYLXQudG91Y2hTdGFydFgpLHQuX2hhbmRsZVN3aXBlKCksImhvdmVyIj09PXQuX2NvbmZpZy5wYXVzZSYmKHQucGF1c2UoKSx0LnRvdWNoVGltZW91dCYmY2xlYXJUaW1lb3V0KHQudG91Y2hUaW1lb3V0KSx0LnRvdWNoVGltZW91dD1zZXRUaW1lb3V0KGZ1bmN0aW9uKGUpe3JldHVybiB0LmN5Y2xlKGUpfSw1MDArdC5fY29uZmlnLmludGVydmFsKSl9O3AodGhpcy5fZWxlbWVudC5xdWVyeVNlbGVjdG9yQWxsKG9lKSkub24oVi5EUkFHX1NUQVJULGZ1bmN0aW9uKGUpe3JldHVybiBlLnByZXZlbnREZWZhdWx0KCl9KSx0aGlzLl9wb2ludGVyRXZlbnQ/KHAodGhpcy5fZWxlbWVudCkub24oVi5QT0lOVEVSRE9XTixmdW5jdGlvbihlKXtyZXR1cm4gbihlKX0pLHAodGhpcy5fZWxlbWVudCkub24oVi5QT0lOVEVSVVAsZnVuY3Rpb24oZSl7cmV0dXJuIGkoZSl9KSx0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5hZGQoZWUpKToocCh0aGlzLl9lbGVtZW50KS5vbihWLlRPVUNIU1RBUlQsZnVuY3Rpb24oZSl7cmV0dXJuIG4oZSl9KSxwKHRoaXMuX2VsZW1lbnQpLm9uKFYuVE9VQ0hNT1ZFLGZ1bmN0aW9uKGUpe3JldHVybiBmdW5jdGlvbihlKXtlLm9yaWdpbmFsRXZlbnQudG91Y2hlcyYmMTxlLm9yaWdpbmFsRXZlbnQudG91Y2hlcy5sZW5ndGg/dC50b3VjaERlbHRhWD0wOnQudG91Y2hEZWx0YVg9ZS5vcmlnaW5hbEV2ZW50LnRvdWNoZXNbMF0uY2xpZW50WC10LnRvdWNoU3RhcnRYfShlKX0pLHAodGhpcy5fZWxlbWVudCkub24oVi5UT1VDSEVORCxmdW5jdGlvbihlKXtyZXR1cm4gaShlKX0pKX19LGUuX2tleWRvd249ZnVuY3Rpb24oZSl7aWYoIS9pbnB1dHx0ZXh0YXJlYS9pLnRlc3QoZS50YXJnZXQudGFnTmFtZSkpc3dpdGNoKGUud2hpY2gpe2Nhc2UgMzc6ZS5wcmV2ZW50RGVmYXVsdCgpLHRoaXMucHJldigpO2JyZWFrO2Nhc2UgMzk6ZS5wcmV2ZW50RGVmYXVsdCgpLHRoaXMubmV4dCgpfX0sZS5fZ2V0SXRlbUluZGV4PWZ1bmN0aW9uKGUpe3JldHVybiB0aGlzLl9pdGVtcz1lJiZlLnBhcmVudE5vZGU/W10uc2xpY2UuY2FsbChlLnBhcmVudE5vZGUucXVlcnlTZWxlY3RvckFsbChpZSkpOltdLHRoaXMuX2l0ZW1zLmluZGV4T2YoZSl9LGUuX2dldEl0ZW1CeURpcmVjdGlvbj1mdW5jdGlvbihlLHQpe3ZhciBuPWU9PT1CLGk9ZT09PXEsbz10aGlzLl9nZXRJdGVtSW5kZXgodCkscj10aGlzLl9pdGVtcy5sZW5ndGgtMTtpZigoaSYmMD09PW98fG4mJm89PT1yKSYmIXRoaXMuX2NvbmZpZy53cmFwKXJldHVybiB0O3ZhciBzPShvKyhlPT09cT8tMToxKSkldGhpcy5faXRlbXMubGVuZ3RoO3JldHVybi0xPT1zP3RoaXMuX2l0ZW1zW3RoaXMuX2l0ZW1zLmxlbmd0aC0xXTp0aGlzLl9pdGVtc1tzXX0sZS5fdHJpZ2dlclNsaWRlRXZlbnQ9ZnVuY3Rpb24oZSx0KXt2YXIgbj10aGlzLl9nZXRJdGVtSW5kZXgoZSksaT10aGlzLl9nZXRJdGVtSW5kZXgodGhpcy5fZWxlbWVudC5xdWVyeVNlbGVjdG9yKG5lKSksbz1wLkV2ZW50KFYuU0xJREUse3JlbGF0ZWRUYXJnZXQ6ZSxkaXJlY3Rpb246dCxmcm9tOmksdG86bn0pO3JldHVybiBwKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIobyksb30sZS5fc2V0QWN0aXZlSW5kaWNhdG9yRWxlbWVudD1mdW5jdGlvbihlKXtpZih0aGlzLl9pbmRpY2F0b3JzRWxlbWVudCl7dmFyIHQ9W10uc2xpY2UuY2FsbCh0aGlzLl9pbmRpY2F0b3JzRWxlbWVudC5xdWVyeVNlbGVjdG9yQWxsKHRlKSk7cCh0KS5yZW1vdmVDbGFzcyh6KTt2YXIgbj10aGlzLl9pbmRpY2F0b3JzRWxlbWVudC5jaGlsZHJlblt0aGlzLl9nZXRJdGVtSW5kZXgoZSldO24mJnAobikuYWRkQ2xhc3Moeil9fSxlLl9zbGlkZT1mdW5jdGlvbihlLHQpe3ZhciBuLGksbyxyPXRoaXMscz10aGlzLl9lbGVtZW50LnF1ZXJ5U2VsZWN0b3IobmUpLGE9dGhpcy5fZ2V0SXRlbUluZGV4KHMpLGw9dHx8cyYmdGhpcy5fZ2V0SXRlbUJ5RGlyZWN0aW9uKGUscyksYz10aGlzLl9nZXRJdGVtSW5kZXgobCksaD1Cb29sZWFuKHRoaXMuX2ludGVydmFsKTtpZihvPWU9PT1CPyhuPSQsaT1KLEspOihuPUcsaT1aLFEpLGwmJnAobCkuaGFzQ2xhc3MoeikpdGhpcy5faXNTbGlkaW5nPSExO2Vsc2UgaWYoIXRoaXMuX3RyaWdnZXJTbGlkZUV2ZW50KGwsbykuaXNEZWZhdWx0UHJldmVudGVkKCkmJnMmJmwpe3RoaXMuX2lzU2xpZGluZz0hMCxoJiZ0aGlzLnBhdXNlKCksdGhpcy5fc2V0QWN0aXZlSW5kaWNhdG9yRWxlbWVudChsKTt2YXIgdT1wLkV2ZW50KFYuU0xJRCx7cmVsYXRlZFRhcmdldDpsLGRpcmVjdGlvbjpvLGZyb206YSx0bzpjfSk7aWYocCh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyhYKSl7cChsKS5hZGRDbGFzcyhpKSxtLnJlZmxvdyhsKSxwKHMpLmFkZENsYXNzKG4pLHAobCkuYWRkQ2xhc3Mobik7dmFyIGY9cGFyc2VJbnQobC5nZXRBdHRyaWJ1dGUoImRhdGEtaW50ZXJ2YWwiKSwxMCk7Zj8odGhpcy5fY29uZmlnLmRlZmF1bHRJbnRlcnZhbD10aGlzLl9jb25maWcuZGVmYXVsdEludGVydmFsfHx0aGlzLl9jb25maWcuaW50ZXJ2YWwsdGhpcy5fY29uZmlnLmludGVydmFsPWYpOnRoaXMuX2NvbmZpZy5pbnRlcnZhbD10aGlzLl9jb25maWcuZGVmYXVsdEludGVydmFsfHx0aGlzLl9jb25maWcuaW50ZXJ2YWw7dmFyIGQ9bS5nZXRUcmFuc2l0aW9uRHVyYXRpb25Gcm9tRWxlbWVudChzKTtwKHMpLm9uZShtLlRSQU5TSVRJT05fRU5ELGZ1bmN0aW9uKCl7cChsKS5yZW1vdmVDbGFzcyhuKyIgIitpKS5hZGRDbGFzcyh6KSxwKHMpLnJlbW92ZUNsYXNzKHorIiAiK2krIiAiK24pLHIuX2lzU2xpZGluZz0hMSxzZXRUaW1lb3V0KGZ1bmN0aW9uKCl7cmV0dXJuIHAoci5fZWxlbWVudCkudHJpZ2dlcih1KX0sMCl9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChkKX1lbHNlIHAocykucmVtb3ZlQ2xhc3MoeikscChsKS5hZGRDbGFzcyh6KSx0aGlzLl9pc1NsaWRpbmc9ITEscCh0aGlzLl9lbGVtZW50KS50cmlnZ2VyKHUpO2gmJnRoaXMuY3ljbGUoKX19LHIuX2pRdWVyeUludGVyZmFjZT1mdW5jdGlvbihpKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzKS5kYXRhKEgpLHQ9bCh7fSxXLHt9LHAodGhpcykuZGF0YSgpKTsib2JqZWN0Ij09dHlwZW9mIGkmJih0PWwoe30sdCx7fSxpKSk7dmFyIG49InN0cmluZyI9PXR5cGVvZiBpP2k6dC5zbGlkZTtpZihlfHwoZT1uZXcgcih0aGlzLHQpLHAodGhpcykuZGF0YShILGUpKSwibnVtYmVyIj09dHlwZW9mIGkpZS50byhpKTtlbHNlIGlmKCJzdHJpbmciPT10eXBlb2Ygbil7aWYoInVuZGVmaW5lZCI9PXR5cGVvZiBlW25dKXRocm93IG5ldyBUeXBlRXJyb3IoJ05vIG1ldGhvZCBuYW1lZCAiJytuKyciJyk7ZVtuXSgpfWVsc2UgdC5pbnRlcnZhbCYmdC5yaWRlJiYoZS5wYXVzZSgpLGUuY3ljbGUoKSl9KX0sci5fZGF0YUFwaUNsaWNrSGFuZGxlcj1mdW5jdGlvbihlKXt2YXIgdD1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQodGhpcyk7aWYodCl7dmFyIG49cCh0KVswXTtpZihuJiZwKG4pLmhhc0NsYXNzKFkpKXt2YXIgaT1sKHt9LHAobikuZGF0YSgpLHt9LHAodGhpcykuZGF0YSgpKSxvPXRoaXMuZ2V0QXR0cmlidXRlKCJkYXRhLXNsaWRlLXRvIik7byYmKGkuaW50ZXJ2YWw9ITEpLHIuX2pRdWVyeUludGVyZmFjZS5jYWxsKHAobiksaSksbyYmcChuKS5kYXRhKEgpLnRvKG8pLGUucHJldmVudERlZmF1bHQoKX19fSxzKHIsbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBXfX1dKSxyfSgpO3AoZG9jdW1lbnQpLm9uKFYuQ0xJQ0tfREFUQV9BUEksYWUsaGUuX2RhdGFBcGlDbGlja0hhbmRsZXIpLHAod2luZG93KS5vbihWLkxPQURfREFUQV9BUEksZnVuY3Rpb24oKXtmb3IodmFyIGU9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKGxlKSksdD0wLG49ZS5sZW5ndGg7dDxuO3QrKyl7dmFyIGk9cChlW3RdKTtoZS5falF1ZXJ5SW50ZXJmYWNlLmNhbGwoaSxpLmRhdGEoKSl9fSkscC5mbltqXT1oZS5falF1ZXJ5SW50ZXJmYWNlLHAuZm5bal0uQ29uc3RydWN0b3I9aGUscC5mbltqXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm5bal09TSxoZS5falF1ZXJ5SW50ZXJmYWNlfTt2YXIgdWU9ImNvbGxhcHNlIixmZT0iYnMuY29sbGFwc2UiLGRlPSIuIitmZSxwZT1wLmZuW3VlXSxtZT17dG9nZ2xlOiEwLHBhcmVudDoiIn0sZ2U9e3RvZ2dsZToiYm9vbGVhbiIscGFyZW50OiIoc3RyaW5nfGVsZW1lbnQpIn0sX2U9e1NIT1c6InNob3ciK2RlLFNIT1dOOiJzaG93biIrZGUsSElERToiaGlkZSIrZGUsSElEREVOOiJoaWRkZW4iK2RlLENMSUNLX0RBVEFfQVBJOiJjbGljayIrZGUrIi5kYXRhLWFwaSJ9LHZlPSJzaG93Iix5ZT0iY29sbGFwc2UiLEVlPSJjb2xsYXBzaW5nIixiZT0iY29sbGFwc2VkIix3ZT0id2lkdGgiLFRlPSJoZWlnaHQiLENlPSIuc2hvdywgLmNvbGxhcHNpbmciLFNlPSdbZGF0YS10b2dnbGU9ImNvbGxhcHNlIl0nLERlPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gYSh0LGUpe3RoaXMuX2lzVHJhbnNpdGlvbmluZz0hMSx0aGlzLl9lbGVtZW50PXQsdGhpcy5fY29uZmlnPXRoaXMuX2dldENvbmZpZyhlKSx0aGlzLl90cmlnZ2VyQXJyYXk9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKCdbZGF0YS10b2dnbGU9ImNvbGxhcHNlIl1baHJlZj0iIycrdC5pZCsnIl0sW2RhdGEtdG9nZ2xlPSJjb2xsYXBzZSJdW2RhdGEtdGFyZ2V0PSIjJyt0LmlkKyciXScpKTtmb3IodmFyIG49W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKFNlKSksaT0wLG89bi5sZW5ndGg7aTxvO2krKyl7dmFyIHI9bltpXSxzPW0uZ2V0U2VsZWN0b3JGcm9tRWxlbWVudChyKSxhPVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChzKSkuZmlsdGVyKGZ1bmN0aW9uKGUpe3JldHVybiBlPT09dH0pO251bGwhPT1zJiYwPGEubGVuZ3RoJiYodGhpcy5fc2VsZWN0b3I9cyx0aGlzLl90cmlnZ2VyQXJyYXkucHVzaChyKSl9dGhpcy5fcGFyZW50PXRoaXMuX2NvbmZpZy5wYXJlbnQ/dGhpcy5fZ2V0UGFyZW50KCk6bnVsbCx0aGlzLl9jb25maWcucGFyZW50fHx0aGlzLl9hZGRBcmlhQW5kQ29sbGFwc2VkQ2xhc3ModGhpcy5fZWxlbWVudCx0aGlzLl90cmlnZ2VyQXJyYXkpLHRoaXMuX2NvbmZpZy50b2dnbGUmJnRoaXMudG9nZ2xlKCl9dmFyIGU9YS5wcm90b3R5cGU7cmV0dXJuIGUudG9nZ2xlPWZ1bmN0aW9uKCl7cCh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyh2ZSk/dGhpcy5oaWRlKCk6dGhpcy5zaG93KCl9LGUuc2hvdz1mdW5jdGlvbigpe3ZhciBlLHQsbj10aGlzO2lmKCF0aGlzLl9pc1RyYW5zaXRpb25pbmcmJiFwKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKHZlKSYmKHRoaXMuX3BhcmVudCYmMD09PShlPVtdLnNsaWNlLmNhbGwodGhpcy5fcGFyZW50LnF1ZXJ5U2VsZWN0b3JBbGwoQ2UpKS5maWx0ZXIoZnVuY3Rpb24oZSl7cmV0dXJuInN0cmluZyI9PXR5cGVvZiBuLl9jb25maWcucGFyZW50P2UuZ2V0QXR0cmlidXRlKCJkYXRhLXBhcmVudCIpPT09bi5fY29uZmlnLnBhcmVudDplLmNsYXNzTGlzdC5jb250YWlucyh5ZSl9KSkubGVuZ3RoJiYoZT1udWxsKSwhKGUmJih0PXAoZSkubm90KHRoaXMuX3NlbGVjdG9yKS5kYXRhKGZlKSkmJnQuX2lzVHJhbnNpdGlvbmluZykpKXt2YXIgaT1wLkV2ZW50KF9lLlNIT1cpO2lmKHAodGhpcy5fZWxlbWVudCkudHJpZ2dlcihpKSwhaS5pc0RlZmF1bHRQcmV2ZW50ZWQoKSl7ZSYmKGEuX2pRdWVyeUludGVyZmFjZS5jYWxsKHAoZSkubm90KHRoaXMuX3NlbGVjdG9yKSwiaGlkZSIpLHR8fHAoZSkuZGF0YShmZSxudWxsKSk7dmFyIG89dGhpcy5fZ2V0RGltZW5zaW9uKCk7cCh0aGlzLl9lbGVtZW50KS5yZW1vdmVDbGFzcyh5ZSkuYWRkQ2xhc3MoRWUpLHRoaXMuX2VsZW1lbnQuc3R5bGVbb109MCx0aGlzLl90cmlnZ2VyQXJyYXkubGVuZ3RoJiZwKHRoaXMuX3RyaWdnZXJBcnJheSkucmVtb3ZlQ2xhc3MoYmUpLmF0dHIoImFyaWEtZXhwYW5kZWQiLCEwKSx0aGlzLnNldFRyYW5zaXRpb25pbmcoITApO3ZhciByPSJzY3JvbGwiKyhvWzBdLnRvVXBwZXJDYXNlKCkrby5zbGljZSgxKSkscz1tLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO3AodGhpcy5fZWxlbWVudCkub25lKG0uVFJBTlNJVElPTl9FTkQsZnVuY3Rpb24oKXtwKG4uX2VsZW1lbnQpLnJlbW92ZUNsYXNzKEVlKS5hZGRDbGFzcyh5ZSkuYWRkQ2xhc3ModmUpLG4uX2VsZW1lbnQuc3R5bGVbb109IiIsbi5zZXRUcmFuc2l0aW9uaW5nKCExKSxwKG4uX2VsZW1lbnQpLnRyaWdnZXIoX2UuU0hPV04pfSkuZW11bGF0ZVRyYW5zaXRpb25FbmQocyksdGhpcy5fZWxlbWVudC5zdHlsZVtvXT10aGlzLl9lbGVtZW50W3JdKyJweCJ9fX0sZS5oaWRlPWZ1bmN0aW9uKCl7dmFyIGU9dGhpcztpZighdGhpcy5faXNUcmFuc2l0aW9uaW5nJiZwKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKHZlKSl7dmFyIHQ9cC5FdmVudChfZS5ISURFKTtpZihwKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIodCksIXQuaXNEZWZhdWx0UHJldmVudGVkKCkpe3ZhciBuPXRoaXMuX2dldERpbWVuc2lvbigpO3RoaXMuX2VsZW1lbnQuc3R5bGVbbl09dGhpcy5fZWxlbWVudC5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKVtuXSsicHgiLG0ucmVmbG93KHRoaXMuX2VsZW1lbnQpLHAodGhpcy5fZWxlbWVudCkuYWRkQ2xhc3MoRWUpLnJlbW92ZUNsYXNzKHllKS5yZW1vdmVDbGFzcyh2ZSk7dmFyIGk9dGhpcy5fdHJpZ2dlckFycmF5Lmxlbmd0aDtpZigwPGkpZm9yKHZhciBvPTA7bzxpO28rKyl7dmFyIHI9dGhpcy5fdHJpZ2dlckFycmF5W29dLHM9bS5nZXRTZWxlY3RvckZyb21FbGVtZW50KHIpO2lmKG51bGwhPT1zKXAoW10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKHMpKSkuaGFzQ2xhc3ModmUpfHxwKHIpLmFkZENsYXNzKGJlKS5hdHRyKCJhcmlhLWV4cGFuZGVkIiwhMSl9dGhpcy5zZXRUcmFuc2l0aW9uaW5nKCEwKTt0aGlzLl9lbGVtZW50LnN0eWxlW25dPSIiO3ZhciBhPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7cCh0aGlzLl9lbGVtZW50KS5vbmUobS5UUkFOU0lUSU9OX0VORCxmdW5jdGlvbigpe2Uuc2V0VHJhbnNpdGlvbmluZyghMSkscChlLl9lbGVtZW50KS5yZW1vdmVDbGFzcyhFZSkuYWRkQ2xhc3MoeWUpLnRyaWdnZXIoX2UuSElEREVOKX0pLmVtdWxhdGVUcmFuc2l0aW9uRW5kKGEpfX19LGUuc2V0VHJhbnNpdGlvbmluZz1mdW5jdGlvbihlKXt0aGlzLl9pc1RyYW5zaXRpb25pbmc9ZX0sZS5kaXNwb3NlPWZ1bmN0aW9uKCl7cC5yZW1vdmVEYXRhKHRoaXMuX2VsZW1lbnQsZmUpLHRoaXMuX2NvbmZpZz1udWxsLHRoaXMuX3BhcmVudD1udWxsLHRoaXMuX2VsZW1lbnQ9bnVsbCx0aGlzLl90cmlnZ2VyQXJyYXk9bnVsbCx0aGlzLl9pc1RyYW5zaXRpb25pbmc9bnVsbH0sZS5fZ2V0Q29uZmlnPWZ1bmN0aW9uKGUpe3JldHVybihlPWwoe30sbWUse30sZSkpLnRvZ2dsZT1Cb29sZWFuKGUudG9nZ2xlKSxtLnR5cGVDaGVja0NvbmZpZyh1ZSxlLGdlKSxlfSxlLl9nZXREaW1lbnNpb249ZnVuY3Rpb24oKXtyZXR1cm4gcCh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyh3ZSk/d2U6VGV9LGUuX2dldFBhcmVudD1mdW5jdGlvbigpe3ZhciBlLG49dGhpczttLmlzRWxlbWVudCh0aGlzLl9jb25maWcucGFyZW50KT8oZT10aGlzLl9jb25maWcucGFyZW50LCJ1bmRlZmluZWQiIT10eXBlb2YgdGhpcy5fY29uZmlnLnBhcmVudC5qcXVlcnkmJihlPXRoaXMuX2NvbmZpZy5wYXJlbnRbMF0pKTplPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IodGhpcy5fY29uZmlnLnBhcmVudCk7dmFyIHQ9J1tkYXRhLXRvZ2dsZT0iY29sbGFwc2UiXVtkYXRhLXBhcmVudD0iJyt0aGlzLl9jb25maWcucGFyZW50KyciXScsaT1bXS5zbGljZS5jYWxsKGUucXVlcnlTZWxlY3RvckFsbCh0KSk7cmV0dXJuIHAoaSkuZWFjaChmdW5jdGlvbihlLHQpe24uX2FkZEFyaWFBbmRDb2xsYXBzZWRDbGFzcyhhLl9nZXRUYXJnZXRGcm9tRWxlbWVudCh0KSxbdF0pfSksZX0sZS5fYWRkQXJpYUFuZENvbGxhcHNlZENsYXNzPWZ1bmN0aW9uKGUsdCl7dmFyIG49cChlKS5oYXNDbGFzcyh2ZSk7dC5sZW5ndGgmJnAodCkudG9nZ2xlQ2xhc3MoYmUsIW4pLmF0dHIoImFyaWEtZXhwYW5kZWQiLG4pfSxhLl9nZXRUYXJnZXRGcm9tRWxlbWVudD1mdW5jdGlvbihlKXt2YXIgdD1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQoZSk7cmV0dXJuIHQ/ZG9jdW1lbnQucXVlcnlTZWxlY3Rvcih0KTpudWxsfSxhLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24oaSl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciBlPXAodGhpcyksdD1lLmRhdGEoZmUpLG49bCh7fSxtZSx7fSxlLmRhdGEoKSx7fSwib2JqZWN0Ij09dHlwZW9mIGkmJmk/aTp7fSk7aWYoIXQmJm4udG9nZ2xlJiYvc2hvd3xoaWRlLy50ZXN0KGkpJiYobi50b2dnbGU9ITEpLHR8fCh0PW5ldyBhKHRoaXMsbiksZS5kYXRhKGZlLHQpKSwic3RyaW5nIj09dHlwZW9mIGkpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdFtpXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicraSsnIicpO3RbaV0oKX19KX0scyhhLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fSx7a2V5OiJEZWZhdWx0IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gbWV9fV0pLGF9KCk7cChkb2N1bWVudCkub24oX2UuQ0xJQ0tfREFUQV9BUEksU2UsZnVuY3Rpb24oZSl7IkEiPT09ZS5jdXJyZW50VGFyZ2V0LnRhZ05hbWUmJmUucHJldmVudERlZmF1bHQoKTt2YXIgbj1wKHRoaXMpLHQ9bS5nZXRTZWxlY3RvckZyb21FbGVtZW50KHRoaXMpLGk9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKHQpKTtwKGkpLmVhY2goZnVuY3Rpb24oKXt2YXIgZT1wKHRoaXMpLHQ9ZS5kYXRhKGZlKT8idG9nZ2xlIjpuLmRhdGEoKTtEZS5falF1ZXJ5SW50ZXJmYWNlLmNhbGwoZSx0KX0pfSkscC5mblt1ZV09RGUuX2pRdWVyeUludGVyZmFjZSxwLmZuW3VlXS5Db25zdHJ1Y3Rvcj1EZSxwLmZuW3VlXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm5bdWVdPXBlLERlLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciBJZT0idW5kZWZpbmVkIiE9dHlwZW9mIHdpbmRvdyYmInVuZGVmaW5lZCIhPXR5cGVvZiBkb2N1bWVudCYmInVuZGVmaW5lZCIhPXR5cGVvZiBuYXZpZ2F0b3IsQWU9ZnVuY3Rpb24oKXtmb3IodmFyIGU9WyJFZGdlIiwiVHJpZGVudCIsIkZpcmVmb3giXSx0PTA7dDxlLmxlbmd0aDt0Kz0xKWlmKEllJiYwPD1uYXZpZ2F0b3IudXNlckFnZW50LmluZGV4T2YoZVt0XSkpcmV0dXJuIDE7cmV0dXJuIDB9KCk7dmFyIE9lPUllJiZ3aW5kb3cuUHJvbWlzZT9mdW5jdGlvbihlKXt2YXIgdD0hMTtyZXR1cm4gZnVuY3Rpb24oKXt0fHwodD0hMCx3aW5kb3cuUHJvbWlzZS5yZXNvbHZlKCkudGhlbihmdW5jdGlvbigpe3Q9ITEsZSgpfSkpfX06ZnVuY3Rpb24oZSl7dmFyIHQ9ITE7cmV0dXJuIGZ1bmN0aW9uKCl7dHx8KHQ9ITAsc2V0VGltZW91dChmdW5jdGlvbigpe3Q9ITEsZSgpfSxBZSkpfX07ZnVuY3Rpb24gTmUoZSl7cmV0dXJuIGUmJiJbb2JqZWN0IEZ1bmN0aW9uXSI9PT17fS50b1N0cmluZy5jYWxsKGUpfWZ1bmN0aW9uIGtlKGUsdCl7aWYoMSE9PWUubm9kZVR5cGUpcmV0dXJuW107dmFyIG49ZS5vd25lckRvY3VtZW50LmRlZmF1bHRWaWV3LmdldENvbXB1dGVkU3R5bGUoZSxudWxsKTtyZXR1cm4gdD9uW3RdOm59ZnVuY3Rpb24gTGUoZSl7cmV0dXJuIkhUTUwiPT09ZS5ub2RlTmFtZT9lOmUucGFyZW50Tm9kZXx8ZS5ob3N0fWZ1bmN0aW9uIFBlKGUpe2lmKCFlKXJldHVybiBkb2N1bWVudC5ib2R5O3N3aXRjaChlLm5vZGVOYW1lKXtjYXNlIkhUTUwiOmNhc2UiQk9EWSI6cmV0dXJuIGUub3duZXJEb2N1bWVudC5ib2R5O2Nhc2UiI2RvY3VtZW50IjpyZXR1cm4gZS5ib2R5fXZhciB0PWtlKGUpLG49dC5vdmVyZmxvdyxpPXQub3ZlcmZsb3dYLG89dC5vdmVyZmxvd1k7cmV0dXJuLyhhdXRvfHNjcm9sbHxvdmVybGF5KS8udGVzdChuK28raSk/ZTpQZShMZShlKSl9ZnVuY3Rpb24geGUoZSl7cmV0dXJuIGUmJmUucmVmZXJlbmNlTm9kZT9lLnJlZmVyZW5jZU5vZGU6ZX12YXIgamU9SWUmJiEoIXdpbmRvdy5NU0lucHV0TWV0aG9kQ29udGV4dHx8IWRvY3VtZW50LmRvY3VtZW50TW9kZSksSGU9SWUmJi9NU0lFIDEwLy50ZXN0KG5hdmlnYXRvci51c2VyQWdlbnQpO2Z1bmN0aW9uIFJlKGUpe3JldHVybiAxMT09PWU/amU6MTA9PT1lP0hlOmplfHxIZX1mdW5jdGlvbiBGZShlKXtpZighZSlyZXR1cm4gZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50O2Zvcih2YXIgdD1SZSgxMCk/ZG9jdW1lbnQuYm9keTpudWxsLG49ZS5vZmZzZXRQYXJlbnR8fG51bGw7bj09PXQmJmUubmV4dEVsZW1lbnRTaWJsaW5nOyluPShlPWUubmV4dEVsZW1lbnRTaWJsaW5nKS5vZmZzZXRQYXJlbnQ7dmFyIGk9biYmbi5ub2RlTmFtZTtyZXR1cm4gaSYmIkJPRFkiIT09aSYmIkhUTUwiIT09aT8tMSE9PVsiVEgiLCJURCIsIlRBQkxFIl0uaW5kZXhPZihuLm5vZGVOYW1lKSYmInN0YXRpYyI9PT1rZShuLCJwb3NpdGlvbiIpP0ZlKG4pOm46ZT9lLm93bmVyRG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50OmRvY3VtZW50LmRvY3VtZW50RWxlbWVudH1mdW5jdGlvbiBNZShlKXtyZXR1cm4gbnVsbCE9PWUucGFyZW50Tm9kZT9NZShlLnBhcmVudE5vZGUpOmV9ZnVuY3Rpb24gV2UoZSx0KXtpZighKGUmJmUubm9kZVR5cGUmJnQmJnQubm9kZVR5cGUpKXJldHVybiBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQ7dmFyIG49ZS5jb21wYXJlRG9jdW1lbnRQb3NpdGlvbih0KSZOb2RlLkRPQ1VNRU5UX1BPU0lUSU9OX0ZPTExPV0lORyxpPW4/ZTp0LG89bj90OmUscj1kb2N1bWVudC5jcmVhdGVSYW5nZSgpO3Iuc2V0U3RhcnQoaSwwKSxyLnNldEVuZChvLDApO3ZhciBzPXIuY29tbW9uQW5jZXN0b3JDb250YWluZXI7aWYoZSE9PXMmJnQhPT1zfHxpLmNvbnRhaW5zKG8pKXJldHVybiBmdW5jdGlvbihlKXt2YXIgdD1lLm5vZGVOYW1lO3JldHVybiJCT0RZIiE9PXQmJigiSFRNTCI9PT10fHxGZShlLmZpcnN0RWxlbWVudENoaWxkKT09PWUpfShzKT9zOkZlKHMpO3ZhciBhPU1lKGUpO3JldHVybiBhLmhvc3Q/V2UoYS5ob3N0LHQpOldlKGUsTWUodCkuaG9zdCl9ZnVuY3Rpb24gVWUoZSx0KXt2YXIgbj0idG9wIj09PSgxPGFyZ3VtZW50cy5sZW5ndGgmJnZvaWQgMCE9PXQ/dDoidG9wIik/InNjcm9sbFRvcCI6InNjcm9sbExlZnQiLGk9ZS5ub2RlTmFtZTtpZigiQk9EWSIhPT1pJiYiSFRNTCIhPT1pKXJldHVybiBlW25dO3ZhciBvPWUub3duZXJEb2N1bWVudC5kb2N1bWVudEVsZW1lbnQ7cmV0dXJuKGUub3duZXJEb2N1bWVudC5zY3JvbGxpbmdFbGVtZW50fHxvKVtuXX1mdW5jdGlvbiBCZShlLHQpe3ZhciBuPSJ4Ij09PXQ/IkxlZnQiOiJUb3AiLGk9IkxlZnQiPT1uPyJSaWdodCI6IkJvdHRvbSI7cmV0dXJuIHBhcnNlRmxvYXQoZVsiYm9yZGVyIituKyJXaWR0aCJdLDEwKStwYXJzZUZsb2F0KGVbImJvcmRlciIraSsiV2lkdGgiXSwxMCl9ZnVuY3Rpb24gcWUoZSx0LG4saSl7cmV0dXJuIE1hdGgubWF4KHRbIm9mZnNldCIrZV0sdFsic2Nyb2xsIitlXSxuWyJjbGllbnQiK2VdLG5bIm9mZnNldCIrZV0sblsic2Nyb2xsIitlXSxSZSgxMCk/cGFyc2VJbnQoblsib2Zmc2V0IitlXSkrcGFyc2VJbnQoaVsibWFyZ2luIisoIkhlaWdodCI9PT1lPyJUb3AiOiJMZWZ0IildKStwYXJzZUludChpWyJtYXJnaW4iKygiSGVpZ2h0Ij09PWU/IkJvdHRvbSI6IlJpZ2h0IildKTowKX1mdW5jdGlvbiBLZShlKXt2YXIgdD1lLmJvZHksbj1lLmRvY3VtZW50RWxlbWVudCxpPVJlKDEwKSYmZ2V0Q29tcHV0ZWRTdHlsZShuKTtyZXR1cm57aGVpZ2h0OnFlKCJIZWlnaHQiLHQsbixpKSx3aWR0aDpxZSgiV2lkdGgiLHQsbixpKX19dmFyIFFlPWZ1bmN0aW9uKGUsdCxuKXtyZXR1cm4gdCYmVmUoZS5wcm90b3R5cGUsdCksbiYmVmUoZSxuKSxlfTtmdW5jdGlvbiBWZShlLHQpe2Zvcih2YXIgbj0wO248dC5sZW5ndGg7bisrKXt2YXIgaT10W25dO2kuZW51bWVyYWJsZT1pLmVudW1lcmFibGV8fCExLGkuY29uZmlndXJhYmxlPSEwLCJ2YWx1ZSJpbiBpJiYoaS53cml0YWJsZT0hMCksT2JqZWN0LmRlZmluZVByb3BlcnR5KGUsaS5rZXksaSl9fWZ1bmN0aW9uIFllKGUsdCxuKXtyZXR1cm4gdCBpbiBlP09iamVjdC5kZWZpbmVQcm9wZXJ0eShlLHQse3ZhbHVlOm4sZW51bWVyYWJsZTohMCxjb25maWd1cmFibGU6ITAsd3JpdGFibGU6ITB9KTplW3RdPW4sZX12YXIgemU9T2JqZWN0LmFzc2lnbnx8ZnVuY3Rpb24oZSl7Zm9yKHZhciB0PTE7dDxhcmd1bWVudHMubGVuZ3RoO3QrKyl7dmFyIG49YXJndW1lbnRzW3RdO2Zvcih2YXIgaSBpbiBuKU9iamVjdC5wcm90b3R5cGUuaGFzT3duUHJvcGVydHkuY2FsbChuLGkpJiYoZVtpXT1uW2ldKX1yZXR1cm4gZX07ZnVuY3Rpb24gWGUoZSl7cmV0dXJuIHplKHt9LGUse3JpZ2h0OmUubGVmdCtlLndpZHRoLGJvdHRvbTplLnRvcCtlLmhlaWdodH0pfWZ1bmN0aW9uIEdlKGUpe3ZhciB0PXt9O3RyeXtpZihSZSgxMCkpe3Q9ZS5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKTt2YXIgbj1VZShlLCJ0b3AiKSxpPVVlKGUsImxlZnQiKTt0LnRvcCs9bix0LmxlZnQrPWksdC5ib3R0b20rPW4sdC5yaWdodCs9aX1lbHNlIHQ9ZS5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKX1jYXRjaChlKXt9dmFyIG89e2xlZnQ6dC5sZWZ0LHRvcDp0LnRvcCx3aWR0aDp0LnJpZ2h0LXQubGVmdCxoZWlnaHQ6dC5ib3R0b20tdC50b3B9LHI9IkhUTUwiPT09ZS5ub2RlTmFtZT9LZShlLm93bmVyRG9jdW1lbnQpOnt9LHM9ci53aWR0aHx8ZS5jbGllbnRXaWR0aHx8by53aWR0aCxhPXIuaGVpZ2h0fHxlLmNsaWVudEhlaWdodHx8by5oZWlnaHQsbD1lLm9mZnNldFdpZHRoLXMsYz1lLm9mZnNldEhlaWdodC1hO2lmKGx8fGMpe3ZhciBoPWtlKGUpO2wtPUJlKGgsIngiKSxjLT1CZShoLCJ5Iiksby53aWR0aC09bCxvLmhlaWdodC09Y31yZXR1cm4gWGUobyl9ZnVuY3Rpb24gJGUoZSx0LG4pe3ZhciBpPTI8YXJndW1lbnRzLmxlbmd0aCYmdm9pZCAwIT09biYmbixvPVJlKDEwKSxyPSJIVE1MIj09PXQubm9kZU5hbWUscz1HZShlKSxhPUdlKHQpLGw9UGUoZSksYz1rZSh0KSxoPXBhcnNlRmxvYXQoYy5ib3JkZXJUb3BXaWR0aCwxMCksdT1wYXJzZUZsb2F0KGMuYm9yZGVyTGVmdFdpZHRoLDEwKTtpJiZyJiYoYS50b3A9TWF0aC5tYXgoYS50b3AsMCksYS5sZWZ0PU1hdGgubWF4KGEubGVmdCwwKSk7dmFyIGY9WGUoe3RvcDpzLnRvcC1hLnRvcC1oLGxlZnQ6cy5sZWZ0LWEubGVmdC11LHdpZHRoOnMud2lkdGgsaGVpZ2h0OnMuaGVpZ2h0fSk7aWYoZi5tYXJnaW5Ub3A9MCxmLm1hcmdpbkxlZnQ9MCwhbyYmcil7dmFyIGQ9cGFyc2VGbG9hdChjLm1hcmdpblRvcCwxMCkscD1wYXJzZUZsb2F0KGMubWFyZ2luTGVmdCwxMCk7Zi50b3AtPWgtZCxmLmJvdHRvbS09aC1kLGYubGVmdC09dS1wLGYucmlnaHQtPXUtcCxmLm1hcmdpblRvcD1kLGYubWFyZ2luTGVmdD1wfXJldHVybihvJiYhaT90LmNvbnRhaW5zKGwpOnQ9PT1sJiYiQk9EWSIhPT1sLm5vZGVOYW1lKSYmKGY9ZnVuY3Rpb24oZSx0LG4pe3ZhciBpPTI8YXJndW1lbnRzLmxlbmd0aCYmdm9pZCAwIT09biYmbixvPVVlKHQsInRvcCIpLHI9VWUodCwibGVmdCIpLHM9aT8tMToxO3JldHVybiBlLnRvcCs9bypzLGUuYm90dG9tKz1vKnMsZS5sZWZ0Kz1yKnMsZS5yaWdodCs9cipzLGV9KGYsdCkpLGZ9ZnVuY3Rpb24gSmUoZSl7aWYoIWV8fCFlLnBhcmVudEVsZW1lbnR8fFJlKCkpcmV0dXJuIGRvY3VtZW50LmRvY3VtZW50RWxlbWVudDtmb3IodmFyIHQ9ZS5wYXJlbnRFbGVtZW50O3QmJiJub25lIj09PWtlKHQsInRyYW5zZm9ybSIpOyl0PXQucGFyZW50RWxlbWVudDtyZXR1cm4gdHx8ZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50fWZ1bmN0aW9uIFplKGUsdCxuLGksbyl7dmFyIHI9NDxhcmd1bWVudHMubGVuZ3RoJiZ2b2lkIDAhPT1vJiZvLHM9e3RvcDowLGxlZnQ6MH0sYT1yP0plKGUpOldlKGUseGUodCkpO2lmKCJ2aWV3cG9ydCI9PT1pKXM9ZnVuY3Rpb24oZSx0KXt2YXIgbj0xPGFyZ3VtZW50cy5sZW5ndGgmJnZvaWQgMCE9PXQmJnQsaT1lLm93bmVyRG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LG89JGUoZSxpKSxyPU1hdGgubWF4KGkuY2xpZW50V2lkdGgsd2luZG93LmlubmVyV2lkdGh8fDApLHM9TWF0aC5tYXgoaS5jbGllbnRIZWlnaHQsd2luZG93LmlubmVySGVpZ2h0fHwwKSxhPW4/MDpVZShpKSxsPW4/MDpVZShpLCJsZWZ0Iik7cmV0dXJuIFhlKHt0b3A6YS1vLnRvcCtvLm1hcmdpblRvcCxsZWZ0Omwtby5sZWZ0K28ubWFyZ2luTGVmdCx3aWR0aDpyLGhlaWdodDpzfSl9KGEscik7ZWxzZXt2YXIgbD12b2lkIDA7InNjcm9sbFBhcmVudCI9PT1pPyJCT0RZIj09PShsPVBlKExlKHQpKSkubm9kZU5hbWUmJihsPWUub3duZXJEb2N1bWVudC5kb2N1bWVudEVsZW1lbnQpOmw9IndpbmRvdyI9PT1pP2Uub3duZXJEb2N1bWVudC5kb2N1bWVudEVsZW1lbnQ6aTt2YXIgYz0kZShsLGEscik7aWYoIkhUTUwiIT09bC5ub2RlTmFtZXx8ZnVuY3Rpb24gZSh0KXt2YXIgbj10Lm5vZGVOYW1lO2lmKCJCT0RZIj09PW58fCJIVE1MIj09PW4pcmV0dXJuITE7aWYoImZpeGVkIj09PWtlKHQsInBvc2l0aW9uIikpcmV0dXJuITA7dmFyIGk9TGUodCk7cmV0dXJuISFpJiZlKGkpfShhKSlzPWM7ZWxzZXt2YXIgaD1LZShlLm93bmVyRG9jdW1lbnQpLHU9aC5oZWlnaHQsZj1oLndpZHRoO3MudG9wKz1jLnRvcC1jLm1hcmdpblRvcCxzLmJvdHRvbT11K2MudG9wLHMubGVmdCs9Yy5sZWZ0LWMubWFyZ2luTGVmdCxzLnJpZ2h0PWYrYy5sZWZ0fX12YXIgZD0ibnVtYmVyIj09dHlwZW9mKG49bnx8MCk7cmV0dXJuIHMubGVmdCs9ZD9uOm4ubGVmdHx8MCxzLnRvcCs9ZD9uOm4udG9wfHwwLHMucmlnaHQtPWQ/bjpuLnJpZ2h0fHwwLHMuYm90dG9tLT1kP246bi5ib3R0b218fDAsc31mdW5jdGlvbiBldChlLHQsaSxuLG8scil7dmFyIHM9NTxhcmd1bWVudHMubGVuZ3RoJiZ2b2lkIDAhPT1yP3I6MDtpZigtMT09PWUuaW5kZXhPZigiYXV0byIpKXJldHVybiBlO3ZhciBhPVplKGksbixzLG8pLGw9e3RvcDp7d2lkdGg6YS53aWR0aCxoZWlnaHQ6dC50b3AtYS50b3B9LHJpZ2h0Ont3aWR0aDphLnJpZ2h0LXQucmlnaHQsaGVpZ2h0OmEuaGVpZ2h0fSxib3R0b206e3dpZHRoOmEud2lkdGgsaGVpZ2h0OmEuYm90dG9tLXQuYm90dG9tfSxsZWZ0Ont3aWR0aDp0LmxlZnQtYS5sZWZ0LGhlaWdodDphLmhlaWdodH19LGM9T2JqZWN0LmtleXMobCkubWFwKGZ1bmN0aW9uKGUpe3JldHVybiB6ZSh7a2V5OmV9LGxbZV0se2FyZWE6ZnVuY3Rpb24oZSl7cmV0dXJuIGUud2lkdGgqZS5oZWlnaHR9KGxbZV0pfSl9KS5zb3J0KGZ1bmN0aW9uKGUsdCl7cmV0dXJuIHQuYXJlYS1lLmFyZWF9KSxoPWMuZmlsdGVyKGZ1bmN0aW9uKGUpe3ZhciB0PWUud2lkdGgsbj1lLmhlaWdodDtyZXR1cm4gdD49aS5jbGllbnRXaWR0aCYmbj49aS5jbGllbnRIZWlnaHR9KSx1PTA8aC5sZW5ndGg/aFswXS5rZXk6Y1swXS5rZXksZj1lLnNwbGl0KCItIilbMV07cmV0dXJuIHUrKGY/Ii0iK2Y6IiIpfWZ1bmN0aW9uIHR0KGUsdCxuLGkpe3ZhciBvPTM8YXJndW1lbnRzLmxlbmd0aCYmdm9pZCAwIT09aT9pOm51bGw7cmV0dXJuICRlKG4sbz9KZSh0KTpXZSh0LHhlKG4pKSxvKX1mdW5jdGlvbiBudChlKXt2YXIgdD1lLm93bmVyRG9jdW1lbnQuZGVmYXVsdFZpZXcuZ2V0Q29tcHV0ZWRTdHlsZShlKSxuPXBhcnNlRmxvYXQodC5tYXJnaW5Ub3B8fDApK3BhcnNlRmxvYXQodC5tYXJnaW5Cb3R0b218fDApLGk9cGFyc2VGbG9hdCh0Lm1hcmdpbkxlZnR8fDApK3BhcnNlRmxvYXQodC5tYXJnaW5SaWdodHx8MCk7cmV0dXJue3dpZHRoOmUub2Zmc2V0V2lkdGgraSxoZWlnaHQ6ZS5vZmZzZXRIZWlnaHQrbn19ZnVuY3Rpb24gaXQoZSl7dmFyIHQ9e2xlZnQ6InJpZ2h0IixyaWdodDoibGVmdCIsYm90dG9tOiJ0b3AiLHRvcDoiYm90dG9tIn07cmV0dXJuIGUucmVwbGFjZSgvbGVmdHxyaWdodHxib3R0b218dG9wL2csZnVuY3Rpb24oZSl7cmV0dXJuIHRbZV19KX1mdW5jdGlvbiBvdChlLHQsbil7bj1uLnNwbGl0KCItIilbMF07dmFyIGk9bnQoZSksbz17d2lkdGg6aS53aWR0aCxoZWlnaHQ6aS5oZWlnaHR9LHI9LTEhPT1bInJpZ2h0IiwibGVmdCJdLmluZGV4T2Yobikscz1yPyJ0b3AiOiJsZWZ0IixhPXI/ImxlZnQiOiJ0b3AiLGw9cj8iaGVpZ2h0Ijoid2lkdGgiLGM9cj8id2lkdGgiOiJoZWlnaHQiO3JldHVybiBvW3NdPXRbc10rdFtsXS8yLWlbbF0vMixvW2FdPW49PT1hP3RbYV0taVtjXTp0W2l0KGEpXSxvfWZ1bmN0aW9uIHJ0KGUsdCl7cmV0dXJuIEFycmF5LnByb3RvdHlwZS5maW5kP2UuZmluZCh0KTplLmZpbHRlcih0KVswXX1mdW5jdGlvbiBzdChlLG4sdCl7cmV0dXJuKHZvaWQgMD09PXQ/ZTplLnNsaWNlKDAsZnVuY3Rpb24oZSx0LG4pe2lmKEFycmF5LnByb3RvdHlwZS5maW5kSW5kZXgpcmV0dXJuIGUuZmluZEluZGV4KGZ1bmN0aW9uKGUpe3JldHVybiBlW3RdPT09bn0pO3ZhciBpPXJ0KGUsZnVuY3Rpb24oZSl7cmV0dXJuIGVbdF09PT1ufSk7cmV0dXJuIGUuaW5kZXhPZihpKX0oZSwibmFtZSIsdCkpKS5mb3JFYWNoKGZ1bmN0aW9uKGUpe2UuZnVuY3Rpb24mJmNvbnNvbGUud2FybigiYG1vZGlmaWVyLmZ1bmN0aW9uYCBpcyBkZXByZWNhdGVkLCB1c2UgYG1vZGlmaWVyLmZuYCEiKTt2YXIgdD1lLmZ1bmN0aW9ufHxlLmZuO2UuZW5hYmxlZCYmTmUodCkmJihuLm9mZnNldHMucG9wcGVyPVhlKG4ub2Zmc2V0cy5wb3BwZXIpLG4ub2Zmc2V0cy5yZWZlcmVuY2U9WGUobi5vZmZzZXRzLnJlZmVyZW5jZSksbj10KG4sZSkpfSksbn1mdW5jdGlvbiBhdChlLG4pe3JldHVybiBlLnNvbWUoZnVuY3Rpb24oZSl7dmFyIHQ9ZS5uYW1lO3JldHVybiBlLmVuYWJsZWQmJnQ9PT1ufSl9ZnVuY3Rpb24gbHQoZSl7Zm9yKHZhciB0PVshMSwibXMiLCJXZWJraXQiLCJNb3oiLCJPIl0sbj1lLmNoYXJBdCgwKS50b1VwcGVyQ2FzZSgpK2Uuc2xpY2UoMSksaT0wO2k8dC5sZW5ndGg7aSsrKXt2YXIgbz10W2ldLHI9bz8iIitvK246ZTtpZigidW5kZWZpbmVkIiE9dHlwZW9mIGRvY3VtZW50LmJvZHkuc3R5bGVbcl0pcmV0dXJuIHJ9cmV0dXJuIG51bGx9ZnVuY3Rpb24gY3QoZSl7dmFyIHQ9ZS5vd25lckRvY3VtZW50O3JldHVybiB0P3QuZGVmYXVsdFZpZXc6d2luZG93fWZ1bmN0aW9uIGh0KGUsdCxuLGkpe24udXBkYXRlQm91bmQ9aSxjdChlKS5hZGRFdmVudExpc3RlbmVyKCJyZXNpemUiLG4udXBkYXRlQm91bmQse3Bhc3NpdmU6ITB9KTt2YXIgbz1QZShlKTtyZXR1cm4gZnVuY3Rpb24gZSh0LG4saSxvKXt2YXIgcj0iQk9EWSI9PT10Lm5vZGVOYW1lLHM9cj90Lm93bmVyRG9jdW1lbnQuZGVmYXVsdFZpZXc6dDtzLmFkZEV2ZW50TGlzdGVuZXIobixpLHtwYXNzaXZlOiEwfSkscnx8ZShQZShzLnBhcmVudE5vZGUpLG4saSxvKSxvLnB1c2gocyl9KG8sInNjcm9sbCIsbi51cGRhdGVCb3VuZCxuLnNjcm9sbFBhcmVudHMpLG4uc2Nyb2xsRWxlbWVudD1vLG4uZXZlbnRzRW5hYmxlZD0hMCxufWZ1bmN0aW9uIHV0KCl7dGhpcy5zdGF0ZS5ldmVudHNFbmFibGVkJiYoY2FuY2VsQW5pbWF0aW9uRnJhbWUodGhpcy5zY2hlZHVsZVVwZGF0ZSksdGhpcy5zdGF0ZT1mdW5jdGlvbihlLHQpe3JldHVybiBjdChlKS5yZW1vdmVFdmVudExpc3RlbmVyKCJyZXNpemUiLHQudXBkYXRlQm91bmQpLHQuc2Nyb2xsUGFyZW50cy5mb3JFYWNoKGZ1bmN0aW9uKGUpe2UucmVtb3ZlRXZlbnRMaXN0ZW5lcigic2Nyb2xsIix0LnVwZGF0ZUJvdW5kKX0pLHQudXBkYXRlQm91bmQ9bnVsbCx0LnNjcm9sbFBhcmVudHM9W10sdC5zY3JvbGxFbGVtZW50PW51bGwsdC5ldmVudHNFbmFibGVkPSExLHR9KHRoaXMucmVmZXJlbmNlLHRoaXMuc3RhdGUpKX1mdW5jdGlvbiBmdChlKXtyZXR1cm4iIiE9PWUmJiFpc05hTihwYXJzZUZsb2F0KGUpKSYmaXNGaW5pdGUoZSl9ZnVuY3Rpb24gZHQobixpKXtPYmplY3Qua2V5cyhpKS5mb3JFYWNoKGZ1bmN0aW9uKGUpe3ZhciB0PSIiOy0xIT09WyJ3aWR0aCIsImhlaWdodCIsInRvcCIsInJpZ2h0IiwiYm90dG9tIiwibGVmdCJdLmluZGV4T2YoZSkmJmZ0KGlbZV0pJiYodD0icHgiKSxuLnN0eWxlW2VdPWlbZV0rdH0pfWZ1bmN0aW9uIHB0KGUsdCl7ZnVuY3Rpb24gbihlKXtyZXR1cm4gZX12YXIgaT1lLm9mZnNldHMsbz1pLnBvcHBlcixyPWkucmVmZXJlbmNlLHM9TWF0aC5yb3VuZCxhPU1hdGguZmxvb3IsbD1zKHIud2lkdGgpLGM9cyhvLndpZHRoKSxoPS0xIT09WyJsZWZ0IiwicmlnaHQiXS5pbmRleE9mKGUucGxhY2VtZW50KSx1PS0xIT09ZS5wbGFjZW1lbnQuaW5kZXhPZigiLSIpLGY9dD9ofHx1fHxsJTI9PWMlMj9zOmE6bixkPXQ/czpuO3JldHVybntsZWZ0OmYobCUyPT0xJiZjJTI9PTEmJiF1JiZ0P28ubGVmdC0xOm8ubGVmdCksdG9wOmQoby50b3ApLGJvdHRvbTpkKG8uYm90dG9tKSxyaWdodDpmKG8ucmlnaHQpfX12YXIgbXQ9SWUmJi9GaXJlZm94L2kudGVzdChuYXZpZ2F0b3IudXNlckFnZW50KTtmdW5jdGlvbiBndChlLHQsbil7dmFyIGk9cnQoZSxmdW5jdGlvbihlKXtyZXR1cm4gZS5uYW1lPT09dH0pLG89ISFpJiZlLnNvbWUoZnVuY3Rpb24oZSl7cmV0dXJuIGUubmFtZT09PW4mJmUuZW5hYmxlZCYmZS5vcmRlcjxpLm9yZGVyfSk7aWYoIW8pe3ZhciByPSJgIit0KyJgIixzPSJgIituKyJgIjtjb25zb2xlLndhcm4ocysiIG1vZGlmaWVyIGlzIHJlcXVpcmVkIGJ5ICIrcisiIG1vZGlmaWVyIGluIG9yZGVyIHRvIHdvcmssIGJlIHN1cmUgdG8gaW5jbHVkZSBpdCBiZWZvcmUgIityKyIhIil9cmV0dXJuIG99dmFyIF90PVsiYXV0by1zdGFydCIsImF1dG8iLCJhdXRvLWVuZCIsInRvcC1zdGFydCIsInRvcCIsInRvcC1lbmQiLCJyaWdodC1zdGFydCIsInJpZ2h0IiwicmlnaHQtZW5kIiwiYm90dG9tLWVuZCIsImJvdHRvbSIsImJvdHRvbS1zdGFydCIsImxlZnQtZW5kIiwibGVmdCIsImxlZnQtc3RhcnQiXSx2dD1fdC5zbGljZSgzKTtmdW5jdGlvbiB5dChlLHQpe3ZhciBuPTE8YXJndW1lbnRzLmxlbmd0aCYmdm9pZCAwIT09dCYmdCxpPXZ0LmluZGV4T2YoZSksbz12dC5zbGljZShpKzEpLmNvbmNhdCh2dC5zbGljZSgwLGkpKTtyZXR1cm4gbj9vLnJldmVyc2UoKTpvfXZhciBFdD0iZmxpcCIsYnQ9ImNsb2Nrd2lzZSIsd3Q9ImNvdW50ZXJjbG9ja3dpc2UiO2Z1bmN0aW9uIFR0KGUsbyxyLHQpe3ZhciBzPVswLDBdLGE9LTEhPT1bInJpZ2h0IiwibGVmdCJdLmluZGV4T2YodCksbj1lLnNwbGl0KC8oXCt8XC0pLykubWFwKGZ1bmN0aW9uKGUpe3JldHVybiBlLnRyaW0oKX0pLGk9bi5pbmRleE9mKHJ0KG4sZnVuY3Rpb24oZSl7cmV0dXJuLTEhPT1lLnNlYXJjaCgvLHxccy8pfSkpO25baV0mJi0xPT09bltpXS5pbmRleE9mKCIsIikmJmNvbnNvbGUud2FybigiT2Zmc2V0cyBzZXBhcmF0ZWQgYnkgd2hpdGUgc3BhY2UocykgYXJlIGRlcHJlY2F0ZWQsIHVzZSBhIGNvbW1hICgsKSBpbnN0ZWFkLiIpO3ZhciBsPS9ccyosXHMqfFxzKy8sYz0tMSE9PWk/W24uc2xpY2UoMCxpKS5jb25jYXQoW25baV0uc3BsaXQobClbMF1dKSxbbltpXS5zcGxpdChsKVsxXV0uY29uY2F0KG4uc2xpY2UoaSsxKSldOltuXTtyZXR1cm4oYz1jLm1hcChmdW5jdGlvbihlLHQpe3ZhciBuPSgxPT09dD8hYTphKT8iaGVpZ2h0Ijoid2lkdGgiLGk9ITE7cmV0dXJuIGUucmVkdWNlKGZ1bmN0aW9uKGUsdCl7cmV0dXJuIiI9PT1lW2UubGVuZ3RoLTFdJiYtMSE9PVsiKyIsIi0iXS5pbmRleE9mKHQpPyhlW2UubGVuZ3RoLTFdPXQsaT0hMCxlKTppPyhlW2UubGVuZ3RoLTFdKz10LGk9ITEsZSk6ZS5jb25jYXQodCl9LFtdKS5tYXAoZnVuY3Rpb24oZSl7cmV0dXJuIGZ1bmN0aW9uKGUsdCxuLGkpe3ZhciBvPWUubWF0Y2goLygoPzpcLXxcKyk/XGQqXC4/XGQqKSguKikvKSxyPStvWzFdLHM9b1syXTtpZighcilyZXR1cm4gZTtpZigwIT09cy5pbmRleE9mKCIlIikpcmV0dXJuInZoIiE9PXMmJiJ2dyIhPT1zP3I6KCJ2aCI9PT1zP01hdGgubWF4KGRvY3VtZW50LmRvY3VtZW50RWxlbWVudC5jbGllbnRIZWlnaHQsd2luZG93LmlubmVySGVpZ2h0fHwwKTpNYXRoLm1heChkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQuY2xpZW50V2lkdGgsd2luZG93LmlubmVyV2lkdGh8fDApKS8xMDAqcjt2YXIgYT12b2lkIDA7c3dpdGNoKHMpe2Nhc2UiJXAiOmE9bjticmVhaztjYXNlIiUiOmNhc2UiJXIiOmRlZmF1bHQ6YT1pfXJldHVybiBYZShhKVt0XS8xMDAqcn0oZSxuLG8scil9KX0pKS5mb3JFYWNoKGZ1bmN0aW9uKG4saSl7bi5mb3JFYWNoKGZ1bmN0aW9uKGUsdCl7ZnQoZSkmJihzW2ldKz1lKigiLSI9PT1uW3QtMV0/LTE6MSkpfSl9KSxzfXZhciBDdD17cGxhY2VtZW50OiJib3R0b20iLHBvc2l0aW9uRml4ZWQ6ITEsZXZlbnRzRW5hYmxlZDohMCxyZW1vdmVPbkRlc3Ryb3k6ITEsb25DcmVhdGU6ZnVuY3Rpb24oKXt9LG9uVXBkYXRlOmZ1bmN0aW9uKCl7fSxtb2RpZmllcnM6e3NoaWZ0OntvcmRlcjoxMDAsZW5hYmxlZDohMCxmbjpmdW5jdGlvbihlKXt2YXIgdD1lLnBsYWNlbWVudCxuPXQuc3BsaXQoIi0iKVswXSxpPXQuc3BsaXQoIi0iKVsxXTtpZihpKXt2YXIgbz1lLm9mZnNldHMscj1vLnJlZmVyZW5jZSxzPW8ucG9wcGVyLGE9LTEhPT1bImJvdHRvbSIsInRvcCJdLmluZGV4T2YobiksbD1hPyJsZWZ0IjoidG9wIixjPWE/IndpZHRoIjoiaGVpZ2h0IixoPXtzdGFydDpZZSh7fSxsLHJbbF0pLGVuZDpZZSh7fSxsLHJbbF0rcltjXS1zW2NdKX07ZS5vZmZzZXRzLnBvcHBlcj16ZSh7fSxzLGhbaV0pfXJldHVybiBlfX0sb2Zmc2V0OntvcmRlcjoyMDAsZW5hYmxlZDohMCxmbjpmdW5jdGlvbihlLHQpe3ZhciBuPXQub2Zmc2V0LGk9ZS5wbGFjZW1lbnQsbz1lLm9mZnNldHMscj1vLnBvcHBlcixzPW8ucmVmZXJlbmNlLGE9aS5zcGxpdCgiLSIpWzBdLGw9dm9pZCAwO3JldHVybiBsPWZ0KCtuKT9bK24sMF06VHQobixyLHMsYSksImxlZnQiPT09YT8oci50b3ArPWxbMF0sci5sZWZ0LT1sWzFdKToicmlnaHQiPT09YT8oci50b3ArPWxbMF0sci5sZWZ0Kz1sWzFdKToidG9wIj09PWE/KHIubGVmdCs9bFswXSxyLnRvcC09bFsxXSk6ImJvdHRvbSI9PT1hJiYoci5sZWZ0Kz1sWzBdLHIudG9wKz1sWzFdKSxlLnBvcHBlcj1yLGV9LG9mZnNldDowfSxwcmV2ZW50T3ZlcmZsb3c6e29yZGVyOjMwMCxlbmFibGVkOiEwLGZuOmZ1bmN0aW9uKGUsaSl7dmFyIHQ9aS5ib3VuZGFyaWVzRWxlbWVudHx8RmUoZS5pbnN0YW5jZS5wb3BwZXIpO2UuaW5zdGFuY2UucmVmZXJlbmNlPT09dCYmKHQ9RmUodCkpO3ZhciBuPWx0KCJ0cmFuc2Zvcm0iKSxvPWUuaW5zdGFuY2UucG9wcGVyLnN0eWxlLHI9by50b3Ascz1vLmxlZnQsYT1vW25dO28udG9wPSIiLG8ubGVmdD0iIixvW25dPSIiO3ZhciBsPVplKGUuaW5zdGFuY2UucG9wcGVyLGUuaW5zdGFuY2UucmVmZXJlbmNlLGkucGFkZGluZyx0LGUucG9zaXRpb25GaXhlZCk7by50b3A9cixvLmxlZnQ9cyxvW25dPWEsaS5ib3VuZGFyaWVzPWw7dmFyIGM9aS5wcmlvcml0eSxoPWUub2Zmc2V0cy5wb3BwZXIsdT17cHJpbWFyeTpmdW5jdGlvbihlKXt2YXIgdD1oW2VdO3JldHVybiBoW2VdPGxbZV0mJiFpLmVzY2FwZVdpdGhSZWZlcmVuY2UmJih0PU1hdGgubWF4KGhbZV0sbFtlXSkpLFllKHt9LGUsdCl9LHNlY29uZGFyeTpmdW5jdGlvbihlKXt2YXIgdD0icmlnaHQiPT09ZT8ibGVmdCI6InRvcCIsbj1oW3RdO3JldHVybiBoW2VdPmxbZV0mJiFpLmVzY2FwZVdpdGhSZWZlcmVuY2UmJihuPU1hdGgubWluKGhbdF0sbFtlXS0oInJpZ2h0Ij09PWU/aC53aWR0aDpoLmhlaWdodCkpKSxZZSh7fSx0LG4pfX07cmV0dXJuIGMuZm9yRWFjaChmdW5jdGlvbihlKXt2YXIgdD0tMSE9PVsibGVmdCIsInRvcCJdLmluZGV4T2YoZSk/InByaW1hcnkiOiJzZWNvbmRhcnkiO2g9emUoe30saCx1W3RdKGUpKX0pLGUub2Zmc2V0cy5wb3BwZXI9aCxlfSxwcmlvcml0eTpbImxlZnQiLCJyaWdodCIsInRvcCIsImJvdHRvbSJdLHBhZGRpbmc6NSxib3VuZGFyaWVzRWxlbWVudDoic2Nyb2xsUGFyZW50In0sa2VlcFRvZ2V0aGVyOntvcmRlcjo0MDAsZW5hYmxlZDohMCxmbjpmdW5jdGlvbihlKXt2YXIgdD1lLm9mZnNldHMsbj10LnBvcHBlcixpPXQucmVmZXJlbmNlLG89ZS5wbGFjZW1lbnQuc3BsaXQoIi0iKVswXSxyPU1hdGguZmxvb3Iscz0tMSE9PVsidG9wIiwiYm90dG9tIl0uaW5kZXhPZihvKSxhPXM/InJpZ2h0IjoiYm90dG9tIixsPXM/ImxlZnQiOiJ0b3AiLGM9cz8id2lkdGgiOiJoZWlnaHQiO3JldHVybiBuW2FdPHIoaVtsXSkmJihlLm9mZnNldHMucG9wcGVyW2xdPXIoaVtsXSktbltjXSksbltsXT5yKGlbYV0pJiYoZS5vZmZzZXRzLnBvcHBlcltsXT1yKGlbYV0pKSxlfX0sYXJyb3c6e29yZGVyOjUwMCxlbmFibGVkOiEwLGZuOmZ1bmN0aW9uKGUsdCl7dmFyIG47aWYoIWd0KGUuaW5zdGFuY2UubW9kaWZpZXJzLCJhcnJvdyIsImtlZXBUb2dldGhlciIpKXJldHVybiBlO3ZhciBpPXQuZWxlbWVudDtpZigic3RyaW5nIj09dHlwZW9mIGkpe2lmKCEoaT1lLmluc3RhbmNlLnBvcHBlci5xdWVyeVNlbGVjdG9yKGkpKSlyZXR1cm4gZX1lbHNlIGlmKCFlLmluc3RhbmNlLnBvcHBlci5jb250YWlucyhpKSlyZXR1cm4gY29uc29sZS53YXJuKCJXQVJOSU5HOiBgYXJyb3cuZWxlbWVudGAgbXVzdCBiZSBjaGlsZCBvZiBpdHMgcG9wcGVyIGVsZW1lbnQhIiksZTt2YXIgbz1lLnBsYWNlbWVudC5zcGxpdCgiLSIpWzBdLHI9ZS5vZmZzZXRzLHM9ci5wb3BwZXIsYT1yLnJlZmVyZW5jZSxsPS0xIT09WyJsZWZ0IiwicmlnaHQiXS5pbmRleE9mKG8pLGM9bD8iaGVpZ2h0Ijoid2lkdGgiLGg9bD8iVG9wIjoiTGVmdCIsdT1oLnRvTG93ZXJDYXNlKCksZj1sPyJsZWZ0IjoidG9wIixkPWw/ImJvdHRvbSI6InJpZ2h0IixwPW50KGkpW2NdO2FbZF0tcDxzW3VdJiYoZS5vZmZzZXRzLnBvcHBlclt1XS09c1t1XS0oYVtkXS1wKSksYVt1XStwPnNbZF0mJihlLm9mZnNldHMucG9wcGVyW3VdKz1hW3VdK3Atc1tkXSksZS5vZmZzZXRzLnBvcHBlcj1YZShlLm9mZnNldHMucG9wcGVyKTt2YXIgbT1hW3VdK2FbY10vMi1wLzIsZz1rZShlLmluc3RhbmNlLnBvcHBlciksXz1wYXJzZUZsb2F0KGdbIm1hcmdpbiIraF0sMTApLHY9cGFyc2VGbG9hdChnWyJib3JkZXIiK2grIldpZHRoIl0sMTApLHk9bS1lLm9mZnNldHMucG9wcGVyW3VdLV8tdjtyZXR1cm4geT1NYXRoLm1heChNYXRoLm1pbihzW2NdLXAseSksMCksZS5hcnJvd0VsZW1lbnQ9aSxlLm9mZnNldHMuYXJyb3c9KFllKG49e30sdSxNYXRoLnJvdW5kKHkpKSxZZShuLGYsIiIpLG4pLGV9LGVsZW1lbnQ6Ilt4LWFycm93XSJ9LGZsaXA6e29yZGVyOjYwMCxlbmFibGVkOiEwLGZuOmZ1bmN0aW9uKG0sZyl7aWYoYXQobS5pbnN0YW5jZS5tb2RpZmllcnMsImlubmVyIikpcmV0dXJuIG07aWYobS5mbGlwcGVkJiZtLnBsYWNlbWVudD09PW0ub3JpZ2luYWxQbGFjZW1lbnQpcmV0dXJuIG07dmFyIF89WmUobS5pbnN0YW5jZS5wb3BwZXIsbS5pbnN0YW5jZS5yZWZlcmVuY2UsZy5wYWRkaW5nLGcuYm91bmRhcmllc0VsZW1lbnQsbS5wb3NpdGlvbkZpeGVkKSx2PW0ucGxhY2VtZW50LnNwbGl0KCItIilbMF0seT1pdCh2KSxFPW0ucGxhY2VtZW50LnNwbGl0KCItIilbMV18fCIiLGI9W107c3dpdGNoKGcuYmVoYXZpb3Ipe2Nhc2UgRXQ6Yj1bdix5XTticmVhaztjYXNlIGJ0OmI9eXQodik7YnJlYWs7Y2FzZSB3dDpiPXl0KHYsITApO2JyZWFrO2RlZmF1bHQ6Yj1nLmJlaGF2aW9yfXJldHVybiBiLmZvckVhY2goZnVuY3Rpb24oZSx0KXtpZih2IT09ZXx8Yi5sZW5ndGg9PT10KzEpcmV0dXJuIG07dj1tLnBsYWNlbWVudC5zcGxpdCgiLSIpWzBdLHk9aXQodik7dmFyIG49bS5vZmZzZXRzLnBvcHBlcixpPW0ub2Zmc2V0cy5yZWZlcmVuY2Usbz1NYXRoLmZsb29yLHI9ImxlZnQiPT09diYmbyhuLnJpZ2h0KT5vKGkubGVmdCl8fCJyaWdodCI9PT12JiZvKG4ubGVmdCk8byhpLnJpZ2h0KXx8InRvcCI9PT12JiZvKG4uYm90dG9tKT5vKGkudG9wKXx8ImJvdHRvbSI9PT12JiZvKG4udG9wKTxvKGkuYm90dG9tKSxzPW8obi5sZWZ0KTxvKF8ubGVmdCksYT1vKG4ucmlnaHQpPm8oXy5yaWdodCksbD1vKG4udG9wKTxvKF8udG9wKSxjPW8obi5ib3R0b20pPm8oXy5ib3R0b20pLGg9ImxlZnQiPT09diYmc3x8InJpZ2h0Ij09PXYmJmF8fCJ0b3AiPT09diYmbHx8ImJvdHRvbSI9PT12JiZjLHU9LTEhPT1bInRvcCIsImJvdHRvbSJdLmluZGV4T2YodiksZj0hIWcuZmxpcFZhcmlhdGlvbnMmJih1JiYic3RhcnQiPT09RSYmc3x8dSYmImVuZCI9PT1FJiZhfHwhdSYmInN0YXJ0Ij09PUUmJmx8fCF1JiYiZW5kIj09PUUmJmMpLGQ9ISFnLmZsaXBWYXJpYXRpb25zQnlDb250ZW50JiYodSYmInN0YXJ0Ij09PUUmJmF8fHUmJiJlbmQiPT09RSYmc3x8IXUmJiJzdGFydCI9PT1FJiZjfHwhdSYmImVuZCI9PT1FJiZsKSxwPWZ8fGQ7KHJ8fGh8fHApJiYobS5mbGlwcGVkPSEwLChyfHxoKSYmKHY9Ylt0KzFdKSxwJiYoRT1mdW5jdGlvbihlKXtyZXR1cm4iZW5kIj09PWU/InN0YXJ0Ijoic3RhcnQiPT09ZT8iZW5kIjplfShFKSksbS5wbGFjZW1lbnQ9disoRT8iLSIrRToiIiksbS5vZmZzZXRzLnBvcHBlcj16ZSh7fSxtLm9mZnNldHMucG9wcGVyLG90KG0uaW5zdGFuY2UucG9wcGVyLG0ub2Zmc2V0cy5yZWZlcmVuY2UsbS5wbGFjZW1lbnQpKSxtPXN0KG0uaW5zdGFuY2UubW9kaWZpZXJzLG0sImZsaXAiKSl9KSxtfSxiZWhhdmlvcjoiZmxpcCIscGFkZGluZzo1LGJvdW5kYXJpZXNFbGVtZW50OiJ2aWV3cG9ydCIsZmxpcFZhcmlhdGlvbnM6ITEsZmxpcFZhcmlhdGlvbnNCeUNvbnRlbnQ6ITF9LGlubmVyOntvcmRlcjo3MDAsZW5hYmxlZDohMSxmbjpmdW5jdGlvbihlKXt2YXIgdD1lLnBsYWNlbWVudCxuPXQuc3BsaXQoIi0iKVswXSxpPWUub2Zmc2V0cyxvPWkucG9wcGVyLHI9aS5yZWZlcmVuY2Uscz0tMSE9PVsibGVmdCIsInJpZ2h0Il0uaW5kZXhPZihuKSxhPS0xPT09WyJ0b3AiLCJsZWZ0Il0uaW5kZXhPZihuKTtyZXR1cm4gb1tzPyJsZWZ0IjoidG9wIl09cltuXS0oYT9vW3M/IndpZHRoIjoiaGVpZ2h0Il06MCksZS5wbGFjZW1lbnQ9aXQodCksZS5vZmZzZXRzLnBvcHBlcj1YZShvKSxlfX0saGlkZTp7b3JkZXI6ODAwLGVuYWJsZWQ6ITAsZm46ZnVuY3Rpb24oZSl7aWYoIWd0KGUuaW5zdGFuY2UubW9kaWZpZXJzLCJoaWRlIiwicHJldmVudE92ZXJmbG93IikpcmV0dXJuIGU7dmFyIHQ9ZS5vZmZzZXRzLnJlZmVyZW5jZSxuPXJ0KGUuaW5zdGFuY2UubW9kaWZpZXJzLGZ1bmN0aW9uKGUpe3JldHVybiJwcmV2ZW50T3ZlcmZsb3ciPT09ZS5uYW1lfSkuYm91bmRhcmllcztpZih0LmJvdHRvbTxuLnRvcHx8dC5sZWZ0Pm4ucmlnaHR8fHQudG9wPm4uYm90dG9tfHx0LnJpZ2h0PG4ubGVmdCl7aWYoITA9PT1lLmhpZGUpcmV0dXJuIGU7ZS5oaWRlPSEwLGUuYXR0cmlidXRlc1sieC1vdXQtb2YtYm91bmRhcmllcyJdPSIifWVsc2V7aWYoITE9PT1lLmhpZGUpcmV0dXJuIGU7ZS5oaWRlPSExLGUuYXR0cmlidXRlc1sieC1vdXQtb2YtYm91bmRhcmllcyJdPSExfXJldHVybiBlfX0sY29tcHV0ZVN0eWxlOntvcmRlcjo4NTAsZW5hYmxlZDohMCxmbjpmdW5jdGlvbihlLHQpe3ZhciBuPXQueCxpPXQueSxvPWUub2Zmc2V0cy5wb3BwZXIscj1ydChlLmluc3RhbmNlLm1vZGlmaWVycyxmdW5jdGlvbihlKXtyZXR1cm4iYXBwbHlTdHlsZSI9PT1lLm5hbWV9KS5ncHVBY2NlbGVyYXRpb247dm9pZCAwIT09ciYmY29uc29sZS53YXJuKCJXQVJOSU5HOiBgZ3B1QWNjZWxlcmF0aW9uYCBvcHRpb24gbW92ZWQgdG8gYGNvbXB1dGVTdHlsZWAgbW9kaWZpZXIgYW5kIHdpbGwgbm90IGJlIHN1cHBvcnRlZCBpbiBmdXR1cmUgdmVyc2lvbnMgb2YgUG9wcGVyLmpzISIpO3ZhciBzPXZvaWQgMCE9PXI/cjp0LmdwdUFjY2VsZXJhdGlvbixhPUZlKGUuaW5zdGFuY2UucG9wcGVyKSxsPUdlKGEpLGM9e3Bvc2l0aW9uOm8ucG9zaXRpb259LGg9cHQoZSx3aW5kb3cuZGV2aWNlUGl4ZWxSYXRpbzwyfHwhbXQpLHU9ImJvdHRvbSI9PT1uPyJ0b3AiOiJib3R0b20iLGY9InJpZ2h0Ij09PWk/ImxlZnQiOiJyaWdodCIsZD1sdCgidHJhbnNmb3JtIikscD12b2lkIDAsbT12b2lkIDA7aWYobT0iYm90dG9tIj09dT8iSFRNTCI9PT1hLm5vZGVOYW1lPy1hLmNsaWVudEhlaWdodCtoLmJvdHRvbTotbC5oZWlnaHQraC5ib3R0b206aC50b3AscD0icmlnaHQiPT1mPyJIVE1MIj09PWEubm9kZU5hbWU/LWEuY2xpZW50V2lkdGgraC5yaWdodDotbC53aWR0aCtoLnJpZ2h0OmgubGVmdCxzJiZkKWNbZF09InRyYW5zbGF0ZTNkKCIrcCsicHgsICIrbSsicHgsIDApIixjW3VdPTAsY1tmXT0wLGMud2lsbENoYW5nZT0idHJhbnNmb3JtIjtlbHNle3ZhciBnPSJib3R0b20iPT11Py0xOjEsXz0icmlnaHQiPT1mPy0xOjE7Y1t1XT1tKmcsY1tmXT1wKl8sYy53aWxsQ2hhbmdlPXUrIiwgIitmfXZhciB2PXsieC1wbGFjZW1lbnQiOmUucGxhY2VtZW50fTtyZXR1cm4gZS5hdHRyaWJ1dGVzPXplKHt9LHYsZS5hdHRyaWJ1dGVzKSxlLnN0eWxlcz16ZSh7fSxjLGUuc3R5bGVzKSxlLmFycm93U3R5bGVzPXplKHt9LGUub2Zmc2V0cy5hcnJvdyxlLmFycm93U3R5bGVzKSxlfSxncHVBY2NlbGVyYXRpb246ITAseDoiYm90dG9tIix5OiJyaWdodCJ9LGFwcGx5U3R5bGU6e29yZGVyOjkwMCxlbmFibGVkOiEwLGZuOmZ1bmN0aW9uKGUpe3JldHVybiBkdChlLmluc3RhbmNlLnBvcHBlcixlLnN0eWxlcyksZnVuY3Rpb24odCxuKXtPYmplY3Qua2V5cyhuKS5mb3JFYWNoKGZ1bmN0aW9uKGUpeyExIT09bltlXT90LnNldEF0dHJpYnV0ZShlLG5bZV0pOnQucmVtb3ZlQXR0cmlidXRlKGUpfSl9KGUuaW5zdGFuY2UucG9wcGVyLGUuYXR0cmlidXRlcyksZS5hcnJvd0VsZW1lbnQmJk9iamVjdC5rZXlzKGUuYXJyb3dTdHlsZXMpLmxlbmd0aCYmZHQoZS5hcnJvd0VsZW1lbnQsZS5hcnJvd1N0eWxlcyksZX0sb25Mb2FkOmZ1bmN0aW9uKGUsdCxuLGksbyl7dmFyIHI9dHQobyx0LGUsbi5wb3NpdGlvbkZpeGVkKSxzPWV0KG4ucGxhY2VtZW50LHIsdCxlLG4ubW9kaWZpZXJzLmZsaXAuYm91bmRhcmllc0VsZW1lbnQsbi5tb2RpZmllcnMuZmxpcC5wYWRkaW5nKTtyZXR1cm4gdC5zZXRBdHRyaWJ1dGUoIngtcGxhY2VtZW50IixzKSxkdCh0LHtwb3NpdGlvbjpuLnBvc2l0aW9uRml4ZWQ/ImZpeGVkIjoiYWJzb2x1dGUifSksbn0sZ3B1QWNjZWxlcmF0aW9uOnZvaWQgMH19fSxTdD0oUWUoRHQsW3trZXk6InVwZGF0ZSIsdmFsdWU6ZnVuY3Rpb24oKXtyZXR1cm4gZnVuY3Rpb24oKXtpZighdGhpcy5zdGF0ZS5pc0Rlc3Ryb3llZCl7dmFyIGU9e2luc3RhbmNlOnRoaXMsc3R5bGVzOnt9LGFycm93U3R5bGVzOnt9LGF0dHJpYnV0ZXM6e30sZmxpcHBlZDohMSxvZmZzZXRzOnt9fTtlLm9mZnNldHMucmVmZXJlbmNlPXR0KHRoaXMuc3RhdGUsdGhpcy5wb3BwZXIsdGhpcy5yZWZlcmVuY2UsdGhpcy5vcHRpb25zLnBvc2l0aW9uRml4ZWQpLGUucGxhY2VtZW50PWV0KHRoaXMub3B0aW9ucy5wbGFjZW1lbnQsZS5vZmZzZXRzLnJlZmVyZW5jZSx0aGlzLnBvcHBlcix0aGlzLnJlZmVyZW5jZSx0aGlzLm9wdGlvbnMubW9kaWZpZXJzLmZsaXAuYm91bmRhcmllc0VsZW1lbnQsdGhpcy5vcHRpb25zLm1vZGlmaWVycy5mbGlwLnBhZGRpbmcpLGUub3JpZ2luYWxQbGFjZW1lbnQ9ZS5wbGFjZW1lbnQsZS5wb3NpdGlvbkZpeGVkPXRoaXMub3B0aW9ucy5wb3NpdGlvbkZpeGVkLGUub2Zmc2V0cy5wb3BwZXI9b3QodGhpcy5wb3BwZXIsZS5vZmZzZXRzLnJlZmVyZW5jZSxlLnBsYWNlbWVudCksZS5vZmZzZXRzLnBvcHBlci5wb3NpdGlvbj10aGlzLm9wdGlvbnMucG9zaXRpb25GaXhlZD8iZml4ZWQiOiJhYnNvbHV0ZSIsZT1zdCh0aGlzLm1vZGlmaWVycyxlKSx0aGlzLnN0YXRlLmlzQ3JlYXRlZD90aGlzLm9wdGlvbnMub25VcGRhdGUoZSk6KHRoaXMuc3RhdGUuaXNDcmVhdGVkPSEwLHRoaXMub3B0aW9ucy5vbkNyZWF0ZShlKSl9fS5jYWxsKHRoaXMpfX0se2tleToiZGVzdHJveSIsdmFsdWU6ZnVuY3Rpb24oKXtyZXR1cm4gZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5zdGF0ZS5pc0Rlc3Ryb3llZD0hMCxhdCh0aGlzLm1vZGlmaWVycywiYXBwbHlTdHlsZSIpJiYodGhpcy5wb3BwZXIucmVtb3ZlQXR0cmlidXRlKCJ4LXBsYWNlbWVudCIpLHRoaXMucG9wcGVyLnN0eWxlLnBvc2l0aW9uPSIiLHRoaXMucG9wcGVyLnN0eWxlLnRvcD0iIix0aGlzLnBvcHBlci5zdHlsZS5sZWZ0PSIiLHRoaXMucG9wcGVyLnN0eWxlLnJpZ2h0PSIiLHRoaXMucG9wcGVyLnN0eWxlLmJvdHRvbT0iIix0aGlzLnBvcHBlci5zdHlsZS53aWxsQ2hhbmdlPSIiLHRoaXMucG9wcGVyLnN0eWxlW2x0KCJ0cmFuc2Zvcm0iKV09IiIpLHRoaXMuZGlzYWJsZUV2ZW50TGlzdGVuZXJzKCksdGhpcy5vcHRpb25zLnJlbW92ZU9uRGVzdHJveSYmdGhpcy5wb3BwZXIucGFyZW50Tm9kZS5yZW1vdmVDaGlsZCh0aGlzLnBvcHBlciksdGhpc30uY2FsbCh0aGlzKX19LHtrZXk6ImVuYWJsZUV2ZW50TGlzdGVuZXJzIix2YWx1ZTpmdW5jdGlvbigpe3JldHVybiBmdW5jdGlvbigpe3RoaXMuc3RhdGUuZXZlbnRzRW5hYmxlZHx8KHRoaXMuc3RhdGU9aHQodGhpcy5yZWZlcmVuY2UsdGhpcy5vcHRpb25zLHRoaXMuc3RhdGUsdGhpcy5zY2hlZHVsZVVwZGF0ZSkpfS5jYWxsKHRoaXMpfX0se2tleToiZGlzYWJsZUV2ZW50TGlzdGVuZXJzIix2YWx1ZTpmdW5jdGlvbigpe3JldHVybiB1dC5jYWxsKHRoaXMpfX1dKSxEdCk7ZnVuY3Rpb24gRHQoZSx0KXt2YXIgbj10aGlzLGk9Mjxhcmd1bWVudHMubGVuZ3RoJiZ2b2lkIDAhPT1hcmd1bWVudHNbMl0/YXJndW1lbnRzWzJdOnt9OyFmdW5jdGlvbihlLHQpe2lmKCEoZSBpbnN0YW5jZW9mIHQpKXRocm93IG5ldyBUeXBlRXJyb3IoIkNhbm5vdCBjYWxsIGEgY2xhc3MgYXMgYSBmdW5jdGlvbiIpfSh0aGlzLER0KSx0aGlzLnNjaGVkdWxlVXBkYXRlPWZ1bmN0aW9uKCl7cmV0dXJuIHJlcXVlc3RBbmltYXRpb25GcmFtZShuLnVwZGF0ZSl9LHRoaXMudXBkYXRlPU9lKHRoaXMudXBkYXRlLmJpbmQodGhpcykpLHRoaXMub3B0aW9ucz16ZSh7fSxEdC5EZWZhdWx0cyxpKSx0aGlzLnN0YXRlPXtpc0Rlc3Ryb3llZDohMSxpc0NyZWF0ZWQ6ITEsc2Nyb2xsUGFyZW50czpbXX0sdGhpcy5yZWZlcmVuY2U9ZSYmZS5qcXVlcnk/ZVswXTplLHRoaXMucG9wcGVyPXQmJnQuanF1ZXJ5P3RbMF06dCx0aGlzLm9wdGlvbnMubW9kaWZpZXJzPXt9LE9iamVjdC5rZXlzKHplKHt9LER0LkRlZmF1bHRzLm1vZGlmaWVycyxpLm1vZGlmaWVycykpLmZvckVhY2goZnVuY3Rpb24oZSl7bi5vcHRpb25zLm1vZGlmaWVyc1tlXT16ZSh7fSxEdC5EZWZhdWx0cy5tb2RpZmllcnNbZV18fHt9LGkubW9kaWZpZXJzP2kubW9kaWZpZXJzW2VdOnt9KX0pLHRoaXMubW9kaWZpZXJzPU9iamVjdC5rZXlzKHRoaXMub3B0aW9ucy5tb2RpZmllcnMpLm1hcChmdW5jdGlvbihlKXtyZXR1cm4gemUoe25hbWU6ZX0sbi5vcHRpb25zLm1vZGlmaWVyc1tlXSl9KS5zb3J0KGZ1bmN0aW9uKGUsdCl7cmV0dXJuIGUub3JkZXItdC5vcmRlcn0pLHRoaXMubW9kaWZpZXJzLmZvckVhY2goZnVuY3Rpb24oZSl7ZS5lbmFibGVkJiZOZShlLm9uTG9hZCkmJmUub25Mb2FkKG4ucmVmZXJlbmNlLG4ucG9wcGVyLG4ub3B0aW9ucyxlLG4uc3RhdGUpfSksdGhpcy51cGRhdGUoKTt2YXIgbz10aGlzLm9wdGlvbnMuZXZlbnRzRW5hYmxlZDtvJiZ0aGlzLmVuYWJsZUV2ZW50TGlzdGVuZXJzKCksdGhpcy5zdGF0ZS5ldmVudHNFbmFibGVkPW99U3QuVXRpbHM9KCJ1bmRlZmluZWQiIT10eXBlb2Ygd2luZG93P3dpbmRvdzpnbG9iYWwpLlBvcHBlclV0aWxzLFN0LnBsYWNlbWVudHM9X3QsU3QuRGVmYXVsdHM9Q3Q7dmFyIEl0PSJkcm9wZG93biIsQXQ9ImJzLmRyb3Bkb3duIixPdD0iLiIrQXQsTnQ9Ii5kYXRhLWFwaSIsa3Q9cC5mbltJdF0sTHQ9bmV3IFJlZ0V4cCgiMzh8NDB8MjciKSxQdD17SElERToiaGlkZSIrT3QsSElEREVOOiJoaWRkZW4iK090LFNIT1c6InNob3ciK090LFNIT1dOOiJzaG93biIrT3QsQ0xJQ0s6ImNsaWNrIitPdCxDTElDS19EQVRBX0FQSToiY2xpY2siK090K050LEtFWURPV05fREFUQV9BUEk6ImtleWRvd24iK090K050LEtFWVVQX0RBVEFfQVBJOiJrZXl1cCIrT3QrTnR9LHh0PSJkaXNhYmxlZCIsanQ9InNob3ciLEh0PSJkcm9wdXAiLFJ0PSJkcm9wcmlnaHQiLEZ0PSJkcm9wbGVmdCIsTXQ9ImRyb3Bkb3duLW1lbnUtcmlnaHQiLFd0PSJwb3NpdGlvbi1zdGF0aWMiLFV0PSdbZGF0YS10b2dnbGU9ImRyb3Bkb3duIl0nLEJ0PSIuZHJvcGRvd24gZm9ybSIscXQ9Ii5kcm9wZG93bi1tZW51IixLdD0iLm5hdmJhci1uYXYiLFF0PSIuZHJvcGRvd24tbWVudSAuZHJvcGRvd24taXRlbTpub3QoLmRpc2FibGVkKTpub3QoOmRpc2FibGVkKSIsVnQ9InRvcC1zdGFydCIsWXQ9InRvcC1lbmQiLHp0PSJib3R0b20tc3RhcnQiLFh0PSJib3R0b20tZW5kIixHdD0icmlnaHQtc3RhcnQiLCR0PSJsZWZ0LXN0YXJ0IixKdD17b2Zmc2V0OjAsZmxpcDohMCxib3VuZGFyeToic2Nyb2xsUGFyZW50IixyZWZlcmVuY2U6InRvZ2dsZSIsZGlzcGxheToiZHluYW1pYyIscG9wcGVyQ29uZmlnOm51bGx9LFp0PXtvZmZzZXQ6IihudW1iZXJ8c3RyaW5nfGZ1bmN0aW9uKSIsZmxpcDoiYm9vbGVhbiIsYm91bmRhcnk6IihzdHJpbmd8ZWxlbWVudCkiLHJlZmVyZW5jZToiKHN0cmluZ3xlbGVtZW50KSIsZGlzcGxheToic3RyaW5nIixwb3BwZXJDb25maWc6IihudWxsfG9iamVjdCkifSxlbj1mdW5jdGlvbigpe2Z1bmN0aW9uIGMoZSx0KXt0aGlzLl9lbGVtZW50PWUsdGhpcy5fcG9wcGVyPW51bGwsdGhpcy5fY29uZmlnPXRoaXMuX2dldENvbmZpZyh0KSx0aGlzLl9tZW51PXRoaXMuX2dldE1lbnVFbGVtZW50KCksdGhpcy5faW5OYXZiYXI9dGhpcy5fZGV0ZWN0TmF2YmFyKCksdGhpcy5fYWRkRXZlbnRMaXN0ZW5lcnMoKX12YXIgZT1jLnByb3RvdHlwZTtyZXR1cm4gZS50b2dnbGU9ZnVuY3Rpb24oKXtpZighdGhpcy5fZWxlbWVudC5kaXNhYmxlZCYmIXAodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoeHQpKXt2YXIgZT1wKHRoaXMuX21lbnUpLmhhc0NsYXNzKGp0KTtjLl9jbGVhck1lbnVzKCksZXx8dGhpcy5zaG93KCEwKX19LGUuc2hvdz1mdW5jdGlvbihlKXtpZih2b2lkIDA9PT1lJiYoZT0hMSksISh0aGlzLl9lbGVtZW50LmRpc2FibGVkfHxwKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKHh0KXx8cCh0aGlzLl9tZW51KS5oYXNDbGFzcyhqdCkpKXt2YXIgdD17cmVsYXRlZFRhcmdldDp0aGlzLl9lbGVtZW50fSxuPXAuRXZlbnQoUHQuU0hPVyx0KSxpPWMuX2dldFBhcmVudEZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO2lmKHAoaSkudHJpZ2dlcihuKSwhbi5pc0RlZmF1bHRQcmV2ZW50ZWQoKSl7aWYoIXRoaXMuX2luTmF2YmFyJiZlKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIFN0KXRocm93IG5ldyBUeXBlRXJyb3IoIkJvb3RzdHJhcCdzIGRyb3Bkb3ducyByZXF1aXJlIFBvcHBlci5qcyAoaHR0cHM6Ly9wb3BwZXIuanMub3JnLykiKTt2YXIgbz10aGlzLl9lbGVtZW50OyJwYXJlbnQiPT09dGhpcy5fY29uZmlnLnJlZmVyZW5jZT9vPWk6bS5pc0VsZW1lbnQodGhpcy5fY29uZmlnLnJlZmVyZW5jZSkmJihvPXRoaXMuX2NvbmZpZy5yZWZlcmVuY2UsInVuZGVmaW5lZCIhPXR5cGVvZiB0aGlzLl9jb25maWcucmVmZXJlbmNlLmpxdWVyeSYmKG89dGhpcy5fY29uZmlnLnJlZmVyZW5jZVswXSkpLCJzY3JvbGxQYXJlbnQiIT09dGhpcy5fY29uZmlnLmJvdW5kYXJ5JiZwKGkpLmFkZENsYXNzKFd0KSx0aGlzLl9wb3BwZXI9bmV3IFN0KG8sdGhpcy5fbWVudSx0aGlzLl9nZXRQb3BwZXJDb25maWcoKSl9Im9udG91Y2hzdGFydCJpbiBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQmJjA9PT1wKGkpLmNsb3Nlc3QoS3QpLmxlbmd0aCYmcChkb2N1bWVudC5ib2R5KS5jaGlsZHJlbigpLm9uKCJtb3VzZW92ZXIiLG51bGwscC5ub29wKSx0aGlzLl9lbGVtZW50LmZvY3VzKCksdGhpcy5fZWxlbWVudC5zZXRBdHRyaWJ1dGUoImFyaWEtZXhwYW5kZWQiLCEwKSxwKHRoaXMuX21lbnUpLnRvZ2dsZUNsYXNzKGp0KSxwKGkpLnRvZ2dsZUNsYXNzKGp0KS50cmlnZ2VyKHAuRXZlbnQoUHQuU0hPV04sdCkpfX19LGUuaGlkZT1mdW5jdGlvbigpe2lmKCF0aGlzLl9lbGVtZW50LmRpc2FibGVkJiYhcCh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyh4dCkmJnAodGhpcy5fbWVudSkuaGFzQ2xhc3MoanQpKXt2YXIgZT17cmVsYXRlZFRhcmdldDp0aGlzLl9lbGVtZW50fSx0PXAuRXZlbnQoUHQuSElERSxlKSxuPWMuX2dldFBhcmVudEZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO3AobikudHJpZ2dlcih0KSx0LmlzRGVmYXVsdFByZXZlbnRlZCgpfHwodGhpcy5fcG9wcGVyJiZ0aGlzLl9wb3BwZXIuZGVzdHJveSgpLHAodGhpcy5fbWVudSkudG9nZ2xlQ2xhc3MoanQpLHAobikudG9nZ2xlQ2xhc3MoanQpLnRyaWdnZXIocC5FdmVudChQdC5ISURERU4sZSkpKX19LGUuZGlzcG9zZT1mdW5jdGlvbigpe3AucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LEF0KSxwKHRoaXMuX2VsZW1lbnQpLm9mZihPdCksdGhpcy5fZWxlbWVudD1udWxsLCh0aGlzLl9tZW51PW51bGwpIT09dGhpcy5fcG9wcGVyJiYodGhpcy5fcG9wcGVyLmRlc3Ryb3koKSx0aGlzLl9wb3BwZXI9bnVsbCl9LGUudXBkYXRlPWZ1bmN0aW9uKCl7dGhpcy5faW5OYXZiYXI9dGhpcy5fZGV0ZWN0TmF2YmFyKCksbnVsbCE9PXRoaXMuX3BvcHBlciYmdGhpcy5fcG9wcGVyLnNjaGVkdWxlVXBkYXRlKCl9LGUuX2FkZEV2ZW50TGlzdGVuZXJzPWZ1bmN0aW9uKCl7dmFyIHQ9dGhpcztwKHRoaXMuX2VsZW1lbnQpLm9uKFB0LkNMSUNLLGZ1bmN0aW9uKGUpe2UucHJldmVudERlZmF1bHQoKSxlLnN0b3BQcm9wYWdhdGlvbigpLHQudG9nZ2xlKCl9KX0sZS5fZ2V0Q29uZmlnPWZ1bmN0aW9uKGUpe3JldHVybiBlPWwoe30sdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0LHt9LHAodGhpcy5fZWxlbWVudCkuZGF0YSgpLHt9LGUpLG0udHlwZUNoZWNrQ29uZmlnKEl0LGUsdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0VHlwZSksZX0sZS5fZ2V0TWVudUVsZW1lbnQ9ZnVuY3Rpb24oKXtpZighdGhpcy5fbWVudSl7dmFyIGU9Yy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7ZSYmKHRoaXMuX21lbnU9ZS5xdWVyeVNlbGVjdG9yKHF0KSl9cmV0dXJuIHRoaXMuX21lbnV9LGUuX2dldFBsYWNlbWVudD1mdW5jdGlvbigpe3ZhciBlPXAodGhpcy5fZWxlbWVudC5wYXJlbnROb2RlKSx0PXp0O3JldHVybiBlLmhhc0NsYXNzKEh0KT8odD1WdCxwKHRoaXMuX21lbnUpLmhhc0NsYXNzKE10KSYmKHQ9WXQpKTplLmhhc0NsYXNzKFJ0KT90PUd0OmUuaGFzQ2xhc3MoRnQpP3Q9JHQ6cCh0aGlzLl9tZW51KS5oYXNDbGFzcyhNdCkmJih0PVh0KSx0fSxlLl9kZXRlY3ROYXZiYXI9ZnVuY3Rpb24oKXtyZXR1cm4gMDxwKHRoaXMuX2VsZW1lbnQpLmNsb3Nlc3QoIi5uYXZiYXIiKS5sZW5ndGh9LGUuX2dldE9mZnNldD1mdW5jdGlvbigpe3ZhciB0PXRoaXMsZT17fTtyZXR1cm4iZnVuY3Rpb24iPT10eXBlb2YgdGhpcy5fY29uZmlnLm9mZnNldD9lLmZuPWZ1bmN0aW9uKGUpe3JldHVybiBlLm9mZnNldHM9bCh7fSxlLm9mZnNldHMse30sdC5fY29uZmlnLm9mZnNldChlLm9mZnNldHMsdC5fZWxlbWVudCl8fHt9KSxlfTplLm9mZnNldD10aGlzLl9jb25maWcub2Zmc2V0LGV9LGUuX2dldFBvcHBlckNvbmZpZz1mdW5jdGlvbigpe3ZhciBlPXtwbGFjZW1lbnQ6dGhpcy5fZ2V0UGxhY2VtZW50KCksbW9kaWZpZXJzOntvZmZzZXQ6dGhpcy5fZ2V0T2Zmc2V0KCksZmxpcDp7ZW5hYmxlZDp0aGlzLl9jb25maWcuZmxpcH0scHJldmVudE92ZXJmbG93Ontib3VuZGFyaWVzRWxlbWVudDp0aGlzLl9jb25maWcuYm91bmRhcnl9fX07cmV0dXJuInN0YXRpYyI9PT10aGlzLl9jb25maWcuZGlzcGxheSYmKGUubW9kaWZpZXJzLmFwcGx5U3R5bGU9e2VuYWJsZWQ6ITF9KSxsKHt9LGUse30sdGhpcy5fY29uZmlnLnBvcHBlckNvbmZpZyl9LGMuX2pRdWVyeUludGVyZmFjZT1mdW5jdGlvbih0KXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzKS5kYXRhKEF0KTtpZihlfHwoZT1uZXcgYyh0aGlzLCJvYmplY3QiPT10eXBlb2YgdD90Om51bGwpLHAodGhpcykuZGF0YShBdCxlKSksInN0cmluZyI9PXR5cGVvZiB0KXtpZigidW5kZWZpbmVkIj09dHlwZW9mIGVbdF0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK3QrJyInKTtlW3RdKCl9fSl9LGMuX2NsZWFyTWVudXM9ZnVuY3Rpb24oZSl7aWYoIWV8fDMhPT1lLndoaWNoJiYoImtleXVwIiE9PWUudHlwZXx8OT09PWUud2hpY2gpKWZvcih2YXIgdD1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoVXQpKSxuPTAsaT10Lmxlbmd0aDtuPGk7bisrKXt2YXIgbz1jLl9nZXRQYXJlbnRGcm9tRWxlbWVudCh0W25dKSxyPXAodFtuXSkuZGF0YShBdCkscz17cmVsYXRlZFRhcmdldDp0W25dfTtpZihlJiYiY2xpY2siPT09ZS50eXBlJiYocy5jbGlja0V2ZW50PWUpLHIpe3ZhciBhPXIuX21lbnU7aWYocChvKS5oYXNDbGFzcyhqdCkmJiEoZSYmKCJjbGljayI9PT1lLnR5cGUmJi9pbnB1dHx0ZXh0YXJlYS9pLnRlc3QoZS50YXJnZXQudGFnTmFtZSl8fCJrZXl1cCI9PT1lLnR5cGUmJjk9PT1lLndoaWNoKSYmcC5jb250YWlucyhvLGUudGFyZ2V0KSkpe3ZhciBsPXAuRXZlbnQoUHQuSElERSxzKTtwKG8pLnRyaWdnZXIobCksbC5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8KCJvbnRvdWNoc3RhcnQiaW4gZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50JiZwKGRvY3VtZW50LmJvZHkpLmNoaWxkcmVuKCkub2ZmKCJtb3VzZW92ZXIiLG51bGwscC5ub29wKSx0W25dLnNldEF0dHJpYnV0ZSgiYXJpYS1leHBhbmRlZCIsImZhbHNlIiksci5fcG9wcGVyJiZyLl9wb3BwZXIuZGVzdHJveSgpLHAoYSkucmVtb3ZlQ2xhc3MoanQpLHAobykucmVtb3ZlQ2xhc3MoanQpLnRyaWdnZXIocC5FdmVudChQdC5ISURERU4scykpKX19fX0sYy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQ9ZnVuY3Rpb24oZSl7dmFyIHQsbj1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQoZSk7cmV0dXJuIG4mJih0PWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IobikpLHR8fGUucGFyZW50Tm9kZX0sYy5fZGF0YUFwaUtleWRvd25IYW5kbGVyPWZ1bmN0aW9uKGUpe2lmKCgvaW5wdXR8dGV4dGFyZWEvaS50ZXN0KGUudGFyZ2V0LnRhZ05hbWUpPyEoMzI9PT1lLndoaWNofHwyNyE9PWUud2hpY2gmJig0MCE9PWUud2hpY2gmJjM4IT09ZS53aGljaHx8cChlLnRhcmdldCkuY2xvc2VzdChxdCkubGVuZ3RoKSk6THQudGVzdChlLndoaWNoKSkmJihlLnByZXZlbnREZWZhdWx0KCksZS5zdG9wUHJvcGFnYXRpb24oKSwhdGhpcy5kaXNhYmxlZCYmIXAodGhpcykuaGFzQ2xhc3MoeHQpKSl7dmFyIHQ9Yy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQodGhpcyksbj1wKHQpLmhhc0NsYXNzKGp0KTtpZihufHwyNyE9PWUud2hpY2gpaWYobiYmKCFufHwyNyE9PWUud2hpY2gmJjMyIT09ZS53aGljaCkpe3ZhciBpPVtdLnNsaWNlLmNhbGwodC5xdWVyeVNlbGVjdG9yQWxsKFF0KSkuZmlsdGVyKGZ1bmN0aW9uKGUpe3JldHVybiBwKGUpLmlzKCI6dmlzaWJsZSIpfSk7aWYoMCE9PWkubGVuZ3RoKXt2YXIgbz1pLmluZGV4T2YoZS50YXJnZXQpOzM4PT09ZS53aGljaCYmMDxvJiZvLS0sNDA9PT1lLndoaWNoJiZvPGkubGVuZ3RoLTEmJm8rKyxvPDAmJihvPTApLGlbb10uZm9jdXMoKX19ZWxzZXtpZigyNz09PWUud2hpY2gpe3ZhciByPXQucXVlcnlTZWxlY3RvcihVdCk7cChyKS50cmlnZ2VyKCJmb2N1cyIpfXAodGhpcykudHJpZ2dlcigiY2xpY2siKX19fSxzKGMsbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBKdH19LHtrZXk6IkRlZmF1bHRUeXBlIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gWnR9fV0pLGN9KCk7cChkb2N1bWVudCkub24oUHQuS0VZRE9XTl9EQVRBX0FQSSxVdCxlbi5fZGF0YUFwaUtleWRvd25IYW5kbGVyKS5vbihQdC5LRVlET1dOX0RBVEFfQVBJLHF0LGVuLl9kYXRhQXBpS2V5ZG93bkhhbmRsZXIpLm9uKFB0LkNMSUNLX0RBVEFfQVBJKyIgIitQdC5LRVlVUF9EQVRBX0FQSSxlbi5fY2xlYXJNZW51cykub24oUHQuQ0xJQ0tfREFUQV9BUEksVXQsZnVuY3Rpb24oZSl7ZS5wcmV2ZW50RGVmYXVsdCgpLGUuc3RvcFByb3BhZ2F0aW9uKCksZW4uX2pRdWVyeUludGVyZmFjZS5jYWxsKHAodGhpcyksInRvZ2dsZSIpfSkub24oUHQuQ0xJQ0tfREFUQV9BUEksQnQsZnVuY3Rpb24oZSl7ZS5zdG9wUHJvcGFnYXRpb24oKX0pLHAuZm5bSXRdPWVuLl9qUXVlcnlJbnRlcmZhY2UscC5mbltJdF0uQ29uc3RydWN0b3I9ZW4scC5mbltJdF0ubm9Db25mbGljdD1mdW5jdGlvbigpe3JldHVybiBwLmZuW0l0XT1rdCxlbi5falF1ZXJ5SW50ZXJmYWNlfTt2YXIgdG49Im1vZGFsIixubj0iYnMubW9kYWwiLG9uPSIuIitubixybj1wLmZuW3RuXSxzbj17YmFja2Ryb3A6ITAsa2V5Ym9hcmQ6ITAsZm9jdXM6ITAsc2hvdzohMH0sYW49e2JhY2tkcm9wOiIoYm9vbGVhbnxzdHJpbmcpIixrZXlib2FyZDoiYm9vbGVhbiIsZm9jdXM6ImJvb2xlYW4iLHNob3c6ImJvb2xlYW4ifSxsbj17SElERToiaGlkZSIrb24sSElERV9QUkVWRU5URUQ6ImhpZGVQcmV2ZW50ZWQiK29uLEhJRERFTjoiaGlkZGVuIitvbixTSE9XOiJzaG93IitvbixTSE9XTjoic2hvd24iK29uLEZPQ1VTSU46ImZvY3VzaW4iK29uLFJFU0laRToicmVzaXplIitvbixDTElDS19ESVNNSVNTOiJjbGljay5kaXNtaXNzIitvbixLRVlET1dOX0RJU01JU1M6ImtleWRvd24uZGlzbWlzcyIrb24sTU9VU0VVUF9ESVNNSVNTOiJtb3VzZXVwLmRpc21pc3MiK29uLE1PVVNFRE9XTl9ESVNNSVNTOiJtb3VzZWRvd24uZGlzbWlzcyIrb24sQ0xJQ0tfREFUQV9BUEk6ImNsaWNrIitvbisiLmRhdGEtYXBpIn0sY249Im1vZGFsLWRpYWxvZy1zY3JvbGxhYmxlIixobj0ibW9kYWwtc2Nyb2xsYmFyLW1lYXN1cmUiLHVuPSJtb2RhbC1iYWNrZHJvcCIsZm49Im1vZGFsLW9wZW4iLGRuPSJmYWRlIixwbj0ic2hvdyIsbW49Im1vZGFsLXN0YXRpYyIsZ249Ii5tb2RhbC1kaWFsb2ciLF9uPSIubW9kYWwtYm9keSIsdm49J1tkYXRhLXRvZ2dsZT0ibW9kYWwiXScseW49J1tkYXRhLWRpc21pc3M9Im1vZGFsIl0nLEVuPSIuZml4ZWQtdG9wLCAuZml4ZWQtYm90dG9tLCAuaXMtZml4ZWQsIC5zdGlja3ktdG9wIixibj0iLnN0aWNreS10b3AiLHduPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gbyhlLHQpe3RoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcodCksdGhpcy5fZWxlbWVudD1lLHRoaXMuX2RpYWxvZz1lLnF1ZXJ5U2VsZWN0b3IoZ24pLHRoaXMuX2JhY2tkcm9wPW51bGwsdGhpcy5faXNTaG93bj0hMSx0aGlzLl9pc0JvZHlPdmVyZmxvd2luZz0hMSx0aGlzLl9pZ25vcmVCYWNrZHJvcENsaWNrPSExLHRoaXMuX2lzVHJhbnNpdGlvbmluZz0hMSx0aGlzLl9zY3JvbGxiYXJXaWR0aD0wfXZhciBlPW8ucHJvdG90eXBlO3JldHVybiBlLnRvZ2dsZT1mdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5faXNTaG93bj90aGlzLmhpZGUoKTp0aGlzLnNob3coZSl9LGUuc2hvdz1mdW5jdGlvbihlKXt2YXIgdD10aGlzO2lmKCF0aGlzLl9pc1Nob3duJiYhdGhpcy5faXNUcmFuc2l0aW9uaW5nKXtwKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKGRuKSYmKHRoaXMuX2lzVHJhbnNpdGlvbmluZz0hMCk7dmFyIG49cC5FdmVudChsbi5TSE9XLHtyZWxhdGVkVGFyZ2V0OmV9KTtwKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIobiksdGhpcy5faXNTaG93bnx8bi5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8KHRoaXMuX2lzU2hvd249ITAsdGhpcy5fY2hlY2tTY3JvbGxiYXIoKSx0aGlzLl9zZXRTY3JvbGxiYXIoKSx0aGlzLl9hZGp1c3REaWFsb2coKSx0aGlzLl9zZXRFc2NhcGVFdmVudCgpLHRoaXMuX3NldFJlc2l6ZUV2ZW50KCkscCh0aGlzLl9lbGVtZW50KS5vbihsbi5DTElDS19ESVNNSVNTLHluLGZ1bmN0aW9uKGUpe3JldHVybiB0LmhpZGUoZSl9KSxwKHRoaXMuX2RpYWxvZykub24obG4uTU9VU0VET1dOX0RJU01JU1MsZnVuY3Rpb24oKXtwKHQuX2VsZW1lbnQpLm9uZShsbi5NT1VTRVVQX0RJU01JU1MsZnVuY3Rpb24oZSl7cChlLnRhcmdldCkuaXModC5fZWxlbWVudCkmJih0Ll9pZ25vcmVCYWNrZHJvcENsaWNrPSEwKX0pfSksdGhpcy5fc2hvd0JhY2tkcm9wKGZ1bmN0aW9uKCl7cmV0dXJuIHQuX3Nob3dFbGVtZW50KGUpfSkpfX0sZS5oaWRlPWZ1bmN0aW9uKGUpe3ZhciB0PXRoaXM7aWYoZSYmZS5wcmV2ZW50RGVmYXVsdCgpLHRoaXMuX2lzU2hvd24mJiF0aGlzLl9pc1RyYW5zaXRpb25pbmcpe3ZhciBuPXAuRXZlbnQobG4uSElERSk7aWYocCh0aGlzLl9lbGVtZW50KS50cmlnZ2VyKG4pLHRoaXMuX2lzU2hvd24mJiFuLmlzRGVmYXVsdFByZXZlbnRlZCgpKXt0aGlzLl9pc1Nob3duPSExO3ZhciBpPXAodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoZG4pO2lmKGkmJih0aGlzLl9pc1RyYW5zaXRpb25pbmc9ITApLHRoaXMuX3NldEVzY2FwZUV2ZW50KCksdGhpcy5fc2V0UmVzaXplRXZlbnQoKSxwKGRvY3VtZW50KS5vZmYobG4uRk9DVVNJTikscCh0aGlzLl9lbGVtZW50KS5yZW1vdmVDbGFzcyhwbikscCh0aGlzLl9lbGVtZW50KS5vZmYobG4uQ0xJQ0tfRElTTUlTUykscCh0aGlzLl9kaWFsb2cpLm9mZihsbi5NT1VTRURPV05fRElTTUlTUyksaSl7dmFyIG89bS5nZXRUcmFuc2l0aW9uRHVyYXRpb25Gcm9tRWxlbWVudCh0aGlzLl9lbGVtZW50KTtwKHRoaXMuX2VsZW1lbnQpLm9uZShtLlRSQU5TSVRJT05fRU5ELGZ1bmN0aW9uKGUpe3JldHVybiB0Ll9oaWRlTW9kYWwoZSl9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChvKX1lbHNlIHRoaXMuX2hpZGVNb2RhbCgpfX19LGUuZGlzcG9zZT1mdW5jdGlvbigpe1t3aW5kb3csdGhpcy5fZWxlbWVudCx0aGlzLl9kaWFsb2ddLmZvckVhY2goZnVuY3Rpb24oZSl7cmV0dXJuIHAoZSkub2ZmKG9uKX0pLHAoZG9jdW1lbnQpLm9mZihsbi5GT0NVU0lOKSxwLnJlbW92ZURhdGEodGhpcy5fZWxlbWVudCxubiksdGhpcy5fY29uZmlnPW51bGwsdGhpcy5fZWxlbWVudD1udWxsLHRoaXMuX2RpYWxvZz1udWxsLHRoaXMuX2JhY2tkcm9wPW51bGwsdGhpcy5faXNTaG93bj1udWxsLHRoaXMuX2lzQm9keU92ZXJmbG93aW5nPW51bGwsdGhpcy5faWdub3JlQmFja2Ryb3BDbGljaz1udWxsLHRoaXMuX2lzVHJhbnNpdGlvbmluZz1udWxsLHRoaXMuX3Njcm9sbGJhcldpZHRoPW51bGx9LGUuaGFuZGxlVXBkYXRlPWZ1bmN0aW9uKCl7dGhpcy5fYWRqdXN0RGlhbG9nKCl9LGUuX2dldENvbmZpZz1mdW5jdGlvbihlKXtyZXR1cm4gZT1sKHt9LHNuLHt9LGUpLG0udHlwZUNoZWNrQ29uZmlnKHRuLGUsYW4pLGV9LGUuX3RyaWdnZXJCYWNrZHJvcFRyYW5zaXRpb249ZnVuY3Rpb24oKXt2YXIgZT10aGlzO2lmKCJzdGF0aWMiPT09dGhpcy5fY29uZmlnLmJhY2tkcm9wKXt2YXIgdD1wLkV2ZW50KGxuLkhJREVfUFJFVkVOVEVEKTtpZihwKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIodCksdC5kZWZhdWx0UHJldmVudGVkKXJldHVybjt0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5hZGQobW4pO3ZhciBuPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7cCh0aGlzLl9lbGVtZW50KS5vbmUobS5UUkFOU0lUSU9OX0VORCxmdW5jdGlvbigpe2UuX2VsZW1lbnQuY2xhc3NMaXN0LnJlbW92ZShtbil9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChuKSx0aGlzLl9lbGVtZW50LmZvY3VzKCl9ZWxzZSB0aGlzLmhpZGUoKX0sZS5fc2hvd0VsZW1lbnQ9ZnVuY3Rpb24oZSl7dmFyIHQ9dGhpcyxuPXAodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoZG4pLGk9dGhpcy5fZGlhbG9nP3RoaXMuX2RpYWxvZy5xdWVyeVNlbGVjdG9yKF9uKTpudWxsO3RoaXMuX2VsZW1lbnQucGFyZW50Tm9kZSYmdGhpcy5fZWxlbWVudC5wYXJlbnROb2RlLm5vZGVUeXBlPT09Tm9kZS5FTEVNRU5UX05PREV8fGRvY3VtZW50LmJvZHkuYXBwZW5kQ2hpbGQodGhpcy5fZWxlbWVudCksdGhpcy5fZWxlbWVudC5zdHlsZS5kaXNwbGF5PSJibG9jayIsdGhpcy5fZWxlbWVudC5yZW1vdmVBdHRyaWJ1dGUoImFyaWEtaGlkZGVuIiksdGhpcy5fZWxlbWVudC5zZXRBdHRyaWJ1dGUoImFyaWEtbW9kYWwiLCEwKSxwKHRoaXMuX2RpYWxvZykuaGFzQ2xhc3MoY24pJiZpP2kuc2Nyb2xsVG9wPTA6dGhpcy5fZWxlbWVudC5zY3JvbGxUb3A9MCxuJiZtLnJlZmxvdyh0aGlzLl9lbGVtZW50KSxwKHRoaXMuX2VsZW1lbnQpLmFkZENsYXNzKHBuKSx0aGlzLl9jb25maWcuZm9jdXMmJnRoaXMuX2VuZm9yY2VGb2N1cygpO2Z1bmN0aW9uIG8oKXt0Ll9jb25maWcuZm9jdXMmJnQuX2VsZW1lbnQuZm9jdXMoKSx0Ll9pc1RyYW5zaXRpb25pbmc9ITEscCh0Ll9lbGVtZW50KS50cmlnZ2VyKHIpfXZhciByPXAuRXZlbnQobG4uU0hPV04se3JlbGF0ZWRUYXJnZXQ6ZX0pO2lmKG4pe3ZhciBzPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZGlhbG9nKTtwKHRoaXMuX2RpYWxvZykub25lKG0uVFJBTlNJVElPTl9FTkQsbykuZW11bGF0ZVRyYW5zaXRpb25FbmQocyl9ZWxzZSBvKCl9LGUuX2VuZm9yY2VGb2N1cz1mdW5jdGlvbigpe3ZhciB0PXRoaXM7cChkb2N1bWVudCkub2ZmKGxuLkZPQ1VTSU4pLm9uKGxuLkZPQ1VTSU4sZnVuY3Rpb24oZSl7ZG9jdW1lbnQhPT1lLnRhcmdldCYmdC5fZWxlbWVudCE9PWUudGFyZ2V0JiYwPT09cCh0Ll9lbGVtZW50KS5oYXMoZS50YXJnZXQpLmxlbmd0aCYmdC5fZWxlbWVudC5mb2N1cygpfSl9LGUuX3NldEVzY2FwZUV2ZW50PWZ1bmN0aW9uKCl7dmFyIHQ9dGhpczt0aGlzLl9pc1Nob3duJiZ0aGlzLl9jb25maWcua2V5Ym9hcmQ/cCh0aGlzLl9lbGVtZW50KS5vbihsbi5LRVlET1dOX0RJU01JU1MsZnVuY3Rpb24oZSl7Mjc9PT1lLndoaWNoJiZ0Ll90cmlnZ2VyQmFja2Ryb3BUcmFuc2l0aW9uKCl9KTp0aGlzLl9pc1Nob3dufHxwKHRoaXMuX2VsZW1lbnQpLm9mZihsbi5LRVlET1dOX0RJU01JU1MpfSxlLl9zZXRSZXNpemVFdmVudD1mdW5jdGlvbigpe3ZhciB0PXRoaXM7dGhpcy5faXNTaG93bj9wKHdpbmRvdykub24obG4uUkVTSVpFLGZ1bmN0aW9uKGUpe3JldHVybiB0LmhhbmRsZVVwZGF0ZShlKX0pOnAod2luZG93KS5vZmYobG4uUkVTSVpFKX0sZS5faGlkZU1vZGFsPWZ1bmN0aW9uKCl7dmFyIGU9dGhpczt0aGlzLl9lbGVtZW50LnN0eWxlLmRpc3BsYXk9Im5vbmUiLHRoaXMuX2VsZW1lbnQuc2V0QXR0cmlidXRlKCJhcmlhLWhpZGRlbiIsITApLHRoaXMuX2VsZW1lbnQucmVtb3ZlQXR0cmlidXRlKCJhcmlhLW1vZGFsIiksdGhpcy5faXNUcmFuc2l0aW9uaW5nPSExLHRoaXMuX3Nob3dCYWNrZHJvcChmdW5jdGlvbigpe3AoZG9jdW1lbnQuYm9keSkucmVtb3ZlQ2xhc3MoZm4pLGUuX3Jlc2V0QWRqdXN0bWVudHMoKSxlLl9yZXNldFNjcm9sbGJhcigpLHAoZS5fZWxlbWVudCkudHJpZ2dlcihsbi5ISURERU4pfSl9LGUuX3JlbW92ZUJhY2tkcm9wPWZ1bmN0aW9uKCl7dGhpcy5fYmFja2Ryb3AmJihwKHRoaXMuX2JhY2tkcm9wKS5yZW1vdmUoKSx0aGlzLl9iYWNrZHJvcD1udWxsKX0sZS5fc2hvd0JhY2tkcm9wPWZ1bmN0aW9uKGUpe3ZhciB0PXRoaXMsbj1wKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKGRuKT9kbjoiIjtpZih0aGlzLl9pc1Nob3duJiZ0aGlzLl9jb25maWcuYmFja2Ryb3Ape2lmKHRoaXMuX2JhY2tkcm9wPWRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoImRpdiIpLHRoaXMuX2JhY2tkcm9wLmNsYXNzTmFtZT11bixuJiZ0aGlzLl9iYWNrZHJvcC5jbGFzc0xpc3QuYWRkKG4pLHAodGhpcy5fYmFja2Ryb3ApLmFwcGVuZFRvKGRvY3VtZW50LmJvZHkpLHAodGhpcy5fZWxlbWVudCkub24obG4uQ0xJQ0tfRElTTUlTUyxmdW5jdGlvbihlKXt0Ll9pZ25vcmVCYWNrZHJvcENsaWNrP3QuX2lnbm9yZUJhY2tkcm9wQ2xpY2s9ITE6ZS50YXJnZXQ9PT1lLmN1cnJlbnRUYXJnZXQmJnQuX3RyaWdnZXJCYWNrZHJvcFRyYW5zaXRpb24oKX0pLG4mJm0ucmVmbG93KHRoaXMuX2JhY2tkcm9wKSxwKHRoaXMuX2JhY2tkcm9wKS5hZGRDbGFzcyhwbiksIWUpcmV0dXJuO2lmKCFuKXJldHVybiB2b2lkIGUoKTt2YXIgaT1tLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2JhY2tkcm9wKTtwKHRoaXMuX2JhY2tkcm9wKS5vbmUobS5UUkFOU0lUSU9OX0VORCxlKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChpKX1lbHNlIGlmKCF0aGlzLl9pc1Nob3duJiZ0aGlzLl9iYWNrZHJvcCl7cCh0aGlzLl9iYWNrZHJvcCkucmVtb3ZlQ2xhc3MocG4pO3ZhciBvPWZ1bmN0aW9uKCl7dC5fcmVtb3ZlQmFja2Ryb3AoKSxlJiZlKCl9O2lmKHAodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoZG4pKXt2YXIgcj1tLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2JhY2tkcm9wKTtwKHRoaXMuX2JhY2tkcm9wKS5vbmUobS5UUkFOU0lUSU9OX0VORCxvKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChyKX1lbHNlIG8oKX1lbHNlIGUmJmUoKX0sZS5fYWRqdXN0RGlhbG9nPWZ1bmN0aW9uKCl7dmFyIGU9dGhpcy5fZWxlbWVudC5zY3JvbGxIZWlnaHQ+ZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LmNsaWVudEhlaWdodDshdGhpcy5faXNCb2R5T3ZlcmZsb3dpbmcmJmUmJih0aGlzLl9lbGVtZW50LnN0eWxlLnBhZGRpbmdMZWZ0PXRoaXMuX3Njcm9sbGJhcldpZHRoKyJweCIpLHRoaXMuX2lzQm9keU92ZXJmbG93aW5nJiYhZSYmKHRoaXMuX2VsZW1lbnQuc3R5bGUucGFkZGluZ1JpZ2h0PXRoaXMuX3Njcm9sbGJhcldpZHRoKyJweCIpfSxlLl9yZXNldEFkanVzdG1lbnRzPWZ1bmN0aW9uKCl7dGhpcy5fZWxlbWVudC5zdHlsZS5wYWRkaW5nTGVmdD0iIix0aGlzLl9lbGVtZW50LnN0eWxlLnBhZGRpbmdSaWdodD0iIn0sZS5fY2hlY2tTY3JvbGxiYXI9ZnVuY3Rpb24oKXt2YXIgZT1kb2N1bWVudC5ib2R5LmdldEJvdW5kaW5nQ2xpZW50UmVjdCgpO3RoaXMuX2lzQm9keU92ZXJmbG93aW5nPWUubGVmdCtlLnJpZ2h0PHdpbmRvdy5pbm5lcldpZHRoLHRoaXMuX3Njcm9sbGJhcldpZHRoPXRoaXMuX2dldFNjcm9sbGJhcldpZHRoKCl9LGUuX3NldFNjcm9sbGJhcj1mdW5jdGlvbigpe3ZhciBvPXRoaXM7aWYodGhpcy5faXNCb2R5T3ZlcmZsb3dpbmcpe3ZhciBlPVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChFbikpLHQ9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKGJuKSk7cChlKS5lYWNoKGZ1bmN0aW9uKGUsdCl7dmFyIG49dC5zdHlsZS5wYWRkaW5nUmlnaHQsaT1wKHQpLmNzcygicGFkZGluZy1yaWdodCIpO3AodCkuZGF0YSgicGFkZGluZy1yaWdodCIsbikuY3NzKCJwYWRkaW5nLXJpZ2h0IixwYXJzZUZsb2F0KGkpK28uX3Njcm9sbGJhcldpZHRoKyJweCIpfSkscCh0KS5lYWNoKGZ1bmN0aW9uKGUsdCl7dmFyIG49dC5zdHlsZS5tYXJnaW5SaWdodCxpPXAodCkuY3NzKCJtYXJnaW4tcmlnaHQiKTtwKHQpLmRhdGEoIm1hcmdpbi1yaWdodCIsbikuY3NzKCJtYXJnaW4tcmlnaHQiLHBhcnNlRmxvYXQoaSktby5fc2Nyb2xsYmFyV2lkdGgrInB4Iil9KTt2YXIgbj1kb2N1bWVudC5ib2R5LnN0eWxlLnBhZGRpbmdSaWdodCxpPXAoZG9jdW1lbnQuYm9keSkuY3NzKCJwYWRkaW5nLXJpZ2h0Iik7cChkb2N1bWVudC5ib2R5KS5kYXRhKCJwYWRkaW5nLXJpZ2h0IixuKS5jc3MoInBhZGRpbmctcmlnaHQiLHBhcnNlRmxvYXQoaSkrdGhpcy5fc2Nyb2xsYmFyV2lkdGgrInB4Iil9cChkb2N1bWVudC5ib2R5KS5hZGRDbGFzcyhmbil9LGUuX3Jlc2V0U2Nyb2xsYmFyPWZ1bmN0aW9uKCl7dmFyIGU9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKEVuKSk7cChlKS5lYWNoKGZ1bmN0aW9uKGUsdCl7dmFyIG49cCh0KS5kYXRhKCJwYWRkaW5nLXJpZ2h0Iik7cCh0KS5yZW1vdmVEYXRhKCJwYWRkaW5nLXJpZ2h0IiksdC5zdHlsZS5wYWRkaW5nUmlnaHQ9bnx8IiJ9KTt2YXIgdD1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoIiIrYm4pKTtwKHQpLmVhY2goZnVuY3Rpb24oZSx0KXt2YXIgbj1wKHQpLmRhdGEoIm1hcmdpbi1yaWdodCIpOyJ1bmRlZmluZWQiIT10eXBlb2YgbiYmcCh0KS5jc3MoIm1hcmdpbi1yaWdodCIsbikucmVtb3ZlRGF0YSgibWFyZ2luLXJpZ2h0Iil9KTt2YXIgbj1wKGRvY3VtZW50LmJvZHkpLmRhdGEoInBhZGRpbmctcmlnaHQiKTtwKGRvY3VtZW50LmJvZHkpLnJlbW92ZURhdGEoInBhZGRpbmctcmlnaHQiKSxkb2N1bWVudC5ib2R5LnN0eWxlLnBhZGRpbmdSaWdodD1ufHwiIn0sZS5fZ2V0U2Nyb2xsYmFyV2lkdGg9ZnVuY3Rpb24oKXt2YXIgZT1kb2N1bWVudC5jcmVhdGVFbGVtZW50KCJkaXYiKTtlLmNsYXNzTmFtZT1obixkb2N1bWVudC5ib2R5LmFwcGVuZENoaWxkKGUpO3ZhciB0PWUuZ2V0Qm91bmRpbmdDbGllbnRSZWN0KCkud2lkdGgtZS5jbGllbnRXaWR0aDtyZXR1cm4gZG9jdW1lbnQuYm9keS5yZW1vdmVDaGlsZChlKSx0fSxvLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24obixpKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzKS5kYXRhKG5uKSx0PWwoe30sc24se30scCh0aGlzKS5kYXRhKCkse30sIm9iamVjdCI9PXR5cGVvZiBuJiZuP246e30pO2lmKGV8fChlPW5ldyBvKHRoaXMsdCkscCh0aGlzKS5kYXRhKG5uLGUpKSwic3RyaW5nIj09dHlwZW9mIG4pe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgZVtuXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrbisnIicpO2Vbbl0oaSl9ZWxzZSB0LnNob3cmJmUuc2hvdyhpKX0pfSxzKG8sbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBzbn19XSksb30oKTtwKGRvY3VtZW50KS5vbihsbi5DTElDS19EQVRBX0FQSSx2bixmdW5jdGlvbihlKXt2YXIgdCxuPXRoaXMsaT1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQodGhpcyk7aSYmKHQ9ZG9jdW1lbnQucXVlcnlTZWxlY3RvcihpKSk7dmFyIG89cCh0KS5kYXRhKG5uKT8idG9nZ2xlIjpsKHt9LHAodCkuZGF0YSgpLHt9LHAodGhpcykuZGF0YSgpKTsiQSIhPT10aGlzLnRhZ05hbWUmJiJBUkVBIiE9PXRoaXMudGFnTmFtZXx8ZS5wcmV2ZW50RGVmYXVsdCgpO3ZhciByPXAodCkub25lKGxuLlNIT1csZnVuY3Rpb24oZSl7ZS5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8ci5vbmUobG4uSElEREVOLGZ1bmN0aW9uKCl7cChuKS5pcygiOnZpc2libGUiKSYmbi5mb2N1cygpfSl9KTt3bi5falF1ZXJ5SW50ZXJmYWNlLmNhbGwocCh0KSxvLHRoaXMpfSkscC5mblt0bl09d24uX2pRdWVyeUludGVyZmFjZSxwLmZuW3RuXS5Db25zdHJ1Y3Rvcj13bixwLmZuW3RuXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm5bdG5dPXJuLHduLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciBUbj1bImJhY2tncm91bmQiLCJjaXRlIiwiaHJlZiIsIml0ZW10eXBlIiwibG9uZ2Rlc2MiLCJwb3N0ZXIiLCJzcmMiLCJ4bGluazpocmVmIl0sQ249eyIqIjpbImNsYXNzIiwiZGlyIiwiaWQiLCJsYW5nIiwicm9sZSIsL15hcmlhLVtcdy1dKiQvaV0sYTpbInRhcmdldCIsImhyZWYiLCJ0aXRsZSIsInJlbCJdLGFyZWE6W10sYjpbXSxicjpbXSxjb2w6W10sY29kZTpbXSxkaXY6W10sZW06W10saHI6W10saDE6W10saDI6W10saDM6W10saDQ6W10saDU6W10saDY6W10saTpbXSxpbWc6WyJzcmMiLCJhbHQiLCJ0aXRsZSIsIndpZHRoIiwiaGVpZ2h0Il0sbGk6W10sb2w6W10scDpbXSxwcmU6W10sczpbXSxzbWFsbDpbXSxzcGFuOltdLHN1YjpbXSxzdXA6W10sc3Ryb25nOltdLHU6W10sdWw6W119LFNuPS9eKD86KD86aHR0cHM/fG1haWx0b3xmdHB8dGVsfGZpbGUpOnxbXiY6Lz8jXSooPzpbLz8jXXwkKSkvZ2ksRG49L15kYXRhOig/OmltYWdlXC8oPzpibXB8Z2lmfGpwZWd8anBnfHBuZ3x0aWZmfHdlYnApfHZpZGVvXC8oPzptcGVnfG1wNHxvZ2d8d2VibSl8YXVkaW9cLyg/Om1wM3xvZ2F8b2dnfG9wdXMpKTtiYXNlNjQsW2EtejAtOSsvXSs9KiQvaTtmdW5jdGlvbiBJbihlLHIsdCl7aWYoMD09PWUubGVuZ3RoKXJldHVybiBlO2lmKHQmJiJmdW5jdGlvbiI9PXR5cGVvZiB0KXJldHVybiB0KGUpO2Zvcih2YXIgbj0obmV3IHdpbmRvdy5ET01QYXJzZXIpLnBhcnNlRnJvbVN0cmluZyhlLCJ0ZXh0L2h0bWwiKSxzPU9iamVjdC5rZXlzKHIpLGE9W10uc2xpY2UuY2FsbChuLmJvZHkucXVlcnlTZWxlY3RvckFsbCgiKiIpKSxpPWZ1bmN0aW9uKGUpe3ZhciB0PWFbZV0sbj10Lm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk7aWYoLTE9PT1zLmluZGV4T2YodC5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpKSlyZXR1cm4gdC5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKHQpLCJjb250aW51ZSI7dmFyIGk9W10uc2xpY2UuY2FsbCh0LmF0dHJpYnV0ZXMpLG89W10uY29uY2F0KHJbIioiXXx8W10scltuXXx8W10pO2kuZm9yRWFjaChmdW5jdGlvbihlKXshZnVuY3Rpb24oZSx0KXt2YXIgbj1lLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk7aWYoLTEhPT10LmluZGV4T2YobikpcmV0dXJuLTE9PT1Ubi5pbmRleE9mKG4pfHxCb29sZWFuKGUubm9kZVZhbHVlLm1hdGNoKFNuKXx8ZS5ub2RlVmFsdWUubWF0Y2goRG4pKTtmb3IodmFyIGk9dC5maWx0ZXIoZnVuY3Rpb24oZSl7cmV0dXJuIGUgaW5zdGFuY2VvZiBSZWdFeHB9KSxvPTAscj1pLmxlbmd0aDtvPHI7bysrKWlmKG4ubWF0Y2goaVtvXSkpcmV0dXJuITA7cmV0dXJuITF9KGUsbykmJnQucmVtb3ZlQXR0cmlidXRlKGUubm9kZU5hbWUpfSl9LG89MCxsPWEubGVuZ3RoO288bDtvKyspaShvKTtyZXR1cm4gbi5ib2R5LmlubmVySFRNTH12YXIgQW49InRvb2x0aXAiLE9uPSJicy50b29sdGlwIixObj0iLiIrT24sa249cC5mbltBbl0sTG49ImJzLXRvb2x0aXAiLFBuPW5ldyBSZWdFeHAoIihefFxccykiK0xuKyJcXFMrIiwiZyIpLHhuPVsic2FuaXRpemUiLCJ3aGl0ZUxpc3QiLCJzYW5pdGl6ZUZuIl0sam49e2FuaW1hdGlvbjoiYm9vbGVhbiIsdGVtcGxhdGU6InN0cmluZyIsdGl0bGU6IihzdHJpbmd8ZWxlbWVudHxmdW5jdGlvbikiLHRyaWdnZXI6InN0cmluZyIsZGVsYXk6IihudW1iZXJ8b2JqZWN0KSIsaHRtbDoiYm9vbGVhbiIsc2VsZWN0b3I6IihzdHJpbmd8Ym9vbGVhbikiLHBsYWNlbWVudDoiKHN0cmluZ3xmdW5jdGlvbikiLG9mZnNldDoiKG51bWJlcnxzdHJpbmd8ZnVuY3Rpb24pIixjb250YWluZXI6IihzdHJpbmd8ZWxlbWVudHxib29sZWFuKSIsZmFsbGJhY2tQbGFjZW1lbnQ6IihzdHJpbmd8YXJyYXkpIixib3VuZGFyeToiKHN0cmluZ3xlbGVtZW50KSIsc2FuaXRpemU6ImJvb2xlYW4iLHNhbml0aXplRm46IihudWxsfGZ1bmN0aW9uKSIsd2hpdGVMaXN0OiJvYmplY3QiLHBvcHBlckNvbmZpZzoiKG51bGx8b2JqZWN0KSJ9LEhuPXtBVVRPOiJhdXRvIixUT1A6InRvcCIsUklHSFQ6InJpZ2h0IixCT1RUT006ImJvdHRvbSIsTEVGVDoibGVmdCJ9LFJuPXthbmltYXRpb246ITAsdGVtcGxhdGU6JzxkaXYgY2xhc3M9InRvb2x0aXAiIHJvbGU9InRvb2x0aXAiPjxkaXYgY2xhc3M9ImFycm93Ij48L2Rpdj48ZGl2IGNsYXNzPSJ0b29sdGlwLWlubmVyIj48L2Rpdj48L2Rpdj4nLHRyaWdnZXI6ImhvdmVyIGZvY3VzIix0aXRsZToiIixkZWxheTowLGh0bWw6ITEsc2VsZWN0b3I6ITEscGxhY2VtZW50OiJ0b3AiLG9mZnNldDowLGNvbnRhaW5lcjohMSxmYWxsYmFja1BsYWNlbWVudDoiZmxpcCIsYm91bmRhcnk6InNjcm9sbFBhcmVudCIsc2FuaXRpemU6ITAsc2FuaXRpemVGbjpudWxsLHdoaXRlTGlzdDpDbixwb3BwZXJDb25maWc6bnVsbH0sRm49InNob3ciLE1uPSJvdXQiLFduPXtISURFOiJoaWRlIitObixISURERU46ImhpZGRlbiIrTm4sU0hPVzoic2hvdyIrTm4sU0hPV046InNob3duIitObixJTlNFUlRFRDoiaW5zZXJ0ZWQiK05uLENMSUNLOiJjbGljayIrTm4sRk9DVVNJTjoiZm9jdXNpbiIrTm4sRk9DVVNPVVQ6ImZvY3Vzb3V0IitObixNT1VTRUVOVEVSOiJtb3VzZWVudGVyIitObixNT1VTRUxFQVZFOiJtb3VzZWxlYXZlIitObn0sVW49ImZhZGUiLEJuPSJzaG93Iixxbj0iLnRvb2x0aXAtaW5uZXIiLEtuPSIuYXJyb3ciLFFuPSJob3ZlciIsVm49ImZvY3VzIixZbj0iY2xpY2siLHpuPSJtYW51YWwiLFhuPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gaShlLHQpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgU3QpdGhyb3cgbmV3IFR5cGVFcnJvcigiQm9vdHN0cmFwJ3MgdG9vbHRpcHMgcmVxdWlyZSBQb3BwZXIuanMgKGh0dHBzOi8vcG9wcGVyLmpzLm9yZy8pIik7dGhpcy5faXNFbmFibGVkPSEwLHRoaXMuX3RpbWVvdXQ9MCx0aGlzLl9ob3ZlclN0YXRlPSIiLHRoaXMuX2FjdGl2ZVRyaWdnZXI9e30sdGhpcy5fcG9wcGVyPW51bGwsdGhpcy5lbGVtZW50PWUsdGhpcy5jb25maWc9dGhpcy5fZ2V0Q29uZmlnKHQpLHRoaXMudGlwPW51bGwsdGhpcy5fc2V0TGlzdGVuZXJzKCl9dmFyIGU9aS5wcm90b3R5cGU7cmV0dXJuIGUuZW5hYmxlPWZ1bmN0aW9uKCl7dGhpcy5faXNFbmFibGVkPSEwfSxlLmRpc2FibGU9ZnVuY3Rpb24oKXt0aGlzLl9pc0VuYWJsZWQ9ITF9LGUudG9nZ2xlRW5hYmxlZD1mdW5jdGlvbigpe3RoaXMuX2lzRW5hYmxlZD0hdGhpcy5faXNFbmFibGVkfSxlLnRvZ2dsZT1mdW5jdGlvbihlKXtpZih0aGlzLl9pc0VuYWJsZWQpaWYoZSl7dmFyIHQ9dGhpcy5jb25zdHJ1Y3Rvci5EQVRBX0tFWSxuPXAoZS5jdXJyZW50VGFyZ2V0KS5kYXRhKHQpO258fChuPW5ldyB0aGlzLmNvbnN0cnVjdG9yKGUuY3VycmVudFRhcmdldCx0aGlzLl9nZXREZWxlZ2F0ZUNvbmZpZygpKSxwKGUuY3VycmVudFRhcmdldCkuZGF0YSh0LG4pKSxuLl9hY3RpdmVUcmlnZ2VyLmNsaWNrPSFuLl9hY3RpdmVUcmlnZ2VyLmNsaWNrLG4uX2lzV2l0aEFjdGl2ZVRyaWdnZXIoKT9uLl9lbnRlcihudWxsLG4pOm4uX2xlYXZlKG51bGwsbil9ZWxzZXtpZihwKHRoaXMuZ2V0VGlwRWxlbWVudCgpKS5oYXNDbGFzcyhCbikpcmV0dXJuIHZvaWQgdGhpcy5fbGVhdmUobnVsbCx0aGlzKTt0aGlzLl9lbnRlcihudWxsLHRoaXMpfX0sZS5kaXNwb3NlPWZ1bmN0aW9uKCl7Y2xlYXJUaW1lb3V0KHRoaXMuX3RpbWVvdXQpLHAucmVtb3ZlRGF0YSh0aGlzLmVsZW1lbnQsdGhpcy5jb25zdHJ1Y3Rvci5EQVRBX0tFWSkscCh0aGlzLmVsZW1lbnQpLm9mZih0aGlzLmNvbnN0cnVjdG9yLkVWRU5UX0tFWSkscCh0aGlzLmVsZW1lbnQpLmNsb3Nlc3QoIi5tb2RhbCIpLm9mZigiaGlkZS5icy5tb2RhbCIsdGhpcy5faGlkZU1vZGFsSGFuZGxlciksdGhpcy50aXAmJnAodGhpcy50aXApLnJlbW92ZSgpLHRoaXMuX2lzRW5hYmxlZD1udWxsLHRoaXMuX3RpbWVvdXQ9bnVsbCx0aGlzLl9ob3ZlclN0YXRlPW51bGwsdGhpcy5fYWN0aXZlVHJpZ2dlcj1udWxsLHRoaXMuX3BvcHBlciYmdGhpcy5fcG9wcGVyLmRlc3Ryb3koKSx0aGlzLl9wb3BwZXI9bnVsbCx0aGlzLmVsZW1lbnQ9bnVsbCx0aGlzLmNvbmZpZz1udWxsLHRoaXMudGlwPW51bGx9LGUuc2hvdz1mdW5jdGlvbigpe3ZhciB0PXRoaXM7aWYoIm5vbmUiPT09cCh0aGlzLmVsZW1lbnQpLmNzcygiZGlzcGxheSIpKXRocm93IG5ldyBFcnJvcigiUGxlYXNlIHVzZSBzaG93IG9uIHZpc2libGUgZWxlbWVudHMiKTt2YXIgZT1wLkV2ZW50KHRoaXMuY29uc3RydWN0b3IuRXZlbnQuU0hPVyk7aWYodGhpcy5pc1dpdGhDb250ZW50KCkmJnRoaXMuX2lzRW5hYmxlZCl7cCh0aGlzLmVsZW1lbnQpLnRyaWdnZXIoZSk7dmFyIG49bS5maW5kU2hhZG93Um9vdCh0aGlzLmVsZW1lbnQpLGk9cC5jb250YWlucyhudWxsIT09bj9uOnRoaXMuZWxlbWVudC5vd25lckRvY3VtZW50LmRvY3VtZW50RWxlbWVudCx0aGlzLmVsZW1lbnQpO2lmKGUuaXNEZWZhdWx0UHJldmVudGVkKCl8fCFpKXJldHVybjt2YXIgbz10aGlzLmdldFRpcEVsZW1lbnQoKSxyPW0uZ2V0VUlEKHRoaXMuY29uc3RydWN0b3IuTkFNRSk7by5zZXRBdHRyaWJ1dGUoImlkIixyKSx0aGlzLmVsZW1lbnQuc2V0QXR0cmlidXRlKCJhcmlhLWRlc2NyaWJlZGJ5IixyKSx0aGlzLnNldENvbnRlbnQoKSx0aGlzLmNvbmZpZy5hbmltYXRpb24mJnAobykuYWRkQ2xhc3MoVW4pO3ZhciBzPSJmdW5jdGlvbiI9PXR5cGVvZiB0aGlzLmNvbmZpZy5wbGFjZW1lbnQ/dGhpcy5jb25maWcucGxhY2VtZW50LmNhbGwodGhpcyxvLHRoaXMuZWxlbWVudCk6dGhpcy5jb25maWcucGxhY2VtZW50LGE9dGhpcy5fZ2V0QXR0YWNobWVudChzKTt0aGlzLmFkZEF0dGFjaG1lbnRDbGFzcyhhKTt2YXIgbD10aGlzLl9nZXRDb250YWluZXIoKTtwKG8pLmRhdGEodGhpcy5jb25zdHJ1Y3Rvci5EQVRBX0tFWSx0aGlzKSxwLmNvbnRhaW5zKHRoaXMuZWxlbWVudC5vd25lckRvY3VtZW50LmRvY3VtZW50RWxlbWVudCx0aGlzLnRpcCl8fHAobykuYXBwZW5kVG8obCkscCh0aGlzLmVsZW1lbnQpLnRyaWdnZXIodGhpcy5jb25zdHJ1Y3Rvci5FdmVudC5JTlNFUlRFRCksdGhpcy5fcG9wcGVyPW5ldyBTdCh0aGlzLmVsZW1lbnQsbyx0aGlzLl9nZXRQb3BwZXJDb25maWcoYSkpLHAobykuYWRkQ2xhc3MoQm4pLCJvbnRvdWNoc3RhcnQiaW4gZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50JiZwKGRvY3VtZW50LmJvZHkpLmNoaWxkcmVuKCkub24oIm1vdXNlb3ZlciIsbnVsbCxwLm5vb3ApO3ZhciBjPWZ1bmN0aW9uKCl7dC5jb25maWcuYW5pbWF0aW9uJiZ0Ll9maXhUcmFuc2l0aW9uKCk7dmFyIGU9dC5faG92ZXJTdGF0ZTt0Ll9ob3ZlclN0YXRlPW51bGwscCh0LmVsZW1lbnQpLnRyaWdnZXIodC5jb25zdHJ1Y3Rvci5FdmVudC5TSE9XTiksZT09PU1uJiZ0Ll9sZWF2ZShudWxsLHQpfTtpZihwKHRoaXMudGlwKS5oYXNDbGFzcyhVbikpe3ZhciBoPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy50aXApO3AodGhpcy50aXApLm9uZShtLlRSQU5TSVRJT05fRU5ELGMpLmVtdWxhdGVUcmFuc2l0aW9uRW5kKGgpfWVsc2UgYygpfX0sZS5oaWRlPWZ1bmN0aW9uKGUpe2Z1bmN0aW9uIHQoKXtuLl9ob3ZlclN0YXRlIT09Rm4mJmkucGFyZW50Tm9kZSYmaS5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKGkpLG4uX2NsZWFuVGlwQ2xhc3MoKSxuLmVsZW1lbnQucmVtb3ZlQXR0cmlidXRlKCJhcmlhLWRlc2NyaWJlZGJ5IikscChuLmVsZW1lbnQpLnRyaWdnZXIobi5jb25zdHJ1Y3Rvci5FdmVudC5ISURERU4pLG51bGwhPT1uLl9wb3BwZXImJm4uX3BvcHBlci5kZXN0cm95KCksZSYmZSgpfXZhciBuPXRoaXMsaT10aGlzLmdldFRpcEVsZW1lbnQoKSxvPXAuRXZlbnQodGhpcy5jb25zdHJ1Y3Rvci5FdmVudC5ISURFKTtpZihwKHRoaXMuZWxlbWVudCkudHJpZ2dlcihvKSwhby5pc0RlZmF1bHRQcmV2ZW50ZWQoKSl7aWYocChpKS5yZW1vdmVDbGFzcyhCbiksIm9udG91Y2hzdGFydCJpbiBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQmJnAoZG9jdW1lbnQuYm9keSkuY2hpbGRyZW4oKS5vZmYoIm1vdXNlb3ZlciIsbnVsbCxwLm5vb3ApLHRoaXMuX2FjdGl2ZVRyaWdnZXJbWW5dPSExLHRoaXMuX2FjdGl2ZVRyaWdnZXJbVm5dPSExLHRoaXMuX2FjdGl2ZVRyaWdnZXJbUW5dPSExLHAodGhpcy50aXApLmhhc0NsYXNzKFVuKSl7dmFyIHI9bS5nZXRUcmFuc2l0aW9uRHVyYXRpb25Gcm9tRWxlbWVudChpKTtwKGkpLm9uZShtLlRSQU5TSVRJT05fRU5ELHQpLmVtdWxhdGVUcmFuc2l0aW9uRW5kKHIpfWVsc2UgdCgpO3RoaXMuX2hvdmVyU3RhdGU9IiJ9fSxlLnVwZGF0ZT1mdW5jdGlvbigpe251bGwhPT10aGlzLl9wb3BwZXImJnRoaXMuX3BvcHBlci5zY2hlZHVsZVVwZGF0ZSgpfSxlLmlzV2l0aENvbnRlbnQ9ZnVuY3Rpb24oKXtyZXR1cm4gQm9vbGVhbih0aGlzLmdldFRpdGxlKCkpfSxlLmFkZEF0dGFjaG1lbnRDbGFzcz1mdW5jdGlvbihlKXtwKHRoaXMuZ2V0VGlwRWxlbWVudCgpKS5hZGRDbGFzcyhMbisiLSIrZSl9LGUuZ2V0VGlwRWxlbWVudD1mdW5jdGlvbigpe3JldHVybiB0aGlzLnRpcD10aGlzLnRpcHx8cCh0aGlzLmNvbmZpZy50ZW1wbGF0ZSlbMF0sdGhpcy50aXB9LGUuc2V0Q29udGVudD1mdW5jdGlvbigpe3ZhciBlPXRoaXMuZ2V0VGlwRWxlbWVudCgpO3RoaXMuc2V0RWxlbWVudENvbnRlbnQocChlLnF1ZXJ5U2VsZWN0b3JBbGwocW4pKSx0aGlzLmdldFRpdGxlKCkpLHAoZSkucmVtb3ZlQ2xhc3MoVW4rIiAiK0JuKX0sZS5zZXRFbGVtZW50Q29udGVudD1mdW5jdGlvbihlLHQpeyJvYmplY3QiIT10eXBlb2YgdHx8IXQubm9kZVR5cGUmJiF0LmpxdWVyeT90aGlzLmNvbmZpZy5odG1sPyh0aGlzLmNvbmZpZy5zYW5pdGl6ZSYmKHQ9SW4odCx0aGlzLmNvbmZpZy53aGl0ZUxpc3QsdGhpcy5jb25maWcuc2FuaXRpemVGbikpLGUuaHRtbCh0KSk6ZS50ZXh0KHQpOnRoaXMuY29uZmlnLmh0bWw/cCh0KS5wYXJlbnQoKS5pcyhlKXx8ZS5lbXB0eSgpLmFwcGVuZCh0KTplLnRleHQocCh0KS50ZXh0KCkpfSxlLmdldFRpdGxlPWZ1bmN0aW9uKCl7dmFyIGU9dGhpcy5lbGVtZW50LmdldEF0dHJpYnV0ZSgiZGF0YS1vcmlnaW5hbC10aXRsZSIpO3JldHVybiBlPWV8fCgiZnVuY3Rpb24iPT10eXBlb2YgdGhpcy5jb25maWcudGl0bGU/dGhpcy5jb25maWcudGl0bGUuY2FsbCh0aGlzLmVsZW1lbnQpOnRoaXMuY29uZmlnLnRpdGxlKX0sZS5fZ2V0UG9wcGVyQ29uZmlnPWZ1bmN0aW9uKGUpe3ZhciB0PXRoaXM7cmV0dXJuIGwoe30se3BsYWNlbWVudDplLG1vZGlmaWVyczp7b2Zmc2V0OnRoaXMuX2dldE9mZnNldCgpLGZsaXA6e2JlaGF2aW9yOnRoaXMuY29uZmlnLmZhbGxiYWNrUGxhY2VtZW50fSxhcnJvdzp7ZWxlbWVudDpLbn0scHJldmVudE92ZXJmbG93Ontib3VuZGFyaWVzRWxlbWVudDp0aGlzLmNvbmZpZy5ib3VuZGFyeX19LG9uQ3JlYXRlOmZ1bmN0aW9uKGUpe2Uub3JpZ2luYWxQbGFjZW1lbnQhPT1lLnBsYWNlbWVudCYmdC5faGFuZGxlUG9wcGVyUGxhY2VtZW50Q2hhbmdlKGUpfSxvblVwZGF0ZTpmdW5jdGlvbihlKXtyZXR1cm4gdC5faGFuZGxlUG9wcGVyUGxhY2VtZW50Q2hhbmdlKGUpfX0se30sdGhpcy5jb25maWcucG9wcGVyQ29uZmlnKX0sZS5fZ2V0T2Zmc2V0PWZ1bmN0aW9uKCl7dmFyIHQ9dGhpcyxlPXt9O3JldHVybiJmdW5jdGlvbiI9PXR5cGVvZiB0aGlzLmNvbmZpZy5vZmZzZXQ/ZS5mbj1mdW5jdGlvbihlKXtyZXR1cm4gZS5vZmZzZXRzPWwoe30sZS5vZmZzZXRzLHt9LHQuY29uZmlnLm9mZnNldChlLm9mZnNldHMsdC5lbGVtZW50KXx8e30pLGV9OmUub2Zmc2V0PXRoaXMuY29uZmlnLm9mZnNldCxlfSxlLl9nZXRDb250YWluZXI9ZnVuY3Rpb24oKXtyZXR1cm4hMT09PXRoaXMuY29uZmlnLmNvbnRhaW5lcj9kb2N1bWVudC5ib2R5Om0uaXNFbGVtZW50KHRoaXMuY29uZmlnLmNvbnRhaW5lcik/cCh0aGlzLmNvbmZpZy5jb250YWluZXIpOnAoZG9jdW1lbnQpLmZpbmQodGhpcy5jb25maWcuY29udGFpbmVyKX0sZS5fZ2V0QXR0YWNobWVudD1mdW5jdGlvbihlKXtyZXR1cm4gSG5bZS50b1VwcGVyQ2FzZSgpXX0sZS5fc2V0TGlzdGVuZXJzPWZ1bmN0aW9uKCl7dmFyIGk9dGhpczt0aGlzLmNvbmZpZy50cmlnZ2VyLnNwbGl0KCIgIikuZm9yRWFjaChmdW5jdGlvbihlKXtpZigiY2xpY2siPT09ZSlwKGkuZWxlbWVudCkub24oaS5jb25zdHJ1Y3Rvci5FdmVudC5DTElDSyxpLmNvbmZpZy5zZWxlY3RvcixmdW5jdGlvbihlKXtyZXR1cm4gaS50b2dnbGUoZSl9KTtlbHNlIGlmKGUhPT16bil7dmFyIHQ9ZT09PVFuP2kuY29uc3RydWN0b3IuRXZlbnQuTU9VU0VFTlRFUjppLmNvbnN0cnVjdG9yLkV2ZW50LkZPQ1VTSU4sbj1lPT09UW4/aS5jb25zdHJ1Y3Rvci5FdmVudC5NT1VTRUxFQVZFOmkuY29uc3RydWN0b3IuRXZlbnQuRk9DVVNPVVQ7cChpLmVsZW1lbnQpLm9uKHQsaS5jb25maWcuc2VsZWN0b3IsZnVuY3Rpb24oZSl7cmV0dXJuIGkuX2VudGVyKGUpfSkub24obixpLmNvbmZpZy5zZWxlY3RvcixmdW5jdGlvbihlKXtyZXR1cm4gaS5fbGVhdmUoZSl9KX19KSx0aGlzLl9oaWRlTW9kYWxIYW5kbGVyPWZ1bmN0aW9uKCl7aS5lbGVtZW50JiZpLmhpZGUoKX0scCh0aGlzLmVsZW1lbnQpLmNsb3Nlc3QoIi5tb2RhbCIpLm9uKCJoaWRlLmJzLm1vZGFsIix0aGlzLl9oaWRlTW9kYWxIYW5kbGVyKSx0aGlzLmNvbmZpZy5zZWxlY3Rvcj90aGlzLmNvbmZpZz1sKHt9LHRoaXMuY29uZmlnLHt0cmlnZ2VyOiJtYW51YWwiLHNlbGVjdG9yOiIifSk6dGhpcy5fZml4VGl0bGUoKX0sZS5fZml4VGl0bGU9ZnVuY3Rpb24oKXt2YXIgZT10eXBlb2YgdGhpcy5lbGVtZW50LmdldEF0dHJpYnV0ZSgiZGF0YS1vcmlnaW5hbC10aXRsZSIpOyF0aGlzLmVsZW1lbnQuZ2V0QXR0cmlidXRlKCJ0aXRsZSIpJiYic3RyaW5nIj09ZXx8KHRoaXMuZWxlbWVudC5zZXRBdHRyaWJ1dGUoImRhdGEtb3JpZ2luYWwtdGl0bGUiLHRoaXMuZWxlbWVudC5nZXRBdHRyaWJ1dGUoInRpdGxlIil8fCIiKSx0aGlzLmVsZW1lbnQuc2V0QXR0cmlidXRlKCJ0aXRsZSIsIiIpKX0sZS5fZW50ZXI9ZnVuY3Rpb24oZSx0KXt2YXIgbj10aGlzLmNvbnN0cnVjdG9yLkRBVEFfS0VZOyh0PXR8fHAoZS5jdXJyZW50VGFyZ2V0KS5kYXRhKG4pKXx8KHQ9bmV3IHRoaXMuY29uc3RydWN0b3IoZS5jdXJyZW50VGFyZ2V0LHRoaXMuX2dldERlbGVnYXRlQ29uZmlnKCkpLHAoZS5jdXJyZW50VGFyZ2V0KS5kYXRhKG4sdCkpLGUmJih0Ll9hY3RpdmVUcmlnZ2VyWyJmb2N1c2luIj09PWUudHlwZT9WbjpRbl09ITApLHAodC5nZXRUaXBFbGVtZW50KCkpLmhhc0NsYXNzKEJuKXx8dC5faG92ZXJTdGF0ZT09PUZuP3QuX2hvdmVyU3RhdGU9Rm46KGNsZWFyVGltZW91dCh0Ll90aW1lb3V0KSx0Ll9ob3ZlclN0YXRlPUZuLHQuY29uZmlnLmRlbGF5JiZ0LmNvbmZpZy5kZWxheS5zaG93P3QuX3RpbWVvdXQ9c2V0VGltZW91dChmdW5jdGlvbigpe3QuX2hvdmVyU3RhdGU9PT1GbiYmdC5zaG93KCl9LHQuY29uZmlnLmRlbGF5LnNob3cpOnQuc2hvdygpKX0sZS5fbGVhdmU9ZnVuY3Rpb24oZSx0KXt2YXIgbj10aGlzLmNvbnN0cnVjdG9yLkRBVEFfS0VZOyh0PXR8fHAoZS5jdXJyZW50VGFyZ2V0KS5kYXRhKG4pKXx8KHQ9bmV3IHRoaXMuY29uc3RydWN0b3IoZS5jdXJyZW50VGFyZ2V0LHRoaXMuX2dldERlbGVnYXRlQ29uZmlnKCkpLHAoZS5jdXJyZW50VGFyZ2V0KS5kYXRhKG4sdCkpLGUmJih0Ll9hY3RpdmVUcmlnZ2VyWyJmb2N1c291dCI9PT1lLnR5cGU/Vm46UW5dPSExKSx0Ll9pc1dpdGhBY3RpdmVUcmlnZ2VyKCl8fChjbGVhclRpbWVvdXQodC5fdGltZW91dCksdC5faG92ZXJTdGF0ZT1Nbix0LmNvbmZpZy5kZWxheSYmdC5jb25maWcuZGVsYXkuaGlkZT90Ll90aW1lb3V0PXNldFRpbWVvdXQoZnVuY3Rpb24oKXt0Ll9ob3ZlclN0YXRlPT09TW4mJnQuaGlkZSgpfSx0LmNvbmZpZy5kZWxheS5oaWRlKTp0LmhpZGUoKSl9LGUuX2lzV2l0aEFjdGl2ZVRyaWdnZXI9ZnVuY3Rpb24oKXtmb3IodmFyIGUgaW4gdGhpcy5fYWN0aXZlVHJpZ2dlcilpZih0aGlzLl9hY3RpdmVUcmlnZ2VyW2VdKXJldHVybiEwO3JldHVybiExfSxlLl9nZXRDb25maWc9ZnVuY3Rpb24oZSl7dmFyIHQ9cCh0aGlzLmVsZW1lbnQpLmRhdGEoKTtyZXR1cm4gT2JqZWN0LmtleXModCkuZm9yRWFjaChmdW5jdGlvbihlKXstMSE9PXhuLmluZGV4T2YoZSkmJmRlbGV0ZSB0W2VdfSksIm51bWJlciI9PXR5cGVvZihlPWwoe30sdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0LHt9LHQse30sIm9iamVjdCI9PXR5cGVvZiBlJiZlP2U6e30pKS5kZWxheSYmKGUuZGVsYXk9e3Nob3c6ZS5kZWxheSxoaWRlOmUuZGVsYXl9KSwibnVtYmVyIj09dHlwZW9mIGUudGl0bGUmJihlLnRpdGxlPWUudGl0bGUudG9TdHJpbmcoKSksIm51bWJlciI9PXR5cGVvZiBlLmNvbnRlbnQmJihlLmNvbnRlbnQ9ZS5jb250ZW50LnRvU3RyaW5nKCkpLG0udHlwZUNoZWNrQ29uZmlnKEFuLGUsdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0VHlwZSksZS5zYW5pdGl6ZSYmKGUudGVtcGxhdGU9SW4oZS50ZW1wbGF0ZSxlLndoaXRlTGlzdCxlLnNhbml0aXplRm4pKSxlfSxlLl9nZXREZWxlZ2F0ZUNvbmZpZz1mdW5jdGlvbigpe3ZhciBlPXt9O2lmKHRoaXMuY29uZmlnKWZvcih2YXIgdCBpbiB0aGlzLmNvbmZpZyl0aGlzLmNvbnN0cnVjdG9yLkRlZmF1bHRbdF0hPT10aGlzLmNvbmZpZ1t0XSYmKGVbdF09dGhpcy5jb25maWdbdF0pO3JldHVybiBlfSxlLl9jbGVhblRpcENsYXNzPWZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzLmdldFRpcEVsZW1lbnQoKSksdD1lLmF0dHIoImNsYXNzIikubWF0Y2goUG4pO251bGwhPT10JiZ0Lmxlbmd0aCYmZS5yZW1vdmVDbGFzcyh0LmpvaW4oIiIpKX0sZS5faGFuZGxlUG9wcGVyUGxhY2VtZW50Q2hhbmdlPWZ1bmN0aW9uKGUpe3ZhciB0PWUuaW5zdGFuY2U7dGhpcy50aXA9dC5wb3BwZXIsdGhpcy5fY2xlYW5UaXBDbGFzcygpLHRoaXMuYWRkQXR0YWNobWVudENsYXNzKHRoaXMuX2dldEF0dGFjaG1lbnQoZS5wbGFjZW1lbnQpKX0sZS5fZml4VHJhbnNpdGlvbj1mdW5jdGlvbigpe3ZhciBlPXRoaXMuZ2V0VGlwRWxlbWVudCgpLHQ9dGhpcy5jb25maWcuYW5pbWF0aW9uO251bGw9PT1lLmdldEF0dHJpYnV0ZSgieC1wbGFjZW1lbnQiKSYmKHAoZSkucmVtb3ZlQ2xhc3MoVW4pLHRoaXMuY29uZmlnLmFuaW1hdGlvbj0hMSx0aGlzLmhpZGUoKSx0aGlzLnNob3coKSx0aGlzLmNvbmZpZy5hbmltYXRpb249dCl9LGkuX2pRdWVyeUludGVyZmFjZT1mdW5jdGlvbihuKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzKS5kYXRhKE9uKSx0PSJvYmplY3QiPT10eXBlb2YgbiYmbjtpZigoZXx8IS9kaXNwb3NlfGhpZGUvLnRlc3QobikpJiYoZXx8KGU9bmV3IGkodGhpcyx0KSxwKHRoaXMpLmRhdGEoT24sZSkpLCJzdHJpbmciPT10eXBlb2Ygbikpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgZVtuXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrbisnIicpO2Vbbl0oKX19KX0scyhpLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fSx7a2V5OiJEZWZhdWx0IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gUm59fSx7a2V5OiJOQU1FIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gQW59fSx7a2V5OiJEQVRBX0tFWSIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIE9ufX0se2tleToiRXZlbnQiLGdldDpmdW5jdGlvbigpe3JldHVybiBXbn19LHtrZXk6IkVWRU5UX0tFWSIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIE5ufX0se2tleToiRGVmYXVsdFR5cGUiLGdldDpmdW5jdGlvbigpe3JldHVybiBqbn19XSksaX0oKTtwLmZuW0FuXT1Ybi5falF1ZXJ5SW50ZXJmYWNlLHAuZm5bQW5dLkNvbnN0cnVjdG9yPVhuLHAuZm5bQW5dLm5vQ29uZmxpY3Q9ZnVuY3Rpb24oKXtyZXR1cm4gcC5mbltBbl09a24sWG4uX2pRdWVyeUludGVyZmFjZX07dmFyIEduPSJwb3BvdmVyIiwkbj0iYnMucG9wb3ZlciIsSm49Ii4iKyRuLFpuPXAuZm5bR25dLGVpPSJicy1wb3BvdmVyIix0aT1uZXcgUmVnRXhwKCIoXnxcXHMpIitlaSsiXFxTKyIsImciKSxuaT1sKHt9LFhuLkRlZmF1bHQse3BsYWNlbWVudDoicmlnaHQiLHRyaWdnZXI6ImNsaWNrIixjb250ZW50OiIiLHRlbXBsYXRlOic8ZGl2IGNsYXNzPSJwb3BvdmVyIiByb2xlPSJ0b29sdGlwIj48ZGl2IGNsYXNzPSJhcnJvdyI+PC9kaXY+PGgzIGNsYXNzPSJwb3BvdmVyLWhlYWRlciI+PC9oMz48ZGl2IGNsYXNzPSJwb3BvdmVyLWJvZHkiPjwvZGl2PjwvZGl2Pid9KSxpaT1sKHt9LFhuLkRlZmF1bHRUeXBlLHtjb250ZW50OiIoc3RyaW5nfGVsZW1lbnR8ZnVuY3Rpb24pIn0pLG9pPSJmYWRlIixyaT0ic2hvdyIsc2k9Ii5wb3BvdmVyLWhlYWRlciIsYWk9Ii5wb3BvdmVyLWJvZHkiLGxpPXtISURFOiJoaWRlIitKbixISURERU46ImhpZGRlbiIrSm4sU0hPVzoic2hvdyIrSm4sU0hPV046InNob3duIitKbixJTlNFUlRFRDoiaW5zZXJ0ZWQiK0puLENMSUNLOiJjbGljayIrSm4sRk9DVVNJTjoiZm9jdXNpbiIrSm4sRk9DVVNPVVQ6ImZvY3Vzb3V0IitKbixNT1VTRUVOVEVSOiJtb3VzZWVudGVyIitKbixNT1VTRUxFQVZFOiJtb3VzZWxlYXZlIitKbn0sY2k9ZnVuY3Rpb24oZSl7ZnVuY3Rpb24gaSgpe3JldHVybiBlLmFwcGx5KHRoaXMsYXJndW1lbnRzKXx8dGhpc30hZnVuY3Rpb24oZSx0KXtlLnByb3RvdHlwZT1PYmplY3QuY3JlYXRlKHQucHJvdG90eXBlKSwoZS5wcm90b3R5cGUuY29uc3RydWN0b3I9ZSkuX19wcm90b19fPXR9KGksZSk7dmFyIHQ9aS5wcm90b3R5cGU7cmV0dXJuIHQuaXNXaXRoQ29udGVudD1mdW5jdGlvbigpe3JldHVybiB0aGlzLmdldFRpdGxlKCl8fHRoaXMuX2dldENvbnRlbnQoKX0sdC5hZGRBdHRhY2htZW50Q2xhc3M9ZnVuY3Rpb24oZSl7cCh0aGlzLmdldFRpcEVsZW1lbnQoKSkuYWRkQ2xhc3MoZWkrIi0iK2UpfSx0LmdldFRpcEVsZW1lbnQ9ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy50aXA9dGhpcy50aXB8fHAodGhpcy5jb25maWcudGVtcGxhdGUpWzBdLHRoaXMudGlwfSx0LnNldENvbnRlbnQ9ZnVuY3Rpb24oKXt2YXIgZT1wKHRoaXMuZ2V0VGlwRWxlbWVudCgpKTt0aGlzLnNldEVsZW1lbnRDb250ZW50KGUuZmluZChzaSksdGhpcy5nZXRUaXRsZSgpKTt2YXIgdD10aGlzLl9nZXRDb250ZW50KCk7ImZ1bmN0aW9uIj09dHlwZW9mIHQmJih0PXQuY2FsbCh0aGlzLmVsZW1lbnQpKSx0aGlzLnNldEVsZW1lbnRDb250ZW50KGUuZmluZChhaSksdCksZS5yZW1vdmVDbGFzcyhvaSsiICIrcmkpfSx0Ll9nZXRDb250ZW50PWZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMuZWxlbWVudC5nZXRBdHRyaWJ1dGUoImRhdGEtY29udGVudCIpfHx0aGlzLmNvbmZpZy5jb250ZW50fSx0Ll9jbGVhblRpcENsYXNzPWZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzLmdldFRpcEVsZW1lbnQoKSksdD1lLmF0dHIoImNsYXNzIikubWF0Y2godGkpO251bGwhPT10JiYwPHQubGVuZ3RoJiZlLnJlbW92ZUNsYXNzKHQuam9pbigiIikpfSxpLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24obil7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciBlPXAodGhpcykuZGF0YSgkbiksdD0ib2JqZWN0Ij09dHlwZW9mIG4/bjpudWxsO2lmKChlfHwhL2Rpc3Bvc2V8aGlkZS8udGVzdChuKSkmJihlfHwoZT1uZXcgaSh0aGlzLHQpLHAodGhpcykuZGF0YSgkbixlKSksInN0cmluZyI9PXR5cGVvZiBuKSl7aWYoInVuZGVmaW5lZCI9PXR5cGVvZiBlW25dKXRocm93IG5ldyBUeXBlRXJyb3IoJ05vIG1ldGhvZCBuYW1lZCAiJytuKyciJyk7ZVtuXSgpfX0pfSxzKGksbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBuaX19LHtrZXk6Ik5BTUUiLGdldDpmdW5jdGlvbigpe3JldHVybiBHbn19LHtrZXk6IkRBVEFfS0VZIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gJG59fSx7a2V5OiJFdmVudCIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIGxpfX0se2tleToiRVZFTlRfS0VZIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gSm59fSx7a2V5OiJEZWZhdWx0VHlwZSIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIGlpfX1dKSxpfShYbik7cC5mbltHbl09Y2kuX2pRdWVyeUludGVyZmFjZSxwLmZuW0duXS5Db25zdHJ1Y3Rvcj1jaSxwLmZuW0duXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm5bR25dPVpuLGNpLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciBoaT0ic2Nyb2xsc3B5Iix1aT0iYnMuc2Nyb2xsc3B5IixmaT0iLiIrdWksZGk9cC5mbltoaV0scGk9e29mZnNldDoxMCxtZXRob2Q6ImF1dG8iLHRhcmdldDoiIn0sbWk9e29mZnNldDoibnVtYmVyIixtZXRob2Q6InN0cmluZyIsdGFyZ2V0OiIoc3RyaW5nfGVsZW1lbnQpIn0sZ2k9e0FDVElWQVRFOiJhY3RpdmF0ZSIrZmksU0NST0xMOiJzY3JvbGwiK2ZpLExPQURfREFUQV9BUEk6ImxvYWQiK2ZpKyIuZGF0YS1hcGkifSxfaT0iZHJvcGRvd24taXRlbSIsdmk9ImFjdGl2ZSIseWk9J1tkYXRhLXNweT0ic2Nyb2xsIl0nLEVpPSIubmF2LCAubGlzdC1ncm91cCIsYmk9Ii5uYXYtbGluayIsd2k9Ii5uYXYtaXRlbSIsVGk9Ii5saXN0LWdyb3VwLWl0ZW0iLENpPSIuZHJvcGRvd24iLFNpPSIuZHJvcGRvd24taXRlbSIsRGk9Ii5kcm9wZG93bi10b2dnbGUiLElpPSJvZmZzZXQiLEFpPSJwb3NpdGlvbiIsT2k9ZnVuY3Rpb24oKXtmdW5jdGlvbiBuKGUsdCl7dmFyIG49dGhpczt0aGlzLl9lbGVtZW50PWUsdGhpcy5fc2Nyb2xsRWxlbWVudD0iQk9EWSI9PT1lLnRhZ05hbWU/d2luZG93OmUsdGhpcy5fY29uZmlnPXRoaXMuX2dldENvbmZpZyh0KSx0aGlzLl9zZWxlY3Rvcj10aGlzLl9jb25maWcudGFyZ2V0KyIgIitiaSsiLCIrdGhpcy5fY29uZmlnLnRhcmdldCsiICIrVGkrIiwiK3RoaXMuX2NvbmZpZy50YXJnZXQrIiAiK1NpLHRoaXMuX29mZnNldHM9W10sdGhpcy5fdGFyZ2V0cz1bXSx0aGlzLl9hY3RpdmVUYXJnZXQ9bnVsbCx0aGlzLl9zY3JvbGxIZWlnaHQ9MCxwKHRoaXMuX3Njcm9sbEVsZW1lbnQpLm9uKGdpLlNDUk9MTCxmdW5jdGlvbihlKXtyZXR1cm4gbi5fcHJvY2VzcyhlKX0pLHRoaXMucmVmcmVzaCgpLHRoaXMuX3Byb2Nlc3MoKX12YXIgZT1uLnByb3RvdHlwZTtyZXR1cm4gZS5yZWZyZXNoPWZ1bmN0aW9uKCl7dmFyIHQ9dGhpcyxlPXRoaXMuX3Njcm9sbEVsZW1lbnQ9PT10aGlzLl9zY3JvbGxFbGVtZW50LndpbmRvdz9JaTpBaSxvPSJhdXRvIj09PXRoaXMuX2NvbmZpZy5tZXRob2Q/ZTp0aGlzLl9jb25maWcubWV0aG9kLHI9bz09PUFpP3RoaXMuX2dldFNjcm9sbFRvcCgpOjA7dGhpcy5fb2Zmc2V0cz1bXSx0aGlzLl90YXJnZXRzPVtdLHRoaXMuX3Njcm9sbEhlaWdodD10aGlzLl9nZXRTY3JvbGxIZWlnaHQoKSxbXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwodGhpcy5fc2VsZWN0b3IpKS5tYXAoZnVuY3Rpb24oZSl7dmFyIHQsbj1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQoZSk7aWYobiYmKHQ9ZG9jdW1lbnQucXVlcnlTZWxlY3RvcihuKSksdCl7dmFyIGk9dC5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKTtpZihpLndpZHRofHxpLmhlaWdodClyZXR1cm5bcCh0KVtvXSgpLnRvcCtyLG5dfXJldHVybiBudWxsfSkuZmlsdGVyKGZ1bmN0aW9uKGUpe3JldHVybiBlfSkuc29ydChmdW5jdGlvbihlLHQpe3JldHVybiBlWzBdLXRbMF19KS5mb3JFYWNoKGZ1bmN0aW9uKGUpe3QuX29mZnNldHMucHVzaChlWzBdKSx0Ll90YXJnZXRzLnB1c2goZVsxXSl9KX0sZS5kaXNwb3NlPWZ1bmN0aW9uKCl7cC5yZW1vdmVEYXRhKHRoaXMuX2VsZW1lbnQsdWkpLHAodGhpcy5fc2Nyb2xsRWxlbWVudCkub2ZmKGZpKSx0aGlzLl9lbGVtZW50PW51bGwsdGhpcy5fc2Nyb2xsRWxlbWVudD1udWxsLHRoaXMuX2NvbmZpZz1udWxsLHRoaXMuX3NlbGVjdG9yPW51bGwsdGhpcy5fb2Zmc2V0cz1udWxsLHRoaXMuX3RhcmdldHM9bnVsbCx0aGlzLl9hY3RpdmVUYXJnZXQ9bnVsbCx0aGlzLl9zY3JvbGxIZWlnaHQ9bnVsbH0sZS5fZ2V0Q29uZmlnPWZ1bmN0aW9uKGUpe2lmKCJzdHJpbmciIT10eXBlb2YoZT1sKHt9LHBpLHt9LCJvYmplY3QiPT10eXBlb2YgZSYmZT9lOnt9KSkudGFyZ2V0KXt2YXIgdD1wKGUudGFyZ2V0KS5hdHRyKCJpZCIpO3R8fCh0PW0uZ2V0VUlEKGhpKSxwKGUudGFyZ2V0KS5hdHRyKCJpZCIsdCkpLGUudGFyZ2V0PSIjIit0fXJldHVybiBtLnR5cGVDaGVja0NvbmZpZyhoaSxlLG1pKSxlfSxlLl9nZXRTY3JvbGxUb3A9ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5fc2Nyb2xsRWxlbWVudD09PXdpbmRvdz90aGlzLl9zY3JvbGxFbGVtZW50LnBhZ2VZT2Zmc2V0OnRoaXMuX3Njcm9sbEVsZW1lbnQuc2Nyb2xsVG9wfSxlLl9nZXRTY3JvbGxIZWlnaHQ9ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5fc2Nyb2xsRWxlbWVudC5zY3JvbGxIZWlnaHR8fE1hdGgubWF4KGRvY3VtZW50LmJvZHkuc2Nyb2xsSGVpZ2h0LGRvY3VtZW50LmRvY3VtZW50RWxlbWVudC5zY3JvbGxIZWlnaHQpfSxlLl9nZXRPZmZzZXRIZWlnaHQ9ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5fc2Nyb2xsRWxlbWVudD09PXdpbmRvdz93aW5kb3cuaW5uZXJIZWlnaHQ6dGhpcy5fc2Nyb2xsRWxlbWVudC5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKS5oZWlnaHR9LGUuX3Byb2Nlc3M9ZnVuY3Rpb24oKXt2YXIgZT10aGlzLl9nZXRTY3JvbGxUb3AoKSt0aGlzLl9jb25maWcub2Zmc2V0LHQ9dGhpcy5fZ2V0U2Nyb2xsSGVpZ2h0KCksbj10aGlzLl9jb25maWcub2Zmc2V0K3QtdGhpcy5fZ2V0T2Zmc2V0SGVpZ2h0KCk7aWYodGhpcy5fc2Nyb2xsSGVpZ2h0IT09dCYmdGhpcy5yZWZyZXNoKCksbjw9ZSl7dmFyIGk9dGhpcy5fdGFyZ2V0c1t0aGlzLl90YXJnZXRzLmxlbmd0aC0xXTt0aGlzLl9hY3RpdmVUYXJnZXQhPT1pJiZ0aGlzLl9hY3RpdmF0ZShpKX1lbHNle2lmKHRoaXMuX2FjdGl2ZVRhcmdldCYmZTx0aGlzLl9vZmZzZXRzWzBdJiYwPHRoaXMuX29mZnNldHNbMF0pcmV0dXJuIHRoaXMuX2FjdGl2ZVRhcmdldD1udWxsLHZvaWQgdGhpcy5fY2xlYXIoKTtmb3IodmFyIG89dGhpcy5fb2Zmc2V0cy5sZW5ndGg7by0tOyl7dGhpcy5fYWN0aXZlVGFyZ2V0IT09dGhpcy5fdGFyZ2V0c1tvXSYmZT49dGhpcy5fb2Zmc2V0c1tvXSYmKCJ1bmRlZmluZWQiPT10eXBlb2YgdGhpcy5fb2Zmc2V0c1tvKzFdfHxlPHRoaXMuX29mZnNldHNbbysxXSkmJnRoaXMuX2FjdGl2YXRlKHRoaXMuX3RhcmdldHNbb10pfX19LGUuX2FjdGl2YXRlPWZ1bmN0aW9uKHQpe3RoaXMuX2FjdGl2ZVRhcmdldD10LHRoaXMuX2NsZWFyKCk7dmFyIGU9dGhpcy5fc2VsZWN0b3Iuc3BsaXQoIiwiKS5tYXAoZnVuY3Rpb24oZSl7cmV0dXJuIGUrJ1tkYXRhLXRhcmdldD0iJyt0KyciXSwnK2UrJ1tocmVmPSInK3QrJyJdJ30pLG49cChbXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoZS5qb2luKCIsIikpKSk7bi5oYXNDbGFzcyhfaSk/KG4uY2xvc2VzdChDaSkuZmluZChEaSkuYWRkQ2xhc3ModmkpLG4uYWRkQ2xhc3ModmkpKToobi5hZGRDbGFzcyh2aSksbi5wYXJlbnRzKEVpKS5wcmV2KGJpKyIsICIrVGkpLmFkZENsYXNzKHZpKSxuLnBhcmVudHMoRWkpLnByZXYod2kpLmNoaWxkcmVuKGJpKS5hZGRDbGFzcyh2aSkpLHAodGhpcy5fc2Nyb2xsRWxlbWVudCkudHJpZ2dlcihnaS5BQ1RJVkFURSx7cmVsYXRlZFRhcmdldDp0fSl9LGUuX2NsZWFyPWZ1bmN0aW9uKCl7W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKHRoaXMuX3NlbGVjdG9yKSkuZmlsdGVyKGZ1bmN0aW9uKGUpe3JldHVybiBlLmNsYXNzTGlzdC5jb250YWlucyh2aSl9KS5mb3JFYWNoKGZ1bmN0aW9uKGUpe3JldHVybiBlLmNsYXNzTGlzdC5yZW1vdmUodmkpfSl9LG4uX2pRdWVyeUludGVyZmFjZT1mdW5jdGlvbih0KXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIGU9cCh0aGlzKS5kYXRhKHVpKTtpZihlfHwoZT1uZXcgbih0aGlzLCJvYmplY3QiPT10eXBlb2YgdCYmdCkscCh0aGlzKS5kYXRhKHVpLGUpKSwic3RyaW5nIj09dHlwZW9mIHQpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgZVt0XSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrdCsnIicpO2VbdF0oKX19KX0scyhuLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fSx7a2V5OiJEZWZhdWx0IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gcGl9fV0pLG59KCk7cCh3aW5kb3cpLm9uKGdpLkxPQURfREFUQV9BUEksZnVuY3Rpb24oKXtmb3IodmFyIGU9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKHlpKSksdD1lLmxlbmd0aDt0LS07KXt2YXIgbj1wKGVbdF0pO09pLl9qUXVlcnlJbnRlcmZhY2UuY2FsbChuLG4uZGF0YSgpKX19KSxwLmZuW2hpXT1PaS5falF1ZXJ5SW50ZXJmYWNlLHAuZm5baGldLkNvbnN0cnVjdG9yPU9pLHAuZm5baGldLm5vQ29uZmxpY3Q9ZnVuY3Rpb24oKXtyZXR1cm4gcC5mbltoaV09ZGksT2kuX2pRdWVyeUludGVyZmFjZX07dmFyIE5pPSJicy50YWIiLGtpPSIuIitOaSxMaT1wLmZuLnRhYixQaT17SElERToiaGlkZSIra2ksSElEREVOOiJoaWRkZW4iK2tpLFNIT1c6InNob3ciK2tpLFNIT1dOOiJzaG93biIra2ksQ0xJQ0tfREFUQV9BUEk6ImNsaWNrIitraSsiLmRhdGEtYXBpIn0seGk9ImRyb3Bkb3duLW1lbnUiLGppPSJhY3RpdmUiLEhpPSJkaXNhYmxlZCIsUmk9ImZhZGUiLEZpPSJzaG93IixNaT0iLmRyb3Bkb3duIixXaT0iLm5hdiwgLmxpc3QtZ3JvdXAiLFVpPSIuYWN0aXZlIixCaT0iPiBsaSA+IC5hY3RpdmUiLHFpPSdbZGF0YS10b2dnbGU9InRhYiJdLCBbZGF0YS10b2dnbGU9InBpbGwiXSwgW2RhdGEtdG9nZ2xlPSJsaXN0Il0nLEtpPSIuZHJvcGRvd24tdG9nZ2xlIixRaT0iPiAuZHJvcGRvd24tbWVudSAuYWN0aXZlIixWaT1mdW5jdGlvbigpe2Z1bmN0aW9uIGkoZSl7dGhpcy5fZWxlbWVudD1lfXZhciBlPWkucHJvdG90eXBlO3JldHVybiBlLnNob3c9ZnVuY3Rpb24oKXt2YXIgbj10aGlzO2lmKCEodGhpcy5fZWxlbWVudC5wYXJlbnROb2RlJiZ0aGlzLl9lbGVtZW50LnBhcmVudE5vZGUubm9kZVR5cGU9PT1Ob2RlLkVMRU1FTlRfTk9ERSYmcCh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyhqaSl8fHAodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoSGkpKSl7dmFyIGUsaSx0PXAodGhpcy5fZWxlbWVudCkuY2xvc2VzdChXaSlbMF0sbz1tLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7aWYodCl7dmFyIHI9IlVMIj09PXQubm9kZU5hbWV8fCJPTCI9PT10Lm5vZGVOYW1lP0JpOlVpO2k9KGk9cC5tYWtlQXJyYXkocCh0KS5maW5kKHIpKSlbaS5sZW5ndGgtMV19dmFyIHM9cC5FdmVudChQaS5ISURFLHtyZWxhdGVkVGFyZ2V0OnRoaXMuX2VsZW1lbnR9KSxhPXAuRXZlbnQoUGkuU0hPVyx7cmVsYXRlZFRhcmdldDppfSk7aWYoaSYmcChpKS50cmlnZ2VyKHMpLHAodGhpcy5fZWxlbWVudCkudHJpZ2dlcihhKSwhYS5pc0RlZmF1bHRQcmV2ZW50ZWQoKSYmIXMuaXNEZWZhdWx0UHJldmVudGVkKCkpe28mJihlPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IobykpLHRoaXMuX2FjdGl2YXRlKHRoaXMuX2VsZW1lbnQsdCk7dmFyIGw9ZnVuY3Rpb24oKXt2YXIgZT1wLkV2ZW50KFBpLkhJRERFTix7cmVsYXRlZFRhcmdldDpuLl9lbGVtZW50fSksdD1wLkV2ZW50KFBpLlNIT1dOLHtyZWxhdGVkVGFyZ2V0Oml9KTtwKGkpLnRyaWdnZXIoZSkscChuLl9lbGVtZW50KS50cmlnZ2VyKHQpfTtlP3RoaXMuX2FjdGl2YXRlKGUsZS5wYXJlbnROb2RlLGwpOmwoKX19fSxlLmRpc3Bvc2U9ZnVuY3Rpb24oKXtwLnJlbW92ZURhdGEodGhpcy5fZWxlbWVudCxOaSksdGhpcy5fZWxlbWVudD1udWxsfSxlLl9hY3RpdmF0ZT1mdW5jdGlvbihlLHQsbil7ZnVuY3Rpb24gaSgpe3JldHVybiBvLl90cmFuc2l0aW9uQ29tcGxldGUoZSxyLG4pfXZhciBvPXRoaXMscj0oIXR8fCJVTCIhPT10Lm5vZGVOYW1lJiYiT0wiIT09dC5ub2RlTmFtZT9wKHQpLmNoaWxkcmVuKFVpKTpwKHQpLmZpbmQoQmkpKVswXSxzPW4mJnImJnAocikuaGFzQ2xhc3MoUmkpO2lmKHImJnMpe3ZhciBhPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQocik7cChyKS5yZW1vdmVDbGFzcyhGaSkub25lKG0uVFJBTlNJVElPTl9FTkQsaSkuZW11bGF0ZVRyYW5zaXRpb25FbmQoYSl9ZWxzZSBpKCl9LGUuX3RyYW5zaXRpb25Db21wbGV0ZT1mdW5jdGlvbihlLHQsbil7aWYodCl7cCh0KS5yZW1vdmVDbGFzcyhqaSk7dmFyIGk9cCh0LnBhcmVudE5vZGUpLmZpbmQoUWkpWzBdO2kmJnAoaSkucmVtb3ZlQ2xhc3MoamkpLCJ0YWIiPT09dC5nZXRBdHRyaWJ1dGUoInJvbGUiKSYmdC5zZXRBdHRyaWJ1dGUoImFyaWEtc2VsZWN0ZWQiLCExKX1pZihwKGUpLmFkZENsYXNzKGppKSwidGFiIj09PWUuZ2V0QXR0cmlidXRlKCJyb2xlIikmJmUuc2V0QXR0cmlidXRlKCJhcmlhLXNlbGVjdGVkIiwhMCksbS5yZWZsb3coZSksZS5jbGFzc0xpc3QuY29udGFpbnMoUmkpJiZlLmNsYXNzTGlzdC5hZGQoRmkpLGUucGFyZW50Tm9kZSYmcChlLnBhcmVudE5vZGUpLmhhc0NsYXNzKHhpKSl7dmFyIG89cChlKS5jbG9zZXN0KE1pKVswXTtpZihvKXt2YXIgcj1bXS5zbGljZS5jYWxsKG8ucXVlcnlTZWxlY3RvckFsbChLaSkpO3AocikuYWRkQ2xhc3MoamkpfWUuc2V0QXR0cmlidXRlKCJhcmlhLWV4cGFuZGVkIiwhMCl9biYmbigpfSxpLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24obil7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciBlPXAodGhpcyksdD1lLmRhdGEoTmkpO2lmKHR8fCh0PW5ldyBpKHRoaXMpLGUuZGF0YShOaSx0KSksInN0cmluZyI9PXR5cGVvZiBuKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIHRbbl0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK24rJyInKTt0W25dKCl9fSl9LHMoaSxudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX1dKSxpfSgpO3AoZG9jdW1lbnQpLm9uKFBpLkNMSUNLX0RBVEFfQVBJLHFpLGZ1bmN0aW9uKGUpe2UucHJldmVudERlZmF1bHQoKSxWaS5falF1ZXJ5SW50ZXJmYWNlLmNhbGwocCh0aGlzKSwic2hvdyIpfSkscC5mbi50YWI9VmkuX2pRdWVyeUludGVyZmFjZSxwLmZuLnRhYi5Db25zdHJ1Y3Rvcj1WaSxwLmZuLnRhYi5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIHAuZm4udGFiPUxpLFZpLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciBZaT0idG9hc3QiLHppPSJicy50b2FzdCIsWGk9Ii4iK3ppLEdpPXAuZm5bWWldLCRpPXtDTElDS19ESVNNSVNTOiJjbGljay5kaXNtaXNzIitYaSxISURFOiJoaWRlIitYaSxISURERU46ImhpZGRlbiIrWGksU0hPVzoic2hvdyIrWGksU0hPV046InNob3duIitYaX0sSmk9ImZhZGUiLFppPSJoaWRlIixlbz0ic2hvdyIsdG89InNob3dpbmciLG5vPXthbmltYXRpb246ImJvb2xlYW4iLGF1dG9oaWRlOiJib29sZWFuIixkZWxheToibnVtYmVyIn0saW89e2FuaW1hdGlvbjohMCxhdXRvaGlkZTohMCxkZWxheTo1MDB9LG9vPSdbZGF0YS1kaXNtaXNzPSJ0b2FzdCJdJyxybz1mdW5jdGlvbigpe2Z1bmN0aW9uIGkoZSx0KXt0aGlzLl9lbGVtZW50PWUsdGhpcy5fY29uZmlnPXRoaXMuX2dldENvbmZpZyh0KSx0aGlzLl90aW1lb3V0PW51bGwsdGhpcy5fc2V0TGlzdGVuZXJzKCl9dmFyIGU9aS5wcm90b3R5cGU7cmV0dXJuIGUuc2hvdz1mdW5jdGlvbigpe3ZhciBlPXRoaXMsdD1wLkV2ZW50KCRpLlNIT1cpO2lmKHAodGhpcy5fZWxlbWVudCkudHJpZ2dlcih0KSwhdC5pc0RlZmF1bHRQcmV2ZW50ZWQoKSl7dGhpcy5fY29uZmlnLmFuaW1hdGlvbiYmdGhpcy5fZWxlbWVudC5jbGFzc0xpc3QuYWRkKEppKTt2YXIgbj1mdW5jdGlvbigpe2UuX2VsZW1lbnQuY2xhc3NMaXN0LnJlbW92ZSh0byksZS5fZWxlbWVudC5jbGFzc0xpc3QuYWRkKGVvKSxwKGUuX2VsZW1lbnQpLnRyaWdnZXIoJGkuU0hPV04pLGUuX2NvbmZpZy5hdXRvaGlkZSYmKGUuX3RpbWVvdXQ9c2V0VGltZW91dChmdW5jdGlvbigpe2UuaGlkZSgpfSxlLl9jb25maWcuZGVsYXkpKX07aWYodGhpcy5fZWxlbWVudC5jbGFzc0xpc3QucmVtb3ZlKFppKSxtLnJlZmxvdyh0aGlzLl9lbGVtZW50KSx0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5hZGQodG8pLHRoaXMuX2NvbmZpZy5hbmltYXRpb24pe3ZhciBpPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7cCh0aGlzLl9lbGVtZW50KS5vbmUobS5UUkFOU0lUSU9OX0VORCxuKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChpKX1lbHNlIG4oKX19LGUuaGlkZT1mdW5jdGlvbigpe2lmKHRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmNvbnRhaW5zKGVvKSl7dmFyIGU9cC5FdmVudCgkaS5ISURFKTtwKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIoZSksZS5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8dGhpcy5fY2xvc2UoKX19LGUuZGlzcG9zZT1mdW5jdGlvbigpe2NsZWFyVGltZW91dCh0aGlzLl90aW1lb3V0KSx0aGlzLl90aW1lb3V0PW51bGwsdGhpcy5fZWxlbWVudC5jbGFzc0xpc3QuY29udGFpbnMoZW8pJiZ0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5yZW1vdmUoZW8pLHAodGhpcy5fZWxlbWVudCkub2ZmKCRpLkNMSUNLX0RJU01JU1MpLHAucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LHppKSx0aGlzLl9lbGVtZW50PW51bGwsdGhpcy5fY29uZmlnPW51bGx9LGUuX2dldENvbmZpZz1mdW5jdGlvbihlKXtyZXR1cm4gZT1sKHt9LGlvLHt9LHAodGhpcy5fZWxlbWVudCkuZGF0YSgpLHt9LCJvYmplY3QiPT10eXBlb2YgZSYmZT9lOnt9KSxtLnR5cGVDaGVja0NvbmZpZyhZaSxlLHRoaXMuY29uc3RydWN0b3IuRGVmYXVsdFR5cGUpLGV9LGUuX3NldExpc3RlbmVycz1mdW5jdGlvbigpe3ZhciBlPXRoaXM7cCh0aGlzLl9lbGVtZW50KS5vbigkaS5DTElDS19ESVNNSVNTLG9vLGZ1bmN0aW9uKCl7cmV0dXJuIGUuaGlkZSgpfSl9LGUuX2Nsb3NlPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gZSgpe3QuX2VsZW1lbnQuY2xhc3NMaXN0LmFkZChaaSkscCh0Ll9lbGVtZW50KS50cmlnZ2VyKCRpLkhJRERFTil9dmFyIHQ9dGhpcztpZih0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5yZW1vdmUoZW8pLHRoaXMuX2NvbmZpZy5hbmltYXRpb24pe3ZhciBuPW0uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7cCh0aGlzLl9lbGVtZW50KS5vbmUobS5UUkFOU0lUSU9OX0VORCxlKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChuKX1lbHNlIGUoKX0saS5falF1ZXJ5SW50ZXJmYWNlPWZ1bmN0aW9uKG4pe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgZT1wKHRoaXMpLHQ9ZS5kYXRhKHppKTtpZih0fHwodD1uZXcgaSh0aGlzLCJvYmplY3QiPT10eXBlb2YgbiYmbiksZS5kYXRhKHppLHQpKSwic3RyaW5nIj09dHlwZW9mIG4pe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdFtuXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrbisnIicpO3Rbbl0odGhpcyl9fSl9LHMoaSxudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX0se2tleToiRGVmYXVsdFR5cGUiLGdldDpmdW5jdGlvbigpe3JldHVybiBub319LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBpb319XSksaX0oKTtwLmZuW1lpXT1yby5falF1ZXJ5SW50ZXJmYWNlLHAuZm5bWWldLkNvbnN0cnVjdG9yPXJvLHAuZm5bWWldLm5vQ29uZmxpY3Q9ZnVuY3Rpb24oKXtyZXR1cm4gcC5mbltZaV09R2kscm8uX2pRdWVyeUludGVyZmFjZX0sZS5BbGVydD1fLGUuQnV0dG9uPXgsZS5DYXJvdXNlbD1oZSxlLkNvbGxhcHNlPURlLGUuRHJvcGRvd249ZW4sZS5Nb2RhbD13bixlLlBvcG92ZXI9Y2ksZS5TY3JvbGxzcHk9T2ksZS5UYWI9VmksZS5Ub2FzdD1ybyxlLlRvb2x0aXA9WG4sZS5VdGlsPW0sT2JqZWN0LmRlZmluZVByb3BlcnR5KGUsIl9fZXNNb2R1bGUiLHt2YWx1ZTohMH0pfSk7Ci8vIyBzb3VyY2VNYXBwaW5nVVJMPWJvb3RzdHJhcC5idW5kbGUubWluLmpzLm1hcA=='); }
if($path == 'bootstrap4/js/bootstrap.min.js'){ return base64_decode('LyohCiAgKiBCb290c3RyYXAgdjQuNC4xIChodHRwczovL2dldGJvb3RzdHJhcC5jb20vKQogICogQ29weXJpZ2h0IDIwMTEtMjAxOSBUaGUgQm9vdHN0cmFwIEF1dGhvcnMgKGh0dHBzOi8vZ2l0aHViLmNvbS90d2JzL2Jvb3RzdHJhcC9ncmFwaHMvY29udHJpYnV0b3JzKQogICogTGljZW5zZWQgdW5kZXIgTUlUIChodHRwczovL2dpdGh1Yi5jb20vdHdicy9ib290c3RyYXAvYmxvYi9tYXN0ZXIvTElDRU5TRSkKICAqLwohZnVuY3Rpb24odCxlKXsib2JqZWN0Ij09dHlwZW9mIGV4cG9ydHMmJiJ1bmRlZmluZWQiIT10eXBlb2YgbW9kdWxlP2UoZXhwb3J0cyxyZXF1aXJlKCJqcXVlcnkiKSxyZXF1aXJlKCJwb3BwZXIuanMiKSk6ImZ1bmN0aW9uIj09dHlwZW9mIGRlZmluZSYmZGVmaW5lLmFtZD9kZWZpbmUoWyJleHBvcnRzIiwianF1ZXJ5IiwicG9wcGVyLmpzIl0sZSk6ZSgodD10fHxzZWxmKS5ib290c3RyYXA9e30sdC5qUXVlcnksdC5Qb3BwZXIpfSh0aGlzLGZ1bmN0aW9uKHQsZyx1KXsidXNlIHN0cmljdCI7ZnVuY3Rpb24gaSh0LGUpe2Zvcih2YXIgbj0wO248ZS5sZW5ndGg7bisrKXt2YXIgaT1lW25dO2kuZW51bWVyYWJsZT1pLmVudW1lcmFibGV8fCExLGkuY29uZmlndXJhYmxlPSEwLCJ2YWx1ZSJpbiBpJiYoaS53cml0YWJsZT0hMCksT2JqZWN0LmRlZmluZVByb3BlcnR5KHQsaS5rZXksaSl9fWZ1bmN0aW9uIHModCxlLG4pe3JldHVybiBlJiZpKHQucHJvdG90eXBlLGUpLG4mJmkodCxuKSx0fWZ1bmN0aW9uIGUoZSx0KXt2YXIgbj1PYmplY3Qua2V5cyhlKTtpZihPYmplY3QuZ2V0T3duUHJvcGVydHlTeW1ib2xzKXt2YXIgaT1PYmplY3QuZ2V0T3duUHJvcGVydHlTeW1ib2xzKGUpO3QmJihpPWkuZmlsdGVyKGZ1bmN0aW9uKHQpe3JldHVybiBPYmplY3QuZ2V0T3duUHJvcGVydHlEZXNjcmlwdG9yKGUsdCkuZW51bWVyYWJsZX0pKSxuLnB1c2guYXBwbHkobixpKX1yZXR1cm4gbn1mdW5jdGlvbiBsKG8pe2Zvcih2YXIgdD0xO3Q8YXJndW1lbnRzLmxlbmd0aDt0Kyspe3ZhciByPW51bGwhPWFyZ3VtZW50c1t0XT9hcmd1bWVudHNbdF06e307dCUyP2UoT2JqZWN0KHIpLCEwKS5mb3JFYWNoKGZ1bmN0aW9uKHQpe3ZhciBlLG4saTtlPW8saT1yW249dF0sbiBpbiBlP09iamVjdC5kZWZpbmVQcm9wZXJ0eShlLG4se3ZhbHVlOmksZW51bWVyYWJsZTohMCxjb25maWd1cmFibGU6ITAsd3JpdGFibGU6ITB9KTplW25dPWl9KTpPYmplY3QuZ2V0T3duUHJvcGVydHlEZXNjcmlwdG9ycz9PYmplY3QuZGVmaW5lUHJvcGVydGllcyhvLE9iamVjdC5nZXRPd25Qcm9wZXJ0eURlc2NyaXB0b3JzKHIpKTplKE9iamVjdChyKSkuZm9yRWFjaChmdW5jdGlvbih0KXtPYmplY3QuZGVmaW5lUHJvcGVydHkobyx0LE9iamVjdC5nZXRPd25Qcm9wZXJ0eURlc2NyaXB0b3Iocix0KSl9KX1yZXR1cm4gb31nPWcmJmcuaGFzT3duUHJvcGVydHkoImRlZmF1bHQiKT9nLmRlZmF1bHQ6Zyx1PXUmJnUuaGFzT3duUHJvcGVydHkoImRlZmF1bHQiKT91LmRlZmF1bHQ6dTt2YXIgbj0idHJhbnNpdGlvbmVuZCI7ZnVuY3Rpb24gbyh0KXt2YXIgZT10aGlzLG49ITE7cmV0dXJuIGcodGhpcykub25lKF8uVFJBTlNJVElPTl9FTkQsZnVuY3Rpb24oKXtuPSEwfSksc2V0VGltZW91dChmdW5jdGlvbigpe258fF8udHJpZ2dlclRyYW5zaXRpb25FbmQoZSl9LHQpLHRoaXN9dmFyIF89e1RSQU5TSVRJT05fRU5EOiJic1RyYW5zaXRpb25FbmQiLGdldFVJRDpmdW5jdGlvbih0KXtmb3IoO3QrPX5+KDFlNipNYXRoLnJhbmRvbSgpKSxkb2N1bWVudC5nZXRFbGVtZW50QnlJZCh0KTspO3JldHVybiB0fSxnZXRTZWxlY3RvckZyb21FbGVtZW50OmZ1bmN0aW9uKHQpe3ZhciBlPXQuZ2V0QXR0cmlidXRlKCJkYXRhLXRhcmdldCIpO2lmKCFlfHwiIyI9PT1lKXt2YXIgbj10LmdldEF0dHJpYnV0ZSgiaHJlZiIpO2U9biYmIiMiIT09bj9uLnRyaW0oKToiIn10cnl7cmV0dXJuIGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoZSk/ZTpudWxsfWNhdGNoKHQpe3JldHVybiBudWxsfX0sZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQ6ZnVuY3Rpb24odCl7aWYoIXQpcmV0dXJuIDA7dmFyIGU9Zyh0KS5jc3MoInRyYW5zaXRpb24tZHVyYXRpb24iKSxuPWcodCkuY3NzKCJ0cmFuc2l0aW9uLWRlbGF5IiksaT1wYXJzZUZsb2F0KGUpLG89cGFyc2VGbG9hdChuKTtyZXR1cm4gaXx8bz8oZT1lLnNwbGl0KCIsIilbMF0sbj1uLnNwbGl0KCIsIilbMF0sMWUzKihwYXJzZUZsb2F0KGUpK3BhcnNlRmxvYXQobikpKTowfSxyZWZsb3c6ZnVuY3Rpb24odCl7cmV0dXJuIHQub2Zmc2V0SGVpZ2h0fSx0cmlnZ2VyVHJhbnNpdGlvbkVuZDpmdW5jdGlvbih0KXtnKHQpLnRyaWdnZXIobil9LHN1cHBvcnRzVHJhbnNpdGlvbkVuZDpmdW5jdGlvbigpe3JldHVybiBCb29sZWFuKG4pfSxpc0VsZW1lbnQ6ZnVuY3Rpb24odCl7cmV0dXJuKHRbMF18fHQpLm5vZGVUeXBlfSx0eXBlQ2hlY2tDb25maWc6ZnVuY3Rpb24odCxlLG4pe2Zvcih2YXIgaSBpbiBuKWlmKE9iamVjdC5wcm90b3R5cGUuaGFzT3duUHJvcGVydHkuY2FsbChuLGkpKXt2YXIgbz1uW2ldLHI9ZVtpXSxzPXImJl8uaXNFbGVtZW50KHIpPyJlbGVtZW50IjooYT1yLHt9LnRvU3RyaW5nLmNhbGwoYSkubWF0Y2goL1xzKFthLXpdKykvaSlbMV0udG9Mb3dlckNhc2UoKSk7aWYoIW5ldyBSZWdFeHAobykudGVzdChzKSl0aHJvdyBuZXcgRXJyb3IodC50b1VwcGVyQ2FzZSgpKyc6IE9wdGlvbiAiJytpKyciIHByb3ZpZGVkIHR5cGUgIicrcysnIiBidXQgZXhwZWN0ZWQgdHlwZSAiJytvKyciLicpfXZhciBhfSxmaW5kU2hhZG93Um9vdDpmdW5jdGlvbih0KXtpZighZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LmF0dGFjaFNoYWRvdylyZXR1cm4gbnVsbDtpZigiZnVuY3Rpb24iIT10eXBlb2YgdC5nZXRSb290Tm9kZSlyZXR1cm4gdCBpbnN0YW5jZW9mIFNoYWRvd1Jvb3Q/dDp0LnBhcmVudE5vZGU/Xy5maW5kU2hhZG93Um9vdCh0LnBhcmVudE5vZGUpOm51bGw7dmFyIGU9dC5nZXRSb290Tm9kZSgpO3JldHVybiBlIGluc3RhbmNlb2YgU2hhZG93Um9vdD9lOm51bGx9LGpRdWVyeURldGVjdGlvbjpmdW5jdGlvbigpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgZyl0aHJvdyBuZXcgVHlwZUVycm9yKCJCb290c3RyYXAncyBKYXZhU2NyaXB0IHJlcXVpcmVzIGpRdWVyeS4galF1ZXJ5IG11c3QgYmUgaW5jbHVkZWQgYmVmb3JlIEJvb3RzdHJhcCdzIEphdmFTY3JpcHQuIik7dmFyIHQ9Zy5mbi5qcXVlcnkuc3BsaXQoIiAiKVswXS5zcGxpdCgiLiIpO2lmKHRbMF08MiYmdFsxXTw5fHwxPT09dFswXSYmOT09PXRbMV0mJnRbMl08MXx8NDw9dFswXSl0aHJvdyBuZXcgRXJyb3IoIkJvb3RzdHJhcCdzIEphdmFTY3JpcHQgcmVxdWlyZXMgYXQgbGVhc3QgalF1ZXJ5IHYxLjkuMSBidXQgbGVzcyB0aGFuIHY0LjAuMCIpfX07Xy5qUXVlcnlEZXRlY3Rpb24oKSxnLmZuLmVtdWxhdGVUcmFuc2l0aW9uRW5kPW8sZy5ldmVudC5zcGVjaWFsW18uVFJBTlNJVElPTl9FTkRdPXtiaW5kVHlwZTpuLGRlbGVnYXRlVHlwZTpuLGhhbmRsZTpmdW5jdGlvbih0KXtpZihnKHQudGFyZ2V0KS5pcyh0aGlzKSlyZXR1cm4gdC5oYW5kbGVPYmouaGFuZGxlci5hcHBseSh0aGlzLGFyZ3VtZW50cyl9fTt2YXIgcj0iYWxlcnQiLGE9ImJzLmFsZXJ0IixjPSIuIithLGg9Zy5mbltyXSxmPXtDTE9TRToiY2xvc2UiK2MsQ0xPU0VEOiJjbG9zZWQiK2MsQ0xJQ0tfREFUQV9BUEk6ImNsaWNrIitjKyIuZGF0YS1hcGkifSxkPSJhbGVydCIsbT0iZmFkZSIscD0ic2hvdyIsdj1mdW5jdGlvbigpe2Z1bmN0aW9uIGkodCl7dGhpcy5fZWxlbWVudD10fXZhciB0PWkucHJvdG90eXBlO3JldHVybiB0LmNsb3NlPWZ1bmN0aW9uKHQpe3ZhciBlPXRoaXMuX2VsZW1lbnQ7dCYmKGU9dGhpcy5fZ2V0Um9vdEVsZW1lbnQodCkpLHRoaXMuX3RyaWdnZXJDbG9zZUV2ZW50KGUpLmlzRGVmYXVsdFByZXZlbnRlZCgpfHx0aGlzLl9yZW1vdmVFbGVtZW50KGUpfSx0LmRpc3Bvc2U9ZnVuY3Rpb24oKXtnLnJlbW92ZURhdGEodGhpcy5fZWxlbWVudCxhKSx0aGlzLl9lbGVtZW50PW51bGx9LHQuX2dldFJvb3RFbGVtZW50PWZ1bmN0aW9uKHQpe3ZhciBlPV8uZ2V0U2VsZWN0b3JGcm9tRWxlbWVudCh0KSxuPSExO3JldHVybiBlJiYobj1kb2N1bWVudC5xdWVyeVNlbGVjdG9yKGUpKSxuPW58fGcodCkuY2xvc2VzdCgiLiIrZClbMF19LHQuX3RyaWdnZXJDbG9zZUV2ZW50PWZ1bmN0aW9uKHQpe3ZhciBlPWcuRXZlbnQoZi5DTE9TRSk7cmV0dXJuIGcodCkudHJpZ2dlcihlKSxlfSx0Ll9yZW1vdmVFbGVtZW50PWZ1bmN0aW9uKGUpe3ZhciBuPXRoaXM7aWYoZyhlKS5yZW1vdmVDbGFzcyhwKSxnKGUpLmhhc0NsYXNzKG0pKXt2YXIgdD1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KGUpO2coZSkub25lKF8uVFJBTlNJVElPTl9FTkQsZnVuY3Rpb24odCl7cmV0dXJuIG4uX2Rlc3Ryb3lFbGVtZW50KGUsdCl9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZCh0KX1lbHNlIHRoaXMuX2Rlc3Ryb3lFbGVtZW50KGUpfSx0Ll9kZXN0cm95RWxlbWVudD1mdW5jdGlvbih0KXtnKHQpLmRldGFjaCgpLnRyaWdnZXIoZi5DTE9TRUQpLnJlbW92ZSgpfSxpLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24obil7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PWcodGhpcyksZT10LmRhdGEoYSk7ZXx8KGU9bmV3IGkodGhpcyksdC5kYXRhKGEsZSkpLCJjbG9zZSI9PT1uJiZlW25dKHRoaXMpfSl9LGkuX2hhbmRsZURpc21pc3M9ZnVuY3Rpb24oZSl7cmV0dXJuIGZ1bmN0aW9uKHQpe3QmJnQucHJldmVudERlZmF1bHQoKSxlLmNsb3NlKHRoaXMpfX0scyhpLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fV0pLGl9KCk7Zyhkb2N1bWVudCkub24oZi5DTElDS19EQVRBX0FQSSwnW2RhdGEtZGlzbWlzcz0iYWxlcnQiXScsdi5faGFuZGxlRGlzbWlzcyhuZXcgdikpLGcuZm5bcl09di5falF1ZXJ5SW50ZXJmYWNlLGcuZm5bcl0uQ29uc3RydWN0b3I9dixnLmZuW3JdLm5vQ29uZmxpY3Q9ZnVuY3Rpb24oKXtyZXR1cm4gZy5mbltyXT1oLHYuX2pRdWVyeUludGVyZmFjZX07dmFyIHk9ImJ1dHRvbiIsRT0iYnMuYnV0dG9uIixDPSIuIitFLFQ9Ii5kYXRhLWFwaSIsYj1nLmZuW3ldLFM9ImFjdGl2ZSIsRD0iYnRuIixJPSJmb2N1cyIsdz0nW2RhdGEtdG9nZ2xlXj0iYnV0dG9uIl0nLEE9J1tkYXRhLXRvZ2dsZT0iYnV0dG9ucyJdJyxOPSdbZGF0YS10b2dnbGU9ImJ1dHRvbiJdJyxPPSdbZGF0YS10b2dnbGU9ImJ1dHRvbnMiXSAuYnRuJyxrPSdpbnB1dDpub3QoW3R5cGU9ImhpZGRlbiJdKScsUD0iLmFjdGl2ZSIsTD0iLmJ0biIsaj17Q0xJQ0tfREFUQV9BUEk6ImNsaWNrIitDK1QsRk9DVVNfQkxVUl9EQVRBX0FQSToiZm9jdXMiK0MrVCsiIGJsdXIiK0MrVCxMT0FEX0RBVEFfQVBJOiJsb2FkIitDK1R9LEg9ZnVuY3Rpb24oKXtmdW5jdGlvbiBuKHQpe3RoaXMuX2VsZW1lbnQ9dH12YXIgdD1uLnByb3RvdHlwZTtyZXR1cm4gdC50b2dnbGU9ZnVuY3Rpb24oKXt2YXIgdD0hMCxlPSEwLG49Zyh0aGlzLl9lbGVtZW50KS5jbG9zZXN0KEEpWzBdO2lmKG4pe3ZhciBpPXRoaXMuX2VsZW1lbnQucXVlcnlTZWxlY3RvcihrKTtpZihpKXtpZigicmFkaW8iPT09aS50eXBlKWlmKGkuY2hlY2tlZCYmdGhpcy5fZWxlbWVudC5jbGFzc0xpc3QuY29udGFpbnMoUykpdD0hMTtlbHNle3ZhciBvPW4ucXVlcnlTZWxlY3RvcihQKTtvJiZnKG8pLnJlbW92ZUNsYXNzKFMpfWVsc2UiY2hlY2tib3giPT09aS50eXBlPyJMQUJFTCI9PT10aGlzLl9lbGVtZW50LnRhZ05hbWUmJmkuY2hlY2tlZD09PXRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmNvbnRhaW5zKFMpJiYodD0hMSk6dD0hMTt0JiYoaS5jaGVja2VkPSF0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5jb250YWlucyhTKSxnKGkpLnRyaWdnZXIoImNoYW5nZSIpKSxpLmZvY3VzKCksZT0hMX19dGhpcy5fZWxlbWVudC5oYXNBdHRyaWJ1dGUoImRpc2FibGVkIil8fHRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmNvbnRhaW5zKCJkaXNhYmxlZCIpfHwoZSYmdGhpcy5fZWxlbWVudC5zZXRBdHRyaWJ1dGUoImFyaWEtcHJlc3NlZCIsIXRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmNvbnRhaW5zKFMpKSx0JiZnKHRoaXMuX2VsZW1lbnQpLnRvZ2dsZUNsYXNzKFMpKX0sdC5kaXNwb3NlPWZ1bmN0aW9uKCl7Zy5yZW1vdmVEYXRhKHRoaXMuX2VsZW1lbnQsRSksdGhpcy5fZWxlbWVudD1udWxsfSxuLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24oZSl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PWcodGhpcykuZGF0YShFKTt0fHwodD1uZXcgbih0aGlzKSxnKHRoaXMpLmRhdGEoRSx0KSksInRvZ2dsZSI9PT1lJiZ0W2VdKCl9KX0scyhuLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fV0pLG59KCk7Zyhkb2N1bWVudCkub24oai5DTElDS19EQVRBX0FQSSx3LGZ1bmN0aW9uKHQpe3ZhciBlPXQudGFyZ2V0O2lmKGcoZSkuaGFzQ2xhc3MoRCl8fChlPWcoZSkuY2xvc2VzdChMKVswXSksIWV8fGUuaGFzQXR0cmlidXRlKCJkaXNhYmxlZCIpfHxlLmNsYXNzTGlzdC5jb250YWlucygiZGlzYWJsZWQiKSl0LnByZXZlbnREZWZhdWx0KCk7ZWxzZXt2YXIgbj1lLnF1ZXJ5U2VsZWN0b3Ioayk7aWYobiYmKG4uaGFzQXR0cmlidXRlKCJkaXNhYmxlZCIpfHxuLmNsYXNzTGlzdC5jb250YWlucygiZGlzYWJsZWQiKSkpcmV0dXJuIHZvaWQgdC5wcmV2ZW50RGVmYXVsdCgpO0guX2pRdWVyeUludGVyZmFjZS5jYWxsKGcoZSksInRvZ2dsZSIpfX0pLm9uKGouRk9DVVNfQkxVUl9EQVRBX0FQSSx3LGZ1bmN0aW9uKHQpe3ZhciBlPWcodC50YXJnZXQpLmNsb3Nlc3QoTClbMF07ZyhlKS50b2dnbGVDbGFzcyhJLC9eZm9jdXMoaW4pPyQvLnRlc3QodC50eXBlKSl9KSxnKHdpbmRvdykub24oai5MT0FEX0RBVEFfQVBJLGZ1bmN0aW9uKCl7Zm9yKHZhciB0PVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChPKSksZT0wLG49dC5sZW5ndGg7ZTxuO2UrKyl7dmFyIGk9dFtlXSxvPWkucXVlcnlTZWxlY3RvcihrKTtvLmNoZWNrZWR8fG8uaGFzQXR0cmlidXRlKCJjaGVja2VkIik/aS5jbGFzc0xpc3QuYWRkKFMpOmkuY2xhc3NMaXN0LnJlbW92ZShTKX1mb3IodmFyIHI9MCxzPSh0PVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChOKSkpLmxlbmd0aDtyPHM7cisrKXt2YXIgYT10W3JdOyJ0cnVlIj09PWEuZ2V0QXR0cmlidXRlKCJhcmlhLXByZXNzZWQiKT9hLmNsYXNzTGlzdC5hZGQoUyk6YS5jbGFzc0xpc3QucmVtb3ZlKFMpfX0pLGcuZm5beV09SC5falF1ZXJ5SW50ZXJmYWNlLGcuZm5beV0uQ29uc3RydWN0b3I9SCxnLmZuW3ldLm5vQ29uZmxpY3Q9ZnVuY3Rpb24oKXtyZXR1cm4gZy5mblt5XT1iLEguX2pRdWVyeUludGVyZmFjZX07dmFyIFI9ImNhcm91c2VsIix4PSJicy5jYXJvdXNlbCIsRj0iLiIreCxVPSIuZGF0YS1hcGkiLFc9Zy5mbltSXSxxPXtpbnRlcnZhbDo1ZTMsa2V5Ym9hcmQ6ITAsc2xpZGU6ITEscGF1c2U6ImhvdmVyIix3cmFwOiEwLHRvdWNoOiEwfSxNPXtpbnRlcnZhbDoiKG51bWJlcnxib29sZWFuKSIsa2V5Ym9hcmQ6ImJvb2xlYW4iLHNsaWRlOiIoYm9vbGVhbnxzdHJpbmcpIixwYXVzZToiKHN0cmluZ3xib29sZWFuKSIsd3JhcDoiYm9vbGVhbiIsdG91Y2g6ImJvb2xlYW4ifSxLPSJuZXh0IixRPSJwcmV2IixCPSJsZWZ0IixWPSJyaWdodCIsWT17U0xJREU6InNsaWRlIitGLFNMSUQ6InNsaWQiK0YsS0VZRE9XTjoia2V5ZG93biIrRixNT1VTRUVOVEVSOiJtb3VzZWVudGVyIitGLE1PVVNFTEVBVkU6Im1vdXNlbGVhdmUiK0YsVE9VQ0hTVEFSVDoidG91Y2hzdGFydCIrRixUT1VDSE1PVkU6InRvdWNobW92ZSIrRixUT1VDSEVORDoidG91Y2hlbmQiK0YsUE9JTlRFUkRPV046InBvaW50ZXJkb3duIitGLFBPSU5URVJVUDoicG9pbnRlcnVwIitGLERSQUdfU1RBUlQ6ImRyYWdzdGFydCIrRixMT0FEX0RBVEFfQVBJOiJsb2FkIitGK1UsQ0xJQ0tfREFUQV9BUEk6ImNsaWNrIitGK1V9LHo9ImNhcm91c2VsIixYPSJhY3RpdmUiLCQ9InNsaWRlIixHPSJjYXJvdXNlbC1pdGVtLXJpZ2h0IixKPSJjYXJvdXNlbC1pdGVtLWxlZnQiLFo9ImNhcm91c2VsLWl0ZW0tbmV4dCIsdHQ9ImNhcm91c2VsLWl0ZW0tcHJldiIsZXQ9InBvaW50ZXItZXZlbnQiLG50PSIuYWN0aXZlIixpdD0iLmFjdGl2ZS5jYXJvdXNlbC1pdGVtIixvdD0iLmNhcm91c2VsLWl0ZW0iLHJ0PSIuY2Fyb3VzZWwtaXRlbSBpbWciLHN0PSIuY2Fyb3VzZWwtaXRlbS1uZXh0LCAuY2Fyb3VzZWwtaXRlbS1wcmV2IixhdD0iLmNhcm91c2VsLWluZGljYXRvcnMiLGx0PSJbZGF0YS1zbGlkZV0sIFtkYXRhLXNsaWRlLXRvXSIsY3Q9J1tkYXRhLXJpZGU9ImNhcm91c2VsIl0nLGh0PXtUT1VDSDoidG91Y2giLFBFTjoicGVuIn0sdXQ9ZnVuY3Rpb24oKXtmdW5jdGlvbiByKHQsZSl7dGhpcy5faXRlbXM9bnVsbCx0aGlzLl9pbnRlcnZhbD1udWxsLHRoaXMuX2FjdGl2ZUVsZW1lbnQ9bnVsbCx0aGlzLl9pc1BhdXNlZD0hMSx0aGlzLl9pc1NsaWRpbmc9ITEsdGhpcy50b3VjaFRpbWVvdXQ9bnVsbCx0aGlzLnRvdWNoU3RhcnRYPTAsdGhpcy50b3VjaERlbHRhWD0wLHRoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcoZSksdGhpcy5fZWxlbWVudD10LHRoaXMuX2luZGljYXRvcnNFbGVtZW50PXRoaXMuX2VsZW1lbnQucXVlcnlTZWxlY3RvcihhdCksdGhpcy5fdG91Y2hTdXBwb3J0ZWQ9Im9udG91Y2hzdGFydCJpbiBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnR8fDA8bmF2aWdhdG9yLm1heFRvdWNoUG9pbnRzLHRoaXMuX3BvaW50ZXJFdmVudD1Cb29sZWFuKHdpbmRvdy5Qb2ludGVyRXZlbnR8fHdpbmRvdy5NU1BvaW50ZXJFdmVudCksdGhpcy5fYWRkRXZlbnRMaXN0ZW5lcnMoKX12YXIgdD1yLnByb3RvdHlwZTtyZXR1cm4gdC5uZXh0PWZ1bmN0aW9uKCl7dGhpcy5faXNTbGlkaW5nfHx0aGlzLl9zbGlkZShLKX0sdC5uZXh0V2hlblZpc2libGU9ZnVuY3Rpb24oKXshZG9jdW1lbnQuaGlkZGVuJiZnKHRoaXMuX2VsZW1lbnQpLmlzKCI6dmlzaWJsZSIpJiYiaGlkZGVuIiE9PWcodGhpcy5fZWxlbWVudCkuY3NzKCJ2aXNpYmlsaXR5IikmJnRoaXMubmV4dCgpfSx0LnByZXY9ZnVuY3Rpb24oKXt0aGlzLl9pc1NsaWRpbmd8fHRoaXMuX3NsaWRlKFEpfSx0LnBhdXNlPWZ1bmN0aW9uKHQpe3R8fCh0aGlzLl9pc1BhdXNlZD0hMCksdGhpcy5fZWxlbWVudC5xdWVyeVNlbGVjdG9yKHN0KSYmKF8udHJpZ2dlclRyYW5zaXRpb25FbmQodGhpcy5fZWxlbWVudCksdGhpcy5jeWNsZSghMCkpLGNsZWFySW50ZXJ2YWwodGhpcy5faW50ZXJ2YWwpLHRoaXMuX2ludGVydmFsPW51bGx9LHQuY3ljbGU9ZnVuY3Rpb24odCl7dHx8KHRoaXMuX2lzUGF1c2VkPSExKSx0aGlzLl9pbnRlcnZhbCYmKGNsZWFySW50ZXJ2YWwodGhpcy5faW50ZXJ2YWwpLHRoaXMuX2ludGVydmFsPW51bGwpLHRoaXMuX2NvbmZpZy5pbnRlcnZhbCYmIXRoaXMuX2lzUGF1c2VkJiYodGhpcy5faW50ZXJ2YWw9c2V0SW50ZXJ2YWwoKGRvY3VtZW50LnZpc2liaWxpdHlTdGF0ZT90aGlzLm5leHRXaGVuVmlzaWJsZTp0aGlzLm5leHQpLmJpbmQodGhpcyksdGhpcy5fY29uZmlnLmludGVydmFsKSl9LHQudG89ZnVuY3Rpb24odCl7dmFyIGU9dGhpczt0aGlzLl9hY3RpdmVFbGVtZW50PXRoaXMuX2VsZW1lbnQucXVlcnlTZWxlY3RvcihpdCk7dmFyIG49dGhpcy5fZ2V0SXRlbUluZGV4KHRoaXMuX2FjdGl2ZUVsZW1lbnQpO2lmKCEodD50aGlzLl9pdGVtcy5sZW5ndGgtMXx8dDwwKSlpZih0aGlzLl9pc1NsaWRpbmcpZyh0aGlzLl9lbGVtZW50KS5vbmUoWS5TTElELGZ1bmN0aW9uKCl7cmV0dXJuIGUudG8odCl9KTtlbHNle2lmKG49PT10KXJldHVybiB0aGlzLnBhdXNlKCksdm9pZCB0aGlzLmN5Y2xlKCk7dmFyIGk9bjx0P0s6UTt0aGlzLl9zbGlkZShpLHRoaXMuX2l0ZW1zW3RdKX19LHQuZGlzcG9zZT1mdW5jdGlvbigpe2codGhpcy5fZWxlbWVudCkub2ZmKEYpLGcucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LHgpLHRoaXMuX2l0ZW1zPW51bGwsdGhpcy5fY29uZmlnPW51bGwsdGhpcy5fZWxlbWVudD1udWxsLHRoaXMuX2ludGVydmFsPW51bGwsdGhpcy5faXNQYXVzZWQ9bnVsbCx0aGlzLl9pc1NsaWRpbmc9bnVsbCx0aGlzLl9hY3RpdmVFbGVtZW50PW51bGwsdGhpcy5faW5kaWNhdG9yc0VsZW1lbnQ9bnVsbH0sdC5fZ2V0Q29uZmlnPWZ1bmN0aW9uKHQpe3JldHVybiB0PWwoe30scSx7fSx0KSxfLnR5cGVDaGVja0NvbmZpZyhSLHQsTSksdH0sdC5faGFuZGxlU3dpcGU9ZnVuY3Rpb24oKXt2YXIgdD1NYXRoLmFicyh0aGlzLnRvdWNoRGVsdGFYKTtpZighKHQ8PTQwKSl7dmFyIGU9dC90aGlzLnRvdWNoRGVsdGFYOyh0aGlzLnRvdWNoRGVsdGFYPTApPGUmJnRoaXMucHJldigpLGU8MCYmdGhpcy5uZXh0KCl9fSx0Ll9hZGRFdmVudExpc3RlbmVycz1mdW5jdGlvbigpe3ZhciBlPXRoaXM7dGhpcy5fY29uZmlnLmtleWJvYXJkJiZnKHRoaXMuX2VsZW1lbnQpLm9uKFkuS0VZRE9XTixmdW5jdGlvbih0KXtyZXR1cm4gZS5fa2V5ZG93bih0KX0pLCJob3ZlciI9PT10aGlzLl9jb25maWcucGF1c2UmJmcodGhpcy5fZWxlbWVudCkub24oWS5NT1VTRUVOVEVSLGZ1bmN0aW9uKHQpe3JldHVybiBlLnBhdXNlKHQpfSkub24oWS5NT1VTRUxFQVZFLGZ1bmN0aW9uKHQpe3JldHVybiBlLmN5Y2xlKHQpfSksdGhpcy5fY29uZmlnLnRvdWNoJiZ0aGlzLl9hZGRUb3VjaEV2ZW50TGlzdGVuZXJzKCl9LHQuX2FkZFRvdWNoRXZlbnRMaXN0ZW5lcnM9ZnVuY3Rpb24oKXt2YXIgZT10aGlzO2lmKHRoaXMuX3RvdWNoU3VwcG9ydGVkKXt2YXIgbj1mdW5jdGlvbih0KXtlLl9wb2ludGVyRXZlbnQmJmh0W3Qub3JpZ2luYWxFdmVudC5wb2ludGVyVHlwZS50b1VwcGVyQ2FzZSgpXT9lLnRvdWNoU3RhcnRYPXQub3JpZ2luYWxFdmVudC5jbGllbnRYOmUuX3BvaW50ZXJFdmVudHx8KGUudG91Y2hTdGFydFg9dC5vcmlnaW5hbEV2ZW50LnRvdWNoZXNbMF0uY2xpZW50WCl9LGk9ZnVuY3Rpb24odCl7ZS5fcG9pbnRlckV2ZW50JiZodFt0Lm9yaWdpbmFsRXZlbnQucG9pbnRlclR5cGUudG9VcHBlckNhc2UoKV0mJihlLnRvdWNoRGVsdGFYPXQub3JpZ2luYWxFdmVudC5jbGllbnRYLWUudG91Y2hTdGFydFgpLGUuX2hhbmRsZVN3aXBlKCksImhvdmVyIj09PWUuX2NvbmZpZy5wYXVzZSYmKGUucGF1c2UoKSxlLnRvdWNoVGltZW91dCYmY2xlYXJUaW1lb3V0KGUudG91Y2hUaW1lb3V0KSxlLnRvdWNoVGltZW91dD1zZXRUaW1lb3V0KGZ1bmN0aW9uKHQpe3JldHVybiBlLmN5Y2xlKHQpfSw1MDArZS5fY29uZmlnLmludGVydmFsKSl9O2codGhpcy5fZWxlbWVudC5xdWVyeVNlbGVjdG9yQWxsKHJ0KSkub24oWS5EUkFHX1NUQVJULGZ1bmN0aW9uKHQpe3JldHVybiB0LnByZXZlbnREZWZhdWx0KCl9KSx0aGlzLl9wb2ludGVyRXZlbnQ/KGcodGhpcy5fZWxlbWVudCkub24oWS5QT0lOVEVSRE9XTixmdW5jdGlvbih0KXtyZXR1cm4gbih0KX0pLGcodGhpcy5fZWxlbWVudCkub24oWS5QT0lOVEVSVVAsZnVuY3Rpb24odCl7cmV0dXJuIGkodCl9KSx0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5hZGQoZXQpKTooZyh0aGlzLl9lbGVtZW50KS5vbihZLlRPVUNIU1RBUlQsZnVuY3Rpb24odCl7cmV0dXJuIG4odCl9KSxnKHRoaXMuX2VsZW1lbnQpLm9uKFkuVE9VQ0hNT1ZFLGZ1bmN0aW9uKHQpe3JldHVybiBmdW5jdGlvbih0KXt0Lm9yaWdpbmFsRXZlbnQudG91Y2hlcyYmMTx0Lm9yaWdpbmFsRXZlbnQudG91Y2hlcy5sZW5ndGg/ZS50b3VjaERlbHRhWD0wOmUudG91Y2hEZWx0YVg9dC5vcmlnaW5hbEV2ZW50LnRvdWNoZXNbMF0uY2xpZW50WC1lLnRvdWNoU3RhcnRYfSh0KX0pLGcodGhpcy5fZWxlbWVudCkub24oWS5UT1VDSEVORCxmdW5jdGlvbih0KXtyZXR1cm4gaSh0KX0pKX19LHQuX2tleWRvd249ZnVuY3Rpb24odCl7aWYoIS9pbnB1dHx0ZXh0YXJlYS9pLnRlc3QodC50YXJnZXQudGFnTmFtZSkpc3dpdGNoKHQud2hpY2gpe2Nhc2UgMzc6dC5wcmV2ZW50RGVmYXVsdCgpLHRoaXMucHJldigpO2JyZWFrO2Nhc2UgMzk6dC5wcmV2ZW50RGVmYXVsdCgpLHRoaXMubmV4dCgpfX0sdC5fZ2V0SXRlbUluZGV4PWZ1bmN0aW9uKHQpe3JldHVybiB0aGlzLl9pdGVtcz10JiZ0LnBhcmVudE5vZGU/W10uc2xpY2UuY2FsbCh0LnBhcmVudE5vZGUucXVlcnlTZWxlY3RvckFsbChvdCkpOltdLHRoaXMuX2l0ZW1zLmluZGV4T2YodCl9LHQuX2dldEl0ZW1CeURpcmVjdGlvbj1mdW5jdGlvbih0LGUpe3ZhciBuPXQ9PT1LLGk9dD09PVEsbz10aGlzLl9nZXRJdGVtSW5kZXgoZSkscj10aGlzLl9pdGVtcy5sZW5ndGgtMTtpZigoaSYmMD09PW98fG4mJm89PT1yKSYmIXRoaXMuX2NvbmZpZy53cmFwKXJldHVybiBlO3ZhciBzPShvKyh0PT09UT8tMToxKSkldGhpcy5faXRlbXMubGVuZ3RoO3JldHVybi0xPT1zP3RoaXMuX2l0ZW1zW3RoaXMuX2l0ZW1zLmxlbmd0aC0xXTp0aGlzLl9pdGVtc1tzXX0sdC5fdHJpZ2dlclNsaWRlRXZlbnQ9ZnVuY3Rpb24odCxlKXt2YXIgbj10aGlzLl9nZXRJdGVtSW5kZXgodCksaT10aGlzLl9nZXRJdGVtSW5kZXgodGhpcy5fZWxlbWVudC5xdWVyeVNlbGVjdG9yKGl0KSksbz1nLkV2ZW50KFkuU0xJREUse3JlbGF0ZWRUYXJnZXQ6dCxkaXJlY3Rpb246ZSxmcm9tOmksdG86bn0pO3JldHVybiBnKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIobyksb30sdC5fc2V0QWN0aXZlSW5kaWNhdG9yRWxlbWVudD1mdW5jdGlvbih0KXtpZih0aGlzLl9pbmRpY2F0b3JzRWxlbWVudCl7dmFyIGU9W10uc2xpY2UuY2FsbCh0aGlzLl9pbmRpY2F0b3JzRWxlbWVudC5xdWVyeVNlbGVjdG9yQWxsKG50KSk7ZyhlKS5yZW1vdmVDbGFzcyhYKTt2YXIgbj10aGlzLl9pbmRpY2F0b3JzRWxlbWVudC5jaGlsZHJlblt0aGlzLl9nZXRJdGVtSW5kZXgodCldO24mJmcobikuYWRkQ2xhc3MoWCl9fSx0Ll9zbGlkZT1mdW5jdGlvbih0LGUpe3ZhciBuLGksbyxyPXRoaXMscz10aGlzLl9lbGVtZW50LnF1ZXJ5U2VsZWN0b3IoaXQpLGE9dGhpcy5fZ2V0SXRlbUluZGV4KHMpLGw9ZXx8cyYmdGhpcy5fZ2V0SXRlbUJ5RGlyZWN0aW9uKHQscyksYz10aGlzLl9nZXRJdGVtSW5kZXgobCksaD1Cb29sZWFuKHRoaXMuX2ludGVydmFsKTtpZihvPXQ9PT1LPyhuPUosaT1aLEIpOihuPUcsaT10dCxWKSxsJiZnKGwpLmhhc0NsYXNzKFgpKXRoaXMuX2lzU2xpZGluZz0hMTtlbHNlIGlmKCF0aGlzLl90cmlnZ2VyU2xpZGVFdmVudChsLG8pLmlzRGVmYXVsdFByZXZlbnRlZCgpJiZzJiZsKXt0aGlzLl9pc1NsaWRpbmc9ITAsaCYmdGhpcy5wYXVzZSgpLHRoaXMuX3NldEFjdGl2ZUluZGljYXRvckVsZW1lbnQobCk7dmFyIHU9Zy5FdmVudChZLlNMSUQse3JlbGF0ZWRUYXJnZXQ6bCxkaXJlY3Rpb246byxmcm9tOmEsdG86Y30pO2lmKGcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoJCkpe2cobCkuYWRkQ2xhc3MoaSksXy5yZWZsb3cobCksZyhzKS5hZGRDbGFzcyhuKSxnKGwpLmFkZENsYXNzKG4pO3ZhciBmPXBhcnNlSW50KGwuZ2V0QXR0cmlidXRlKCJkYXRhLWludGVydmFsIiksMTApO2Y/KHRoaXMuX2NvbmZpZy5kZWZhdWx0SW50ZXJ2YWw9dGhpcy5fY29uZmlnLmRlZmF1bHRJbnRlcnZhbHx8dGhpcy5fY29uZmlnLmludGVydmFsLHRoaXMuX2NvbmZpZy5pbnRlcnZhbD1mKTp0aGlzLl9jb25maWcuaW50ZXJ2YWw9dGhpcy5fY29uZmlnLmRlZmF1bHRJbnRlcnZhbHx8dGhpcy5fY29uZmlnLmludGVydmFsO3ZhciBkPV8uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQocyk7ZyhzKS5vbmUoXy5UUkFOU0lUSU9OX0VORCxmdW5jdGlvbigpe2cobCkucmVtb3ZlQ2xhc3MobisiICIraSkuYWRkQ2xhc3MoWCksZyhzKS5yZW1vdmVDbGFzcyhYKyIgIitpKyIgIituKSxyLl9pc1NsaWRpbmc9ITEsc2V0VGltZW91dChmdW5jdGlvbigpe3JldHVybiBnKHIuX2VsZW1lbnQpLnRyaWdnZXIodSl9LDApfSkuZW11bGF0ZVRyYW5zaXRpb25FbmQoZCl9ZWxzZSBnKHMpLnJlbW92ZUNsYXNzKFgpLGcobCkuYWRkQ2xhc3MoWCksdGhpcy5faXNTbGlkaW5nPSExLGcodGhpcy5fZWxlbWVudCkudHJpZ2dlcih1KTtoJiZ0aGlzLmN5Y2xlKCl9fSxyLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24oaSl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PWcodGhpcykuZGF0YSh4KSxlPWwoe30scSx7fSxnKHRoaXMpLmRhdGEoKSk7Im9iamVjdCI9PXR5cGVvZiBpJiYoZT1sKHt9LGUse30saSkpO3ZhciBuPSJzdHJpbmciPT10eXBlb2YgaT9pOmUuc2xpZGU7aWYodHx8KHQ9bmV3IHIodGhpcyxlKSxnKHRoaXMpLmRhdGEoeCx0KSksIm51bWJlciI9PXR5cGVvZiBpKXQudG8oaSk7ZWxzZSBpZigic3RyaW5nIj09dHlwZW9mIG4pe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdFtuXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrbisnIicpO3Rbbl0oKX1lbHNlIGUuaW50ZXJ2YWwmJmUucmlkZSYmKHQucGF1c2UoKSx0LmN5Y2xlKCkpfSl9LHIuX2RhdGFBcGlDbGlja0hhbmRsZXI9ZnVuY3Rpb24odCl7dmFyIGU9Xy5nZXRTZWxlY3RvckZyb21FbGVtZW50KHRoaXMpO2lmKGUpe3ZhciBuPWcoZSlbMF07aWYobiYmZyhuKS5oYXNDbGFzcyh6KSl7dmFyIGk9bCh7fSxnKG4pLmRhdGEoKSx7fSxnKHRoaXMpLmRhdGEoKSksbz10aGlzLmdldEF0dHJpYnV0ZSgiZGF0YS1zbGlkZS10byIpO28mJihpLmludGVydmFsPSExKSxyLl9qUXVlcnlJbnRlcmZhY2UuY2FsbChnKG4pLGkpLG8mJmcobikuZGF0YSh4KS50byhvKSx0LnByZXZlbnREZWZhdWx0KCl9fX0scyhyLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fSx7a2V5OiJEZWZhdWx0IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gcX19XSkscn0oKTtnKGRvY3VtZW50KS5vbihZLkNMSUNLX0RBVEFfQVBJLGx0LHV0Ll9kYXRhQXBpQ2xpY2tIYW5kbGVyKSxnKHdpbmRvdykub24oWS5MT0FEX0RBVEFfQVBJLGZ1bmN0aW9uKCl7Zm9yKHZhciB0PVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChjdCkpLGU9MCxuPXQubGVuZ3RoO2U8bjtlKyspe3ZhciBpPWcodFtlXSk7dXQuX2pRdWVyeUludGVyZmFjZS5jYWxsKGksaS5kYXRhKCkpfX0pLGcuZm5bUl09dXQuX2pRdWVyeUludGVyZmFjZSxnLmZuW1JdLkNvbnN0cnVjdG9yPXV0LGcuZm5bUl0ubm9Db25mbGljdD1mdW5jdGlvbigpe3JldHVybiBnLmZuW1JdPVcsdXQuX2pRdWVyeUludGVyZmFjZX07dmFyIGZ0PSJjb2xsYXBzZSIsZHQ9ImJzLmNvbGxhcHNlIixndD0iLiIrZHQsX3Q9Zy5mbltmdF0sbXQ9e3RvZ2dsZTohMCxwYXJlbnQ6IiJ9LHB0PXt0b2dnbGU6ImJvb2xlYW4iLHBhcmVudDoiKHN0cmluZ3xlbGVtZW50KSJ9LHZ0PXtTSE9XOiJzaG93IitndCxTSE9XTjoic2hvd24iK2d0LEhJREU6ImhpZGUiK2d0LEhJRERFTjoiaGlkZGVuIitndCxDTElDS19EQVRBX0FQSToiY2xpY2siK2d0KyIuZGF0YS1hcGkifSx5dD0ic2hvdyIsRXQ9ImNvbGxhcHNlIixDdD0iY29sbGFwc2luZyIsVHQ9ImNvbGxhcHNlZCIsYnQ9IndpZHRoIixTdD0iaGVpZ2h0IixEdD0iLnNob3csIC5jb2xsYXBzaW5nIixJdD0nW2RhdGEtdG9nZ2xlPSJjb2xsYXBzZSJdJyx3dD1mdW5jdGlvbigpe2Z1bmN0aW9uIGEoZSx0KXt0aGlzLl9pc1RyYW5zaXRpb25pbmc9ITEsdGhpcy5fZWxlbWVudD1lLHRoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcodCksdGhpcy5fdHJpZ2dlckFycmF5PVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbCgnW2RhdGEtdG9nZ2xlPSJjb2xsYXBzZSJdW2hyZWY9IiMnK2UuaWQrJyJdLFtkYXRhLXRvZ2dsZT0iY29sbGFwc2UiXVtkYXRhLXRhcmdldD0iIycrZS5pZCsnIl0nKSk7Zm9yKHZhciBuPVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChJdCkpLGk9MCxvPW4ubGVuZ3RoO2k8bztpKyspe3ZhciByPW5baV0scz1fLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQociksYT1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwocykpLmZpbHRlcihmdW5jdGlvbih0KXtyZXR1cm4gdD09PWV9KTtudWxsIT09cyYmMDxhLmxlbmd0aCYmKHRoaXMuX3NlbGVjdG9yPXMsdGhpcy5fdHJpZ2dlckFycmF5LnB1c2gocikpfXRoaXMuX3BhcmVudD10aGlzLl9jb25maWcucGFyZW50P3RoaXMuX2dldFBhcmVudCgpOm51bGwsdGhpcy5fY29uZmlnLnBhcmVudHx8dGhpcy5fYWRkQXJpYUFuZENvbGxhcHNlZENsYXNzKHRoaXMuX2VsZW1lbnQsdGhpcy5fdHJpZ2dlckFycmF5KSx0aGlzLl9jb25maWcudG9nZ2xlJiZ0aGlzLnRvZ2dsZSgpfXZhciB0PWEucHJvdG90eXBlO3JldHVybiB0LnRvZ2dsZT1mdW5jdGlvbigpe2codGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoeXQpP3RoaXMuaGlkZSgpOnRoaXMuc2hvdygpfSx0LnNob3c9ZnVuY3Rpb24oKXt2YXIgdCxlLG49dGhpcztpZighdGhpcy5faXNUcmFuc2l0aW9uaW5nJiYhZyh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyh5dCkmJih0aGlzLl9wYXJlbnQmJjA9PT0odD1bXS5zbGljZS5jYWxsKHRoaXMuX3BhcmVudC5xdWVyeVNlbGVjdG9yQWxsKER0KSkuZmlsdGVyKGZ1bmN0aW9uKHQpe3JldHVybiJzdHJpbmciPT10eXBlb2Ygbi5fY29uZmlnLnBhcmVudD90LmdldEF0dHJpYnV0ZSgiZGF0YS1wYXJlbnQiKT09PW4uX2NvbmZpZy5wYXJlbnQ6dC5jbGFzc0xpc3QuY29udGFpbnMoRXQpfSkpLmxlbmd0aCYmKHQ9bnVsbCksISh0JiYoZT1nKHQpLm5vdCh0aGlzLl9zZWxlY3RvcikuZGF0YShkdCkpJiZlLl9pc1RyYW5zaXRpb25pbmcpKSl7dmFyIGk9Zy5FdmVudCh2dC5TSE9XKTtpZihnKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIoaSksIWkuaXNEZWZhdWx0UHJldmVudGVkKCkpe3QmJihhLl9qUXVlcnlJbnRlcmZhY2UuY2FsbChnKHQpLm5vdCh0aGlzLl9zZWxlY3RvciksImhpZGUiKSxlfHxnKHQpLmRhdGEoZHQsbnVsbCkpO3ZhciBvPXRoaXMuX2dldERpbWVuc2lvbigpO2codGhpcy5fZWxlbWVudCkucmVtb3ZlQ2xhc3MoRXQpLmFkZENsYXNzKEN0KSx0aGlzLl9lbGVtZW50LnN0eWxlW29dPTAsdGhpcy5fdHJpZ2dlckFycmF5Lmxlbmd0aCYmZyh0aGlzLl90cmlnZ2VyQXJyYXkpLnJlbW92ZUNsYXNzKFR0KS5hdHRyKCJhcmlhLWV4cGFuZGVkIiwhMCksdGhpcy5zZXRUcmFuc2l0aW9uaW5nKCEwKTt2YXIgcj0ic2Nyb2xsIisob1swXS50b1VwcGVyQ2FzZSgpK28uc2xpY2UoMSkpLHM9Xy5nZXRUcmFuc2l0aW9uRHVyYXRpb25Gcm9tRWxlbWVudCh0aGlzLl9lbGVtZW50KTtnKHRoaXMuX2VsZW1lbnQpLm9uZShfLlRSQU5TSVRJT05fRU5ELGZ1bmN0aW9uKCl7ZyhuLl9lbGVtZW50KS5yZW1vdmVDbGFzcyhDdCkuYWRkQ2xhc3MoRXQpLmFkZENsYXNzKHl0KSxuLl9lbGVtZW50LnN0eWxlW29dPSIiLG4uc2V0VHJhbnNpdGlvbmluZyghMSksZyhuLl9lbGVtZW50KS50cmlnZ2VyKHZ0LlNIT1dOKX0pLmVtdWxhdGVUcmFuc2l0aW9uRW5kKHMpLHRoaXMuX2VsZW1lbnQuc3R5bGVbb109dGhpcy5fZWxlbWVudFtyXSsicHgifX19LHQuaGlkZT1mdW5jdGlvbigpe3ZhciB0PXRoaXM7aWYoIXRoaXMuX2lzVHJhbnNpdGlvbmluZyYmZyh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyh5dCkpe3ZhciBlPWcuRXZlbnQodnQuSElERSk7aWYoZyh0aGlzLl9lbGVtZW50KS50cmlnZ2VyKGUpLCFlLmlzRGVmYXVsdFByZXZlbnRlZCgpKXt2YXIgbj10aGlzLl9nZXREaW1lbnNpb24oKTt0aGlzLl9lbGVtZW50LnN0eWxlW25dPXRoaXMuX2VsZW1lbnQuZ2V0Qm91bmRpbmdDbGllbnRSZWN0KClbbl0rInB4IixfLnJlZmxvdyh0aGlzLl9lbGVtZW50KSxnKHRoaXMuX2VsZW1lbnQpLmFkZENsYXNzKEN0KS5yZW1vdmVDbGFzcyhFdCkucmVtb3ZlQ2xhc3MoeXQpO3ZhciBpPXRoaXMuX3RyaWdnZXJBcnJheS5sZW5ndGg7aWYoMDxpKWZvcih2YXIgbz0wO288aTtvKyspe3ZhciByPXRoaXMuX3RyaWdnZXJBcnJheVtvXSxzPV8uZ2V0U2VsZWN0b3JGcm9tRWxlbWVudChyKTtpZihudWxsIT09cylnKFtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChzKSkpLmhhc0NsYXNzKHl0KXx8ZyhyKS5hZGRDbGFzcyhUdCkuYXR0cigiYXJpYS1leHBhbmRlZCIsITEpfXRoaXMuc2V0VHJhbnNpdGlvbmluZyghMCk7dGhpcy5fZWxlbWVudC5zdHlsZVtuXT0iIjt2YXIgYT1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO2codGhpcy5fZWxlbWVudCkub25lKF8uVFJBTlNJVElPTl9FTkQsZnVuY3Rpb24oKXt0LnNldFRyYW5zaXRpb25pbmcoITEpLGcodC5fZWxlbWVudCkucmVtb3ZlQ2xhc3MoQ3QpLmFkZENsYXNzKEV0KS50cmlnZ2VyKHZ0LkhJRERFTil9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChhKX19fSx0LnNldFRyYW5zaXRpb25pbmc9ZnVuY3Rpb24odCl7dGhpcy5faXNUcmFuc2l0aW9uaW5nPXR9LHQuZGlzcG9zZT1mdW5jdGlvbigpe2cucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LGR0KSx0aGlzLl9jb25maWc9bnVsbCx0aGlzLl9wYXJlbnQ9bnVsbCx0aGlzLl9lbGVtZW50PW51bGwsdGhpcy5fdHJpZ2dlckFycmF5PW51bGwsdGhpcy5faXNUcmFuc2l0aW9uaW5nPW51bGx9LHQuX2dldENvbmZpZz1mdW5jdGlvbih0KXtyZXR1cm4odD1sKHt9LG10LHt9LHQpKS50b2dnbGU9Qm9vbGVhbih0LnRvZ2dsZSksXy50eXBlQ2hlY2tDb25maWcoZnQsdCxwdCksdH0sdC5fZ2V0RGltZW5zaW9uPWZ1bmN0aW9uKCl7cmV0dXJuIGcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoYnQpP2J0OlN0fSx0Ll9nZXRQYXJlbnQ9ZnVuY3Rpb24oKXt2YXIgdCxuPXRoaXM7Xy5pc0VsZW1lbnQodGhpcy5fY29uZmlnLnBhcmVudCk/KHQ9dGhpcy5fY29uZmlnLnBhcmVudCwidW5kZWZpbmVkIiE9dHlwZW9mIHRoaXMuX2NvbmZpZy5wYXJlbnQuanF1ZXJ5JiYodD10aGlzLl9jb25maWcucGFyZW50WzBdKSk6dD1kb2N1bWVudC5xdWVyeVNlbGVjdG9yKHRoaXMuX2NvbmZpZy5wYXJlbnQpO3ZhciBlPSdbZGF0YS10b2dnbGU9ImNvbGxhcHNlIl1bZGF0YS1wYXJlbnQ9IicrdGhpcy5fY29uZmlnLnBhcmVudCsnIl0nLGk9W10uc2xpY2UuY2FsbCh0LnF1ZXJ5U2VsZWN0b3JBbGwoZSkpO3JldHVybiBnKGkpLmVhY2goZnVuY3Rpb24odCxlKXtuLl9hZGRBcmlhQW5kQ29sbGFwc2VkQ2xhc3MoYS5fZ2V0VGFyZ2V0RnJvbUVsZW1lbnQoZSksW2VdKX0pLHR9LHQuX2FkZEFyaWFBbmRDb2xsYXBzZWRDbGFzcz1mdW5jdGlvbih0LGUpe3ZhciBuPWcodCkuaGFzQ2xhc3MoeXQpO2UubGVuZ3RoJiZnKGUpLnRvZ2dsZUNsYXNzKFR0LCFuKS5hdHRyKCJhcmlhLWV4cGFuZGVkIixuKX0sYS5fZ2V0VGFyZ2V0RnJvbUVsZW1lbnQ9ZnVuY3Rpb24odCl7dmFyIGU9Xy5nZXRTZWxlY3RvckZyb21FbGVtZW50KHQpO3JldHVybiBlP2RvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoZSk6bnVsbH0sYS5falF1ZXJ5SW50ZXJmYWNlPWZ1bmN0aW9uKGkpe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgdD1nKHRoaXMpLGU9dC5kYXRhKGR0KSxuPWwoe30sbXQse30sdC5kYXRhKCkse30sIm9iamVjdCI9PXR5cGVvZiBpJiZpP2k6e30pO2lmKCFlJiZuLnRvZ2dsZSYmL3Nob3d8aGlkZS8udGVzdChpKSYmKG4udG9nZ2xlPSExKSxlfHwoZT1uZXcgYSh0aGlzLG4pLHQuZGF0YShkdCxlKSksInN0cmluZyI9PXR5cGVvZiBpKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIGVbaV0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK2krJyInKTtlW2ldKCl9fSl9LHMoYSxudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX0se2tleToiRGVmYXVsdCIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIG10fX1dKSxhfSgpO2coZG9jdW1lbnQpLm9uKHZ0LkNMSUNLX0RBVEFfQVBJLEl0LGZ1bmN0aW9uKHQpeyJBIj09PXQuY3VycmVudFRhcmdldC50YWdOYW1lJiZ0LnByZXZlbnREZWZhdWx0KCk7dmFyIG49Zyh0aGlzKSxlPV8uZ2V0U2VsZWN0b3JGcm9tRWxlbWVudCh0aGlzKSxpPVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChlKSk7ZyhpKS5lYWNoKGZ1bmN0aW9uKCl7dmFyIHQ9Zyh0aGlzKSxlPXQuZGF0YShkdCk/InRvZ2dsZSI6bi5kYXRhKCk7d3QuX2pRdWVyeUludGVyZmFjZS5jYWxsKHQsZSl9KX0pLGcuZm5bZnRdPXd0Ll9qUXVlcnlJbnRlcmZhY2UsZy5mbltmdF0uQ29uc3RydWN0b3I9d3QsZy5mbltmdF0ubm9Db25mbGljdD1mdW5jdGlvbigpe3JldHVybiBnLmZuW2Z0XT1fdCx3dC5falF1ZXJ5SW50ZXJmYWNlfTt2YXIgQXQ9ImRyb3Bkb3duIixOdD0iYnMuZHJvcGRvd24iLE90PSIuIitOdCxrdD0iLmRhdGEtYXBpIixQdD1nLmZuW0F0XSxMdD1uZXcgUmVnRXhwKCIzOHw0MHwyNyIpLGp0PXtISURFOiJoaWRlIitPdCxISURERU46ImhpZGRlbiIrT3QsU0hPVzoic2hvdyIrT3QsU0hPV046InNob3duIitPdCxDTElDSzoiY2xpY2siK090LENMSUNLX0RBVEFfQVBJOiJjbGljayIrT3Qra3QsS0VZRE9XTl9EQVRBX0FQSToia2V5ZG93biIrT3Qra3QsS0VZVVBfREFUQV9BUEk6ImtleXVwIitPdCtrdH0sSHQ9ImRpc2FibGVkIixSdD0ic2hvdyIseHQ9ImRyb3B1cCIsRnQ9ImRyb3ByaWdodCIsVXQ9ImRyb3BsZWZ0IixXdD0iZHJvcGRvd24tbWVudS1yaWdodCIscXQ9InBvc2l0aW9uLXN0YXRpYyIsTXQ9J1tkYXRhLXRvZ2dsZT0iZHJvcGRvd24iXScsS3Q9Ii5kcm9wZG93biBmb3JtIixRdD0iLmRyb3Bkb3duLW1lbnUiLEJ0PSIubmF2YmFyLW5hdiIsVnQ9Ii5kcm9wZG93bi1tZW51IC5kcm9wZG93bi1pdGVtOm5vdCguZGlzYWJsZWQpOm5vdCg6ZGlzYWJsZWQpIixZdD0idG9wLXN0YXJ0Iix6dD0idG9wLWVuZCIsWHQ9ImJvdHRvbS1zdGFydCIsJHQ9ImJvdHRvbS1lbmQiLEd0PSJyaWdodC1zdGFydCIsSnQ9ImxlZnQtc3RhcnQiLFp0PXtvZmZzZXQ6MCxmbGlwOiEwLGJvdW5kYXJ5OiJzY3JvbGxQYXJlbnQiLHJlZmVyZW5jZToidG9nZ2xlIixkaXNwbGF5OiJkeW5hbWljIixwb3BwZXJDb25maWc6bnVsbH0sdGU9e29mZnNldDoiKG51bWJlcnxzdHJpbmd8ZnVuY3Rpb24pIixmbGlwOiJib29sZWFuIixib3VuZGFyeToiKHN0cmluZ3xlbGVtZW50KSIscmVmZXJlbmNlOiIoc3RyaW5nfGVsZW1lbnQpIixkaXNwbGF5OiJzdHJpbmciLHBvcHBlckNvbmZpZzoiKG51bGx8b2JqZWN0KSJ9LGVlPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gYyh0LGUpe3RoaXMuX2VsZW1lbnQ9dCx0aGlzLl9wb3BwZXI9bnVsbCx0aGlzLl9jb25maWc9dGhpcy5fZ2V0Q29uZmlnKGUpLHRoaXMuX21lbnU9dGhpcy5fZ2V0TWVudUVsZW1lbnQoKSx0aGlzLl9pbk5hdmJhcj10aGlzLl9kZXRlY3ROYXZiYXIoKSx0aGlzLl9hZGRFdmVudExpc3RlbmVycygpfXZhciB0PWMucHJvdG90eXBlO3JldHVybiB0LnRvZ2dsZT1mdW5jdGlvbigpe2lmKCF0aGlzLl9lbGVtZW50LmRpc2FibGVkJiYhZyh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyhIdCkpe3ZhciB0PWcodGhpcy5fbWVudSkuaGFzQ2xhc3MoUnQpO2MuX2NsZWFyTWVudXMoKSx0fHx0aGlzLnNob3coITApfX0sdC5zaG93PWZ1bmN0aW9uKHQpe2lmKHZvaWQgMD09PXQmJih0PSExKSwhKHRoaXMuX2VsZW1lbnQuZGlzYWJsZWR8fGcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoSHQpfHxnKHRoaXMuX21lbnUpLmhhc0NsYXNzKFJ0KSkpe3ZhciBlPXtyZWxhdGVkVGFyZ2V0OnRoaXMuX2VsZW1lbnR9LG49Zy5FdmVudChqdC5TSE9XLGUpLGk9Yy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7aWYoZyhpKS50cmlnZ2VyKG4pLCFuLmlzRGVmYXVsdFByZXZlbnRlZCgpKXtpZighdGhpcy5faW5OYXZiYXImJnQpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdSl0aHJvdyBuZXcgVHlwZUVycm9yKCJCb290c3RyYXAncyBkcm9wZG93bnMgcmVxdWlyZSBQb3BwZXIuanMgKGh0dHBzOi8vcG9wcGVyLmpzLm9yZy8pIik7dmFyIG89dGhpcy5fZWxlbWVudDsicGFyZW50Ij09PXRoaXMuX2NvbmZpZy5yZWZlcmVuY2U/bz1pOl8uaXNFbGVtZW50KHRoaXMuX2NvbmZpZy5yZWZlcmVuY2UpJiYobz10aGlzLl9jb25maWcucmVmZXJlbmNlLCJ1bmRlZmluZWQiIT10eXBlb2YgdGhpcy5fY29uZmlnLnJlZmVyZW5jZS5qcXVlcnkmJihvPXRoaXMuX2NvbmZpZy5yZWZlcmVuY2VbMF0pKSwic2Nyb2xsUGFyZW50IiE9PXRoaXMuX2NvbmZpZy5ib3VuZGFyeSYmZyhpKS5hZGRDbGFzcyhxdCksdGhpcy5fcG9wcGVyPW5ldyB1KG8sdGhpcy5fbWVudSx0aGlzLl9nZXRQb3BwZXJDb25maWcoKSl9Im9udG91Y2hzdGFydCJpbiBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQmJjA9PT1nKGkpLmNsb3Nlc3QoQnQpLmxlbmd0aCYmZyhkb2N1bWVudC5ib2R5KS5jaGlsZHJlbigpLm9uKCJtb3VzZW92ZXIiLG51bGwsZy5ub29wKSx0aGlzLl9lbGVtZW50LmZvY3VzKCksdGhpcy5fZWxlbWVudC5zZXRBdHRyaWJ1dGUoImFyaWEtZXhwYW5kZWQiLCEwKSxnKHRoaXMuX21lbnUpLnRvZ2dsZUNsYXNzKFJ0KSxnKGkpLnRvZ2dsZUNsYXNzKFJ0KS50cmlnZ2VyKGcuRXZlbnQoanQuU0hPV04sZSkpfX19LHQuaGlkZT1mdW5jdGlvbigpe2lmKCF0aGlzLl9lbGVtZW50LmRpc2FibGVkJiYhZyh0aGlzLl9lbGVtZW50KS5oYXNDbGFzcyhIdCkmJmcodGhpcy5fbWVudSkuaGFzQ2xhc3MoUnQpKXt2YXIgdD17cmVsYXRlZFRhcmdldDp0aGlzLl9lbGVtZW50fSxlPWcuRXZlbnQoanQuSElERSx0KSxuPWMuX2dldFBhcmVudEZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO2cobikudHJpZ2dlcihlKSxlLmlzRGVmYXVsdFByZXZlbnRlZCgpfHwodGhpcy5fcG9wcGVyJiZ0aGlzLl9wb3BwZXIuZGVzdHJveSgpLGcodGhpcy5fbWVudSkudG9nZ2xlQ2xhc3MoUnQpLGcobikudG9nZ2xlQ2xhc3MoUnQpLnRyaWdnZXIoZy5FdmVudChqdC5ISURERU4sdCkpKX19LHQuZGlzcG9zZT1mdW5jdGlvbigpe2cucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LE50KSxnKHRoaXMuX2VsZW1lbnQpLm9mZihPdCksdGhpcy5fZWxlbWVudD1udWxsLCh0aGlzLl9tZW51PW51bGwpIT09dGhpcy5fcG9wcGVyJiYodGhpcy5fcG9wcGVyLmRlc3Ryb3koKSx0aGlzLl9wb3BwZXI9bnVsbCl9LHQudXBkYXRlPWZ1bmN0aW9uKCl7dGhpcy5faW5OYXZiYXI9dGhpcy5fZGV0ZWN0TmF2YmFyKCksbnVsbCE9PXRoaXMuX3BvcHBlciYmdGhpcy5fcG9wcGVyLnNjaGVkdWxlVXBkYXRlKCl9LHQuX2FkZEV2ZW50TGlzdGVuZXJzPWZ1bmN0aW9uKCl7dmFyIGU9dGhpcztnKHRoaXMuX2VsZW1lbnQpLm9uKGp0LkNMSUNLLGZ1bmN0aW9uKHQpe3QucHJldmVudERlZmF1bHQoKSx0LnN0b3BQcm9wYWdhdGlvbigpLGUudG9nZ2xlKCl9KX0sdC5fZ2V0Q29uZmlnPWZ1bmN0aW9uKHQpe3JldHVybiB0PWwoe30sdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0LHt9LGcodGhpcy5fZWxlbWVudCkuZGF0YSgpLHt9LHQpLF8udHlwZUNoZWNrQ29uZmlnKEF0LHQsdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0VHlwZSksdH0sdC5fZ2V0TWVudUVsZW1lbnQ9ZnVuY3Rpb24oKXtpZighdGhpcy5fbWVudSl7dmFyIHQ9Yy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7dCYmKHRoaXMuX21lbnU9dC5xdWVyeVNlbGVjdG9yKFF0KSl9cmV0dXJuIHRoaXMuX21lbnV9LHQuX2dldFBsYWNlbWVudD1mdW5jdGlvbigpe3ZhciB0PWcodGhpcy5fZWxlbWVudC5wYXJlbnROb2RlKSxlPVh0O3JldHVybiB0Lmhhc0NsYXNzKHh0KT8oZT1ZdCxnKHRoaXMuX21lbnUpLmhhc0NsYXNzKFd0KSYmKGU9enQpKTp0Lmhhc0NsYXNzKEZ0KT9lPUd0OnQuaGFzQ2xhc3MoVXQpP2U9SnQ6Zyh0aGlzLl9tZW51KS5oYXNDbGFzcyhXdCkmJihlPSR0KSxlfSx0Ll9kZXRlY3ROYXZiYXI9ZnVuY3Rpb24oKXtyZXR1cm4gMDxnKHRoaXMuX2VsZW1lbnQpLmNsb3Nlc3QoIi5uYXZiYXIiKS5sZW5ndGh9LHQuX2dldE9mZnNldD1mdW5jdGlvbigpe3ZhciBlPXRoaXMsdD17fTtyZXR1cm4iZnVuY3Rpb24iPT10eXBlb2YgdGhpcy5fY29uZmlnLm9mZnNldD90LmZuPWZ1bmN0aW9uKHQpe3JldHVybiB0Lm9mZnNldHM9bCh7fSx0Lm9mZnNldHMse30sZS5fY29uZmlnLm9mZnNldCh0Lm9mZnNldHMsZS5fZWxlbWVudCl8fHt9KSx0fTp0Lm9mZnNldD10aGlzLl9jb25maWcub2Zmc2V0LHR9LHQuX2dldFBvcHBlckNvbmZpZz1mdW5jdGlvbigpe3ZhciB0PXtwbGFjZW1lbnQ6dGhpcy5fZ2V0UGxhY2VtZW50KCksbW9kaWZpZXJzOntvZmZzZXQ6dGhpcy5fZ2V0T2Zmc2V0KCksZmxpcDp7ZW5hYmxlZDp0aGlzLl9jb25maWcuZmxpcH0scHJldmVudE92ZXJmbG93Ontib3VuZGFyaWVzRWxlbWVudDp0aGlzLl9jb25maWcuYm91bmRhcnl9fX07cmV0dXJuInN0YXRpYyI9PT10aGlzLl9jb25maWcuZGlzcGxheSYmKHQubW9kaWZpZXJzLmFwcGx5U3R5bGU9e2VuYWJsZWQ6ITF9KSxsKHt9LHQse30sdGhpcy5fY29uZmlnLnBvcHBlckNvbmZpZyl9LGMuX2pRdWVyeUludGVyZmFjZT1mdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIHQ9Zyh0aGlzKS5kYXRhKE50KTtpZih0fHwodD1uZXcgYyh0aGlzLCJvYmplY3QiPT10eXBlb2YgZT9lOm51bGwpLGcodGhpcykuZGF0YShOdCx0KSksInN0cmluZyI9PXR5cGVvZiBlKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIHRbZV0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK2UrJyInKTt0W2VdKCl9fSl9LGMuX2NsZWFyTWVudXM9ZnVuY3Rpb24odCl7aWYoIXR8fDMhPT10LndoaWNoJiYoImtleXVwIiE9PXQudHlwZXx8OT09PXQud2hpY2gpKWZvcih2YXIgZT1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoTXQpKSxuPTAsaT1lLmxlbmd0aDtuPGk7bisrKXt2YXIgbz1jLl9nZXRQYXJlbnRGcm9tRWxlbWVudChlW25dKSxyPWcoZVtuXSkuZGF0YShOdCkscz17cmVsYXRlZFRhcmdldDplW25dfTtpZih0JiYiY2xpY2siPT09dC50eXBlJiYocy5jbGlja0V2ZW50PXQpLHIpe3ZhciBhPXIuX21lbnU7aWYoZyhvKS5oYXNDbGFzcyhSdCkmJiEodCYmKCJjbGljayI9PT10LnR5cGUmJi9pbnB1dHx0ZXh0YXJlYS9pLnRlc3QodC50YXJnZXQudGFnTmFtZSl8fCJrZXl1cCI9PT10LnR5cGUmJjk9PT10LndoaWNoKSYmZy5jb250YWlucyhvLHQudGFyZ2V0KSkpe3ZhciBsPWcuRXZlbnQoanQuSElERSxzKTtnKG8pLnRyaWdnZXIobCksbC5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8KCJvbnRvdWNoc3RhcnQiaW4gZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50JiZnKGRvY3VtZW50LmJvZHkpLmNoaWxkcmVuKCkub2ZmKCJtb3VzZW92ZXIiLG51bGwsZy5ub29wKSxlW25dLnNldEF0dHJpYnV0ZSgiYXJpYS1leHBhbmRlZCIsImZhbHNlIiksci5fcG9wcGVyJiZyLl9wb3BwZXIuZGVzdHJveSgpLGcoYSkucmVtb3ZlQ2xhc3MoUnQpLGcobykucmVtb3ZlQ2xhc3MoUnQpLnRyaWdnZXIoZy5FdmVudChqdC5ISURERU4scykpKX19fX0sYy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQ9ZnVuY3Rpb24odCl7dmFyIGUsbj1fLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQodCk7cmV0dXJuIG4mJihlPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IobikpLGV8fHQucGFyZW50Tm9kZX0sYy5fZGF0YUFwaUtleWRvd25IYW5kbGVyPWZ1bmN0aW9uKHQpe2lmKCgvaW5wdXR8dGV4dGFyZWEvaS50ZXN0KHQudGFyZ2V0LnRhZ05hbWUpPyEoMzI9PT10LndoaWNofHwyNyE9PXQud2hpY2gmJig0MCE9PXQud2hpY2gmJjM4IT09dC53aGljaHx8Zyh0LnRhcmdldCkuY2xvc2VzdChRdCkubGVuZ3RoKSk6THQudGVzdCh0LndoaWNoKSkmJih0LnByZXZlbnREZWZhdWx0KCksdC5zdG9wUHJvcGFnYXRpb24oKSwhdGhpcy5kaXNhYmxlZCYmIWcodGhpcykuaGFzQ2xhc3MoSHQpKSl7dmFyIGU9Yy5fZ2V0UGFyZW50RnJvbUVsZW1lbnQodGhpcyksbj1nKGUpLmhhc0NsYXNzKFJ0KTtpZihufHwyNyE9PXQud2hpY2gpaWYobiYmKCFufHwyNyE9PXQud2hpY2gmJjMyIT09dC53aGljaCkpe3ZhciBpPVtdLnNsaWNlLmNhbGwoZS5xdWVyeVNlbGVjdG9yQWxsKFZ0KSkuZmlsdGVyKGZ1bmN0aW9uKHQpe3JldHVybiBnKHQpLmlzKCI6dmlzaWJsZSIpfSk7aWYoMCE9PWkubGVuZ3RoKXt2YXIgbz1pLmluZGV4T2YodC50YXJnZXQpOzM4PT09dC53aGljaCYmMDxvJiZvLS0sNDA9PT10LndoaWNoJiZvPGkubGVuZ3RoLTEmJm8rKyxvPDAmJihvPTApLGlbb10uZm9jdXMoKX19ZWxzZXtpZigyNz09PXQud2hpY2gpe3ZhciByPWUucXVlcnlTZWxlY3RvcihNdCk7ZyhyKS50cmlnZ2VyKCJmb2N1cyIpfWcodGhpcykudHJpZ2dlcigiY2xpY2siKX19fSxzKGMsbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBadH19LHtrZXk6IkRlZmF1bHRUeXBlIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gdGV9fV0pLGN9KCk7Zyhkb2N1bWVudCkub24oanQuS0VZRE9XTl9EQVRBX0FQSSxNdCxlZS5fZGF0YUFwaUtleWRvd25IYW5kbGVyKS5vbihqdC5LRVlET1dOX0RBVEFfQVBJLFF0LGVlLl9kYXRhQXBpS2V5ZG93bkhhbmRsZXIpLm9uKGp0LkNMSUNLX0RBVEFfQVBJKyIgIitqdC5LRVlVUF9EQVRBX0FQSSxlZS5fY2xlYXJNZW51cykub24oanQuQ0xJQ0tfREFUQV9BUEksTXQsZnVuY3Rpb24odCl7dC5wcmV2ZW50RGVmYXVsdCgpLHQuc3RvcFByb3BhZ2F0aW9uKCksZWUuX2pRdWVyeUludGVyZmFjZS5jYWxsKGcodGhpcyksInRvZ2dsZSIpfSkub24oanQuQ0xJQ0tfREFUQV9BUEksS3QsZnVuY3Rpb24odCl7dC5zdG9wUHJvcGFnYXRpb24oKX0pLGcuZm5bQXRdPWVlLl9qUXVlcnlJbnRlcmZhY2UsZy5mbltBdF0uQ29uc3RydWN0b3I9ZWUsZy5mbltBdF0ubm9Db25mbGljdD1mdW5jdGlvbigpe3JldHVybiBnLmZuW0F0XT1QdCxlZS5falF1ZXJ5SW50ZXJmYWNlfTt2YXIgbmU9Im1vZGFsIixpZT0iYnMubW9kYWwiLG9lPSIuIitpZSxyZT1nLmZuW25lXSxzZT17YmFja2Ryb3A6ITAsa2V5Ym9hcmQ6ITAsZm9jdXM6ITAsc2hvdzohMH0sYWU9e2JhY2tkcm9wOiIoYm9vbGVhbnxzdHJpbmcpIixrZXlib2FyZDoiYm9vbGVhbiIsZm9jdXM6ImJvb2xlYW4iLHNob3c6ImJvb2xlYW4ifSxsZT17SElERToiaGlkZSIrb2UsSElERV9QUkVWRU5URUQ6ImhpZGVQcmV2ZW50ZWQiK29lLEhJRERFTjoiaGlkZGVuIitvZSxTSE9XOiJzaG93IitvZSxTSE9XTjoic2hvd24iK29lLEZPQ1VTSU46ImZvY3VzaW4iK29lLFJFU0laRToicmVzaXplIitvZSxDTElDS19ESVNNSVNTOiJjbGljay5kaXNtaXNzIitvZSxLRVlET1dOX0RJU01JU1M6ImtleWRvd24uZGlzbWlzcyIrb2UsTU9VU0VVUF9ESVNNSVNTOiJtb3VzZXVwLmRpc21pc3MiK29lLE1PVVNFRE9XTl9ESVNNSVNTOiJtb3VzZWRvd24uZGlzbWlzcyIrb2UsQ0xJQ0tfREFUQV9BUEk6ImNsaWNrIitvZSsiLmRhdGEtYXBpIn0sY2U9Im1vZGFsLWRpYWxvZy1zY3JvbGxhYmxlIixoZT0ibW9kYWwtc2Nyb2xsYmFyLW1lYXN1cmUiLHVlPSJtb2RhbC1iYWNrZHJvcCIsZmU9Im1vZGFsLW9wZW4iLGRlPSJmYWRlIixnZT0ic2hvdyIsX2U9Im1vZGFsLXN0YXRpYyIsbWU9Ii5tb2RhbC1kaWFsb2ciLHBlPSIubW9kYWwtYm9keSIsdmU9J1tkYXRhLXRvZ2dsZT0ibW9kYWwiXScseWU9J1tkYXRhLWRpc21pc3M9Im1vZGFsIl0nLEVlPSIuZml4ZWQtdG9wLCAuZml4ZWQtYm90dG9tLCAuaXMtZml4ZWQsIC5zdGlja3ktdG9wIixDZT0iLnN0aWNreS10b3AiLFRlPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gbyh0LGUpe3RoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcoZSksdGhpcy5fZWxlbWVudD10LHRoaXMuX2RpYWxvZz10LnF1ZXJ5U2VsZWN0b3IobWUpLHRoaXMuX2JhY2tkcm9wPW51bGwsdGhpcy5faXNTaG93bj0hMSx0aGlzLl9pc0JvZHlPdmVyZmxvd2luZz0hMSx0aGlzLl9pZ25vcmVCYWNrZHJvcENsaWNrPSExLHRoaXMuX2lzVHJhbnNpdGlvbmluZz0hMSx0aGlzLl9zY3JvbGxiYXJXaWR0aD0wfXZhciB0PW8ucHJvdG90eXBlO3JldHVybiB0LnRvZ2dsZT1mdW5jdGlvbih0KXtyZXR1cm4gdGhpcy5faXNTaG93bj90aGlzLmhpZGUoKTp0aGlzLnNob3codCl9LHQuc2hvdz1mdW5jdGlvbih0KXt2YXIgZT10aGlzO2lmKCF0aGlzLl9pc1Nob3duJiYhdGhpcy5faXNUcmFuc2l0aW9uaW5nKXtnKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKGRlKSYmKHRoaXMuX2lzVHJhbnNpdGlvbmluZz0hMCk7dmFyIG49Zy5FdmVudChsZS5TSE9XLHtyZWxhdGVkVGFyZ2V0OnR9KTtnKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIobiksdGhpcy5faXNTaG93bnx8bi5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8KHRoaXMuX2lzU2hvd249ITAsdGhpcy5fY2hlY2tTY3JvbGxiYXIoKSx0aGlzLl9zZXRTY3JvbGxiYXIoKSx0aGlzLl9hZGp1c3REaWFsb2coKSx0aGlzLl9zZXRFc2NhcGVFdmVudCgpLHRoaXMuX3NldFJlc2l6ZUV2ZW50KCksZyh0aGlzLl9lbGVtZW50KS5vbihsZS5DTElDS19ESVNNSVNTLHllLGZ1bmN0aW9uKHQpe3JldHVybiBlLmhpZGUodCl9KSxnKHRoaXMuX2RpYWxvZykub24obGUuTU9VU0VET1dOX0RJU01JU1MsZnVuY3Rpb24oKXtnKGUuX2VsZW1lbnQpLm9uZShsZS5NT1VTRVVQX0RJU01JU1MsZnVuY3Rpb24odCl7Zyh0LnRhcmdldCkuaXMoZS5fZWxlbWVudCkmJihlLl9pZ25vcmVCYWNrZHJvcENsaWNrPSEwKX0pfSksdGhpcy5fc2hvd0JhY2tkcm9wKGZ1bmN0aW9uKCl7cmV0dXJuIGUuX3Nob3dFbGVtZW50KHQpfSkpfX0sdC5oaWRlPWZ1bmN0aW9uKHQpe3ZhciBlPXRoaXM7aWYodCYmdC5wcmV2ZW50RGVmYXVsdCgpLHRoaXMuX2lzU2hvd24mJiF0aGlzLl9pc1RyYW5zaXRpb25pbmcpe3ZhciBuPWcuRXZlbnQobGUuSElERSk7aWYoZyh0aGlzLl9lbGVtZW50KS50cmlnZ2VyKG4pLHRoaXMuX2lzU2hvd24mJiFuLmlzRGVmYXVsdFByZXZlbnRlZCgpKXt0aGlzLl9pc1Nob3duPSExO3ZhciBpPWcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoZGUpO2lmKGkmJih0aGlzLl9pc1RyYW5zaXRpb25pbmc9ITApLHRoaXMuX3NldEVzY2FwZUV2ZW50KCksdGhpcy5fc2V0UmVzaXplRXZlbnQoKSxnKGRvY3VtZW50KS5vZmYobGUuRk9DVVNJTiksZyh0aGlzLl9lbGVtZW50KS5yZW1vdmVDbGFzcyhnZSksZyh0aGlzLl9lbGVtZW50KS5vZmYobGUuQ0xJQ0tfRElTTUlTUyksZyh0aGlzLl9kaWFsb2cpLm9mZihsZS5NT1VTRURPV05fRElTTUlTUyksaSl7dmFyIG89Xy5nZXRUcmFuc2l0aW9uRHVyYXRpb25Gcm9tRWxlbWVudCh0aGlzLl9lbGVtZW50KTtnKHRoaXMuX2VsZW1lbnQpLm9uZShfLlRSQU5TSVRJT05fRU5ELGZ1bmN0aW9uKHQpe3JldHVybiBlLl9oaWRlTW9kYWwodCl9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChvKX1lbHNlIHRoaXMuX2hpZGVNb2RhbCgpfX19LHQuZGlzcG9zZT1mdW5jdGlvbigpe1t3aW5kb3csdGhpcy5fZWxlbWVudCx0aGlzLl9kaWFsb2ddLmZvckVhY2goZnVuY3Rpb24odCl7cmV0dXJuIGcodCkub2ZmKG9lKX0pLGcoZG9jdW1lbnQpLm9mZihsZS5GT0NVU0lOKSxnLnJlbW92ZURhdGEodGhpcy5fZWxlbWVudCxpZSksdGhpcy5fY29uZmlnPW51bGwsdGhpcy5fZWxlbWVudD1udWxsLHRoaXMuX2RpYWxvZz1udWxsLHRoaXMuX2JhY2tkcm9wPW51bGwsdGhpcy5faXNTaG93bj1udWxsLHRoaXMuX2lzQm9keU92ZXJmbG93aW5nPW51bGwsdGhpcy5faWdub3JlQmFja2Ryb3BDbGljaz1udWxsLHRoaXMuX2lzVHJhbnNpdGlvbmluZz1udWxsLHRoaXMuX3Njcm9sbGJhcldpZHRoPW51bGx9LHQuaGFuZGxlVXBkYXRlPWZ1bmN0aW9uKCl7dGhpcy5fYWRqdXN0RGlhbG9nKCl9LHQuX2dldENvbmZpZz1mdW5jdGlvbih0KXtyZXR1cm4gdD1sKHt9LHNlLHt9LHQpLF8udHlwZUNoZWNrQ29uZmlnKG5lLHQsYWUpLHR9LHQuX3RyaWdnZXJCYWNrZHJvcFRyYW5zaXRpb249ZnVuY3Rpb24oKXt2YXIgdD10aGlzO2lmKCJzdGF0aWMiPT09dGhpcy5fY29uZmlnLmJhY2tkcm9wKXt2YXIgZT1nLkV2ZW50KGxlLkhJREVfUFJFVkVOVEVEKTtpZihnKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIoZSksZS5kZWZhdWx0UHJldmVudGVkKXJldHVybjt0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5hZGQoX2UpO3ZhciBuPV8uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZWxlbWVudCk7Zyh0aGlzLl9lbGVtZW50KS5vbmUoXy5UUkFOU0lUSU9OX0VORCxmdW5jdGlvbigpe3QuX2VsZW1lbnQuY2xhc3NMaXN0LnJlbW92ZShfZSl9KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChuKSx0aGlzLl9lbGVtZW50LmZvY3VzKCl9ZWxzZSB0aGlzLmhpZGUoKX0sdC5fc2hvd0VsZW1lbnQ9ZnVuY3Rpb24odCl7dmFyIGU9dGhpcyxuPWcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoZGUpLGk9dGhpcy5fZGlhbG9nP3RoaXMuX2RpYWxvZy5xdWVyeVNlbGVjdG9yKHBlKTpudWxsO3RoaXMuX2VsZW1lbnQucGFyZW50Tm9kZSYmdGhpcy5fZWxlbWVudC5wYXJlbnROb2RlLm5vZGVUeXBlPT09Tm9kZS5FTEVNRU5UX05PREV8fGRvY3VtZW50LmJvZHkuYXBwZW5kQ2hpbGQodGhpcy5fZWxlbWVudCksdGhpcy5fZWxlbWVudC5zdHlsZS5kaXNwbGF5PSJibG9jayIsdGhpcy5fZWxlbWVudC5yZW1vdmVBdHRyaWJ1dGUoImFyaWEtaGlkZGVuIiksdGhpcy5fZWxlbWVudC5zZXRBdHRyaWJ1dGUoImFyaWEtbW9kYWwiLCEwKSxnKHRoaXMuX2RpYWxvZykuaGFzQ2xhc3MoY2UpJiZpP2kuc2Nyb2xsVG9wPTA6dGhpcy5fZWxlbWVudC5zY3JvbGxUb3A9MCxuJiZfLnJlZmxvdyh0aGlzLl9lbGVtZW50KSxnKHRoaXMuX2VsZW1lbnQpLmFkZENsYXNzKGdlKSx0aGlzLl9jb25maWcuZm9jdXMmJnRoaXMuX2VuZm9yY2VGb2N1cygpO2Z1bmN0aW9uIG8oKXtlLl9jb25maWcuZm9jdXMmJmUuX2VsZW1lbnQuZm9jdXMoKSxlLl9pc1RyYW5zaXRpb25pbmc9ITEsZyhlLl9lbGVtZW50KS50cmlnZ2VyKHIpfXZhciByPWcuRXZlbnQobGUuU0hPV04se3JlbGF0ZWRUYXJnZXQ6dH0pO2lmKG4pe3ZhciBzPV8uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQodGhpcy5fZGlhbG9nKTtnKHRoaXMuX2RpYWxvZykub25lKF8uVFJBTlNJVElPTl9FTkQsbykuZW11bGF0ZVRyYW5zaXRpb25FbmQocyl9ZWxzZSBvKCl9LHQuX2VuZm9yY2VGb2N1cz1mdW5jdGlvbigpe3ZhciBlPXRoaXM7Zyhkb2N1bWVudCkub2ZmKGxlLkZPQ1VTSU4pLm9uKGxlLkZPQ1VTSU4sZnVuY3Rpb24odCl7ZG9jdW1lbnQhPT10LnRhcmdldCYmZS5fZWxlbWVudCE9PXQudGFyZ2V0JiYwPT09ZyhlLl9lbGVtZW50KS5oYXModC50YXJnZXQpLmxlbmd0aCYmZS5fZWxlbWVudC5mb2N1cygpfSl9LHQuX3NldEVzY2FwZUV2ZW50PWZ1bmN0aW9uKCl7dmFyIGU9dGhpczt0aGlzLl9pc1Nob3duJiZ0aGlzLl9jb25maWcua2V5Ym9hcmQ/Zyh0aGlzLl9lbGVtZW50KS5vbihsZS5LRVlET1dOX0RJU01JU1MsZnVuY3Rpb24odCl7Mjc9PT10LndoaWNoJiZlLl90cmlnZ2VyQmFja2Ryb3BUcmFuc2l0aW9uKCl9KTp0aGlzLl9pc1Nob3dufHxnKHRoaXMuX2VsZW1lbnQpLm9mZihsZS5LRVlET1dOX0RJU01JU1MpfSx0Ll9zZXRSZXNpemVFdmVudD1mdW5jdGlvbigpe3ZhciBlPXRoaXM7dGhpcy5faXNTaG93bj9nKHdpbmRvdykub24obGUuUkVTSVpFLGZ1bmN0aW9uKHQpe3JldHVybiBlLmhhbmRsZVVwZGF0ZSh0KX0pOmcod2luZG93KS5vZmYobGUuUkVTSVpFKX0sdC5faGlkZU1vZGFsPWZ1bmN0aW9uKCl7dmFyIHQ9dGhpczt0aGlzLl9lbGVtZW50LnN0eWxlLmRpc3BsYXk9Im5vbmUiLHRoaXMuX2VsZW1lbnQuc2V0QXR0cmlidXRlKCJhcmlhLWhpZGRlbiIsITApLHRoaXMuX2VsZW1lbnQucmVtb3ZlQXR0cmlidXRlKCJhcmlhLW1vZGFsIiksdGhpcy5faXNUcmFuc2l0aW9uaW5nPSExLHRoaXMuX3Nob3dCYWNrZHJvcChmdW5jdGlvbigpe2coZG9jdW1lbnQuYm9keSkucmVtb3ZlQ2xhc3MoZmUpLHQuX3Jlc2V0QWRqdXN0bWVudHMoKSx0Ll9yZXNldFNjcm9sbGJhcigpLGcodC5fZWxlbWVudCkudHJpZ2dlcihsZS5ISURERU4pfSl9LHQuX3JlbW92ZUJhY2tkcm9wPWZ1bmN0aW9uKCl7dGhpcy5fYmFja2Ryb3AmJihnKHRoaXMuX2JhY2tkcm9wKS5yZW1vdmUoKSx0aGlzLl9iYWNrZHJvcD1udWxsKX0sdC5fc2hvd0JhY2tkcm9wPWZ1bmN0aW9uKHQpe3ZhciBlPXRoaXMsbj1nKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKGRlKT9kZToiIjtpZih0aGlzLl9pc1Nob3duJiZ0aGlzLl9jb25maWcuYmFja2Ryb3Ape2lmKHRoaXMuX2JhY2tkcm9wPWRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoImRpdiIpLHRoaXMuX2JhY2tkcm9wLmNsYXNzTmFtZT11ZSxuJiZ0aGlzLl9iYWNrZHJvcC5jbGFzc0xpc3QuYWRkKG4pLGcodGhpcy5fYmFja2Ryb3ApLmFwcGVuZFRvKGRvY3VtZW50LmJvZHkpLGcodGhpcy5fZWxlbWVudCkub24obGUuQ0xJQ0tfRElTTUlTUyxmdW5jdGlvbih0KXtlLl9pZ25vcmVCYWNrZHJvcENsaWNrP2UuX2lnbm9yZUJhY2tkcm9wQ2xpY2s9ITE6dC50YXJnZXQ9PT10LmN1cnJlbnRUYXJnZXQmJmUuX3RyaWdnZXJCYWNrZHJvcFRyYW5zaXRpb24oKX0pLG4mJl8ucmVmbG93KHRoaXMuX2JhY2tkcm9wKSxnKHRoaXMuX2JhY2tkcm9wKS5hZGRDbGFzcyhnZSksIXQpcmV0dXJuO2lmKCFuKXJldHVybiB2b2lkIHQoKTt2YXIgaT1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2JhY2tkcm9wKTtnKHRoaXMuX2JhY2tkcm9wKS5vbmUoXy5UUkFOU0lUSU9OX0VORCx0KS5lbXVsYXRlVHJhbnNpdGlvbkVuZChpKX1lbHNlIGlmKCF0aGlzLl9pc1Nob3duJiZ0aGlzLl9iYWNrZHJvcCl7Zyh0aGlzLl9iYWNrZHJvcCkucmVtb3ZlQ2xhc3MoZ2UpO3ZhciBvPWZ1bmN0aW9uKCl7ZS5fcmVtb3ZlQmFja2Ryb3AoKSx0JiZ0KCl9O2lmKGcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoZGUpKXt2YXIgcj1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2JhY2tkcm9wKTtnKHRoaXMuX2JhY2tkcm9wKS5vbmUoXy5UUkFOU0lUSU9OX0VORCxvKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChyKX1lbHNlIG8oKX1lbHNlIHQmJnQoKX0sdC5fYWRqdXN0RGlhbG9nPWZ1bmN0aW9uKCl7dmFyIHQ9dGhpcy5fZWxlbWVudC5zY3JvbGxIZWlnaHQ+ZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LmNsaWVudEhlaWdodDshdGhpcy5faXNCb2R5T3ZlcmZsb3dpbmcmJnQmJih0aGlzLl9lbGVtZW50LnN0eWxlLnBhZGRpbmdMZWZ0PXRoaXMuX3Njcm9sbGJhcldpZHRoKyJweCIpLHRoaXMuX2lzQm9keU92ZXJmbG93aW5nJiYhdCYmKHRoaXMuX2VsZW1lbnQuc3R5bGUucGFkZGluZ1JpZ2h0PXRoaXMuX3Njcm9sbGJhcldpZHRoKyJweCIpfSx0Ll9yZXNldEFkanVzdG1lbnRzPWZ1bmN0aW9uKCl7dGhpcy5fZWxlbWVudC5zdHlsZS5wYWRkaW5nTGVmdD0iIix0aGlzLl9lbGVtZW50LnN0eWxlLnBhZGRpbmdSaWdodD0iIn0sdC5fY2hlY2tTY3JvbGxiYXI9ZnVuY3Rpb24oKXt2YXIgdD1kb2N1bWVudC5ib2R5LmdldEJvdW5kaW5nQ2xpZW50UmVjdCgpO3RoaXMuX2lzQm9keU92ZXJmbG93aW5nPXQubGVmdCt0LnJpZ2h0PHdpbmRvdy5pbm5lcldpZHRoLHRoaXMuX3Njcm9sbGJhcldpZHRoPXRoaXMuX2dldFNjcm9sbGJhcldpZHRoKCl9LHQuX3NldFNjcm9sbGJhcj1mdW5jdGlvbigpe3ZhciBvPXRoaXM7aWYodGhpcy5faXNCb2R5T3ZlcmZsb3dpbmcpe3ZhciB0PVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChFZSkpLGU9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKENlKSk7Zyh0KS5lYWNoKGZ1bmN0aW9uKHQsZSl7dmFyIG49ZS5zdHlsZS5wYWRkaW5nUmlnaHQsaT1nKGUpLmNzcygicGFkZGluZy1yaWdodCIpO2coZSkuZGF0YSgicGFkZGluZy1yaWdodCIsbikuY3NzKCJwYWRkaW5nLXJpZ2h0IixwYXJzZUZsb2F0KGkpK28uX3Njcm9sbGJhcldpZHRoKyJweCIpfSksZyhlKS5lYWNoKGZ1bmN0aW9uKHQsZSl7dmFyIG49ZS5zdHlsZS5tYXJnaW5SaWdodCxpPWcoZSkuY3NzKCJtYXJnaW4tcmlnaHQiKTtnKGUpLmRhdGEoIm1hcmdpbi1yaWdodCIsbikuY3NzKCJtYXJnaW4tcmlnaHQiLHBhcnNlRmxvYXQoaSktby5fc2Nyb2xsYmFyV2lkdGgrInB4Iil9KTt2YXIgbj1kb2N1bWVudC5ib2R5LnN0eWxlLnBhZGRpbmdSaWdodCxpPWcoZG9jdW1lbnQuYm9keSkuY3NzKCJwYWRkaW5nLXJpZ2h0Iik7Zyhkb2N1bWVudC5ib2R5KS5kYXRhKCJwYWRkaW5nLXJpZ2h0IixuKS5jc3MoInBhZGRpbmctcmlnaHQiLHBhcnNlRmxvYXQoaSkrdGhpcy5fc2Nyb2xsYmFyV2lkdGgrInB4Iil9Zyhkb2N1bWVudC5ib2R5KS5hZGRDbGFzcyhmZSl9LHQuX3Jlc2V0U2Nyb2xsYmFyPWZ1bmN0aW9uKCl7dmFyIHQ9W10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKEVlKSk7Zyh0KS5lYWNoKGZ1bmN0aW9uKHQsZSl7dmFyIG49ZyhlKS5kYXRhKCJwYWRkaW5nLXJpZ2h0Iik7ZyhlKS5yZW1vdmVEYXRhKCJwYWRkaW5nLXJpZ2h0IiksZS5zdHlsZS5wYWRkaW5nUmlnaHQ9bnx8IiJ9KTt2YXIgZT1bXS5zbGljZS5jYWxsKGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoIiIrQ2UpKTtnKGUpLmVhY2goZnVuY3Rpb24odCxlKXt2YXIgbj1nKGUpLmRhdGEoIm1hcmdpbi1yaWdodCIpOyJ1bmRlZmluZWQiIT10eXBlb2YgbiYmZyhlKS5jc3MoIm1hcmdpbi1yaWdodCIsbikucmVtb3ZlRGF0YSgibWFyZ2luLXJpZ2h0Iil9KTt2YXIgbj1nKGRvY3VtZW50LmJvZHkpLmRhdGEoInBhZGRpbmctcmlnaHQiKTtnKGRvY3VtZW50LmJvZHkpLnJlbW92ZURhdGEoInBhZGRpbmctcmlnaHQiKSxkb2N1bWVudC5ib2R5LnN0eWxlLnBhZGRpbmdSaWdodD1ufHwiIn0sdC5fZ2V0U2Nyb2xsYmFyV2lkdGg9ZnVuY3Rpb24oKXt2YXIgdD1kb2N1bWVudC5jcmVhdGVFbGVtZW50KCJkaXYiKTt0LmNsYXNzTmFtZT1oZSxkb2N1bWVudC5ib2R5LmFwcGVuZENoaWxkKHQpO3ZhciBlPXQuZ2V0Qm91bmRpbmdDbGllbnRSZWN0KCkud2lkdGgtdC5jbGllbnRXaWR0aDtyZXR1cm4gZG9jdW1lbnQuYm9keS5yZW1vdmVDaGlsZCh0KSxlfSxvLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24obixpKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIHQ9Zyh0aGlzKS5kYXRhKGllKSxlPWwoe30sc2Use30sZyh0aGlzKS5kYXRhKCkse30sIm9iamVjdCI9PXR5cGVvZiBuJiZuP246e30pO2lmKHR8fCh0PW5ldyBvKHRoaXMsZSksZyh0aGlzKS5kYXRhKGllLHQpKSwic3RyaW5nIj09dHlwZW9mIG4pe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdFtuXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrbisnIicpO3Rbbl0oaSl9ZWxzZSBlLnNob3cmJnQuc2hvdyhpKX0pfSxzKG8sbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHQiLGdldDpmdW5jdGlvbigpe3JldHVybiBzZX19XSksb30oKTtnKGRvY3VtZW50KS5vbihsZS5DTElDS19EQVRBX0FQSSx2ZSxmdW5jdGlvbih0KXt2YXIgZSxuPXRoaXMsaT1fLmdldFNlbGVjdG9yRnJvbUVsZW1lbnQodGhpcyk7aSYmKGU9ZG9jdW1lbnQucXVlcnlTZWxlY3RvcihpKSk7dmFyIG89ZyhlKS5kYXRhKGllKT8idG9nZ2xlIjpsKHt9LGcoZSkuZGF0YSgpLHt9LGcodGhpcykuZGF0YSgpKTsiQSIhPT10aGlzLnRhZ05hbWUmJiJBUkVBIiE9PXRoaXMudGFnTmFtZXx8dC5wcmV2ZW50RGVmYXVsdCgpO3ZhciByPWcoZSkub25lKGxlLlNIT1csZnVuY3Rpb24odCl7dC5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8ci5vbmUobGUuSElEREVOLGZ1bmN0aW9uKCl7ZyhuKS5pcygiOnZpc2libGUiKSYmbi5mb2N1cygpfSl9KTtUZS5falF1ZXJ5SW50ZXJmYWNlLmNhbGwoZyhlKSxvLHRoaXMpfSksZy5mbltuZV09VGUuX2pRdWVyeUludGVyZmFjZSxnLmZuW25lXS5Db25zdHJ1Y3Rvcj1UZSxnLmZuW25lXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIGcuZm5bbmVdPXJlLFRlLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciBiZT1bImJhY2tncm91bmQiLCJjaXRlIiwiaHJlZiIsIml0ZW10eXBlIiwibG9uZ2Rlc2MiLCJwb3N0ZXIiLCJzcmMiLCJ4bGluazpocmVmIl0sU2U9eyIqIjpbImNsYXNzIiwiZGlyIiwiaWQiLCJsYW5nIiwicm9sZSIsL15hcmlhLVtcdy1dKiQvaV0sYTpbInRhcmdldCIsImhyZWYiLCJ0aXRsZSIsInJlbCJdLGFyZWE6W10sYjpbXSxicjpbXSxjb2w6W10sY29kZTpbXSxkaXY6W10sZW06W10saHI6W10saDE6W10saDI6W10saDM6W10saDQ6W10saDU6W10saDY6W10saTpbXSxpbWc6WyJzcmMiLCJhbHQiLCJ0aXRsZSIsIndpZHRoIiwiaGVpZ2h0Il0sbGk6W10sb2w6W10scDpbXSxwcmU6W10sczpbXSxzbWFsbDpbXSxzcGFuOltdLHN1YjpbXSxzdXA6W10sc3Ryb25nOltdLHU6W10sdWw6W119LERlPS9eKD86KD86aHR0cHM/fG1haWx0b3xmdHB8dGVsfGZpbGUpOnxbXiY6Lz8jXSooPzpbLz8jXXwkKSkvZ2ksSWU9L15kYXRhOig/OmltYWdlXC8oPzpibXB8Z2lmfGpwZWd8anBnfHBuZ3x0aWZmfHdlYnApfHZpZGVvXC8oPzptcGVnfG1wNHxvZ2d8d2VibSl8YXVkaW9cLyg/Om1wM3xvZ2F8b2dnfG9wdXMpKTtiYXNlNjQsW2EtejAtOSsvXSs9KiQvaTtmdW5jdGlvbiB3ZSh0LHIsZSl7aWYoMD09PXQubGVuZ3RoKXJldHVybiB0O2lmKGUmJiJmdW5jdGlvbiI9PXR5cGVvZiBlKXJldHVybiBlKHQpO2Zvcih2YXIgbj0obmV3IHdpbmRvdy5ET01QYXJzZXIpLnBhcnNlRnJvbVN0cmluZyh0LCJ0ZXh0L2h0bWwiKSxzPU9iamVjdC5rZXlzKHIpLGE9W10uc2xpY2UuY2FsbChuLmJvZHkucXVlcnlTZWxlY3RvckFsbCgiKiIpKSxpPWZ1bmN0aW9uKHQpe3ZhciBlPWFbdF0sbj1lLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk7aWYoLTE9PT1zLmluZGV4T2YoZS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpKSlyZXR1cm4gZS5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKGUpLCJjb250aW51ZSI7dmFyIGk9W10uc2xpY2UuY2FsbChlLmF0dHJpYnV0ZXMpLG89W10uY29uY2F0KHJbIioiXXx8W10scltuXXx8W10pO2kuZm9yRWFjaChmdW5jdGlvbih0KXshZnVuY3Rpb24odCxlKXt2YXIgbj10Lm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk7aWYoLTEhPT1lLmluZGV4T2YobikpcmV0dXJuLTE9PT1iZS5pbmRleE9mKG4pfHxCb29sZWFuKHQubm9kZVZhbHVlLm1hdGNoKERlKXx8dC5ub2RlVmFsdWUubWF0Y2goSWUpKTtmb3IodmFyIGk9ZS5maWx0ZXIoZnVuY3Rpb24odCl7cmV0dXJuIHQgaW5zdGFuY2VvZiBSZWdFeHB9KSxvPTAscj1pLmxlbmd0aDtvPHI7bysrKWlmKG4ubWF0Y2goaVtvXSkpcmV0dXJuITA7cmV0dXJuITF9KHQsbykmJmUucmVtb3ZlQXR0cmlidXRlKHQubm9kZU5hbWUpfSl9LG89MCxsPWEubGVuZ3RoO288bDtvKyspaShvKTtyZXR1cm4gbi5ib2R5LmlubmVySFRNTH12YXIgQWU9InRvb2x0aXAiLE5lPSJicy50b29sdGlwIixPZT0iLiIrTmUsa2U9Zy5mbltBZV0sUGU9ImJzLXRvb2x0aXAiLExlPW5ldyBSZWdFeHAoIihefFxccykiK1BlKyJcXFMrIiwiZyIpLGplPVsic2FuaXRpemUiLCJ3aGl0ZUxpc3QiLCJzYW5pdGl6ZUZuIl0sSGU9e2FuaW1hdGlvbjoiYm9vbGVhbiIsdGVtcGxhdGU6InN0cmluZyIsdGl0bGU6IihzdHJpbmd8ZWxlbWVudHxmdW5jdGlvbikiLHRyaWdnZXI6InN0cmluZyIsZGVsYXk6IihudW1iZXJ8b2JqZWN0KSIsaHRtbDoiYm9vbGVhbiIsc2VsZWN0b3I6IihzdHJpbmd8Ym9vbGVhbikiLHBsYWNlbWVudDoiKHN0cmluZ3xmdW5jdGlvbikiLG9mZnNldDoiKG51bWJlcnxzdHJpbmd8ZnVuY3Rpb24pIixjb250YWluZXI6IihzdHJpbmd8ZWxlbWVudHxib29sZWFuKSIsZmFsbGJhY2tQbGFjZW1lbnQ6IihzdHJpbmd8YXJyYXkpIixib3VuZGFyeToiKHN0cmluZ3xlbGVtZW50KSIsc2FuaXRpemU6ImJvb2xlYW4iLHNhbml0aXplRm46IihudWxsfGZ1bmN0aW9uKSIsd2hpdGVMaXN0OiJvYmplY3QiLHBvcHBlckNvbmZpZzoiKG51bGx8b2JqZWN0KSJ9LFJlPXtBVVRPOiJhdXRvIixUT1A6InRvcCIsUklHSFQ6InJpZ2h0IixCT1RUT006ImJvdHRvbSIsTEVGVDoibGVmdCJ9LHhlPXthbmltYXRpb246ITAsdGVtcGxhdGU6JzxkaXYgY2xhc3M9InRvb2x0aXAiIHJvbGU9InRvb2x0aXAiPjxkaXYgY2xhc3M9ImFycm93Ij48L2Rpdj48ZGl2IGNsYXNzPSJ0b29sdGlwLWlubmVyIj48L2Rpdj48L2Rpdj4nLHRyaWdnZXI6ImhvdmVyIGZvY3VzIix0aXRsZToiIixkZWxheTowLGh0bWw6ITEsc2VsZWN0b3I6ITEscGxhY2VtZW50OiJ0b3AiLG9mZnNldDowLGNvbnRhaW5lcjohMSxmYWxsYmFja1BsYWNlbWVudDoiZmxpcCIsYm91bmRhcnk6InNjcm9sbFBhcmVudCIsc2FuaXRpemU6ITAsc2FuaXRpemVGbjpudWxsLHdoaXRlTGlzdDpTZSxwb3BwZXJDb25maWc6bnVsbH0sRmU9InNob3ciLFVlPSJvdXQiLFdlPXtISURFOiJoaWRlIitPZSxISURERU46ImhpZGRlbiIrT2UsU0hPVzoic2hvdyIrT2UsU0hPV046InNob3duIitPZSxJTlNFUlRFRDoiaW5zZXJ0ZWQiK09lLENMSUNLOiJjbGljayIrT2UsRk9DVVNJTjoiZm9jdXNpbiIrT2UsRk9DVVNPVVQ6ImZvY3Vzb3V0IitPZSxNT1VTRUVOVEVSOiJtb3VzZWVudGVyIitPZSxNT1VTRUxFQVZFOiJtb3VzZWxlYXZlIitPZX0scWU9ImZhZGUiLE1lPSJzaG93IixLZT0iLnRvb2x0aXAtaW5uZXIiLFFlPSIuYXJyb3ciLEJlPSJob3ZlciIsVmU9ImZvY3VzIixZZT0iY2xpY2siLHplPSJtYW51YWwiLFhlPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gaSh0LGUpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdSl0aHJvdyBuZXcgVHlwZUVycm9yKCJCb290c3RyYXAncyB0b29sdGlwcyByZXF1aXJlIFBvcHBlci5qcyAoaHR0cHM6Ly9wb3BwZXIuanMub3JnLykiKTt0aGlzLl9pc0VuYWJsZWQ9ITAsdGhpcy5fdGltZW91dD0wLHRoaXMuX2hvdmVyU3RhdGU9IiIsdGhpcy5fYWN0aXZlVHJpZ2dlcj17fSx0aGlzLl9wb3BwZXI9bnVsbCx0aGlzLmVsZW1lbnQ9dCx0aGlzLmNvbmZpZz10aGlzLl9nZXRDb25maWcoZSksdGhpcy50aXA9bnVsbCx0aGlzLl9zZXRMaXN0ZW5lcnMoKX12YXIgdD1pLnByb3RvdHlwZTtyZXR1cm4gdC5lbmFibGU9ZnVuY3Rpb24oKXt0aGlzLl9pc0VuYWJsZWQ9ITB9LHQuZGlzYWJsZT1mdW5jdGlvbigpe3RoaXMuX2lzRW5hYmxlZD0hMX0sdC50b2dnbGVFbmFibGVkPWZ1bmN0aW9uKCl7dGhpcy5faXNFbmFibGVkPSF0aGlzLl9pc0VuYWJsZWR9LHQudG9nZ2xlPWZ1bmN0aW9uKHQpe2lmKHRoaXMuX2lzRW5hYmxlZClpZih0KXt2YXIgZT10aGlzLmNvbnN0cnVjdG9yLkRBVEFfS0VZLG49Zyh0LmN1cnJlbnRUYXJnZXQpLmRhdGEoZSk7bnx8KG49bmV3IHRoaXMuY29uc3RydWN0b3IodC5jdXJyZW50VGFyZ2V0LHRoaXMuX2dldERlbGVnYXRlQ29uZmlnKCkpLGcodC5jdXJyZW50VGFyZ2V0KS5kYXRhKGUsbikpLG4uX2FjdGl2ZVRyaWdnZXIuY2xpY2s9IW4uX2FjdGl2ZVRyaWdnZXIuY2xpY2ssbi5faXNXaXRoQWN0aXZlVHJpZ2dlcigpP24uX2VudGVyKG51bGwsbik6bi5fbGVhdmUobnVsbCxuKX1lbHNle2lmKGcodGhpcy5nZXRUaXBFbGVtZW50KCkpLmhhc0NsYXNzKE1lKSlyZXR1cm4gdm9pZCB0aGlzLl9sZWF2ZShudWxsLHRoaXMpO3RoaXMuX2VudGVyKG51bGwsdGhpcyl9fSx0LmRpc3Bvc2U9ZnVuY3Rpb24oKXtjbGVhclRpbWVvdXQodGhpcy5fdGltZW91dCksZy5yZW1vdmVEYXRhKHRoaXMuZWxlbWVudCx0aGlzLmNvbnN0cnVjdG9yLkRBVEFfS0VZKSxnKHRoaXMuZWxlbWVudCkub2ZmKHRoaXMuY29uc3RydWN0b3IuRVZFTlRfS0VZKSxnKHRoaXMuZWxlbWVudCkuY2xvc2VzdCgiLm1vZGFsIikub2ZmKCJoaWRlLmJzLm1vZGFsIix0aGlzLl9oaWRlTW9kYWxIYW5kbGVyKSx0aGlzLnRpcCYmZyh0aGlzLnRpcCkucmVtb3ZlKCksdGhpcy5faXNFbmFibGVkPW51bGwsdGhpcy5fdGltZW91dD1udWxsLHRoaXMuX2hvdmVyU3RhdGU9bnVsbCx0aGlzLl9hY3RpdmVUcmlnZ2VyPW51bGwsdGhpcy5fcG9wcGVyJiZ0aGlzLl9wb3BwZXIuZGVzdHJveSgpLHRoaXMuX3BvcHBlcj1udWxsLHRoaXMuZWxlbWVudD1udWxsLHRoaXMuY29uZmlnPW51bGwsdGhpcy50aXA9bnVsbH0sdC5zaG93PWZ1bmN0aW9uKCl7dmFyIGU9dGhpcztpZigibm9uZSI9PT1nKHRoaXMuZWxlbWVudCkuY3NzKCJkaXNwbGF5IikpdGhyb3cgbmV3IEVycm9yKCJQbGVhc2UgdXNlIHNob3cgb24gdmlzaWJsZSBlbGVtZW50cyIpO3ZhciB0PWcuRXZlbnQodGhpcy5jb25zdHJ1Y3Rvci5FdmVudC5TSE9XKTtpZih0aGlzLmlzV2l0aENvbnRlbnQoKSYmdGhpcy5faXNFbmFibGVkKXtnKHRoaXMuZWxlbWVudCkudHJpZ2dlcih0KTt2YXIgbj1fLmZpbmRTaGFkb3dSb290KHRoaXMuZWxlbWVudCksaT1nLmNvbnRhaW5zKG51bGwhPT1uP246dGhpcy5lbGVtZW50Lm93bmVyRG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LHRoaXMuZWxlbWVudCk7aWYodC5pc0RlZmF1bHRQcmV2ZW50ZWQoKXx8IWkpcmV0dXJuO3ZhciBvPXRoaXMuZ2V0VGlwRWxlbWVudCgpLHI9Xy5nZXRVSUQodGhpcy5jb25zdHJ1Y3Rvci5OQU1FKTtvLnNldEF0dHJpYnV0ZSgiaWQiLHIpLHRoaXMuZWxlbWVudC5zZXRBdHRyaWJ1dGUoImFyaWEtZGVzY3JpYmVkYnkiLHIpLHRoaXMuc2V0Q29udGVudCgpLHRoaXMuY29uZmlnLmFuaW1hdGlvbiYmZyhvKS5hZGRDbGFzcyhxZSk7dmFyIHM9ImZ1bmN0aW9uIj09dHlwZW9mIHRoaXMuY29uZmlnLnBsYWNlbWVudD90aGlzLmNvbmZpZy5wbGFjZW1lbnQuY2FsbCh0aGlzLG8sdGhpcy5lbGVtZW50KTp0aGlzLmNvbmZpZy5wbGFjZW1lbnQsYT10aGlzLl9nZXRBdHRhY2htZW50KHMpO3RoaXMuYWRkQXR0YWNobWVudENsYXNzKGEpO3ZhciBsPXRoaXMuX2dldENvbnRhaW5lcigpO2cobykuZGF0YSh0aGlzLmNvbnN0cnVjdG9yLkRBVEFfS0VZLHRoaXMpLGcuY29udGFpbnModGhpcy5lbGVtZW50Lm93bmVyRG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LHRoaXMudGlwKXx8ZyhvKS5hcHBlbmRUbyhsKSxnKHRoaXMuZWxlbWVudCkudHJpZ2dlcih0aGlzLmNvbnN0cnVjdG9yLkV2ZW50LklOU0VSVEVEKSx0aGlzLl9wb3BwZXI9bmV3IHUodGhpcy5lbGVtZW50LG8sdGhpcy5fZ2V0UG9wcGVyQ29uZmlnKGEpKSxnKG8pLmFkZENsYXNzKE1lKSwib250b3VjaHN0YXJ0ImluIGRvY3VtZW50LmRvY3VtZW50RWxlbWVudCYmZyhkb2N1bWVudC5ib2R5KS5jaGlsZHJlbigpLm9uKCJtb3VzZW92ZXIiLG51bGwsZy5ub29wKTt2YXIgYz1mdW5jdGlvbigpe2UuY29uZmlnLmFuaW1hdGlvbiYmZS5fZml4VHJhbnNpdGlvbigpO3ZhciB0PWUuX2hvdmVyU3RhdGU7ZS5faG92ZXJTdGF0ZT1udWxsLGcoZS5lbGVtZW50KS50cmlnZ2VyKGUuY29uc3RydWN0b3IuRXZlbnQuU0hPV04pLHQ9PT1VZSYmZS5fbGVhdmUobnVsbCxlKX07aWYoZyh0aGlzLnRpcCkuaGFzQ2xhc3MocWUpKXt2YXIgaD1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMudGlwKTtnKHRoaXMudGlwKS5vbmUoXy5UUkFOU0lUSU9OX0VORCxjKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChoKX1lbHNlIGMoKX19LHQuaGlkZT1mdW5jdGlvbih0KXtmdW5jdGlvbiBlKCl7bi5faG92ZXJTdGF0ZSE9PUZlJiZpLnBhcmVudE5vZGUmJmkucGFyZW50Tm9kZS5yZW1vdmVDaGlsZChpKSxuLl9jbGVhblRpcENsYXNzKCksbi5lbGVtZW50LnJlbW92ZUF0dHJpYnV0ZSgiYXJpYS1kZXNjcmliZWRieSIpLGcobi5lbGVtZW50KS50cmlnZ2VyKG4uY29uc3RydWN0b3IuRXZlbnQuSElEREVOKSxudWxsIT09bi5fcG9wcGVyJiZuLl9wb3BwZXIuZGVzdHJveSgpLHQmJnQoKX12YXIgbj10aGlzLGk9dGhpcy5nZXRUaXBFbGVtZW50KCksbz1nLkV2ZW50KHRoaXMuY29uc3RydWN0b3IuRXZlbnQuSElERSk7aWYoZyh0aGlzLmVsZW1lbnQpLnRyaWdnZXIobyksIW8uaXNEZWZhdWx0UHJldmVudGVkKCkpe2lmKGcoaSkucmVtb3ZlQ2xhc3MoTWUpLCJvbnRvdWNoc3RhcnQiaW4gZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50JiZnKGRvY3VtZW50LmJvZHkpLmNoaWxkcmVuKCkub2ZmKCJtb3VzZW92ZXIiLG51bGwsZy5ub29wKSx0aGlzLl9hY3RpdmVUcmlnZ2VyW1llXT0hMSx0aGlzLl9hY3RpdmVUcmlnZ2VyW1ZlXT0hMSx0aGlzLl9hY3RpdmVUcmlnZ2VyW0JlXT0hMSxnKHRoaXMudGlwKS5oYXNDbGFzcyhxZSkpe3ZhciByPV8uZ2V0VHJhbnNpdGlvbkR1cmF0aW9uRnJvbUVsZW1lbnQoaSk7ZyhpKS5vbmUoXy5UUkFOU0lUSU9OX0VORCxlKS5lbXVsYXRlVHJhbnNpdGlvbkVuZChyKX1lbHNlIGUoKTt0aGlzLl9ob3ZlclN0YXRlPSIifX0sdC51cGRhdGU9ZnVuY3Rpb24oKXtudWxsIT09dGhpcy5fcG9wcGVyJiZ0aGlzLl9wb3BwZXIuc2NoZWR1bGVVcGRhdGUoKX0sdC5pc1dpdGhDb250ZW50PWZ1bmN0aW9uKCl7cmV0dXJuIEJvb2xlYW4odGhpcy5nZXRUaXRsZSgpKX0sdC5hZGRBdHRhY2htZW50Q2xhc3M9ZnVuY3Rpb24odCl7Zyh0aGlzLmdldFRpcEVsZW1lbnQoKSkuYWRkQ2xhc3MoUGUrIi0iK3QpfSx0LmdldFRpcEVsZW1lbnQ9ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy50aXA9dGhpcy50aXB8fGcodGhpcy5jb25maWcudGVtcGxhdGUpWzBdLHRoaXMudGlwfSx0LnNldENvbnRlbnQ9ZnVuY3Rpb24oKXt2YXIgdD10aGlzLmdldFRpcEVsZW1lbnQoKTt0aGlzLnNldEVsZW1lbnRDb250ZW50KGcodC5xdWVyeVNlbGVjdG9yQWxsKEtlKSksdGhpcy5nZXRUaXRsZSgpKSxnKHQpLnJlbW92ZUNsYXNzKHFlKyIgIitNZSl9LHQuc2V0RWxlbWVudENvbnRlbnQ9ZnVuY3Rpb24odCxlKXsib2JqZWN0IiE9dHlwZW9mIGV8fCFlLm5vZGVUeXBlJiYhZS5qcXVlcnk/dGhpcy5jb25maWcuaHRtbD8odGhpcy5jb25maWcuc2FuaXRpemUmJihlPXdlKGUsdGhpcy5jb25maWcud2hpdGVMaXN0LHRoaXMuY29uZmlnLnNhbml0aXplRm4pKSx0Lmh0bWwoZSkpOnQudGV4dChlKTp0aGlzLmNvbmZpZy5odG1sP2coZSkucGFyZW50KCkuaXModCl8fHQuZW1wdHkoKS5hcHBlbmQoZSk6dC50ZXh0KGcoZSkudGV4dCgpKX0sdC5nZXRUaXRsZT1mdW5jdGlvbigpe3ZhciB0PXRoaXMuZWxlbWVudC5nZXRBdHRyaWJ1dGUoImRhdGEtb3JpZ2luYWwtdGl0bGUiKTtyZXR1cm4gdD10fHwoImZ1bmN0aW9uIj09dHlwZW9mIHRoaXMuY29uZmlnLnRpdGxlP3RoaXMuY29uZmlnLnRpdGxlLmNhbGwodGhpcy5lbGVtZW50KTp0aGlzLmNvbmZpZy50aXRsZSl9LHQuX2dldFBvcHBlckNvbmZpZz1mdW5jdGlvbih0KXt2YXIgZT10aGlzO3JldHVybiBsKHt9LHtwbGFjZW1lbnQ6dCxtb2RpZmllcnM6e29mZnNldDp0aGlzLl9nZXRPZmZzZXQoKSxmbGlwOntiZWhhdmlvcjp0aGlzLmNvbmZpZy5mYWxsYmFja1BsYWNlbWVudH0sYXJyb3c6e2VsZW1lbnQ6UWV9LHByZXZlbnRPdmVyZmxvdzp7Ym91bmRhcmllc0VsZW1lbnQ6dGhpcy5jb25maWcuYm91bmRhcnl9fSxvbkNyZWF0ZTpmdW5jdGlvbih0KXt0Lm9yaWdpbmFsUGxhY2VtZW50IT09dC5wbGFjZW1lbnQmJmUuX2hhbmRsZVBvcHBlclBsYWNlbWVudENoYW5nZSh0KX0sb25VcGRhdGU6ZnVuY3Rpb24odCl7cmV0dXJuIGUuX2hhbmRsZVBvcHBlclBsYWNlbWVudENoYW5nZSh0KX19LHt9LHRoaXMuY29uZmlnLnBvcHBlckNvbmZpZyl9LHQuX2dldE9mZnNldD1mdW5jdGlvbigpe3ZhciBlPXRoaXMsdD17fTtyZXR1cm4iZnVuY3Rpb24iPT10eXBlb2YgdGhpcy5jb25maWcub2Zmc2V0P3QuZm49ZnVuY3Rpb24odCl7cmV0dXJuIHQub2Zmc2V0cz1sKHt9LHQub2Zmc2V0cyx7fSxlLmNvbmZpZy5vZmZzZXQodC5vZmZzZXRzLGUuZWxlbWVudCl8fHt9KSx0fTp0Lm9mZnNldD10aGlzLmNvbmZpZy5vZmZzZXQsdH0sdC5fZ2V0Q29udGFpbmVyPWZ1bmN0aW9uKCl7cmV0dXJuITE9PT10aGlzLmNvbmZpZy5jb250YWluZXI/ZG9jdW1lbnQuYm9keTpfLmlzRWxlbWVudCh0aGlzLmNvbmZpZy5jb250YWluZXIpP2codGhpcy5jb25maWcuY29udGFpbmVyKTpnKGRvY3VtZW50KS5maW5kKHRoaXMuY29uZmlnLmNvbnRhaW5lcil9LHQuX2dldEF0dGFjaG1lbnQ9ZnVuY3Rpb24odCl7cmV0dXJuIFJlW3QudG9VcHBlckNhc2UoKV19LHQuX3NldExpc3RlbmVycz1mdW5jdGlvbigpe3ZhciBpPXRoaXM7dGhpcy5jb25maWcudHJpZ2dlci5zcGxpdCgiICIpLmZvckVhY2goZnVuY3Rpb24odCl7aWYoImNsaWNrIj09PXQpZyhpLmVsZW1lbnQpLm9uKGkuY29uc3RydWN0b3IuRXZlbnQuQ0xJQ0ssaS5jb25maWcuc2VsZWN0b3IsZnVuY3Rpb24odCl7cmV0dXJuIGkudG9nZ2xlKHQpfSk7ZWxzZSBpZih0IT09emUpe3ZhciBlPXQ9PT1CZT9pLmNvbnN0cnVjdG9yLkV2ZW50Lk1PVVNFRU5URVI6aS5jb25zdHJ1Y3Rvci5FdmVudC5GT0NVU0lOLG49dD09PUJlP2kuY29uc3RydWN0b3IuRXZlbnQuTU9VU0VMRUFWRTppLmNvbnN0cnVjdG9yLkV2ZW50LkZPQ1VTT1VUO2coaS5lbGVtZW50KS5vbihlLGkuY29uZmlnLnNlbGVjdG9yLGZ1bmN0aW9uKHQpe3JldHVybiBpLl9lbnRlcih0KX0pLm9uKG4saS5jb25maWcuc2VsZWN0b3IsZnVuY3Rpb24odCl7cmV0dXJuIGkuX2xlYXZlKHQpfSl9fSksdGhpcy5faGlkZU1vZGFsSGFuZGxlcj1mdW5jdGlvbigpe2kuZWxlbWVudCYmaS5oaWRlKCl9LGcodGhpcy5lbGVtZW50KS5jbG9zZXN0KCIubW9kYWwiKS5vbigiaGlkZS5icy5tb2RhbCIsdGhpcy5faGlkZU1vZGFsSGFuZGxlciksdGhpcy5jb25maWcuc2VsZWN0b3I/dGhpcy5jb25maWc9bCh7fSx0aGlzLmNvbmZpZyx7dHJpZ2dlcjoibWFudWFsIixzZWxlY3RvcjoiIn0pOnRoaXMuX2ZpeFRpdGxlKCl9LHQuX2ZpeFRpdGxlPWZ1bmN0aW9uKCl7dmFyIHQ9dHlwZW9mIHRoaXMuZWxlbWVudC5nZXRBdHRyaWJ1dGUoImRhdGEtb3JpZ2luYWwtdGl0bGUiKTshdGhpcy5lbGVtZW50LmdldEF0dHJpYnV0ZSgidGl0bGUiKSYmInN0cmluZyI9PXR8fCh0aGlzLmVsZW1lbnQuc2V0QXR0cmlidXRlKCJkYXRhLW9yaWdpbmFsLXRpdGxlIix0aGlzLmVsZW1lbnQuZ2V0QXR0cmlidXRlKCJ0aXRsZSIpfHwiIiksdGhpcy5lbGVtZW50LnNldEF0dHJpYnV0ZSgidGl0bGUiLCIiKSl9LHQuX2VudGVyPWZ1bmN0aW9uKHQsZSl7dmFyIG49dGhpcy5jb25zdHJ1Y3Rvci5EQVRBX0tFWTsoZT1lfHxnKHQuY3VycmVudFRhcmdldCkuZGF0YShuKSl8fChlPW5ldyB0aGlzLmNvbnN0cnVjdG9yKHQuY3VycmVudFRhcmdldCx0aGlzLl9nZXREZWxlZ2F0ZUNvbmZpZygpKSxnKHQuY3VycmVudFRhcmdldCkuZGF0YShuLGUpKSx0JiYoZS5fYWN0aXZlVHJpZ2dlclsiZm9jdXNpbiI9PT10LnR5cGU/VmU6QmVdPSEwKSxnKGUuZ2V0VGlwRWxlbWVudCgpKS5oYXNDbGFzcyhNZSl8fGUuX2hvdmVyU3RhdGU9PT1GZT9lLl9ob3ZlclN0YXRlPUZlOihjbGVhclRpbWVvdXQoZS5fdGltZW91dCksZS5faG92ZXJTdGF0ZT1GZSxlLmNvbmZpZy5kZWxheSYmZS5jb25maWcuZGVsYXkuc2hvdz9lLl90aW1lb3V0PXNldFRpbWVvdXQoZnVuY3Rpb24oKXtlLl9ob3ZlclN0YXRlPT09RmUmJmUuc2hvdygpfSxlLmNvbmZpZy5kZWxheS5zaG93KTplLnNob3coKSl9LHQuX2xlYXZlPWZ1bmN0aW9uKHQsZSl7dmFyIG49dGhpcy5jb25zdHJ1Y3Rvci5EQVRBX0tFWTsoZT1lfHxnKHQuY3VycmVudFRhcmdldCkuZGF0YShuKSl8fChlPW5ldyB0aGlzLmNvbnN0cnVjdG9yKHQuY3VycmVudFRhcmdldCx0aGlzLl9nZXREZWxlZ2F0ZUNvbmZpZygpKSxnKHQuY3VycmVudFRhcmdldCkuZGF0YShuLGUpKSx0JiYoZS5fYWN0aXZlVHJpZ2dlclsiZm9jdXNvdXQiPT09dC50eXBlP1ZlOkJlXT0hMSksZS5faXNXaXRoQWN0aXZlVHJpZ2dlcigpfHwoY2xlYXJUaW1lb3V0KGUuX3RpbWVvdXQpLGUuX2hvdmVyU3RhdGU9VWUsZS5jb25maWcuZGVsYXkmJmUuY29uZmlnLmRlbGF5LmhpZGU/ZS5fdGltZW91dD1zZXRUaW1lb3V0KGZ1bmN0aW9uKCl7ZS5faG92ZXJTdGF0ZT09PVVlJiZlLmhpZGUoKX0sZS5jb25maWcuZGVsYXkuaGlkZSk6ZS5oaWRlKCkpfSx0Ll9pc1dpdGhBY3RpdmVUcmlnZ2VyPWZ1bmN0aW9uKCl7Zm9yKHZhciB0IGluIHRoaXMuX2FjdGl2ZVRyaWdnZXIpaWYodGhpcy5fYWN0aXZlVHJpZ2dlclt0XSlyZXR1cm4hMDtyZXR1cm4hMX0sdC5fZ2V0Q29uZmlnPWZ1bmN0aW9uKHQpe3ZhciBlPWcodGhpcy5lbGVtZW50KS5kYXRhKCk7cmV0dXJuIE9iamVjdC5rZXlzKGUpLmZvckVhY2goZnVuY3Rpb24odCl7LTEhPT1qZS5pbmRleE9mKHQpJiZkZWxldGUgZVt0XX0pLCJudW1iZXIiPT10eXBlb2YodD1sKHt9LHRoaXMuY29uc3RydWN0b3IuRGVmYXVsdCx7fSxlLHt9LCJvYmplY3QiPT10eXBlb2YgdCYmdD90Ont9KSkuZGVsYXkmJih0LmRlbGF5PXtzaG93OnQuZGVsYXksaGlkZTp0LmRlbGF5fSksIm51bWJlciI9PXR5cGVvZiB0LnRpdGxlJiYodC50aXRsZT10LnRpdGxlLnRvU3RyaW5nKCkpLCJudW1iZXIiPT10eXBlb2YgdC5jb250ZW50JiYodC5jb250ZW50PXQuY29udGVudC50b1N0cmluZygpKSxfLnR5cGVDaGVja0NvbmZpZyhBZSx0LHRoaXMuY29uc3RydWN0b3IuRGVmYXVsdFR5cGUpLHQuc2FuaXRpemUmJih0LnRlbXBsYXRlPXdlKHQudGVtcGxhdGUsdC53aGl0ZUxpc3QsdC5zYW5pdGl6ZUZuKSksdH0sdC5fZ2V0RGVsZWdhdGVDb25maWc9ZnVuY3Rpb24oKXt2YXIgdD17fTtpZih0aGlzLmNvbmZpZylmb3IodmFyIGUgaW4gdGhpcy5jb25maWcpdGhpcy5jb25zdHJ1Y3Rvci5EZWZhdWx0W2VdIT09dGhpcy5jb25maWdbZV0mJih0W2VdPXRoaXMuY29uZmlnW2VdKTtyZXR1cm4gdH0sdC5fY2xlYW5UaXBDbGFzcz1mdW5jdGlvbigpe3ZhciB0PWcodGhpcy5nZXRUaXBFbGVtZW50KCkpLGU9dC5hdHRyKCJjbGFzcyIpLm1hdGNoKExlKTtudWxsIT09ZSYmZS5sZW5ndGgmJnQucmVtb3ZlQ2xhc3MoZS5qb2luKCIiKSl9LHQuX2hhbmRsZVBvcHBlclBsYWNlbWVudENoYW5nZT1mdW5jdGlvbih0KXt2YXIgZT10Lmluc3RhbmNlO3RoaXMudGlwPWUucG9wcGVyLHRoaXMuX2NsZWFuVGlwQ2xhc3MoKSx0aGlzLmFkZEF0dGFjaG1lbnRDbGFzcyh0aGlzLl9nZXRBdHRhY2htZW50KHQucGxhY2VtZW50KSl9LHQuX2ZpeFRyYW5zaXRpb249ZnVuY3Rpb24oKXt2YXIgdD10aGlzLmdldFRpcEVsZW1lbnQoKSxlPXRoaXMuY29uZmlnLmFuaW1hdGlvbjtudWxsPT09dC5nZXRBdHRyaWJ1dGUoIngtcGxhY2VtZW50IikmJihnKHQpLnJlbW92ZUNsYXNzKHFlKSx0aGlzLmNvbmZpZy5hbmltYXRpb249ITEsdGhpcy5oaWRlKCksdGhpcy5zaG93KCksdGhpcy5jb25maWcuYW5pbWF0aW9uPWUpfSxpLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24obil7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PWcodGhpcykuZGF0YShOZSksZT0ib2JqZWN0Ij09dHlwZW9mIG4mJm47aWYoKHR8fCEvZGlzcG9zZXxoaWRlLy50ZXN0KG4pKSYmKHR8fCh0PW5ldyBpKHRoaXMsZSksZyh0aGlzKS5kYXRhKE5lLHQpKSwic3RyaW5nIj09dHlwZW9mIG4pKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIHRbbl0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK24rJyInKTt0W25dKCl9fSl9LHMoaSxudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX0se2tleToiRGVmYXVsdCIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIHhlfX0se2tleToiTkFNRSIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIEFlfX0se2tleToiREFUQV9LRVkiLGdldDpmdW5jdGlvbigpe3JldHVybiBOZX19LHtrZXk6IkV2ZW50IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gV2V9fSx7a2V5OiJFVkVOVF9LRVkiLGdldDpmdW5jdGlvbigpe3JldHVybiBPZX19LHtrZXk6IkRlZmF1bHRUeXBlIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gSGV9fV0pLGl9KCk7Zy5mbltBZV09WGUuX2pRdWVyeUludGVyZmFjZSxnLmZuW0FlXS5Db25zdHJ1Y3Rvcj1YZSxnLmZuW0FlXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIGcuZm5bQWVdPWtlLFhlLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciAkZT0icG9wb3ZlciIsR2U9ImJzLnBvcG92ZXIiLEplPSIuIitHZSxaZT1nLmZuWyRlXSx0bj0iYnMtcG9wb3ZlciIsZW49bmV3IFJlZ0V4cCgiKF58XFxzKSIrdG4rIlxcUysiLCJnIiksbm49bCh7fSxYZS5EZWZhdWx0LHtwbGFjZW1lbnQ6InJpZ2h0Iix0cmlnZ2VyOiJjbGljayIsY29udGVudDoiIix0ZW1wbGF0ZTonPGRpdiBjbGFzcz0icG9wb3ZlciIgcm9sZT0idG9vbHRpcCI+PGRpdiBjbGFzcz0iYXJyb3ciPjwvZGl2PjxoMyBjbGFzcz0icG9wb3Zlci1oZWFkZXIiPjwvaDM+PGRpdiBjbGFzcz0icG9wb3Zlci1ib2R5Ij48L2Rpdj48L2Rpdj4nfSksb249bCh7fSxYZS5EZWZhdWx0VHlwZSx7Y29udGVudDoiKHN0cmluZ3xlbGVtZW50fGZ1bmN0aW9uKSJ9KSxybj0iZmFkZSIsc249InNob3ciLGFuPSIucG9wb3Zlci1oZWFkZXIiLGxuPSIucG9wb3Zlci1ib2R5Iixjbj17SElERToiaGlkZSIrSmUsSElEREVOOiJoaWRkZW4iK0plLFNIT1c6InNob3ciK0plLFNIT1dOOiJzaG93biIrSmUsSU5TRVJURUQ6Imluc2VydGVkIitKZSxDTElDSzoiY2xpY2siK0plLEZPQ1VTSU46ImZvY3VzaW4iK0plLEZPQ1VTT1VUOiJmb2N1c291dCIrSmUsTU9VU0VFTlRFUjoibW91c2VlbnRlciIrSmUsTU9VU0VMRUFWRToibW91c2VsZWF2ZSIrSmV9LGhuPWZ1bmN0aW9uKHQpe2Z1bmN0aW9uIGkoKXtyZXR1cm4gdC5hcHBseSh0aGlzLGFyZ3VtZW50cyl8fHRoaXN9IWZ1bmN0aW9uKHQsZSl7dC5wcm90b3R5cGU9T2JqZWN0LmNyZWF0ZShlLnByb3RvdHlwZSksKHQucHJvdG90eXBlLmNvbnN0cnVjdG9yPXQpLl9fcHJvdG9fXz1lfShpLHQpO3ZhciBlPWkucHJvdG90eXBlO3JldHVybiBlLmlzV2l0aENvbnRlbnQ9ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5nZXRUaXRsZSgpfHx0aGlzLl9nZXRDb250ZW50KCl9LGUuYWRkQXR0YWNobWVudENsYXNzPWZ1bmN0aW9uKHQpe2codGhpcy5nZXRUaXBFbGVtZW50KCkpLmFkZENsYXNzKHRuKyItIit0KX0sZS5nZXRUaXBFbGVtZW50PWZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMudGlwPXRoaXMudGlwfHxnKHRoaXMuY29uZmlnLnRlbXBsYXRlKVswXSx0aGlzLnRpcH0sZS5zZXRDb250ZW50PWZ1bmN0aW9uKCl7dmFyIHQ9Zyh0aGlzLmdldFRpcEVsZW1lbnQoKSk7dGhpcy5zZXRFbGVtZW50Q29udGVudCh0LmZpbmQoYW4pLHRoaXMuZ2V0VGl0bGUoKSk7dmFyIGU9dGhpcy5fZ2V0Q29udGVudCgpOyJmdW5jdGlvbiI9PXR5cGVvZiBlJiYoZT1lLmNhbGwodGhpcy5lbGVtZW50KSksdGhpcy5zZXRFbGVtZW50Q29udGVudCh0LmZpbmQobG4pLGUpLHQucmVtb3ZlQ2xhc3Mocm4rIiAiK3NuKX0sZS5fZ2V0Q29udGVudD1mdW5jdGlvbigpe3JldHVybiB0aGlzLmVsZW1lbnQuZ2V0QXR0cmlidXRlKCJkYXRhLWNvbnRlbnQiKXx8dGhpcy5jb25maWcuY29udGVudH0sZS5fY2xlYW5UaXBDbGFzcz1mdW5jdGlvbigpe3ZhciB0PWcodGhpcy5nZXRUaXBFbGVtZW50KCkpLGU9dC5hdHRyKCJjbGFzcyIpLm1hdGNoKGVuKTtudWxsIT09ZSYmMDxlLmxlbmd0aCYmdC5yZW1vdmVDbGFzcyhlLmpvaW4oIiIpKX0saS5falF1ZXJ5SW50ZXJmYWNlPWZ1bmN0aW9uKG4pe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgdD1nKHRoaXMpLmRhdGEoR2UpLGU9Im9iamVjdCI9PXR5cGVvZiBuP246bnVsbDtpZigodHx8IS9kaXNwb3NlfGhpZGUvLnRlc3QobikpJiYodHx8KHQ9bmV3IGkodGhpcyxlKSxnKHRoaXMpLmRhdGEoR2UsdCkpLCJzdHJpbmciPT10eXBlb2Ygbikpe2lmKCJ1bmRlZmluZWQiPT10eXBlb2YgdFtuXSl0aHJvdyBuZXcgVHlwZUVycm9yKCdObyBtZXRob2QgbmFtZWQgIicrbisnIicpO3Rbbl0oKX19KX0scyhpLG51bGwsW3trZXk6IlZFUlNJT04iLGdldDpmdW5jdGlvbigpe3JldHVybiI0LjQuMSJ9fSx7a2V5OiJEZWZhdWx0IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gbm59fSx7a2V5OiJOQU1FIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gJGV9fSx7a2V5OiJEQVRBX0tFWSIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIEdlfX0se2tleToiRXZlbnQiLGdldDpmdW5jdGlvbigpe3JldHVybiBjbn19LHtrZXk6IkVWRU5UX0tFWSIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIEplfX0se2tleToiRGVmYXVsdFR5cGUiLGdldDpmdW5jdGlvbigpe3JldHVybiBvbn19XSksaX0oWGUpO2cuZm5bJGVdPWhuLl9qUXVlcnlJbnRlcmZhY2UsZy5mblskZV0uQ29uc3RydWN0b3I9aG4sZy5mblskZV0ubm9Db25mbGljdD1mdW5jdGlvbigpe3JldHVybiBnLmZuWyRlXT1aZSxobi5falF1ZXJ5SW50ZXJmYWNlfTt2YXIgdW49InNjcm9sbHNweSIsZm49ImJzLnNjcm9sbHNweSIsZG49Ii4iK2ZuLGduPWcuZm5bdW5dLF9uPXtvZmZzZXQ6MTAsbWV0aG9kOiJhdXRvIix0YXJnZXQ6IiJ9LG1uPXtvZmZzZXQ6Im51bWJlciIsbWV0aG9kOiJzdHJpbmciLHRhcmdldDoiKHN0cmluZ3xlbGVtZW50KSJ9LHBuPXtBQ1RJVkFURToiYWN0aXZhdGUiK2RuLFNDUk9MTDoic2Nyb2xsIitkbixMT0FEX0RBVEFfQVBJOiJsb2FkIitkbisiLmRhdGEtYXBpIn0sdm49ImRyb3Bkb3duLWl0ZW0iLHluPSJhY3RpdmUiLEVuPSdbZGF0YS1zcHk9InNjcm9sbCJdJyxDbj0iLm5hdiwgLmxpc3QtZ3JvdXAiLFRuPSIubmF2LWxpbmsiLGJuPSIubmF2LWl0ZW0iLFNuPSIubGlzdC1ncm91cC1pdGVtIixEbj0iLmRyb3Bkb3duIixJbj0iLmRyb3Bkb3duLWl0ZW0iLHduPSIuZHJvcGRvd24tdG9nZ2xlIixBbj0ib2Zmc2V0IixObj0icG9zaXRpb24iLE9uPWZ1bmN0aW9uKCl7ZnVuY3Rpb24gbih0LGUpe3ZhciBuPXRoaXM7dGhpcy5fZWxlbWVudD10LHRoaXMuX3Njcm9sbEVsZW1lbnQ9IkJPRFkiPT09dC50YWdOYW1lP3dpbmRvdzp0LHRoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcoZSksdGhpcy5fc2VsZWN0b3I9dGhpcy5fY29uZmlnLnRhcmdldCsiICIrVG4rIiwiK3RoaXMuX2NvbmZpZy50YXJnZXQrIiAiK1NuKyIsIit0aGlzLl9jb25maWcudGFyZ2V0KyIgIitJbix0aGlzLl9vZmZzZXRzPVtdLHRoaXMuX3RhcmdldHM9W10sdGhpcy5fYWN0aXZlVGFyZ2V0PW51bGwsdGhpcy5fc2Nyb2xsSGVpZ2h0PTAsZyh0aGlzLl9zY3JvbGxFbGVtZW50KS5vbihwbi5TQ1JPTEwsZnVuY3Rpb24odCl7cmV0dXJuIG4uX3Byb2Nlc3ModCl9KSx0aGlzLnJlZnJlc2goKSx0aGlzLl9wcm9jZXNzKCl9dmFyIHQ9bi5wcm90b3R5cGU7cmV0dXJuIHQucmVmcmVzaD1mdW5jdGlvbigpe3ZhciBlPXRoaXMsdD10aGlzLl9zY3JvbGxFbGVtZW50PT09dGhpcy5fc2Nyb2xsRWxlbWVudC53aW5kb3c/QW46Tm4sbz0iYXV0byI9PT10aGlzLl9jb25maWcubWV0aG9kP3Q6dGhpcy5fY29uZmlnLm1ldGhvZCxyPW89PT1Obj90aGlzLl9nZXRTY3JvbGxUb3AoKTowO3RoaXMuX29mZnNldHM9W10sdGhpcy5fdGFyZ2V0cz1bXSx0aGlzLl9zY3JvbGxIZWlnaHQ9dGhpcy5fZ2V0U2Nyb2xsSGVpZ2h0KCksW10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKHRoaXMuX3NlbGVjdG9yKSkubWFwKGZ1bmN0aW9uKHQpe3ZhciBlLG49Xy5nZXRTZWxlY3RvckZyb21FbGVtZW50KHQpO2lmKG4mJihlPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IobikpLGUpe3ZhciBpPWUuZ2V0Qm91bmRpbmdDbGllbnRSZWN0KCk7aWYoaS53aWR0aHx8aS5oZWlnaHQpcmV0dXJuW2coZSlbb10oKS50b3ArcixuXX1yZXR1cm4gbnVsbH0pLmZpbHRlcihmdW5jdGlvbih0KXtyZXR1cm4gdH0pLnNvcnQoZnVuY3Rpb24odCxlKXtyZXR1cm4gdFswXS1lWzBdfSkuZm9yRWFjaChmdW5jdGlvbih0KXtlLl9vZmZzZXRzLnB1c2godFswXSksZS5fdGFyZ2V0cy5wdXNoKHRbMV0pfSl9LHQuZGlzcG9zZT1mdW5jdGlvbigpe2cucmVtb3ZlRGF0YSh0aGlzLl9lbGVtZW50LGZuKSxnKHRoaXMuX3Njcm9sbEVsZW1lbnQpLm9mZihkbiksdGhpcy5fZWxlbWVudD1udWxsLHRoaXMuX3Njcm9sbEVsZW1lbnQ9bnVsbCx0aGlzLl9jb25maWc9bnVsbCx0aGlzLl9zZWxlY3Rvcj1udWxsLHRoaXMuX29mZnNldHM9bnVsbCx0aGlzLl90YXJnZXRzPW51bGwsdGhpcy5fYWN0aXZlVGFyZ2V0PW51bGwsdGhpcy5fc2Nyb2xsSGVpZ2h0PW51bGx9LHQuX2dldENvbmZpZz1mdW5jdGlvbih0KXtpZigic3RyaW5nIiE9dHlwZW9mKHQ9bCh7fSxfbix7fSwib2JqZWN0Ij09dHlwZW9mIHQmJnQ/dDp7fSkpLnRhcmdldCl7dmFyIGU9Zyh0LnRhcmdldCkuYXR0cigiaWQiKTtlfHwoZT1fLmdldFVJRCh1biksZyh0LnRhcmdldCkuYXR0cigiaWQiLGUpKSx0LnRhcmdldD0iIyIrZX1yZXR1cm4gXy50eXBlQ2hlY2tDb25maWcodW4sdCxtbiksdH0sdC5fZ2V0U2Nyb2xsVG9wPWZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMuX3Njcm9sbEVsZW1lbnQ9PT13aW5kb3c/dGhpcy5fc2Nyb2xsRWxlbWVudC5wYWdlWU9mZnNldDp0aGlzLl9zY3JvbGxFbGVtZW50LnNjcm9sbFRvcH0sdC5fZ2V0U2Nyb2xsSGVpZ2h0PWZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMuX3Njcm9sbEVsZW1lbnQuc2Nyb2xsSGVpZ2h0fHxNYXRoLm1heChkb2N1bWVudC5ib2R5LnNjcm9sbEhlaWdodCxkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQuc2Nyb2xsSGVpZ2h0KX0sdC5fZ2V0T2Zmc2V0SGVpZ2h0PWZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMuX3Njcm9sbEVsZW1lbnQ9PT13aW5kb3c/d2luZG93LmlubmVySGVpZ2h0OnRoaXMuX3Njcm9sbEVsZW1lbnQuZ2V0Qm91bmRpbmdDbGllbnRSZWN0KCkuaGVpZ2h0fSx0Ll9wcm9jZXNzPWZ1bmN0aW9uKCl7dmFyIHQ9dGhpcy5fZ2V0U2Nyb2xsVG9wKCkrdGhpcy5fY29uZmlnLm9mZnNldCxlPXRoaXMuX2dldFNjcm9sbEhlaWdodCgpLG49dGhpcy5fY29uZmlnLm9mZnNldCtlLXRoaXMuX2dldE9mZnNldEhlaWdodCgpO2lmKHRoaXMuX3Njcm9sbEhlaWdodCE9PWUmJnRoaXMucmVmcmVzaCgpLG48PXQpe3ZhciBpPXRoaXMuX3RhcmdldHNbdGhpcy5fdGFyZ2V0cy5sZW5ndGgtMV07dGhpcy5fYWN0aXZlVGFyZ2V0IT09aSYmdGhpcy5fYWN0aXZhdGUoaSl9ZWxzZXtpZih0aGlzLl9hY3RpdmVUYXJnZXQmJnQ8dGhpcy5fb2Zmc2V0c1swXSYmMDx0aGlzLl9vZmZzZXRzWzBdKXJldHVybiB0aGlzLl9hY3RpdmVUYXJnZXQ9bnVsbCx2b2lkIHRoaXMuX2NsZWFyKCk7Zm9yKHZhciBvPXRoaXMuX29mZnNldHMubGVuZ3RoO28tLTspe3RoaXMuX2FjdGl2ZVRhcmdldCE9PXRoaXMuX3RhcmdldHNbb10mJnQ+PXRoaXMuX29mZnNldHNbb10mJigidW5kZWZpbmVkIj09dHlwZW9mIHRoaXMuX29mZnNldHNbbysxXXx8dDx0aGlzLl9vZmZzZXRzW28rMV0pJiZ0aGlzLl9hY3RpdmF0ZSh0aGlzLl90YXJnZXRzW29dKX19fSx0Ll9hY3RpdmF0ZT1mdW5jdGlvbihlKXt0aGlzLl9hY3RpdmVUYXJnZXQ9ZSx0aGlzLl9jbGVhcigpO3ZhciB0PXRoaXMuX3NlbGVjdG9yLnNwbGl0KCIsIikubWFwKGZ1bmN0aW9uKHQpe3JldHVybiB0KydbZGF0YS10YXJnZXQ9IicrZSsnIl0sJyt0KydbaHJlZj0iJytlKyciXSd9KSxuPWcoW10uc2xpY2UuY2FsbChkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKHQuam9pbigiLCIpKSkpO24uaGFzQ2xhc3Modm4pPyhuLmNsb3Nlc3QoRG4pLmZpbmQod24pLmFkZENsYXNzKHluKSxuLmFkZENsYXNzKHluKSk6KG4uYWRkQ2xhc3MoeW4pLG4ucGFyZW50cyhDbikucHJldihUbisiLCAiK1NuKS5hZGRDbGFzcyh5biksbi5wYXJlbnRzKENuKS5wcmV2KGJuKS5jaGlsZHJlbihUbikuYWRkQ2xhc3MoeW4pKSxnKHRoaXMuX3Njcm9sbEVsZW1lbnQpLnRyaWdnZXIocG4uQUNUSVZBVEUse3JlbGF0ZWRUYXJnZXQ6ZX0pfSx0Ll9jbGVhcj1mdW5jdGlvbigpe1tdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbCh0aGlzLl9zZWxlY3RvcikpLmZpbHRlcihmdW5jdGlvbih0KXtyZXR1cm4gdC5jbGFzc0xpc3QuY29udGFpbnMoeW4pfSkuZm9yRWFjaChmdW5jdGlvbih0KXtyZXR1cm4gdC5jbGFzc0xpc3QucmVtb3ZlKHluKX0pfSxuLl9qUXVlcnlJbnRlcmZhY2U9ZnVuY3Rpb24oZSl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PWcodGhpcykuZGF0YShmbik7aWYodHx8KHQ9bmV3IG4odGhpcywib2JqZWN0Ij09dHlwZW9mIGUmJmUpLGcodGhpcykuZGF0YShmbix0KSksInN0cmluZyI9PXR5cGVvZiBlKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIHRbZV0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK2UrJyInKTt0W2VdKCl9fSl9LHMobixudWxsLFt7a2V5OiJWRVJTSU9OIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4iNC40LjEifX0se2tleToiRGVmYXVsdCIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIF9ufX1dKSxufSgpO2cod2luZG93KS5vbihwbi5MT0FEX0RBVEFfQVBJLGZ1bmN0aW9uKCl7Zm9yKHZhciB0PVtdLnNsaWNlLmNhbGwoZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChFbikpLGU9dC5sZW5ndGg7ZS0tOyl7dmFyIG49Zyh0W2VdKTtPbi5falF1ZXJ5SW50ZXJmYWNlLmNhbGwobixuLmRhdGEoKSl9fSksZy5mblt1bl09T24uX2pRdWVyeUludGVyZmFjZSxnLmZuW3VuXS5Db25zdHJ1Y3Rvcj1PbixnLmZuW3VuXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIGcuZm5bdW5dPWduLE9uLl9qUXVlcnlJbnRlcmZhY2V9O3ZhciBrbj0iYnMudGFiIixQbj0iLiIra24sTG49Zy5mbi50YWIsam49e0hJREU6ImhpZGUiK1BuLEhJRERFTjoiaGlkZGVuIitQbixTSE9XOiJzaG93IitQbixTSE9XTjoic2hvd24iK1BuLENMSUNLX0RBVEFfQVBJOiJjbGljayIrUG4rIi5kYXRhLWFwaSJ9LEhuPSJkcm9wZG93bi1tZW51IixSbj0iYWN0aXZlIix4bj0iZGlzYWJsZWQiLEZuPSJmYWRlIixVbj0ic2hvdyIsV249Ii5kcm9wZG93biIscW49Ii5uYXYsIC5saXN0LWdyb3VwIixNbj0iLmFjdGl2ZSIsS249Ij4gbGkgPiAuYWN0aXZlIixRbj0nW2RhdGEtdG9nZ2xlPSJ0YWIiXSwgW2RhdGEtdG9nZ2xlPSJwaWxsIl0sIFtkYXRhLXRvZ2dsZT0ibGlzdCJdJyxCbj0iLmRyb3Bkb3duLXRvZ2dsZSIsVm49Ij4gLmRyb3Bkb3duLW1lbnUgLmFjdGl2ZSIsWW49ZnVuY3Rpb24oKXtmdW5jdGlvbiBpKHQpe3RoaXMuX2VsZW1lbnQ9dH12YXIgdD1pLnByb3RvdHlwZTtyZXR1cm4gdC5zaG93PWZ1bmN0aW9uKCl7dmFyIG49dGhpcztpZighKHRoaXMuX2VsZW1lbnQucGFyZW50Tm9kZSYmdGhpcy5fZWxlbWVudC5wYXJlbnROb2RlLm5vZGVUeXBlPT09Tm9kZS5FTEVNRU5UX05PREUmJmcodGhpcy5fZWxlbWVudCkuaGFzQ2xhc3MoUm4pfHxnKHRoaXMuX2VsZW1lbnQpLmhhc0NsYXNzKHhuKSkpe3ZhciB0LGksZT1nKHRoaXMuX2VsZW1lbnQpLmNsb3Nlc3QocW4pWzBdLG89Xy5nZXRTZWxlY3RvckZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO2lmKGUpe3ZhciByPSJVTCI9PT1lLm5vZGVOYW1lfHwiT0wiPT09ZS5ub2RlTmFtZT9LbjpNbjtpPShpPWcubWFrZUFycmF5KGcoZSkuZmluZChyKSkpW2kubGVuZ3RoLTFdfXZhciBzPWcuRXZlbnQoam4uSElERSx7cmVsYXRlZFRhcmdldDp0aGlzLl9lbGVtZW50fSksYT1nLkV2ZW50KGpuLlNIT1cse3JlbGF0ZWRUYXJnZXQ6aX0pO2lmKGkmJmcoaSkudHJpZ2dlcihzKSxnKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIoYSksIWEuaXNEZWZhdWx0UHJldmVudGVkKCkmJiFzLmlzRGVmYXVsdFByZXZlbnRlZCgpKXtvJiYodD1kb2N1bWVudC5xdWVyeVNlbGVjdG9yKG8pKSx0aGlzLl9hY3RpdmF0ZSh0aGlzLl9lbGVtZW50LGUpO3ZhciBsPWZ1bmN0aW9uKCl7dmFyIHQ9Zy5FdmVudChqbi5ISURERU4se3JlbGF0ZWRUYXJnZXQ6bi5fZWxlbWVudH0pLGU9Zy5FdmVudChqbi5TSE9XTix7cmVsYXRlZFRhcmdldDppfSk7ZyhpKS50cmlnZ2VyKHQpLGcobi5fZWxlbWVudCkudHJpZ2dlcihlKX07dD90aGlzLl9hY3RpdmF0ZSh0LHQucGFyZW50Tm9kZSxsKTpsKCl9fX0sdC5kaXNwb3NlPWZ1bmN0aW9uKCl7Zy5yZW1vdmVEYXRhKHRoaXMuX2VsZW1lbnQsa24pLHRoaXMuX2VsZW1lbnQ9bnVsbH0sdC5fYWN0aXZhdGU9ZnVuY3Rpb24odCxlLG4pe2Z1bmN0aW9uIGkoKXtyZXR1cm4gby5fdHJhbnNpdGlvbkNvbXBsZXRlKHQscixuKX12YXIgbz10aGlzLHI9KCFlfHwiVUwiIT09ZS5ub2RlTmFtZSYmIk9MIiE9PWUubm9kZU5hbWU/ZyhlKS5jaGlsZHJlbihNbik6ZyhlKS5maW5kKEtuKSlbMF0scz1uJiZyJiZnKHIpLmhhc0NsYXNzKEZuKTtpZihyJiZzKXt2YXIgYT1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHIpO2cocikucmVtb3ZlQ2xhc3MoVW4pLm9uZShfLlRSQU5TSVRJT05fRU5ELGkpLmVtdWxhdGVUcmFuc2l0aW9uRW5kKGEpfWVsc2UgaSgpfSx0Ll90cmFuc2l0aW9uQ29tcGxldGU9ZnVuY3Rpb24odCxlLG4pe2lmKGUpe2coZSkucmVtb3ZlQ2xhc3MoUm4pO3ZhciBpPWcoZS5wYXJlbnROb2RlKS5maW5kKFZuKVswXTtpJiZnKGkpLnJlbW92ZUNsYXNzKFJuKSwidGFiIj09PWUuZ2V0QXR0cmlidXRlKCJyb2xlIikmJmUuc2V0QXR0cmlidXRlKCJhcmlhLXNlbGVjdGVkIiwhMSl9aWYoZyh0KS5hZGRDbGFzcyhSbiksInRhYiI9PT10LmdldEF0dHJpYnV0ZSgicm9sZSIpJiZ0LnNldEF0dHJpYnV0ZSgiYXJpYS1zZWxlY3RlZCIsITApLF8ucmVmbG93KHQpLHQuY2xhc3NMaXN0LmNvbnRhaW5zKEZuKSYmdC5jbGFzc0xpc3QuYWRkKFVuKSx0LnBhcmVudE5vZGUmJmcodC5wYXJlbnROb2RlKS5oYXNDbGFzcyhIbikpe3ZhciBvPWcodCkuY2xvc2VzdChXbilbMF07aWYobyl7dmFyIHI9W10uc2xpY2UuY2FsbChvLnF1ZXJ5U2VsZWN0b3JBbGwoQm4pKTtnKHIpLmFkZENsYXNzKFJuKX10LnNldEF0dHJpYnV0ZSgiYXJpYS1leHBhbmRlZCIsITApfW4mJm4oKX0saS5falF1ZXJ5SW50ZXJmYWNlPWZ1bmN0aW9uKG4pe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgdD1nKHRoaXMpLGU9dC5kYXRhKGtuKTtpZihlfHwoZT1uZXcgaSh0aGlzKSx0LmRhdGEoa24sZSkpLCJzdHJpbmciPT10eXBlb2Ygbil7aWYoInVuZGVmaW5lZCI9PXR5cGVvZiBlW25dKXRocm93IG5ldyBUeXBlRXJyb3IoJ05vIG1ldGhvZCBuYW1lZCAiJytuKyciJyk7ZVtuXSgpfX0pfSxzKGksbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19XSksaX0oKTtnKGRvY3VtZW50KS5vbihqbi5DTElDS19EQVRBX0FQSSxRbixmdW5jdGlvbih0KXt0LnByZXZlbnREZWZhdWx0KCksWW4uX2pRdWVyeUludGVyZmFjZS5jYWxsKGcodGhpcyksInNob3ciKX0pLGcuZm4udGFiPVluLl9qUXVlcnlJbnRlcmZhY2UsZy5mbi50YWIuQ29uc3RydWN0b3I9WW4sZy5mbi50YWIubm9Db25mbGljdD1mdW5jdGlvbigpe3JldHVybiBnLmZuLnRhYj1MbixZbi5falF1ZXJ5SW50ZXJmYWNlfTt2YXIgem49InRvYXN0IixYbj0iYnMudG9hc3QiLCRuPSIuIitYbixHbj1nLmZuW3puXSxKbj17Q0xJQ0tfRElTTUlTUzoiY2xpY2suZGlzbWlzcyIrJG4sSElERToiaGlkZSIrJG4sSElEREVOOiJoaWRkZW4iKyRuLFNIT1c6InNob3ciKyRuLFNIT1dOOiJzaG93biIrJG59LFpuPSJmYWRlIix0aT0iaGlkZSIsZWk9InNob3ciLG5pPSJzaG93aW5nIixpaT17YW5pbWF0aW9uOiJib29sZWFuIixhdXRvaGlkZToiYm9vbGVhbiIsZGVsYXk6Im51bWJlciJ9LG9pPXthbmltYXRpb246ITAsYXV0b2hpZGU6ITAsZGVsYXk6NTAwfSxyaT0nW2RhdGEtZGlzbWlzcz0idG9hc3QiXScsc2k9ZnVuY3Rpb24oKXtmdW5jdGlvbiBpKHQsZSl7dGhpcy5fZWxlbWVudD10LHRoaXMuX2NvbmZpZz10aGlzLl9nZXRDb25maWcoZSksdGhpcy5fdGltZW91dD1udWxsLHRoaXMuX3NldExpc3RlbmVycygpfXZhciB0PWkucHJvdG90eXBlO3JldHVybiB0LnNob3c9ZnVuY3Rpb24oKXt2YXIgdD10aGlzLGU9Zy5FdmVudChKbi5TSE9XKTtpZihnKHRoaXMuX2VsZW1lbnQpLnRyaWdnZXIoZSksIWUuaXNEZWZhdWx0UHJldmVudGVkKCkpe3RoaXMuX2NvbmZpZy5hbmltYXRpb24mJnRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmFkZChabik7dmFyIG49ZnVuY3Rpb24oKXt0Ll9lbGVtZW50LmNsYXNzTGlzdC5yZW1vdmUobmkpLHQuX2VsZW1lbnQuY2xhc3NMaXN0LmFkZChlaSksZyh0Ll9lbGVtZW50KS50cmlnZ2VyKEpuLlNIT1dOKSx0Ll9jb25maWcuYXV0b2hpZGUmJih0Ll90aW1lb3V0PXNldFRpbWVvdXQoZnVuY3Rpb24oKXt0LmhpZGUoKX0sdC5fY29uZmlnLmRlbGF5KSl9O2lmKHRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LnJlbW92ZSh0aSksXy5yZWZsb3codGhpcy5fZWxlbWVudCksdGhpcy5fZWxlbWVudC5jbGFzc0xpc3QuYWRkKG5pKSx0aGlzLl9jb25maWcuYW5pbWF0aW9uKXt2YXIgaT1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO2codGhpcy5fZWxlbWVudCkub25lKF8uVFJBTlNJVElPTl9FTkQsbikuZW11bGF0ZVRyYW5zaXRpb25FbmQoaSl9ZWxzZSBuKCl9fSx0LmhpZGU9ZnVuY3Rpb24oKXtpZih0aGlzLl9lbGVtZW50LmNsYXNzTGlzdC5jb250YWlucyhlaSkpe3ZhciB0PWcuRXZlbnQoSm4uSElERSk7Zyh0aGlzLl9lbGVtZW50KS50cmlnZ2VyKHQpLHQuaXNEZWZhdWx0UHJldmVudGVkKCl8fHRoaXMuX2Nsb3NlKCl9fSx0LmRpc3Bvc2U9ZnVuY3Rpb24oKXtjbGVhclRpbWVvdXQodGhpcy5fdGltZW91dCksdGhpcy5fdGltZW91dD1udWxsLHRoaXMuX2VsZW1lbnQuY2xhc3NMaXN0LmNvbnRhaW5zKGVpKSYmdGhpcy5fZWxlbWVudC5jbGFzc0xpc3QucmVtb3ZlKGVpKSxnKHRoaXMuX2VsZW1lbnQpLm9mZihKbi5DTElDS19ESVNNSVNTKSxnLnJlbW92ZURhdGEodGhpcy5fZWxlbWVudCxYbiksdGhpcy5fZWxlbWVudD1udWxsLHRoaXMuX2NvbmZpZz1udWxsfSx0Ll9nZXRDb25maWc9ZnVuY3Rpb24odCl7cmV0dXJuIHQ9bCh7fSxvaSx7fSxnKHRoaXMuX2VsZW1lbnQpLmRhdGEoKSx7fSwib2JqZWN0Ij09dHlwZW9mIHQmJnQ/dDp7fSksXy50eXBlQ2hlY2tDb25maWcoem4sdCx0aGlzLmNvbnN0cnVjdG9yLkRlZmF1bHRUeXBlKSx0fSx0Ll9zZXRMaXN0ZW5lcnM9ZnVuY3Rpb24oKXt2YXIgdD10aGlzO2codGhpcy5fZWxlbWVudCkub24oSm4uQ0xJQ0tfRElTTUlTUyxyaSxmdW5jdGlvbigpe3JldHVybiB0LmhpZGUoKX0pfSx0Ll9jbG9zZT1mdW5jdGlvbigpe2Z1bmN0aW9uIHQoKXtlLl9lbGVtZW50LmNsYXNzTGlzdC5hZGQodGkpLGcoZS5fZWxlbWVudCkudHJpZ2dlcihKbi5ISURERU4pfXZhciBlPXRoaXM7aWYodGhpcy5fZWxlbWVudC5jbGFzc0xpc3QucmVtb3ZlKGVpKSx0aGlzLl9jb25maWcuYW5pbWF0aW9uKXt2YXIgbj1fLmdldFRyYW5zaXRpb25EdXJhdGlvbkZyb21FbGVtZW50KHRoaXMuX2VsZW1lbnQpO2codGhpcy5fZWxlbWVudCkub25lKF8uVFJBTlNJVElPTl9FTkQsdCkuZW11bGF0ZVRyYW5zaXRpb25FbmQobil9ZWxzZSB0KCl9LGkuX2pRdWVyeUludGVyZmFjZT1mdW5jdGlvbihuKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIHQ9Zyh0aGlzKSxlPXQuZGF0YShYbik7aWYoZXx8KGU9bmV3IGkodGhpcywib2JqZWN0Ij09dHlwZW9mIG4mJm4pLHQuZGF0YShYbixlKSksInN0cmluZyI9PXR5cGVvZiBuKXtpZigidW5kZWZpbmVkIj09dHlwZW9mIGVbbl0pdGhyb3cgbmV3IFR5cGVFcnJvcignTm8gbWV0aG9kIG5hbWVkICInK24rJyInKTtlW25dKHRoaXMpfX0pfSxzKGksbnVsbCxbe2tleToiVkVSU0lPTiIsZ2V0OmZ1bmN0aW9uKCl7cmV0dXJuIjQuNC4xIn19LHtrZXk6IkRlZmF1bHRUeXBlIixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gaWl9fSx7a2V5OiJEZWZhdWx0IixnZXQ6ZnVuY3Rpb24oKXtyZXR1cm4gb2l9fV0pLGl9KCk7Zy5mblt6bl09c2kuX2pRdWVyeUludGVyZmFjZSxnLmZuW3puXS5Db25zdHJ1Y3Rvcj1zaSxnLmZuW3puXS5ub0NvbmZsaWN0PWZ1bmN0aW9uKCl7cmV0dXJuIGcuZm5bem5dPUduLHNpLl9qUXVlcnlJbnRlcmZhY2V9LHQuQWxlcnQ9dix0LkJ1dHRvbj1ILHQuQ2Fyb3VzZWw9dXQsdC5Db2xsYXBzZT13dCx0LkRyb3Bkb3duPWVlLHQuTW9kYWw9VGUsdC5Qb3BvdmVyPWhuLHQuU2Nyb2xsc3B5PU9uLHQuVGFiPVluLHQuVG9hc3Q9c2ksdC5Ub29sdGlwPVhlLHQuVXRpbD1fLE9iamVjdC5kZWZpbmVQcm9wZXJ0eSh0LCJfX2VzTW9kdWxlIix7dmFsdWU6ITB9KX0pOwovLyMgc291cmNlTWFwcGluZ1VSTD1ib290c3RyYXAubWluLmpzLm1hcA=='); }
if($path == 'remote-finder/images/new_folder.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDBWMHoiLz48cGF0aCBkPSJNMjAgNmgtOGwtMi0ySDRjLTEuMTEgMC0xLjk5Ljg5LTEuOTkgMkwyIDE4YzAgMS4xMS44OSAyIDIgMmgxNmMxLjExIDAgMi0uODkgMi0yVjhjMC0xLjExLS44OS0yLTItMnptLTEgOGgtM3YzaC0ydi0zaC0zdi0yaDNWOWgydjNoM3YyeiIvPjwvc3ZnPg=='); }
if($path == 'remote-finder/images/new_file.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBkPSJNMCAwaDI0djI0SDB6IiBmaWxsPSJub25lIi8+PHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyczQuNDggMTAgMTAgMTAgMTAtNC40OCAxMC0xMFMxNy41MiAyIDEyIDJ6bTUgMTFoLTR2NGgtMnYtNEg3di0yaDRWN2gydjRoNHYyeiIvPjwvc3ZnPg=='); }
if($path == 'remote-finder/images/file.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBkPSJNNiAyYy0xLjEgMC0xLjk5LjktMS45OSAyTDQgMjBjMCAxLjEuODkgMiAxLjk5IDJIMThjMS4xIDAgMi0uOSAyLTJWOGwtNi02SDZ6bTcgN1YzLjVMMTguNSA5SDEzeiIvPjxwYXRoIGQ9Ik0wIDBoMjR2MjRIMHoiIGZpbGw9Im5vbmUiLz48L3N2Zz4='); }
if($path == 'remote-finder/images/rename.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBkPSJNMyAxNy4yNVYyMWgzLjc1TDE3LjgxIDkuOTRsLTMuNzUtMy43NUwzIDE3LjI1ek0yMC43MSA3LjA0Yy4zOS0uMzkuMzktMS4wMiAwLTEuNDFsLTIuMzQtMi4zNGMtLjM5LS4zOS0xLjAyLS4zOS0xLjQxIDBsLTEuODMgMS44MyAzLjc1IDMuNzUgMS44My0xLjgzeiIvPjxwYXRoIGQ9Ik0wIDBoMjR2MjRIMHoiIGZpbGw9Im5vbmUiLz48L3N2Zz4='); }
if($path == 'remote-finder/images/copy.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDB6Ii8+PHBhdGggZD0iTTE2IDFINGMtMS4xIDAtMiAuOS0yIDJ2MTRoMlYzaDEyVjF6bS0xIDRsNiA2djEwYzAgMS4xLS45IDItMiAySDcuOTlDNi44OSAyMyA2IDIyLjEgNiAyMWwuMDEtMTRjMC0xLjEuODktMiAxLjk5LTJoN3ptLTEgN2g1LjVMMTQgNi41VjEyeiIvPjwvc3ZnPg=='); }
if($path == 'remote-finder/images/delete.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBkPSJNNiAxOWMwIDEuMS45IDIgMiAyaDhjMS4xIDAgMi0uOSAyLTJWN0g2djEyek0xOSA0aC0zLjVsLTEtMWgtNWwtMSAxSDV2MmgxNFY0eiIvPjxwYXRoIGQ9Ik0wIDBoMjR2MjRIMHoiIGZpbGw9Im5vbmUiLz48L3N2Zz4='); }
if($path == 'remote-finder/images/readonly.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBkPSJNMCAwaDI0djI0SDB6IiBmaWxsPSJub25lIi8+PHBhdGggZD0iTTE4IDhoLTFWNmMwLTIuNzYtMi4yNC01LTUtNVM3IDMuMjQgNyA2djJINmMtMS4xIDAtMiAuOS0yIDJ2MTBjMCAxLjEuOSAyIDIgMmgxMmMxLjEgMCAyLS45IDItMlYxMGMwLTEuMS0uOS0yLTItMnptLTYgOWMtMS4xIDAtMi0uOS0yLTJzLjktMiAyLTIgMiAuOSAyIDItLjkgMi0yIDJ6bTMuMS05SDguOVY2YzAtMS43MSAxLjM5LTMuMSAzLjEtMy4xIDEuNzEgMCAzLjEgMS4zOSAzLjEgMy4xdjJ6Ii8+PC9zdmc+'); }
if($path == 'remote-finder/images/folder.svg'){ return base64_decode('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBkPSJNMTAgNEg0Yy0xLjEgMC0xLjk5LjktMS45OSAyTDIgMThjMCAxLjEuOSAyIDIgMmgxNmMxLjEgMCAyLS45IDItMlY4YzAtMS4xLS45LTItMi0yaC04bC0yLTJ6Ii8+PHBhdGggZD0iTTAgMGgyNHYyNEgweiIgZmlsbD0ibm9uZSIvPjwvc3ZnPg=='); }
if($path == 'remote-finder/remote-finder.css'){ return base64_decode('LnJlbW90ZS1maW5kZXIgewogIGRpc3BsYXk6IC1tcy1mbGV4Ym94OwogIGRpc3BsYXk6IGZsZXg7CiAgLW1zLWZsZXgtZGlyZWN0aW9uOiBjb2x1bW47CiAgICAgIGZsZXgtZGlyZWN0aW9uOiBjb2x1bW47CiAgLW1zLWZsZXgtcGFjazogc3RhcnQ7CiAgICAgIGp1c3RpZnktY29udGVudDogZmxleC1zdGFydDsKICBiYWNrZ3JvdW5kLWNvbG9yOiAjZjlmOWY5OwogIG92ZXJmbG93OiBoaWRkZW47IH0KICAucmVtb3RlLWZpbmRlciBhIHsKICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmY7CiAgICBjb2xvcjogIzMzMzsKICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsgfQogICAgLnJlbW90ZS1maW5kZXIgYTpob3ZlciB7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNlZWU7CiAgICAgIGNvbG9yOiAjMDAwOyB9CiAgLnJlbW90ZS1maW5kZXIgYnV0dG9uIHsKICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmY7CiAgICBjb2xvcjogIzMzMzsKICAgIGJvcmRlcjogMXB4IHNvbGlkICM2NjY7CiAgICBjdXJzb3I6IHBvaW50ZXI7IH0KICAgIC5yZW1vdGUtZmluZGVyIGJ1dHRvbjpob3ZlciB7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNlZWU7CiAgICAgIGNvbG9yOiAjMDAwOyB9CiAgLnJlbW90ZS1maW5kZXJfX21lbnUgewogICAgbWFyZ2luOiAwICFpbXBvcnRhbnQ7CiAgICBwYWRkaW5nOiAwICFpbXBvcnRhbnQ7CiAgICBkaXNwbGF5OiAtbXMtZmxleGJveDsKICAgIGRpc3BsYXk6IGZsZXg7CiAgICBib3JkZXItYm90dG9tOiAxcHggc29saWQgIzY2NjsKICAgIG1heC13aWR0aDogMTAwJTsKICAgIC1tcy1mbGV4LW5lZ2F0aXZlOiAwOwogICAgICAgIGZsZXgtc2hyaW5rOiAwOwogICAgLW1zLWZsZXgtd3JhcDogbm93cmFwOwogICAgICAgIGZsZXgtd3JhcDogbm93cmFwOyB9CiAgICAucmVtb3RlLWZpbmRlcl9fbWVudSA+IGxpIHsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICBsaXN0LXN0eWxlLXR5cGU6IG5vbmU7CiAgICAgIGJvcmRlci1yaWdodDogMXB4IHNvbGlkICM5OTk7CiAgICAgIG1heC13aWR0aDogMTAwJTsgfQogICAgICAucmVtb3RlLWZpbmRlcl9fbWVudSA+IGxpOmxhc3QtY2hpbGQgewogICAgICAgIGJvcmRlci1yaWdodDogbm9uZTsgfQogICAgICAucmVtb3RlLWZpbmRlcl9fbWVudSA+IGxpID4gYSB7CiAgICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgICAgcG9zaXRpb246IHJlbGF0aXZlOwogICAgICAgIHBhZGRpbmc6IDAuM2VtIDAuNWVtOwogICAgICAgIGJveC1zaXppbmc6IGJvcmRlci1ib3g7CiAgICAgICAgd2lkdGg6IDEwMCU7CiAgICAgICAgaGVpZ2h0OiAxMDAlOwogICAgICAgIHRleHQtb3ZlcmZsb3c6IGVsbGlwc2lzOwogICAgICAgIG92ZXJmbG93OiBoaWRkZW47CiAgICAgICAgd2hpdGUtc3BhY2U6IG5vd3JhcDsgfQogICAgICAucmVtb3RlLWZpbmRlcl9fbWVudSA+IGxpID4gaW5wdXRbdHlwZT10ZXh0XSB7CiAgICAgICAgd2lkdGg6IDEwMCU7CiAgICAgICAgaGVpZ2h0OiAxMDAlOwogICAgICAgIGJveC1zaXppbmc6IGJvcmRlci1ib3g7CiAgICAgICAgYm9yZGVyOiAwOwogICAgICAgIG1hcmdpbjogMDsKICAgICAgICBsaW5lLWhlaWdodDogMTsKICAgICAgICBib3JkZXItcmFkaXVzOiAwOwogICAgICAgIG91dGxpbmU6IG5vbmU7IH0KICAucmVtb3RlLWZpbmRlcl9fcGF0aC1iYXIgewogICAgbWFyZ2luOiAwICFpbXBvcnRhbnQ7CiAgICBwYWRkaW5nOiAwICFpbXBvcnRhbnQ7CiAgICBkaXNwbGF5OiAtbXMtZmxleGJveDsKICAgIGRpc3BsYXk6IGZsZXg7CiAgICBvdmVyZmxvdzogYXV0bzsKICAgIHBvc2l0aW9uOiByZWxhdGl2ZTsKICAgIGJvcmRlci1ib3R0b206IDFweCBzb2xpZCAjNjY2OwogICAgbWF4LXdpZHRoOiAxMDAlOwogICAgLW1zLWZsZXgtbmVnYXRpdmU6IDA7CiAgICAgICAgZmxleC1zaHJpbms6IDA7CiAgICAtbXMtZmxleC13cmFwOiB3cmFwOwogICAgICAgIGZsZXgtd3JhcDogd3JhcDsgfQogICAgLnJlbW90ZS1maW5kZXJfX3BhdGgtYmFyID4gbGkgewogICAgICBkaXNwbGF5OiBibG9jazsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICBsaXN0LXN0eWxlLXR5cGU6IG5vbmU7CiAgICAgIGJvcmRlci1yaWdodDogbm9uZTsKICAgICAgbWF4LXdpZHRoOiAxMDAlOwogICAgICBwb3NpdGlvbjogcmVsYXRpdmU7IH0KICAgICAgLnJlbW90ZS1maW5kZXJfX3BhdGgtYmFyID4gbGk6bGFzdC1jaGlsZCB7CiAgICAgICAgYm9yZGVyLXJpZ2h0OiBub25lOyB9CiAgICAgIC5yZW1vdGUtZmluZGVyX19wYXRoLWJhciA+IGxpID4gYSB7CiAgICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgICAgcG9zaXRpb246IHJlbGF0aXZlOwogICAgICAgIHBhZGRpbmc6IDAuM2VtIDAuNWVtIDAuM2VtIDFlbTsKICAgICAgICBib3gtc2l6aW5nOiBib3JkZXItYm94OwogICAgICAgIHdpZHRoOiAxMDAlOwogICAgICAgIGhlaWdodDogMTAwJTsKICAgICAgICB0ZXh0LW92ZXJmbG93OiBlbGxpcHNpczsKICAgICAgICB3aGl0ZS1zcGFjZTogbm93cmFwOyB9CiAgICAgICAgLnJlbW90ZS1maW5kZXJfX3BhdGgtYmFyID4gbGkgPiBhOjpiZWZvcmUgewogICAgICAgICAgY29udGVudDogIiAiOwogICAgICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgICAgICBwb3NpdGlvbjogYWJzb2x1dGU7CiAgICAgICAgICByaWdodDogLTVweDsKICAgICAgICAgIHRvcDogMDsKICAgICAgICAgIHdpZHRoOiAxNXB4OwogICAgICAgICAgaGVpZ2h0OiA1MCU7CiAgICAgICAgICBib3JkZXItcmlnaHQ6IDFweCBzb2xpZCAjOTk5OwogICAgICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmZjsKICAgICAgICAgIHRyYW5zZm9ybTogc2tldygzMGRlZyk7IH0KICAgICAgICAucmVtb3RlLWZpbmRlcl9fcGF0aC1iYXIgPiBsaSA+IGE6OmFmdGVyIHsKICAgICAgICAgIGNvbnRlbnQ6ICIgIjsKICAgICAgICAgIGRpc3BsYXk6IGJsb2NrOwogICAgICAgICAgcG9zaXRpb246IGFic29sdXRlOwogICAgICAgICAgcmlnaHQ6IC01cHg7CiAgICAgICAgICBib3R0b206IDA7CiAgICAgICAgICB3aWR0aDogMTVweDsKICAgICAgICAgIGhlaWdodDogNTAlOwogICAgICAgICAgYm9yZGVyLXJpZ2h0OiAxcHggc29saWQgIzk5OTsKICAgICAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmY7CiAgICAgICAgICB0cmFuc2Zvcm06IHNrZXcoLTMwZGVnKTsgfQogICAgICAgIC5yZW1vdGUtZmluZGVyX19wYXRoLWJhciA+IGxpID4gYTpob3Zlcjo6YmVmb3JlLCAucmVtb3RlLWZpbmRlcl9fcGF0aC1iYXIgPiBsaSA+IGE6aG92ZXI6OmFmdGVyIHsKICAgICAgICAgIGJhY2tncm91bmQtY29sb3I6ICNlZWU7CiAgICAgICAgICBjb2xvcjogIzAwMDsgfQogICAgICAucmVtb3RlLWZpbmRlcl9fcGF0aC1iYXIgPiBsaTpmaXJzdC1jaGlsZCA+IGEgewogICAgICAgIHBhZGRpbmctbGVmdDogMC4zZW07IH0KICAucmVtb3RlLWZpbmRlcl9fZmlsZS1saXN0IHsKICAgIG1hcmdpbjogMCAhaW1wb3J0YW50OwogICAgcGFkZGluZzogMCAhaW1wb3J0YW50OwogICAgb3ZlcmZsb3c6IGF1dG87IH0KICAgIC5yZW1vdGUtZmluZGVyX19maWxlLWxpc3QgPiBsaSB7CiAgICAgIGRpc3BsYXk6IGJsb2NrOwogICAgICBtYXJnaW46IDA7CiAgICAgIHBhZGRpbmc6IDA7CiAgICAgIGxpc3Qtc3R5bGUtdHlwZTogbm9uZTsKICAgICAgYm9yZGVyLWJvdHRvbTogMXB4IHNvbGlkICM5OTk7IH0KICAgICAgLnJlbW90ZS1maW5kZXJfX2ZpbGUtbGlzdCA+IGxpOmxhc3QtY2hpbGQgewogICAgICAgIGJvcmRlci1ib3R0b206IG5vbmU7IH0KICAgICAgLnJlbW90ZS1maW5kZXJfX2ZpbGUtbGlzdCA+IGxpID4gYSB7CiAgICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgICAgcG9zaXRpb246IHJlbGF0aXZlOwogICAgICAgIHBhZGRpbmc6IDAuM2VtIDAuNWVtOwogICAgICAgIGJveC1zaXppbmc6IGJvcmRlci1ib3g7CiAgICAgICAgd2lkdGg6IDEwMCU7CiAgICAgICAgaGVpZ2h0OiAxMDAlOwogICAgICAgIHRleHQtb3ZlcmZsb3c6IGVsbGlwc2lzOwogICAgICAgIG92ZXJmbG93OiBoaWRkZW47CiAgICAgICAgd2hpdGUtc3BhY2U6IG5vd3JhcDsgfQogIC5yZW1vdGUtZmluZGVyX19maWxlLWxpc3Qtc3VibWVudSB7CiAgICBtYXJnaW46IDAgIWltcG9ydGFudDsKICAgIHBhZGRpbmc6IDAgIWltcG9ydGFudDsKICAgIHBvc2l0aW9uOiBhYnNvbHV0ZTsKICAgIHRvcDogMDsKICAgIHJpZ2h0OiAwOwogICAgZGlzcGxheTogbm9uZTsKICAgIG92ZXJmbG93OiBhdXRvOyB9CiAgICAucmVtb3RlLWZpbmRlcl9fZmlsZS1saXN0LXN1Ym1lbnUgPiBsaSB7CiAgICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICBsaXN0LXN0eWxlLXR5cGU6IG5vbmU7CiAgICAgIGJvcmRlcjogbm9uZTsgfQogICAgICAucmVtb3RlLWZpbmRlcl9fZmlsZS1saXN0LXN1Ym1lbnUgPiBsaTpsYXN0LWNoaWxkIHsKICAgICAgICBib3JkZXItYm90dG9tOiBub25lOyB9CiAgICAgIC5yZW1vdGUtZmluZGVyX19maWxlLWxpc3Qtc3VibWVudSA+IGxpID4gYSB7CiAgICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgICAgcGFkZGluZzogMC4zZW0gMC41ZW07CiAgICAgICAgYm94LXNpemluZzogYm9yZGVyLWJveDsKICAgICAgICB3aWR0aDogMTAwJTsKICAgICAgICBoZWlnaHQ6IDEwMCU7CiAgICAgICAgdGV4dC1vdmVyZmxvdzogZWxsaXBzaXM7CiAgICAgICAgb3ZlcmZsb3c6IGhpZGRlbjsKICAgICAgICB3aGl0ZS1zcGFjZTogbm93cmFwOyB9CiAgLnJlbW90ZS1maW5kZXJfX2ZpbGUtbGlzdCBhOmhvdmVyIC5yZW1vdGUtZmluZGVyX19maWxlLWxpc3Qtc3VibWVudSB7CiAgICBkaXNwbGF5OiBibG9jazsgfQogIC5yZW1vdGUtZmluZGVyX19pY28tbmV3LWZvbGRlcjo6YmVmb3JlIHsKICAgIGNvbnRlbnQ6ICcnOwogICAgZGlzcGxheTogaW5saW5lLWJsb2NrOwogICAgd2lkdGg6IDFlbTsKICAgIGhlaWdodDogMWVtOwogICAgYmFja2dyb3VuZC1pbWFnZTogdXJsKCI/cmVzPXJlbW90ZS1maW5kZXIvaW1hZ2VzL25ld19mb2xkZXIuc3ZnIik7CiAgICBiYWNrZ3JvdW5kLXNpemU6IGNvbnRhaW47CiAgICB2ZXJ0aWNhbC1hbGlnbjogbWlkZGxlOwogICAgbWFyZ2luLXJpZ2h0OiAwLjVlbTsgfQogIC5yZW1vdGUtZmluZGVyX19pY28tbmV3LWZpbGU6OmJlZm9yZSB7CiAgICBjb250ZW50OiAnJzsKICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgIHdpZHRoOiAxZW07CiAgICBoZWlnaHQ6IDFlbTsKICAgIGJhY2tncm91bmQtaW1hZ2U6IHVybCgiP3Jlcz1yZW1vdGUtZmluZGVyL2ltYWdlcy9uZXdfZmlsZS5zdmciKTsKICAgIGJhY2tncm91bmQtc2l6ZTogY29udGFpbjsKICAgIHZlcnRpY2FsLWFsaWduOiBtaWRkbGU7CiAgICBtYXJnaW4tcmlnaHQ6IDAuNWVtOyB9CiAgLnJlbW90ZS1maW5kZXJfX2ljby1mb2xkZXI6OmJlZm9yZSB7CiAgICBjb250ZW50OiAnJzsKICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgIHdpZHRoOiAxZW07CiAgICBoZWlnaHQ6IDFlbTsKICAgIGJhY2tncm91bmQtaW1hZ2U6IHVybCgiP3Jlcz1yZW1vdGUtZmluZGVyL2ltYWdlcy9mb2xkZXIuc3ZnIik7CiAgICBiYWNrZ3JvdW5kLXNpemU6IGNvbnRhaW47CiAgICB2ZXJ0aWNhbC1hbGlnbjogbWlkZGxlOwogICAgbWFyZ2luLXJpZ2h0OiAwLjVlbTsgfQogIC5yZW1vdGUtZmluZGVyX19pY28tZmlsZTo6YmVmb3JlIHsKICAgIGNvbnRlbnQ6ICcnOwogICAgZGlzcGxheTogaW5saW5lLWJsb2NrOwogICAgd2lkdGg6IDFlbTsKICAgIGhlaWdodDogMWVtOwogICAgYmFja2dyb3VuZC1pbWFnZTogdXJsKCI/cmVzPXJlbW90ZS1maW5kZXIvaW1hZ2VzL2ZpbGUuc3ZnIik7CiAgICBiYWNrZ3JvdW5kLXNpemU6IGNvbnRhaW47CiAgICB2ZXJ0aWNhbC1hbGlnbjogbWlkZGxlOwogICAgbWFyZ2luLXJpZ2h0OiAwLjVlbTsgfQogIC5yZW1vdGUtZmluZGVyX19pY28tY29weTo6YmVmb3JlIHsKICAgIGNvbnRlbnQ6ICcnOwogICAgZGlzcGxheTogaW5saW5lLWJsb2NrOwogICAgd2lkdGg6IDFlbTsKICAgIGhlaWdodDogMWVtOwogICAgYmFja2dyb3VuZC1pbWFnZTogdXJsKCI/cmVzPXJlbW90ZS1maW5kZXIvaW1hZ2VzL2NvcHkuc3ZnIik7CiAgICBiYWNrZ3JvdW5kLXNpemU6IGNvbnRhaW47CiAgICB2ZXJ0aWNhbC1hbGlnbjogbWlkZGxlOwogICAgbWFyZ2luLXJpZ2h0OiAwLjVlbTsgfQogIC5yZW1vdGUtZmluZGVyX19pY28tcmVuYW1lOjpiZWZvcmUgewogICAgY29udGVudDogJyc7CiAgICBkaXNwbGF5OiBpbmxpbmUtYmxvY2s7CiAgICB3aWR0aDogMWVtOwogICAgaGVpZ2h0OiAxZW07CiAgICBiYWNrZ3JvdW5kLWltYWdlOiB1cmwoIj9yZXM9cmVtb3RlLWZpbmRlci9pbWFnZXMvcmVuYW1lLnN2ZyIpOwogICAgYmFja2dyb3VuZC1zaXplOiBjb250YWluOwogICAgdmVydGljYWwtYWxpZ246IG1pZGRsZTsKICAgIG1hcmdpbi1yaWdodDogMC41ZW07IH0KICAucmVtb3RlLWZpbmRlcl9faWNvLWRlbGV0ZTo6YmVmb3JlIHsKICAgIGNvbnRlbnQ6ICcnOwogICAgZGlzcGxheTogaW5saW5lLWJsb2NrOwogICAgd2lkdGg6IDFlbTsKICAgIGhlaWdodDogMWVtOwogICAgYmFja2dyb3VuZC1pbWFnZTogdXJsKCI/cmVzPXJlbW90ZS1maW5kZXIvaW1hZ2VzL2RlbGV0ZS5zdmciKTsKICAgIGJhY2tncm91bmQtc2l6ZTogY29udGFpbjsKICAgIHZlcnRpY2FsLWFsaWduOiBtaWRkbGU7CiAgICBtYXJnaW4tcmlnaHQ6IDAuNWVtOyB9CiAgLnJlbW90ZS1maW5kZXJfX2ljby1yZWFkb25seTo6YWZ0ZXIgewogICAgY29udGVudDogJyc7CiAgICBkaXNwbGF5OiBpbmxpbmUtYmxvY2s7CiAgICB3aWR0aDogMWVtOwogICAgaGVpZ2h0OiAxZW07CiAgICBiYWNrZ3JvdW5kLWltYWdlOiB1cmwoIj9yZXM9cmVtb3RlLWZpbmRlci9pbWFnZXMvcmVhZG9ubHkuc3ZnIik7CiAgICBiYWNrZ3JvdW5kLXNpemU6IGNvbnRhaW47CiAgICB2ZXJ0aWNhbC1hbGlnbjogbWlkZGxlOwogICAgbWFyZ2luLXJpZ2h0OiAwLjVlbTsKICAgIG1hcmdpbi1yaWdodDogMDsKICAgIG1hcmdpbi1sZWZ0OiAwLjVlbTsgfQo='); }
if($path == 'remote-finder/remote-finder.js'){ return base64_decode('KGZ1bmN0aW9uIGUodCxuLHIpe2Z1bmN0aW9uIHMobyx1KXtpZighbltvXSl7aWYoIXRbb10pe3ZhciBhPXR5cGVvZiByZXF1aXJlPT0iZnVuY3Rpb24iJiZyZXF1aXJlO2lmKCF1JiZhKXJldHVybiBhKG8sITApO2lmKGkpcmV0dXJuIGkobywhMCk7dGhyb3cgbmV3IEVycm9yKCJDYW5ub3QgZmluZCBtb2R1bGUgJyIrbysiJyIpfXZhciBmPW5bb109e2V4cG9ydHM6e319O3Rbb11bMF0uY2FsbChmLmV4cG9ydHMsZnVuY3Rpb24oZSl7dmFyIG49dFtvXVsxXVtlXTtyZXR1cm4gcyhuP246ZSl9LGYsZi5leHBvcnRzLGUsdCxuLHIpfXJldHVybiBuW29dLmV4cG9ydHN9dmFyIGk9dHlwZW9mIHJlcXVpcmU9PSJmdW5jdGlvbiImJnJlcXVpcmU7Zm9yKHZhciBvPTA7bzxyLmxlbmd0aDtvKyspcyhyW29dKTtyZXR1cm4gc30pKHsxOltmdW5jdGlvbihyZXF1aXJlLG1vZHVsZSxleHBvcnRzKXsKLyoqCiAqIFJlbW90ZSBGaW5kZXIKICovCndpbmRvdy5SZW1vdGVGaW5kZXIgPSBmdW5jdGlvbigkZWxtLCBvcHRpb25zKXsKCXZhciBfdGhpcyA9IHRoaXM7Cgl2YXIgY3VycmVudF9kaXIgPSAnLyc7Cgl2YXIgZmlsdGVyID0gJyc7Cgl2YXIgJHBhdGhCYXI7Cgl2YXIgJGZpbGVMaXN0OwoJb3B0aW9ucyA9IG9wdGlvbnMgfHwge307CglvcHRpb25zLmdwaUJyaWRnZSA9IG9wdGlvbnMuZ3BpQnJpZGdlIHx8IGZ1bmN0aW9uKCl7fTsKCW9wdGlvbnMub3BlbiA9IG9wdGlvbnMub3BlbiB8fCBmdW5jdGlvbihwYXRoaW5mbywgY2FsbGJhY2spewoJCWNhbGxiYWNrKCk7Cgl9OwoJb3B0aW9ucy5ta2RpciA9IG9wdGlvbnMubWtkaXIgfHwgZnVuY3Rpb24oY3VycmVudF9kaXIsIGNhbGxiYWNrKXsKCQl2YXIgZm9sZGVybmFtZSA9IHByb21wdCgnRm9sZGVyIG5hbWU6Jyk7CgkJaWYoICFmb2xkZXJuYW1lICl7IHJldHVybjsgfQoJCWNhbGxiYWNrKCBmb2xkZXJuYW1lICk7CgkJcmV0dXJuOwoJfTsKCW9wdGlvbnMubWtmaWxlID0gb3B0aW9ucy5ta2ZpbGUgfHwgZnVuY3Rpb24oY3VycmVudF9kaXIsIGNhbGxiYWNrKXsKCQl2YXIgZmlsZW5hbWUgPSBwcm9tcHQoJ0ZpbGUgbmFtZTonKTsKCQlpZiggIWZpbGVuYW1lICl7IHJldHVybjsgfQoJCWNhbGxiYWNrKCBmaWxlbmFtZSApOwoJCXJldHVybjsKCX07CglvcHRpb25zLmNvcHkgPSBvcHRpb25zLmNvcHkgfHwgZnVuY3Rpb24oY29weUZyb20sIGNhbGxiYWNrKXsKCQl2YXIgY29weVRvID0gcHJvbXB0KCdDb3B5IGZyb20gJytjb3B5RnJvbSsnIHRvOicsIGNvcHlGcm9tKTsKCQljYWxsYmFjayggY29weUZyb20sIGNvcHlUbyApOwoJCXJldHVybjsKCX07CglvcHRpb25zLnJlbmFtZSA9IG9wdGlvbnMucmVuYW1lIHx8IGZ1bmN0aW9uKHJlbmFtZUZyb20sIGNhbGxiYWNrKXsKCQl2YXIgcmVuYW1lVG8gPSBwcm9tcHQoJ1JlbmFtZSBmcm9tICcrcmVuYW1lRnJvbSsnIHRvOicsIHJlbmFtZUZyb20pOwoJCWNhbGxiYWNrKCByZW5hbWVGcm9tLCByZW5hbWVUbyApOwoJCXJldHVybjsKCX07CglvcHRpb25zLnJlbW92ZSA9IG9wdGlvbnMucmVtb3ZlIHx8IGZ1bmN0aW9uKHBhdGhfdGFyZ2V0LCBjYWxsYmFjayl7CgkJaWYoICFjb25maXJtKCdSZWFsbHk/JykgKXsKCQkJcmV0dXJuOwoJCX0KCQljYWxsYmFjaygpOwoJCXJldHVybjsKCX07CgkkZWxtLmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXInKTsKCgkvKioKCSAqIOOCteODvOODkOODvOOCteOCpOODieOCueOCr+ODquODl+ODiOOBq+WVj+OBhOWQiOOCj+OBm+OCiwoJICovCglmdW5jdGlvbiBncGlCcmlkZ2UoaW5wdXQsIGNhbGxiYWNrKXsKCQlvcHRpb25zLmdwaUJyaWRnZShpbnB1dCwgY2FsbGJhY2spOwoJfQoKCS8qKgoJICog44OV44Kh44Kk44Or44KS6ZaL44GPCgkgKi8KCXRoaXMub3BlbiA9IGZ1bmN0aW9uKHBhdGgsIGNhbGxiYWNrKXsKCQl2YXIgZXh0ID0gbnVsbDsKCQl0cnl7CgkJCWlmKCBwYXRoLm1hdGNoKC9eW1xzXFNdKlwuKFtcc1xTXSs/KSQvKSApewoJCQkJZXh0ID0gUmVnRXhwLiQxLnRvTG93ZXJDYXNlKCk7CgkJCX0KCQl9Y2F0Y2goZSl7fQoKCQl2YXIgcGF0aGluZm8gPSB7CgkJCSdwYXRoJzogcGF0aCwKCQkJJ2V4dCc6IGV4dAoJCX07CgkJb3B0aW9ucy5vcGVuKHBhdGhpbmZvLCBmdW5jdGlvbihpc0NvbXBldGVkKXsKCQkJaWYoIGlzQ29tcGV0ZWQgKXsKCQkJCXJldHVybjsKCQkJfQoJCQljYWxsYmFjayhpc0NvbXBldGVkKTsKCQl9KTsKCX0KCgkvKioKCSAqIOODleOCqeODq+ODgOOCkuS9nOaIkOOBmeOCiwoJICovCgl0aGlzLm1rZGlyID0gZnVuY3Rpb24oY3VycmVudF9kaXIsIGNhbGxiYWNrKXsKCQlvcHRpb25zLm1rZGlyKGN1cnJlbnRfZGlyLCBmdW5jdGlvbihmb2xkZXJuYW1lKXsKCQkJaWYoICFmb2xkZXJuYW1lICl7CgkJCQlyZXR1cm47CgkJCX0KCQkJZ3BpQnJpZGdlKAoJCQkJewoJCQkJCSdhcGknOiAnY3JlYXRlTmV3Rm9sZGVyJywKCQkJCQkncGF0aCc6IGN1cnJlbnRfZGlyK2ZvbGRlcm5hbWUKCQkJCX0sCgkJCQlmdW5jdGlvbihyZXN1bHQpewoJCQkJCWlmKCFyZXN1bHQucmVzdWx0KXsKCQkJCQkJYWxlcnQocmVzdWx0Lm1lc3NhZ2UpOwoJCQkJCX0KCQkJCQljYWxsYmFjaygpOwoJCQkJfQoJCQkpOwoJCQlyZXR1cm47CgkJfSk7Cgl9CgoJLyoqCgkgKiDjg5XjgqHjgqTjg6vjgpLkvZzmiJDjgZnjgosKCSAqLwoJdGhpcy5ta2ZpbGUgPSBmdW5jdGlvbihjdXJyZW50X2RpciwgY2FsbGJhY2spewoJCW9wdGlvbnMubWtmaWxlKGN1cnJlbnRfZGlyLCBmdW5jdGlvbihmaWxlbmFtZSl7CgkJCWlmKCAhZmlsZW5hbWUgKXsKCQkJCXJldHVybjsKCQkJfQoJCQlncGlCcmlkZ2UoCgkJCQl7CgkJCQkJJ2FwaSc6ICdjcmVhdGVOZXdGaWxlJywKCQkJCQkncGF0aCc6IGN1cnJlbnRfZGlyK2ZpbGVuYW1lCgkJCQl9LAoJCQkJZnVuY3Rpb24ocmVzdWx0KXsKCQkJCQlpZighcmVzdWx0LnJlc3VsdCl7CgkJCQkJCWFsZXJ0KHJlc3VsdC5tZXNzYWdlKTsKCQkJCQl9CgkJCQkJY2FsbGJhY2soKTsKCQkJCX0KCQkJKTsKCQkJcmV0dXJuOwoJCX0pOwoJfQoKCS8qKgoJICog44OV44Kh44Kk44Or44KE44OV44Kp44Or44OA44KS6KSH6KO944GZ44KLCgkgKi8KCXRoaXMuY29weSA9IGZ1bmN0aW9uKGNvcHlGcm9tLCBjYWxsYmFjayl7CgkJb3B0aW9ucy5jb3B5KGNvcHlGcm9tLCBmdW5jdGlvbihjb3B5RnJvbSwgY29weVRvKXsKCQkJaWYoICFjb3B5VG8gKXsgcmV0dXJuOyB9CgkJCWlmKCBjb3B5VG8gPT0gY29weUZyb20gKXsgcmV0dXJuOyB9CgkJCWdwaUJyaWRnZSgKCQkJCXsKCQkJCQknYXBpJzogJ2NvcHknLAoJCQkJCSdwYXRoJzogY29weUZyb20sCgkJCQkJJ29wdGlvbnMnOiB7CgkJCQkJCSd0byc6IGNvcHlUbwoJCQkJCX0KCQkJCX0sCgkJCQlmdW5jdGlvbihyZXN1bHQpewoJCQkJCWlmKCFyZXN1bHQucmVzdWx0KXsKCQkJCQkJYWxlcnQocmVzdWx0Lm1lc3NhZ2UpOwoJCQkJCX0KCQkJCQljYWxsYmFjaygpOwoJCQkJfQoJCQkpOwoJCQlyZXR1cm47CgkJfSk7Cgl9CgoJLyoqCgkgKiDjg5XjgqHjgqTjg6vjgoTjg5Xjgqnjg6vjg4Djga7lkI3liY3jgpLlpInmm7TjgZnjgosKCSAqLwoJdGhpcy5yZW5hbWUgPSBmdW5jdGlvbihyZW5hbWVGcm9tLCBjYWxsYmFjayl7CgkJb3B0aW9ucy5yZW5hbWUocmVuYW1lRnJvbSwgZnVuY3Rpb24ocmVuYW1lRnJvbSwgcmVuYW1lVG8pewoJCQlpZiggIXJlbmFtZVRvICl7IHJldHVybjsgfQoJCQlpZiggcmVuYW1lVG8gPT0gcmVuYW1lRnJvbSApeyByZXR1cm47IH0KCQkJZ3BpQnJpZGdlKAoJCQkJewoJCQkJCSdhcGknOiAncmVuYW1lJywKCQkJCQkncGF0aCc6IHJlbmFtZUZyb20sCgkJCQkJJ29wdGlvbnMnOiB7CgkJCQkJCSd0byc6IHJlbmFtZVRvCgkJCQkJfQoJCQkJfSwKCQkJCWZ1bmN0aW9uKHJlc3VsdCl7CgkJCQkJaWYoIXJlc3VsdC5yZXN1bHQpewoJCQkJCQlhbGVydChyZXN1bHQubWVzc2FnZSk7CgkJCQkJfQoJCQkJCWNhbGxiYWNrKCk7CgkJCQl9CgkJCSk7CgkJCXJldHVybjsKCQl9KTsKCX0KCgkvKioKCSAqIOODleOCoeOCpOODq+OChOODleOCqeODq+ODgOOCkuWJiumZpOOBmeOCiwoJICovCgl0aGlzLnJlbW92ZSA9IGZ1bmN0aW9uKHBhdGhfdGFyZ2V0LCBjYWxsYmFjayl7CgkJb3B0aW9ucy5yZW1vdmUocGF0aF90YXJnZXQsIGZ1bmN0aW9uKCl7CgkJCWdwaUJyaWRnZSgKCQkJCXsKCQkJCQknYXBpJzogJ3JlbW92ZScsCgkJCQkJJ3BhdGgnOiBwYXRoX3RhcmdldAoJCQkJfSwKCQkJCWZ1bmN0aW9uKHJlc3VsdCl7CgkJCQkJaWYoIXJlc3VsdC5yZXN1bHQpewoJCQkJCQlhbGVydChyZXN1bHQubWVzc2FnZSk7CgkJCQkJfQoJCQkJCWNhbGxiYWNrKCk7CgkJCQl9CgkJCSk7CgkJCXJldHVybjsKCQl9KTsKCX0KCgkvKioKCSAqIOOCq+ODrOODs+ODiOODh+OCo+ODrOOCr+ODiOODquOCkuW+l+OCiwoJICovCgl0aGlzLmdldEN1cnJlbnREaXIgPSBmdW5jdGlvbigpewoJCXJldHVybiBjdXJyZW50X2RpcjsKCX0KCgkvKioKCSAqIOOCq+ODrOODs+ODiOODh+OCo+ODrOOCr+ODiOODquOCkuOCu+ODg+ODiOOBmeOCiwoJICovCgl0aGlzLnNldEN1cnJlbnREaXIgPSBmdW5jdGlvbihwYXRoLCBjYWxsYmFjayl7CgkJY3VycmVudF9kaXIgPSBwYXRoOwoJCWNhbGxiYWNrID0gY2FsbGJhY2sgfHwgZnVuY3Rpb24oKXt9OwoJCWdwaUJyaWRnZSgKCQkJewoJCQkJJ2FwaSc6ICdnZXRJdGVtTGlzdCcsCgkJCQkncGF0aCc6IHBhdGgsCgkJCQknb3B0aW9ucyc6IG9wdGlvbnMKCQkJfSwKCQkJZnVuY3Rpb24ocmVzdWx0KXsKCQkJCWlmKCAhcmVzdWx0LnJlc3VsdCApewoJCQkJCWFsZXJ0KCByZXN1bHQubWVzc2FnZSApOwoJCQkJCXJldHVybjsKCQkJCX0KCgkJCQkvLyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQoJCQkJLy8gUGF0aCBCYXIKCQkJCSRwYXRoQmFyLmlubmVySFRNTCA9ICcnOwoJCQkJdmFyIHRtcEN1cnJlbnRQYXRoID0gJyc7CgkJCQl2YXIgdG1wWkluZGV4ID0gMTAwMDA7CgkJCQl2YXIgYnJlYWRjcnVtYiA9IHBhdGgucmVwbGFjZSgvXlwvKy8sICcnKS5yZXBsYWNlKC9cLyskLywgJycpLnNwbGl0KCcvJyk7CgkJCQl2YXIgJGxpID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnbGknKTsKCQkJCSRsaS5zdHlsZS56SW5kZXggPSB0bXBaSW5kZXg7dG1wWkluZGV4IC0tOwoJCQkJdmFyICRhID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnYScpOwoJCQkJJGEudGV4dENvbnRlbnQgPSAnLyc7CgkJCQkkYS5ocmVmID0gJ2phdmFzY3JpcHQ6Oyc7CgkJCQkkYS5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKCl7CgkJCQkJX3RoaXMuc2V0Q3VycmVudERpciggJy8nICk7CgkJCQl9KTsKCQkJCSRsaS5hcHBlbmQoJGEpOwoJCQkJJHBhdGhCYXIuYXBwZW5kKCRsaSk7CgkJCQlmb3IodmFyIGkgPSAwOyBpIDwgYnJlYWRjcnVtYi5sZW5ndGg7IGkgKyspewoJCQkJCWlmKCAhYnJlYWRjcnVtYltpXS5sZW5ndGggKXsKCQkJCQkJY29udGludWU7CgkJCQkJfQoJCQkJCXZhciAkbGkgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdsaScpOwoJCQkJCSRsaS5zdHlsZS56SW5kZXggPSB0bXBaSW5kZXg7dG1wWkluZGV4IC0tOwoJCQkJCXZhciAkYSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ2EnKTsKCQkJCQkkYS50ZXh0Q29udGVudCA9IGJyZWFkY3J1bWJbaV07CgkJCQkJJGEuaHJlZiA9ICdqYXZhc2NyaXB0OjsnOwoJCQkJCSRhLnNldEF0dHJpYnV0ZSgnZGF0YS1maWxlbmFtZScsIGJyZWFkY3J1bWJbaV0pOwoJCQkJCSRhLnNldEF0dHJpYnV0ZSgnZGF0YS1wYXRoJywgJy8nICsgdG1wQ3VycmVudFBhdGggKyBicmVhZGNydW1iW2ldICsgJy8nKTsKCQkJCQkkYS5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKCl7CgkJCQkJCXZhciB0YXJnZXRQYXRoID0gdGhpcy5nZXRBdHRyaWJ1dGUoJ2RhdGEtcGF0aCcpOwoJCQkJCQlfdGhpcy5zZXRDdXJyZW50RGlyKCB0YXJnZXRQYXRoICk7CgkJCQkJfSk7CgkJCQkJJGxpLmFwcGVuZCgkYSk7CgkJCQkJJHBhdGhCYXIuYXBwZW5kKCRsaSk7CgkJCQkJdG1wQ3VycmVudFBhdGggKz0gYnJlYWRjcnVtYltpXSArICcvJzsKCQkJCX0KCgkJCQkkZWxtLmFwcGVuZCgkcGF0aEJhcik7CgoJCQkJLy8gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KCQkJCS8vIEZpbGUgbGlzdAoJCQkJJGZpbGVMaXN0LmlubmVySFRNTCA9ICcnOwoKCQkJCS8vIHBhcmVudCBkaXJlY3RvcnkKCQkJCWlmKHBhdGggIT0gJy8nICYmIHBhdGgpewoJCQkJCXZhciAkbGkgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdsaScpOwoJCQkJCXZhciAkYSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ2EnKTsKCQkJCQkkYS50ZXh0Q29udGVudCA9ICcuLi8nOwoJCQkJCSRhLmhyZWYgPSAnamF2YXNjcmlwdDo7JzsKCQkJCQkkYS5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKCl7CgkJCQkJCXZhciB0bXBfcGF0aCA9IHBhdGg7CgkJCQkJCXRtcF9wYXRoID0gdG1wX3BhdGgucmVwbGFjZSgvXC8oPzpbXlwvXSpcLz8pJC8sICcvJyk7CgkJCQkJCV90aGlzLnNldEN1cnJlbnREaXIoIHRtcF9wYXRoICk7CgkJCQkJfSk7CgkJCQkJJGxpLmFwcGVuZCgkYSk7CgkJCQkJJGZpbGVMaXN0LmFwcGVuZCgkbGkpOwoJCQkJfQoKCgkJCQkvLyBjb250YWluZWQgZmlsZSBhbmQgZm9sZGVycwoJCQkJZm9yKCB2YXIgaWR4IGluIHJlc3VsdC5saXN0ICl7CgkJCQkJaWYoIGZpbHRlci5sZW5ndGggKXsKCQkJCQkJaWYoIHJlc3VsdC5saXN0W2lkeF0ubmFtZS5zcGxpdChmaWx0ZXIpLmxlbmd0aCA8IDIgKXsKCQkJCQkJCWNvbnRpbnVlOwoJCQkJCQl9CgkJCQkJfQoKCQkJCQl2YXIgJGxpID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnbGknKTsKCQkJCQl2YXIgJGEgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdhJyk7CgkJCQkJJGEudGV4dENvbnRlbnQgPSByZXN1bHQubGlzdFtpZHhdLm5hbWU7CgkJCQkJJGEuaHJlZiA9ICdqYXZhc2NyaXB0OjsnOwoJCQkJCSRhLnNldEF0dHJpYnV0ZSgnZGF0YS1maWxlbmFtZScsIHJlc3VsdC5saXN0W2lkeF0ubmFtZSk7CgkJCQkJJGEuc2V0QXR0cmlidXRlKCdkYXRhLXBhdGgnLCBwYXRoICsgcmVzdWx0Lmxpc3RbaWR4XS5uYW1lKTsKCQkJCQkkc3VibWVudSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ3VsJyk7CgkJCQkJJHN1Ym1lbnUuY2xhc3NMaXN0LmFkZCgncmVtb3RlLWZpbmRlcl9fZmlsZS1saXN0LXN1Ym1lbnUnKTsKCQkJCQlpZihyZXN1bHQubGlzdFtpZHhdLnR5cGUgPT0gJ2RpcicpewoJCQkJCQkkYS50ZXh0Q29udGVudCArPSAnLyc7CgkJCQkJCSRhLmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX2ljby1mb2xkZXInKTsKCQkJCQkJJGEuYWRkRXZlbnRMaXN0ZW5lcignY2xpY2snLCBmdW5jdGlvbihlKXsKCQkJCQkJCXZhciBmaWxlbmFtZSA9IHRoaXMuZ2V0QXR0cmlidXRlKCdkYXRhLWZpbGVuYW1lJyk7CgkJCQkJCQlfdGhpcy5zZXRDdXJyZW50RGlyKCBwYXRoK2ZpbGVuYW1lKycvJyApOwoJCQkJCQl9KTsKCgkJCQkJfWVsc2UgaWYocmVzdWx0Lmxpc3RbaWR4XS50eXBlID09ICdmaWxlJyl7CgkJCQkJCSRhLmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX2ljby1maWxlJyk7CgkJCQkJCSRhLmFkZEV2ZW50TGlzdGVuZXIoJ2NsaWNrJywgZnVuY3Rpb24oZSl7CgkJCQkJCQl2YXIgcGF0aCA9IHRoaXMuZ2V0QXR0cmlidXRlKCdkYXRhLXBhdGgnKTsKCQkJCQkJCV90aGlzLm9wZW4oIHBhdGgsIGZ1bmN0aW9uKHJlcyl7fSApOwoJCQkJCQl9KTsKCQkJCQl9CgoJCQkJCWlmKCAhcmVzdWx0Lmxpc3RbaWR4XS53cml0YWJsZSApewoJCQkJCQkkYS5jbGFzc0xpc3QuYWRkKCdyZW1vdGUtZmluZGVyX19pY28tcmVhZG9ubHknKTsKCQkJCQl9CgoJCQkJCS8vIGNvcHkKCQkJCQkkbWVudSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ2J1dHRvbicpOwoJCQkJCSRtZW51LnRleHRDb250ZW50ID0gJ2NvcHknOwoJCQkJCSRtZW51LmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX2ljby1jb3B5Jyk7CgkJCQkJJG1lbnUuc2V0QXR0cmlidXRlKCdkYXRhLWZpbGVuYW1lJywgcmVzdWx0Lmxpc3RbaWR4XS5uYW1lKTsKCQkJCQkkbWVudS5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKGUpewoJCQkJCQllLnN0b3BQcm9wYWdhdGlvbigpOwoJCQkJCQl2YXIgZmlsZW5hbWUgPSB0aGlzLmdldEF0dHJpYnV0ZSgnZGF0YS1maWxlbmFtZScpOwoJCQkJCQlfdGhpcy5jb3B5KHBhdGgrZmlsZW5hbWUsIGZ1bmN0aW9uKCl7CgkJCQkJCQlfdGhpcy5zZXRDdXJyZW50RGlyKCBwYXRoICk7CgkJCQkJCX0pOwoJCQkJCX0pOwoJCQkJCSRzdWJtZW51TGkgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdsaScpOwoJCQkJCSRzdWJtZW51TGkuYXBwZW5kKCRtZW51KTsKCQkJCQkkc3VibWVudS5hcHBlbmQoJHN1Ym1lbnVMaSk7CgoJCQkJCS8vIHJlbmFtZQoJCQkJCSRtZW51ID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnYnV0dG9uJyk7CgkJCQkJJG1lbnUudGV4dENvbnRlbnQgPSAncmVuYW1lJzsKCQkJCQkkbWVudS5jbGFzc0xpc3QuYWRkKCdyZW1vdGUtZmluZGVyX19pY28tcmVuYW1lJyk7CgkJCQkJJG1lbnUuc2V0QXR0cmlidXRlKCdkYXRhLWZpbGVuYW1lJywgcmVzdWx0Lmxpc3RbaWR4XS5uYW1lKTsKCQkJCQkkbWVudS5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKGUpewoJCQkJCQllLnN0b3BQcm9wYWdhdGlvbigpOwoJCQkJCQl2YXIgZmlsZW5hbWUgPSB0aGlzLmdldEF0dHJpYnV0ZSgnZGF0YS1maWxlbmFtZScpOwoJCQkJCQlfdGhpcy5yZW5hbWUocGF0aCtmaWxlbmFtZSwgZnVuY3Rpb24oKXsKCQkJCQkJCV90aGlzLnNldEN1cnJlbnREaXIoIHBhdGggKTsKCQkJCQkJfSk7CgkJCQkJfSk7CgkJCQkJJHN1Ym1lbnVMaSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ2xpJyk7CgkJCQkJJHN1Ym1lbnVMaS5hcHBlbmQoJG1lbnUpOwoJCQkJCSRzdWJtZW51LmFwcGVuZCgkc3VibWVudUxpKTsKCgkJCQkJLy8gZGVsZXRlCgkJCQkJJG1lbnUgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdidXR0b24nKTsKCQkJCQkkbWVudS50ZXh0Q29udGVudCA9ICdkZWxldGUnOwoJCQkJCSRtZW51LmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX2ljby1kZWxldGUnKTsKCQkJCQkkbWVudS5zZXRBdHRyaWJ1dGUoJ2RhdGEtZmlsZW5hbWUnLCByZXN1bHQubGlzdFtpZHhdLm5hbWUpOwoJCQkJCSRtZW51LmFkZEV2ZW50TGlzdGVuZXIoJ2NsaWNrJywgZnVuY3Rpb24oZSl7CgkJCQkJCWUuc3RvcFByb3BhZ2F0aW9uKCk7CgkJCQkJCXZhciBmaWxlbmFtZSA9IHRoaXMuZ2V0QXR0cmlidXRlKCdkYXRhLWZpbGVuYW1lJyk7CgkJCQkJCV90aGlzLnJlbW92ZShwYXRoK2ZpbGVuYW1lLCBmdW5jdGlvbigpewoJCQkJCQkJX3RoaXMuc2V0Q3VycmVudERpciggcGF0aCApOwoJCQkJCQl9KTsKCQkJCQl9KTsKCQkJCQkkc3VibWVudUxpID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnbGknKTsKCQkJCQkkc3VibWVudUxpLmFwcGVuZCgkbWVudSk7CgkJCQkJJHN1Ym1lbnUuYXBwZW5kKCRzdWJtZW51TGkpOwoKCQkJCQkkYS5hcHBlbmQoJHN1Ym1lbnUpOwoJCQkJCSRsaS5hcHBlbmQoJGEpOwoJCQkJCSRmaWxlTGlzdC5hcHBlbmQoJGxpKTsKCQkJCX0KCQkJCSRlbG0uYXBwZW5kKCRmaWxlTGlzdCk7CgkJCX0KCQkpOwoJCXJldHVybjsKCX0KCgkvKioKCSAqIEZpbmRlcuOCkuWIneacn+WMluOBl+OBvuOBmeOAggoJICovCgl0aGlzLmluaXQgPSBmdW5jdGlvbiggcGF0aCwgb3B0aW9ucywgY2FsbGJhY2sgKXsKCQljdXJyZW50X2RpciA9IHBhdGg7CgkJY2FsbGJhY2sgPSBjYWxsYmFjayB8fCBmdW5jdGlvbigpe307CgoKCQkvLyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQoJCS8vIE1FTlUKCQl2YXIgJHVsTWVudSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ3VsJyk7CgkJJHVsTWVudS5jbGFzc0xpc3QuYWRkKCdyZW1vdGUtZmluZGVyX19tZW51Jyk7CgoJCS8vIGNyZWF0ZSBuZXcgZm9sZGVyCgkJdmFyICRsaSA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoJ2xpJyk7CgkJdmFyICRhID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnYScpOwoJCSRhLnRleHRDb250ZW50ID0gJ05ldyBGb2xkZXInOwoJCSRhLmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX2ljby1uZXctZm9sZGVyJyk7CgkJJGEuaHJlZiA9ICdqYXZhc2NyaXB0OjsnOwoJCSRhLmFkZEV2ZW50TGlzdGVuZXIoJ2NsaWNrJywgZnVuY3Rpb24oKXsKCQkJX3RoaXMubWtkaXIoY3VycmVudF9kaXIsIGZ1bmN0aW9uKCl7CgkJCQlfdGhpcy5zZXRDdXJyZW50RGlyKCBjdXJyZW50X2RpciApOwoJCQl9KTsKCQl9KTsKCQkkbGkuYXBwZW5kKCRhKTsKCQkkdWxNZW51LmFwcGVuZCgkbGkpOwoKCQkvLyBjcmVhdGUgbmV3IGZpbGUKCQl2YXIgJGxpID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnbGknKTsKCQl2YXIgJGEgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdhJyk7CgkJJGEudGV4dENvbnRlbnQgPSAnTmV3IEZpbGUnOwoJCSRhLmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX2ljby1uZXctZmlsZScpOwoJCSRhLmhyZWYgPSAnamF2YXNjcmlwdDo7JzsKCQkkYS5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKCl7CgkJCV90aGlzLm1rZmlsZShjdXJyZW50X2RpciwgZnVuY3Rpb24oKXsKCQkJCV90aGlzLnNldEN1cnJlbnREaXIoIGN1cnJlbnRfZGlyICk7CgkJCX0pOwoJCX0pOwoJCSRsaS5hcHBlbmQoJGEpOwoJCSR1bE1lbnUuYXBwZW5kKCRsaSk7CgoJCS8vIGZpbGUgbmFtZSBmaWx0ZXIKCQl2YXIgJGxpID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnbGknKTsKCQl2YXIgJGlucHV0ID0gZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnaW5wdXQnKTsKCQkkaW5wdXQucGxhY2Vob2xkZXIgPSAnRmlsdGVyLi4uJzsKCQkkaW5wdXQudHlwZSA9ICd0ZXh0JzsKCQkkaW5wdXQudmFsdWUgPSBmaWx0ZXI7CgkJJGlucHV0LmFkZEV2ZW50TGlzdGVuZXIoJ2NoYW5nZScsIGZ1bmN0aW9uKCl7CgkJCWZpbHRlciA9IHRoaXMudmFsdWU7CgkJCV90aGlzLnNldEN1cnJlbnREaXIoIGN1cnJlbnRfZGlyICk7CgkJfSk7CgkJJGxpLmFwcGVuZCgkaW5wdXQpOwoJCSR1bE1lbnUuYXBwZW5kKCRsaSk7CgoJCSRlbG0uYXBwZW5kKCR1bE1lbnUpOwoKCQkvLyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQoJCS8vIFBhdGggQmFyCgkJJHBhdGhCYXIgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCd1bCcpOwoJCSRwYXRoQmFyLmNsYXNzTGlzdC5hZGQoJ3JlbW90ZS1maW5kZXJfX3BhdGgtYmFyJyk7CgoJCSRlbG0uYXBwZW5kKCRwYXRoQmFyKTsKCgkJLy8gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KCQkvLyBGaWxlIGxpc3QKCQkkZmlsZUxpc3QgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCd1bCcpOwoJCSRmaWxlTGlzdC5jbGFzc0xpc3QuYWRkKCdyZW1vdGUtZmluZGVyX19maWxlLWxpc3QnKTsKCgkJJGVsbS5hcHBlbmQoJGZpbGVMaXN0KTsKCgkJdGhpcy5zZXRDdXJyZW50RGlyKHBhdGgsIGNhbGxiYWNrKTsKCX0KfQoKfSx7fV19LHt9LFsxXSk='); }


		return false;
	}

}
?>
