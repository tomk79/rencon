<?php
/**
 * rencon conf class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_conf{
	private $conf;
	public $users;
	public $disabled;
	public $databases;
	public $files_paths_invisible;
	public $files_paths_readonly;

	/**
	 * Constructor
	 */
	public function __construct( $conf ){
		$this->conf = (object) $conf;

		// --------------------------------------
		// $conf->users
		$this->users = null;
		if( property_exists( $conf, 'users' ) && !is_null( $conf->users ) ){
			$this->users = (array) $conf->users;
		}

		// --------------------------------------
		// $conf->disabled
		$this->disabled = array();
		if( property_exists( $conf, 'disabled' ) && !is_null( $conf->disabled ) ){
			$this->disabled = (array) $conf->disabled;
		}

		// --------------------------------------
		// $conf->databases
		$this->databases = null;
		if( property_exists( $conf, 'databases' ) && !is_null( $conf->databases ) ){
			$this->databases = (array) $conf->databases;
		}

		// --------------------------------------
		// $conf->files_paths_invisible
		$this->files_paths_invisible = null;
		if( property_exists( $conf, 'files_paths_invisible' ) && !is_null( $conf->files_paths_invisible ) ){
			$this->files_paths_invisible = (array) $conf->files_paths_invisible;
		}

		// --------------------------------------
		// $conf->files_paths_readonly
		$this->files_paths_readonly = null;
		if( property_exists( $conf, 'files_paths_readonly' ) && !is_null( $conf->files_paths_readonly ) ){
			$this->files_paths_readonly = (array) $conf->files_paths_readonly;
		}

	}

	/**
	 * ログインが必要か？
	 */
	public function is_login_required(){
		if( !is_array($this->users) ){
			return false;
		}
		return true;
	}

}
?>
