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

		$disabled = $this->rencon->conf()->disabled;
		if( array_search($app, $disabled) !== false ){
			$this->rencon->theme()->set_h1($app);
			ob_start(); ?>
<p>この機能 <code><?= htmlspecialchars($app) ?></code> は、利用が制限されています。</p>
<p>設定を編集して、制限を解除することができます。</p>
<?php
			$code = ob_get_clean();
			echo $this->rencon->theme()->bind( $code );
			exit;
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
