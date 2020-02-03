<?php
/**
 * test for tomk79/rencon
 */
class mainTest extends PHPUnit_Framework_TestCase{
	private $fs;

	public function setup(){
		mb_internal_encoding('UTF-8');
		require_once(__DIR__.'/libs/simple_html_dom.php');

		require_once(__DIR__.'/libs/server_setup.php');
		test_helper_server_setup();
	}


	/**
	 * テスト
	 */
	public function testStandard(){
		$this->assertEquals( 1, 1 );
	}

}
