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
	</head>
	<body>
		<p>rencon</p>

<h1><?= htmlspecialchars( nl2br( $this->h1 ) ); ?></h1>
<div class="contents">
<?= $content ?>
</div>

	</body>
</html>
<?php
		$rtn = ob_get_clean();
		return $rtn;
	}
}
?>
