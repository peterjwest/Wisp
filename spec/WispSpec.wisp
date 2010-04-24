<?php

class TestToken
	function value($value = null) $this->value = $value
	function transform($tokens) $tokens->tokensCalled[] = get_class($this)
	
class TestListToken extends TestToken
	function addInner($token) $this->innerToken = $token
	
class SomeToken extends TestToken
class SomeListToken extends TestListToken

class WispTokeniserSpec extends PweSpec
	function beforeEach() 
		$this->token = new TestToken
		$this->listToken = new TestListToken
		$this->star = new WispTokeniser($this->token, $this->listToken)
	
	function _shouldGetSameClass()
		_($this->star->getRootClass("TestToken"))->shouldBe("TestToken")
	
	function _shouldGetParentClass()
		_($this->star->getRootClass("SomeToken"))->shouldBe("TestToken")
		
	function _shouldGetParentParentClass()
		_($this->star->getRootClass("SomeListToken"))->shouldBe("TestToken")
	
	function _shouldRunTokens()
		$file = "<?php echo 'foo';"
		$this->star->transform($file)
		_($this->listToken->tokensCalled)->shouldBe(array("TestToken", "TestListToken", "SomeToken", "SomeListToken"))
		_($this->listToken->innerToken)->shouldBe($this->token)
		_($this->token->value)->shouldBe($file)
	
class WispFileHandlerSpec extends PweSpec 
	function beforeEach() $this->star = new WispFileHandler
	
	function _shouldCreateDir()
		$dir = "WispTestDir"
		$this->star->createDir($dir)
		_(file_exists($dir))->shouldBe(true)
		_(is_dir($dir))->shouldBe(true)
		rmdir($dir)
		
	function _shouldCreateInnerDir()
		$dir = "WispTestDir"
		$innerDir = "InnerDir"
		$this->star->createDir($dir."/".$innerDir)
		_(file_exists($dir."/".$innerDir))->shouldBe(true)
		_(is_dir($dir."/".$innerDir))->shouldBe(true)
		rmdir($dir."/".$innerDir)
		rmdir($dir)
		
	function _createExistingDirShouldNotThrow()
		$dir = "WispTestDir"
		mkdir($dir, 0777, true)
		_(rescue(array($this->star, 'createDir'), array($dir)))->shouldBe(null)
		rmdir($dir)
	
	function _shouldBeNewerThan()
		$file1 = "WispTestFile1.php"
		$file2 = "WispTestFile2.php"
		file_put_contents($file1, "foo")
		sleep(1)
		file_put_contents($file2, "foo")
		_($this->star->newerThan($file2, $file1))->shouldBe(true)
		unlink($file1)
		unlink($file2)
	
	function _shouldNotBeNewerThan()
		$file1 = "WispTestFile1.php"
		$file2 = "WispTestFile2.php"
		file_put_contents($file1, "foo")
		sleep(1)
		file_put_contents($file2, "foo")
		_($this->star->newerThan($file1, $file2))->shouldBe(false)
		unlink($file1)
		unlink($file2)
	
	function _shouldNormaliseNewlines()
		_($this->star->normaliseNewlines("foo\rbar\nzim\r\ngir\n\r"))->shouldBe("foo\nbar\nzim\ngir\n\n")
		
	function _shouldGetDirFiles()
		$dir = "WispTestDir"
		$files = array($dir."/WispTestFile1.php", $dir."/WispTestFile2.php", $dir."/WispTestFile3.php")
		mkdir($dir, 0777, true)
		foreach($files as $file) file_put_contents($file, "foo")
		_($this->star->getDirFiles($dir))->shouldBe($files)
		foreach($files as $file) unlink($file)
		rmdir($dir)
		
	function _shouldGetEmptyDir()
		$dir = "WispTestDir"
		mkdir($dir, 0777, true)
		_($this->star->getDirFiles($dir))->shouldBe(array())
		rmdir($dir)
		
	function _nonExistantDirShouldThrow()
		_(rescue(array($this->star, 'getDirFiles'), array("WispTestDir")))->shouldBeInstanceOf("Exception")
		
	function _shouldSaveNewFile()
		$filename = 'WispTestFile.php'
		$contents = "<?php echo 'foo';"
		$this->star->saveFile($filename, $contents)
		_(file_get_contents($filename))->shouldBe($contents)
		unlink($filename)
	
	function _shouldSaveExistingFile()
		$filename = 'WispTestFile.php'
		$contents = "<?php echo 'foo';"
		file_put_contents($filename, "<?php echo '';")
		$this->star->saveFile($filename, $contents)
		_(file_get_contents($filename))->shouldBe($contents)
		unlink($filename)
		
	function _shouldSaveFileInExistingDir()
		$dir = "WispTestDir"
		$filename = 'WispTestFile.php'
		$contents = "<?php echo 'foo';"
		mkdir($dir, 0777, true)
		$this->star->saveFile($dir."/".$filename, $contents)
		_(file_get_contents($dir."/".$filename))->shouldBe($contents)
		unlink($dir."/".$filename)
		rmdir($dir)
		
	function _shouldSaveFileInNonExistantDir()
		$dir = "WispTestDir"
		$filename = 'WispTestFile.php'
		$contents = "<?php echo 'foo';"
		$this->star->saveFile($dir."/".$filename, $contents)
		_(file_get_contents($dir."/".$filename))->shouldBe($contents)
		unlink($dir."/".$filename)
		rmdir($dir)

with(new PweRunner)->runDefined();