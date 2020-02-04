<?php
/**
 * rencon dbh class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_dbh{
	private $pdo;
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * PDOが有効か調べる
	 */
	public function is_pdo_enabled(){
		if( !class_exists('\\PDO') ){
			return false;
		}
		return true;
	}

	/**
	 * dbkey から dbinfo を取得する
	 */
	public function get_dbinfo_by_key( $dbkey ){
		$conf = $this->rencon->conf();
		$dbinfo = false;
		if( strlen($dbkey) && array_key_exists($dbkey, $conf->databases) ){
			$dbinfo = $conf->databases[$dbkey];
		}
		return $dbinfo;
	}

	/**
	 * DSNを生成する
	 */
	public function get_dsn_info_by_dbinfo( $dbinfo ){
		$dbinfo = (array) $dbinfo;
		$rtn = array(
			'dsn' => '',
			'username' => '',
			'password' => '',
			'options' => array(),
		);
		if( array_key_exists( 'username', $dbinfo ) ){
			$rtn['username'] = $dbinfo['username'];
		}
		if( array_key_exists( 'password', $dbinfo ) ){
			$rtn['password'] = $dbinfo['password'];
		}
		if( array_key_exists( 'options', $dbinfo ) ){
			$rtn['options'] = $dbinfo['options'];
		}

		if( array_key_exists( 'dsn', $dbinfo ) ){
			$rtn['dsn'] = $dbinfo['dsn'];
		}elseif( array_key_exists( 'driver', $dbinfo ) ){
			$rtn['dsn'] = strtolower($dbinfo['driver']).':';
			switch(strtolower($dbinfo['driver'])){
				case 'sqlite':
					$rtn['dsn'] .= $dbinfo['database'];
					break;
				case 'mysql':
				case 'pgsql':
					$array_dsns = array();
					if( array_key_exists( 'host', $dbinfo ) && strlen($dbinfo['host']) ){
						$array_dsns[] = 'host='.$dbinfo['host'];
					}
					if( array_key_exists( 'port', $dbinfo ) && strlen($dbinfo['port']) ){
						$array_dsns[] = 'port='.$dbinfo['port'];
					}
					if( array_key_exists( 'database', $dbinfo ) && strlen($dbinfo['database']) ){
						$array_dsns[] = 'dbname='.$dbinfo['database'];
					}
					if( array_key_exists( 'username', $dbinfo ) && strlen($dbinfo['username']) ){
						$array_dsns[] = 'user='.$dbinfo['username'];
					}
					if( array_key_exists( 'password', $dbinfo ) && strlen($dbinfo['password']) ){
						$array_dsns[] = 'password='.$dbinfo['password'];
					}
					$rtn['dsn'] .= implode(';', $array_dsns);
					break;
			}
		}
		return $rtn;
	}

	/**
	 * データベースに接続する
	 */
	public function connect( $dbkey ){
		$dbinfo = $this->get_dbinfo_by_key($dbkey);
		$dsn = $this->get_dsn_info_by_dbinfo($dbinfo);
		$this->pdo = new \PDO(
			$dsn['dsn'],
			$dsn['username'],
			$dsn['password'],
			$dsn['options']
		);
		return true;
	}

	/**
	 * PDO に直接アクセスする
	 */
	public function pdo(){
		return $this->pdo;
	}

	/**
	 * PDO::getAvailableDrivers()
	 */
	public function get_available_drivers(){
		if( !$this->is_pdo_enabled() ){
			return false;
		}
		return \PDO::getAvailableDrivers();
	}
}
?>
