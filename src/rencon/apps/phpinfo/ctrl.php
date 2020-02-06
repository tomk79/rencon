<?php
/**
 * rencon app
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_apps_phpinfo_ctrl{
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
		$this->rencon->theme()->set_h1('phpinfo');
		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

	/**
	 * show
	 */
	public function show(){
        phpinfo();
        exit;
	}

}
?>
