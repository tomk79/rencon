<?php
/**
 * rencon app
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_apps_databases_ctrl{
	private $rencon;
	private $dbh;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
		$this->dbh = new rencon_dbh($this->rencon);

		$rencon->view()->set('dbh', $this->dbh);
		$rencon->theme()->set_h1('データベース管理');
	}

	/**
	 * デフォルトアクション
	 */
	public function index(){
		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

	/**
	 * テーブル一覧
	 */
	public function tables(){
		$conf = $this->rencon->conf();
		$dbkey = $this->rencon->req()->get_param('dbkey');
		$dbinfo = $this->dbh->get_dbinfo_by_key( $dbkey );
		$this->rencon->view()->set('dbkey', $dbkey);
		$this->rencon->view()->set('dbinfo', $dbinfo);
		if( $dbinfo ){
			$this->dbh->connect($dbkey);
		}

		$pdo = $this->dbh->pdo();
		if( !is_object($pdo) ){
			echo $this->rencon->theme()->bind(
				'<p>DB接続に失敗しました。</p>'
			);
			exit;
		}

		$this->rencon->view()->set('pdo_driver_name', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
		$this->rencon->view()->set('pdo_client_version', $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION));
		if( $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ){
			$this->rencon->view()->set('pdo_server_info', $pdo->getAttribute(PDO::ATTR_SERVER_INFO));
		}
		$this->rencon->view()->set('pdo_server_version', $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

		$sql = $this->rencon->req()->get_param('db_sql');
		$sql = trim($sql);
		$result = null;
		$affectedRows = 0;
		$lastInsertId = null;
		$sthError = null;
		if( strlen($sql) ){
			$sth = $this->dbh->pdo()->query($sql);
			if($sth){
				$result = $sth->fetchAll(PDO::FETCH_ASSOC);
				if( preg_match( '/^INSERT\s/si', trim($sql) ) ){
					$lastInsertId = $pdo->lastInsertId();
					if( $lastInsertId === false || $lastInsertId === "0" ){
						$lastInsertId = null;
					}
				}
				$affectedRows = $sth->rowCount();
				$sthError = $sth->errorInfo();
			}
		}
		$this->rencon->view()->set('result', $result);
		$this->rencon->view()->set('affectedRows', $affectedRows);
		$this->rencon->view()->set('lastInsertId', $lastInsertId);
		$this->rencon->view()->set('pdo_error_info', $pdo->errorInfo());
		$this->rencon->view()->set('pdo_sth_error_info', $sthError);
		$this->rencon->view()->set('pdo', $pdo);

		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

}
?>
