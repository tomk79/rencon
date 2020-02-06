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

		$sql = $this->rencon->req()->get_param('db_sql');
		$sql = trim($sql);
		$result = null;
		$affectedRows = 0;
		if( strlen($sql) ){
			$sth = $this->dbh->pdo()->query($sql);
			if($sth){
				$result = $sth->fetchAll(PDO::FETCH_ASSOC);
				$affectedRows = $sth->rowCount();
			}
		}
		$this->rencon->view()->set('result', $result);
		$this->rencon->view()->set('affectedRows', $affectedRows);

		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

}
?>
