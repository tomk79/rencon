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
	private $request;
	private $view;

	/**
	 * Constructor
	 */
	public function __construct( $conf ){
		$this->conf = new rencon_conf( $conf );
		$this->theme = new rencon_theme($this);
		$this->resourceMgr = new rencon_resourceMgr($this);
		$this->request = new rencon_request();
		$this->view = new rencon_view($this);
	}

	/**
	 * アプリケーションを実行
	 */
	public function execute(){
		if( strlen( $this->request->get_param('res') ) ){
			$this->resourceMgr->echo_resource( $this->request->get_param('res') );
			return;
		}

		header('Content-type: text/html'); // default

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
			$this->theme->set_h1('ホーム');
			ob_start(); ?>
<ul>
	<li><a href="?a=db">データベース管理</a></li>
	<li><a href="?a=files">ファイルとフォルダ</a></li>
</ul>
<?php
			echo $this->theme->bind( ob_get_clean() );
			exit;
		}

		$router = new rencon_router($this);
		$router->route();

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
		if( !strlen($action) ){
			return '';
		}
		$action_ary = explode('.', $action);
		if( count($action_ary) == 1 ){
			$action_ary[1] = 'index';
		}elseif( array_key_exists(1, $action_ary) && !strlen($action_ary[1]) ){
			$action_ary[1] = 'index';
		}
		return implode('.', $action_ary);
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

	/**
	 * Theme Object
	 */
	public function theme(){
		return $this->theme;
	}

	/**
	 * View Object
	 */
	public function view(){
		return $this->view;
	}
}
?>
