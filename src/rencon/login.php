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
		$conf = $this->rencon->conf();
		$users = null;
		if( property_exists( $conf, 'users' ) ){
			$users = (array) $conf->users;
		}
		if( !is_array( $users ) ){
			return true;
		}

		$login_id = $this->rencon->req()->get_param('login_id');
		$login_pw = $this->rencon->req()->get_param('login_pw');
		$login_try = $this->rencon->req()->get_param('login_try');
		if( strlen( $login_try ) && strlen($login_id) && strlen($login_pw) ){
			// ログイン評価
			if( array_key_exists($login_id, $users) && $users[$login_id] == sha1($login_pw) ){
				$this->rencon->req()->set_session('login_id', $login_id);
				$this->rencon->req()->set_session('login_pw', sha1($login_pw));
				return true;
			}
		}


		$login_id = $this->rencon->req()->get_session('login_id');
		$login_pw_hash = $this->rencon->req()->get_session('login_pw');
		if( strlen($login_id) && strlen($login_pw_hash) ){
			// ログイン済みか評価
			if( array_key_exists($login_id, $users) && $users[$login_id] == $login_pw_hash ){
				return true;
			}
			$this->rencon->req()->delete_session('login_id');
			$this->rencon->req()->delete_session('login_pw');
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
		$this->rencon->req()->delete_session('login_id');
		$this->rencon->req()->delete_session('login_pw');

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
