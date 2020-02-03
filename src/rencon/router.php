<?php
/**
 * rencon router class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_router{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * ルーティング
	 */
	public function route(){
		$action = $this->rencon->action();
		$action_ary = explode('.', $action);

		$app = $action_ary[0];
		$act = $action_ary[1];

		if( !strlen($app) ){
			return false;
		}

		$className = 'rencon_apps_'.$app.'_ctrl';
		if( class_exists( $className ) ){
			$appObj = new $className( $this->rencon );
			if( method_exists($appObj, $act) ){
				return $appObj->$act();
			}
		}

		return false;
	}

}
?>
