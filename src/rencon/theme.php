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
		$action_ary = explode('.', $this->rencon->req()->get_param('a'));
		if( !is_array($action_ary) || !count($action_ary) ){
			$action_ary[0] = '';
		}
		$class_active['active'] = $action_ary[0];
		ob_start(); ?>
<!doctype html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>rencon</title>
		<meta name="robots" content="nofollow, noindex, noarchive" />
		<script src="?res=jquery/jquery-3.4.1.min.js"></script>
		<link rel="stylesheet" href="?res=bootstrap4/css/bootstrap.min.css" />
		<script src="?res=bootstrap4/js/bootstrap.min.js"></script>
		<link rel="stylesheet" href="?res=styles/common.css" />
	</head>
	<body>
		<nav class="navbar fixed-top navbar-expand-md navbar-dark bg-dark">
			<a href="<?= htmlspecialchars($this->rencon->href()); ?>" class="navbar-brand">rencon</a>
			<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>

			<div class="collapse navbar-collapse" id="navbarSupportedContent">
				<ul class="navbar-nav mr-auto">
					<li class="nav-item <?= array_search('', $class_active) ?>">
						<a class="nav-link" href="<?= htmlspecialchars($this->rencon->href()); ?>">Home <span class="sr-only">(current)</span></a>
					</li>
					<li class="nav-item <?= array_search('databases', $class_active) ?>">
						<a class="nav-link" href="?a=databases">Databases</a>
					</li>
					<li class="nav-item <?= array_search('files', $class_active) ?>">
						<a class="nav-link" href="?a=files">Files</a>
					</li>
				</ul>
			</div>
		</nav>
		<div class="theme-main-block">
			<div class="container">
				<h1><?= htmlspecialchars( nl2br( $this->h1 ) ); ?></h1>
				<div class="theme-main-area">
<div class="contents">
<?= $content ?>
</div>

				</div>
			</div>
		</div>
		<div class="theme-footer-block">
			<div class="container">
<?php if( $this->rencon->conf()->is_login_required() ){ ?>
				<nav class="navbar navbar-expand-sm navbar-light bg-light">
					<div>
						<ul class="navbar-nav">
							<li><a href="?a=logout" class="nav-link">Logout</a></li>
						</ul>
					</div>
				</nav>
<?php } ?>
				<p class="text-center">
					PHP v<?= htmlspecialchars(phpversion()) ?>
				</p>
				<p class="text-center">
					<a href="https://github.com/tomk79/rencon" target="_blank">rencon on Github</a>
				</p>
				<p class="text-center">
					&copy; Tomoya Koyanagi.
				</p>
			</div>
		</div>
	</body>
</html>
<?php
		$rtn = ob_get_clean();
		return $rtn;
	}
}
?>
