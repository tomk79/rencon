<?php
/**
 * rencon core class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon{
	private $conf;
	private $theme;
	private $resourceMgr;

	/**
	 * Constructor
	 */
	public function __construct( $conf ){
		$this->conf = $conf;
		$this->theme = new rencon_theme($this);
		$this->resourceMgr = new rencon_resourceMgr($this);
		$this->request = new rencon_request();
	}

	/**
	 * アプリケーションを実行
	 */
	public function execute(){

		$action = $this->request->get_param('a');
		$action_ary = explode( '.', $action );
		// var_dump($action_ary);

		if( strlen( $this->request->get_param('res') ) ){
			$this->resourceMgr->echo_resource( $this->request->get_param('res') );
			return;
		}

		header('Content-type: text/html');
		$this->theme->set_h1('ホーム');
		echo $this->theme->bind('<p>ホーム画面</p>');
		exit;
	}
}
?>
