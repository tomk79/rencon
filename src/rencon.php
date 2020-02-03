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
		$this->conf = (object) $conf;
		$this->theme = new rencon_theme($this);
		$this->resourceMgr = new rencon_resourceMgr($this);
		$this->request = new rencon_request();
	}

	/**
	 * アプリケーションを実行
	 */
	public function execute(){
		if( strlen( $this->request->get_param('res') ) ){
			$this->resourceMgr->echo_resource( $this->request->get_param('res') );
			return;
		}

		$login = new rencon_login($this);
		if( !$login->check() ){
			$login->please_login();
			exit;
		}

		$action = $this->request->get_param('a');
		$action_ary = explode( '.', $action );
		// var_dump($action_ary);

		if( $action == 'logout' ){
			$login->logout();
			exit;
		}

		header('Content-type: text/html');
		$this->theme->set_h1('ホーム');
		echo $this->theme->bind('<p>ホーム画面</p>');
		exit;
	}


	/**
	 * 現在のアクションを返す
	 */
	public function action(){
		$action = $this->request->get_param('a');
		return $action;
	}

	/**
	 * リンク先を返す
	 */
	public function href( $action = '' ){
		if( is_array( $action ) ){
			$action = implode('.', $action);
		}
		return '?a='.urlencode($action);
	}

	/**
	 * Config Object
	 */
	public function conf(){
		return $this->conf;
	}

	/**
	 * Request Object
	 */
	public function req(){
		return $this->request;
	}
}
?>
