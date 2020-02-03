<?php
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
