## Introduction:
Wisp is whitespace sensitive PHP. It lets you code in PHP without curly braces or semicolons by using indentation to control behaviour.
Wisp uses PHP to compile Wisp files (.wisp) to PHP files.
Wisp is currently partially unit tested using Pwe (pronounced Peewee) as part of [fluidics](http://github.com/olliesaunders/fluidics)

## Example Wisp syntax:
	<?php
	class foo 
		function manyfoo($n) 
			if ($n > 0) return "foo".$this->manyfoo($n - 1)

See WispExample.wisp for more Wisp syntax examples.

## Example usage:
	$indentSize = 3; //Number of spaces you use per tab
	$phpDir = "php"; //Directory to put compiled PHP into
	$compilerEnabled = true; //Allows you to disable the compiler (Wisp will not modify files)
	$wisp = new Wisp($indentSize, $phpDir, $compilerEnabled)
	require($wisp->compile("lib/useful_stuff.wisp")) //Requires the PHP file path: "php/lib/useful_stuff.wisp"

## Problems:
- Anonymous functions in arguments and in other bracketed constructs are not supported (they require curly braces and semicolons)

## Features todo:
- Anonymous function support
- Option to uncompile php to wisp
- Ability to use objects immediately after instantiation (e.g. `($x = new Foo)->bar()`)
- Ability to use array keys without assigning to a variable (e.g. `$x->getArray()[0]`)
- Shortening of $this (e.g. `$this->foo => $_foo` or something similar)
- Automatic property assignment for a method
- Implicit returns (e.g. `function add($a,$b) $a + $b returns the value of $a + $b`)