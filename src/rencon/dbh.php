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
	 * データベースに接続する
	 */
	public function connect( $database_setting ){
		$this->pdo = new \PDO(
			'sqlite:'.'./database.sqlite',
			null, null,
			array(
				\PDO::ATTR_PERSISTENT => false, // ←これをtrueにすると、"持続的な接続" になる
			)
		);
	}
}
?>
