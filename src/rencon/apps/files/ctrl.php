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

	/**
	 * ファイルを取得
	 */
	public function getfile(){
		header('Content-type: text/json');
		$path_root = $this->rencon->conf()->files_path_root;
		$realpath_root = realpath($path_root);

		$path_file = $_REQUEST['path_file'];
		$value = array(
			'result' => null,
			'message' => null,
			'basename' => null,
			'ext' => null,
			'mime' => null,
			'base64' => null,
		);

		$realpath_file = realpath($realpath_root.'/'.$path_file);
		if( $realpath_file === false ){
			$value['result'] = false;
			$value['message'] = 'File not found.'.$path_file;
			echo json_encode($value);
			exit;
		}
		if( !is_file($realpath_file) ){
			$value['result'] = false;
			$value['message'] = 'It is not a file.';
			echo json_encode($value);
			exit;
		}
		if( !is_readable($realpath_file) ){
			$value['result'] = false;
			$value['message'] = 'It is not readable.';
			echo json_encode($value);
			exit;
		}

		$value['basename'] = basename( $path_file );
		$value['base64'] = base64_encode( file_get_contents( $realpath_file ) );
		if( preg_match( '/^.*\.([a-zA-Z0-9\_\-]*?)$/si', $realpath_file, $matched ) ){
			$value['ext'] = strtolower($matched[1]);
		}
		$value['mime'] = $this->rencon->resourceMgr()->get_mime_type( $value['ext'] );
		$value['result'] = true;
		$value['message'] = 'OK';

		header('Content-type: text/json');
		echo json_encode($value);
		exit;
	}

}
?>
