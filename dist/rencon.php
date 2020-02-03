<?php
/* ---------------------
  rencon v0.0.1-alpha.1+dev
  (C)Tomoya Koyanagi
  -- developers preview build @2020-02-03T04:02:16+00:00 --
--------------------- */

$conf = new stdClass();

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

	/**
	 * Constructor
	 */
	public function __construct( $conf ){
		$this->conf = $conf;
	}

	/**
	 * アプリケーションを実行
	 */
	public function execute(){
		header('Content-type: text/html');
		?>
<!doctype html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>rencon</title>
		<meta name="robots" content="nofollow, noindex, noarchive" />
	</head>
	<body>
		<p>rencon</p>
	</body>
</html>
<?php
		exit;
	}
}
?>