<?php
require_once(__DIR__.'/../vendor/autoload.php');
$builder = new builder();
$builder->run();
exit;

class builder{
	private $realpath_proj_src;
	private $realpath_dist;
	private $composerJson;

	/**
	 * constructor
	 */
	public function __construct(){
		$this->realpath_proj_src = realpath(__DIR__.'/../src/').'/';
		if( !is_dir(__DIR__.'/../dist/') ){
			echo '[ERROR] dist directory is not exists.'."\n";
			exit(40);
		}
		if( !is_writable(__DIR__.'/../dist/') ){
			echo '[ERROR] dist directory is not writable.'."\n";
			exit(41);
		}
		$this->realpath_dist = realpath(__DIR__.'/../dist/').'/';

		$this->composerJson = json_decode(file_get_contents( __DIR__.'/../composer.json' ));
	}

	/**
	 * ビルドを実行する
	 */
	public function run(){
		echo '--------------'."\n";
		echo 'rencon build'."\n";
		echo 'dist to: '.$this->realpath_dist."\n";
		echo 'Version: '.$this->composerJson->version."\n";
		echo '--------------'."\n";

		$this->cleanup();

		$this->initialize_rencon();
		$this->append_src_file( 'main.php' );

		echo ''."\n";
		echo ''."\n";
		echo ''."\n";

		echo 'finished.'."\n";
		echo ''."\n";
	}

	private function cleanup(){
		$list = scandir($this->realpath_dist);
		// var_dump($list);
		foreach( $list as $basename ){
			if( $basename == '.' || $basename == '..' ){ continue; }
			if( is_file( $this->realpath_dist.$basename ) ){
				if(!unlink( $this->realpath_dist.$basename )){
					trigger_error( 'Unable to remove file: '.$basename );
				}
			}
		}
		return true;
	}

	private function initialize_rencon(){
		touch($this->realpath_dist.'rencon.php');
		$this->append('<'.'?php'."\n");
		$this->append('/'.'* ---------------------'."\n");
		$this->append('  rencon v'.$this->composerJson->version."\n");
		$this->append('  (C)Tomoya Koyanagi'."\n");
		if( preg_match( '/\+dev$/s', $this->composerJson->version) ){
			$this->append('  -- developers preview build @'.date('c').' --'."\n");
		}
		$this->append('--------------------- *'.'/'."\n");
		$this->append('?'.'>');
	}

	private function append_src_file( $path ){
		$code = file_get_contents( $this->realpath_proj_src.$path );
		return $this->append($code);
	}

	private function append( $code ){
		return file_put_contents($this->realpath_dist.'rencon.php', $code, FILE_APPEND|LOCK_EX);
	}
}
