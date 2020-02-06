<?php
/**
 * rencon app
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_apps_files_ctrl{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	/**
	 * デフォルトアクション
	 */
	public function index(){
		$this->rencon->theme()->set_h1('ファイルとフォルダ');
		$path_root = $this->rencon->conf()->files_path_root;
		$realpath_root = realpath($path_root);

		if( !is_dir( $realpath_root ) ){
			echo $this->rencon->theme()->bind(
				'<p>ルートディレクトリ '.htmlspecialchars($path_root).' は存在しないか、ディレクトリではありません。</p>'
			);
			exit;
		}

		echo $this->rencon->theme()->bind(
			$this->rencon->view()->bind()
		);
		exit;
	}

	/**
	 * remoteFinder GPI
	 */
	public function rfgpi(){
		$path_root = $this->rencon->conf()->files_path_root;
		$realpath_root = realpath($path_root);

		$remoteFinder = new rencon_vendor_tomk79_remoteFinder_main(array(
			'default' => $realpath_root,
		), array(
			'paths_invisible' => $this->rencon->conf()->files_paths_invisible,
			'paths_readonly' => $this->rencon->conf()->files_paths_readonly,
		));
		$value = $remoteFinder->gpi( json_decode( $_REQUEST['data'] ) );
		header('Content-type: text/json');
		echo json_encode($value);
		exit;
	}

}
?>
