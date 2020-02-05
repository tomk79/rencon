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
			'paths_invisible' => array(
			),
			'paths_readonly' => array(
				'/*',
			),
		));
		$value = $remoteFinder->gpi( json_decode( $_REQUEST['data'] ) );
		header('Content-type: text/json');
		echo json_encode($value);
		exit;
	}

}
?>
