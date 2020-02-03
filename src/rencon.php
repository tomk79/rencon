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

		}elseif( !strlen($action) ){
			header('Content-type: text/html');
			$this->theme->set_h1('ホーム');
			echo $this->theme->bind('<p>ホーム画面</p>');
			exit;
		}

		$this->notfound();
		exit;
	}


	/**
	 * Not Found 画面を出力
	 */
	public function notfound(){
		header('HTTP/1.1 404 Not Found');
		header('Content-type: text/html');
		$this->theme->set_h1('Not Found');
		echo $this->theme->bind('<p>お探しの画面はありません。<a href="?">戻る</a></p>');
		exit;
	}

	/**
	 * Forbidden 画面を出力
	 */
	public function forbidden(){
		header('HTTP/1.1 403 Forbidden');
		header('Content-type: text/html');
		$this->theme->set_h1('Forbidden');
		echo $this->theme->bind('<p>ログインしてください。<a href="?a='.urlencode($this->action()).'">戻る</a></p>');
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
