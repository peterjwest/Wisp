<?php
class ExampleClass
	function exampleFunction()
		$exampleVar = true
		if ($exampleVar)
			$exampleVar = false
			while (!$exampleVar) $exampleVar = true
		return $exampleVar

class EmptyClass

class EmptyFunctionClass function emptyFunction()

class OneLineClass function oneLineFunction() return true

class CommentClass
		/*block comment*/
	function oneLineComment() //one line comment
		return true 
			//another one line comment
	function blockComment()
		return /*block comment
over
several
lines*/ true

class LiteralsClass
	function string()
		return "foo bar"
		
	function heredoc()
		return <<<heredoc
foo bar
heredoc

class EmbeddedHTMLClass
	function embeddedHTML()
		$exampleVar = true
		?><div>Some html</div><?php
		return $exampleVar

	function embeddedHTMLOnMultipleLines()
		?>
<html>
	<head></head>
	<body>
		<div>Hello world!</div>
	</body>
</html>
<?php

	function embeddedHTMLAndPHPStatements()
		$foo = 'foo'
		$bar = 'bar'
		echo $foo ?><b><?php echo $bar ?></b><?php