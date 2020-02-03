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
		$this->rencon->view()->set('name', 'value');

		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

}
?>
