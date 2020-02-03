<?php
/**
 * rencon app
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_apps_db_ctrl{
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
		$this->rencon->theme()->set_h1('データベース管理');
		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

}
?>
