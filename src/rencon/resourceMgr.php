<?php
/**
 * rencon resourceMgr class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class rencon_resourceMgr{
	private $rencon;

	/**
	 * Constructor
	 */
	public function __construct( $rencon ){
		$this->rencon = $rencon;
	}

	public function echo_resource( $path ){
		$ext = null;
		if( preg_match('/\.([a-zA-Z0-9\_\-]*)$/', $path, $matched) ){
			$ext = $matched[1];
			$ext = strtolower($ext);
			$mime = $this->get_mime_type($ext);
			if( !$mime ){ $mime = 'text/html'; }
			header('Content-type: '.$mime);
		}
		echo $this->get($path);
		exit;
	}

	/**
	 * リソースを取得
	 */
	public function get( $path ){
		$path = preg_replace( '/$(?:\/*|\.\.?\/)*/', '', $path );

/** {$resourceList} **/

		return false;
	}

	/**
	 * 拡張子から mime-type を得る
	 */
	public function get_mime_type($ext){
		switch( $ext ){
			case 'html':
			case 'htm':
				return 'text/html';
				break;
			case 'js':
				return 'text/javascript';
				break;
			case 'css':
			case 'scss':
				return 'text/css';
				break;
			case 'gif':
				return 'image/gif';
				break;
			case 'png':
				return 'image/png';
				break;
			case 'jpg':
			case 'jpeg':
			case 'jpe':
				return 'image/jpeg';
				break;
			case 'svg':
				return 'image/svg+xml ';
				break;
			case 'text':
			case 'txt':
			case 'log':
			case 'sh':
			case 'bat':
			case 'php':
			case 'json':
			case 'yml':
			case 'yml':
			case 'htaccess':
				return 'text/plain';
				break;
		}
		return false;
	}

}
?>
