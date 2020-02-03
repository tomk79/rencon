<?php
/**
 * rencon views class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_view{
	private $rencon;
	private $values = array();

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * 値をセット
	 */
	public function set($key, $val){
		$this->values[$key] = $val;
		return true;
	}

	/**
	 * 値を取得
	 */
	public function get($key){
		if( !array_key_exists($key, $this->values) ){
			return false;
		}
		return $this->values[$key];
	}

	/**
	 * ビューに値をバインド
	 */
	public function bind( $action = null ){
		if(!strlen( $action )){
			$action = $this->rencon->action();
		}
		$action_ary = explode('.', $action);
		$app = null;
		$act = null;

		if( array_key_exists(0, $action_ary) ){
			$app = $action_ary[0];
		}
		if( array_key_exists(1, $action_ary) ){
			$act = $action_ary[1];
		}
		if( !strlen($act) ){
			$act = 'index';
		}

/** {$viewList} **/

		return false;
	}

}
?>
