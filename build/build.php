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
		$this->append_src_file( 'rencon.php' );
		$this->append_src_file( 'rencon/theme.php' );
		$this->append_src_file( 'rencon/login.php' );
		$this->append_src_file( 'rencon/request.php' );
		$this->append_resourceMgr();

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
		$this->append_src('<'.'?php'."\n");
		$this->append_src('/'.'* ---------------------'."\n");
		$this->append_src('  rencon v'.$this->composerJson->version."\n");
		$this->append_src('  (C)Tomoya Koyanagi'."\n");
		if( preg_match( '/\+dev$/s', $this->composerJson->version) ){
			$this->append_src('  -- developers preview build @'.date('c').' --'."\n");
		}
		$this->append_src('--------------------- *'.'/'."\n");
		$this->append_src(''."\n");
		$this->append_src('$conf = new stdClass();'."\n");
		$this->append_src(''."\n");
		$this->append_src('$conf->users = array('."\n");
		$this->append_src('	"admin" => sha1("admin"),'."\n");
		$this->append_src(');'."\n");
		$this->append_src(''."\n");
		$this->append_src('$rencon = new rencon($conf);'."\n");
		$this->append_src('$rencon->execute();'."\n");
		$this->append_src('exit;'."\n");
		$this->append_src(''."\n");
		$this->append_src('?'.'>');
	}

	private function append_resourceMgr(){
		$code = file_get_contents( $this->realpath_proj_src.'rencon/resourceMgr.php' );

		$metakeyword = '/** {$resourceList} **/';

		$realpath_target_dir = realpath( __DIR__.'/../resources/' ).'/';
		$finder = new \Symfony\Component\Finder\Finder();
		$iterator = $finder
			->in($realpath_target_dir)
			->name('*')
			->files();

		$list = array();
		foreach ($iterator as $fileinfo) {
			$realpath = $fileinfo->getPathname();
			$localpath = preg_replace( '/^'.preg_quote($realpath_target_dir, '/').'/', '', $realpath );
			$base64 = base64_encode( file_get_contents($realpath) );
			$list[] = 'if($path == '.var_export($localpath, true).'){ return base64_decode('.var_export($base64, true).'); }'."\n";
		}
		// var_dump($list);

		$code = str_replace($metakeyword, implode("", $list), $code);

		return $this->append_src($code);
	}

	private function append_src_file( $path ){
		$code = file_get_contents( $this->realpath_proj_src.$path );
		return $this->append_src($code);
	}

	private function append_src( $code ){
		return file_put_contents($this->realpath_dist.'rencon.php', $code, FILE_APPEND|LOCK_EX);
	}
}
