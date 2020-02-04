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
			switch( $ext ){
				case 'html': case 'htm': header('Content-type: text/html'); break;
				case 'js': header('Content-type: text/javascript'); break;
				case 'css': header('Content-type: text/css'); break;
				case 'jpg': case 'jpe': case 'jpeg': header('Content-type: image/jpeg'); break;
				case 'gif': header('Content-type: image/gif'); break;
				case 'png': header('Content-type: image/png'); break;
				case 'svg': header('Content-type: image/svg+xml'); break;
			}
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

}
?>
