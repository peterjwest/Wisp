<?php
/*
Wisp Version 0.92
by Peter West
peterjwest3@gmail.com
Modified: 16 Feb 2010

Introduction:
Wisp is whitespace sensitive PHP. It lets you code in PHP without curly braces or semicolons by using indentation to control behaviour.
Wisp uses PHP to compile Wisp files (.wisp) to PHP files.
See WispExample.wisp for Wisp syntax examples.

Example usage:
$indentSize = 3; //Number of spaces you use per tab
$phpDir = "php"; //Directory to put compiled PHP into
$compilerEnabled = true; //Allows you to disable the compiler, Wisp will just return the PHP file path
$wisp = new Wisp($indentSize, $phpDir, $compilerEnabled)
require($wisp->compile("lib/useful_stuff.wisp")) //Returns the PHP file path "php/lib/useful_stuff.wisp"

Problems:
- Anonymous functions in arguments and in other bracketed constructs are not supported (they require curly braces and semicolons)

Features todo:
- Anonymous function support
- Option to uncompile php to wisp
- Ability to use objects immediately after instantiation (e.g. ($x = new Foo)->bar())
- Ability to use array keys without assigning to a variable (e.g. $x->getArray()[0])
- Shortening of $this (e.g. $this->foo => $_foo or something similar)
- Implicit returns (e.g. function add($a,$b) $a + $b returns the value of $a + $b)
*/
class StaticWisp {
	static $wisp = null; }

class Wisp {
	function __construct($indentSize, $phpDir = "php", $compilerEnabled = true) {
		$this->indentSize = $indentSize;
		$this->phpDir = $phpDir;
		$this->compilerEnabled = $compilerEnabled;
		$this->fileHandler = new WispFileHandler;
		$this->tokeniser = new WispTokeniser; }
	
	function compile($filePath) {
		if ($this->compilerEnabled) 
			if (!$this->fileHandler->exists($filePath)) throw new Exception("Wisp source file $filePath does not exist");
			if (!$this->fileHandler->exists($this->phpPath($filePath)) || $this->fileHandler->newerThan($filePath, $this->phpPath($filePath))) {
				$wispFile = $this->normaliseIndentation($this->fileHandler->normaliseNewlines($this->fileHandler->loadFile($filePath)));
				$phpFile = $this->tokeniser->transform($wispFile, $this->indentSize);
				if (!$this->fileHandler->saveFile($this->phpPath($filePath), $phpFile)) throw new Exception("Wisp cannot write to file {$this->phpPath($filePath)}"); }
		return $this->phpPath($filePath); }

	function compileFolders($folderPath) {
		$files = array();
		foreach (func_get_args() as $folderPath) {
			foreach ($this->fileHandler->getDirFiles($folderPath) as $file) {
				if ($this->isWispFile($file)) {
					$files[] = $this->compile($folderPath."/".$file); }
				if ($this->isPHPFile($file)) {
					$files[] = $folderPath."/".$file; } } }
		return $files; }

	function isPHPFile($file) { return preg_match("~.php$~", $file); }
	function isWispFile($file) { return preg_match("~.wisp$~", $file); }
	function normaliseIndentation($file) { return preg_replace("~".$this->nSpaces($this->indentSize)."~","\t", $file); }
	function phpPath($filename) { return $this->phpDir."/".preg_replace("~.wisp$~", "", $filename).'.php'; }
	function nSpaces($size) { return $size > 0 ? ' '.$this->nSpaces($size - 1) : null; } }

class WispFileHandler {
	function createDir($dir) { if ($dir && !file_exists($dir)) mkdir($dir, 0777, true); }
	function loadFile($filePath) { return file_get_contents($filePath); }
	function exists($filePath) { return file_exists($filePath); }
	function newerThan($filePath1, $filePath2) { return filemtime($filePath1) > filemtime($filePath2); }
	function normaliseNewlines($file) { return preg_replace("~\r\n|\r~", "\n", $file); }
	
	function getDirFiles($dirPath) {
		if (!$this->exists($dirPath)) throw new Exception("Directory does not exist");
		$dir = opendir($dirPath);
		$files = array();
		while ($file = readdir($dir)) $files[] = $file;
		closedir($dir);
		return $files; }
	
	function saveFile($filePath, $contents) { 
		$this->createDir(dirname($filePath)); 
		return file_put_contents($filePath, $contents); } }

class WispTokeniser {
	function transform($file, $indentSize) {
		$tokens = new WispListToken(new WispToken($file));
		foreach (get_declared_classes() as $tokenName) {
			$parentName = get_parent_class($tokenName);
			if ($parentName == 'WispToken' || $parentName == 'WispListToken') {
				$token = new $tokenName();
				$token->transform($tokens); } }
		$value = $tokens->value();
		$tokens->destroy();
		return $value; } }

class WispToken {
	var $list = null;
	var $next = null;
	var $prev = null;
	var $regex = null;
	var $value = null;
	var $container = null;

	function __construct($value = null) { $this->value = $value; }
	function isFirst() { return $this->prev === null; }
	function isLast() { return $this->next === null; }
	function first($first = false) { return $first !== false ? $this->list->firstInner = $first : $this->list->firstInner; }
	function last($last = false) { return $last !== false ? $this->list->lastInner = $last : $this->list->lastInner; }
	function cur($cur = false) { return $cur !== false ? $this->list->curInner = $cur : $this->list->curInner; }
	function is($class) { return get_class($this) == $class; }
	function to($token) { return $token instanceof WispToken && $token->list ? $token->list->curInner = $token : false; }
	function value($value = false) { return $value !== false ? $this->value = $value : $this->value; }
	function destroy() { $this->list = $this->next = $this->prev = $this->value = null; }
	function debug($depth) { return $depth == 0 ? $this->value() : "[".substr(get_class($this),4)." ".$this->value()."]"; }
	
	function newInstance($value = null) { 
		$class = get_class($this);
		return $value === null ? new $class() : new $class($value); }
	
	function addBefore($token) {
		$token->list = $this->list;
		$prevToken = $this->prev;
		$this->prev = $token;
		$token->next = $this;
		if ($prevToken) { 
			$prevToken->next = $token;
			$token->prev = $prevToken; }
		else { $this->first($token); } 
		return $token; }
	
	function addAfter($token) {
		$token->list = $this->list;
		$nextToken = $this->next;
		$this->next = $token;
		$token->prev = $this;
		if ($nextToken) { 
			$nextToken->prev = $token;
			$token->next = $nextToken; }
		else { $this->last($token); } 
		return $token; }
	
	function remove() {
		$nextToken = $this->next;
		$prevToken = $this->prev;
		$nextToken ? $nextToken->prev = $prevToken : $this->last($prevToken);
		$prevToken ? $prevToken->next = $nextToken : $this->first($nextToken);
		if ($this->cur() === $this) {
			$this->prev ? $this->to($this->prev) : $this->to($this->next); }
		$this->list = $this->next = $this->prev = null; 
		return $this; }
	
	function split($newToken, $position = 0) {
		$this->addAfter($newToken);
		if (strlen($start = substr($this->value(), 0, $position))) {
			$newToken->addBefore($this->newInstance($start)); }
		if (strlen($end = substr($this->value(), $position + strlen($newToken->value)))) {
			$newToken->addAfter($this->newInstance($end)); }
		$this->remove(); 
		return $newToken; }

	function findToken($token, $regex) {
		preg_match($regex, $token->value(), $results, PREG_OFFSET_CAPTURE);
		if (isset($results[0])) {
			return array('value' => $results[0][0], 'pos' => $results[0][1]); } }
	
	function transform($tokens) {
		if ($this->regex === null) return;
		while ($tokens->eachInner()) {
			if ($tokens->curInner->is('WispToken')) {
				if ($newToken = $this->findToken($tokens->curInner, $this->regex)) {
					$tokens->curInner->split($this->newInstance($newToken['value']), $newToken['pos']); } } } } }

class WispListToken extends WispToken {
	var $curInner = null;
	var $firstInner = null;
	var $lastInner = null;
	var $eachInner = false;

	function __construct($inner = null) { if ($inner !== null) { $this->addInner($inner); } }
	function isEmpty() { if ($this->firstInner === null && $this->lastInner === null) return true; }
	function consumeNext() { $this->isEmpty() ? $this->addInner($this->next->remove()) : $this->lastInner->addAfter($this->next->remove()); }
	function consumePrev() { $this->isEmpty() ? $this->addInner($this->prev->remove()) : $this->firstInner->addBefore($this->prev->remove()); }
	function ejectFirst() { if (!$this->isEmpty()) $this->addBefore($this->lastInner->remove()); }
	function ejectLast() { if (!$this->isEmpty()) $this->addAfter($this->lastInner->remove()); }
	
	function destroy() { 
		while ($this->eachInner()) { $this->curInner->destroy(); }
		$this->curInner = $this->firstInner = $this->lastInner = null;
		parent::destroy(); }
	
	function debug($depth) {
		if ($depth == 0) return $this->value();
		$value = null;
		while ($this->eachInner()) { 
			$value .= $this->curInner->debug($depth-1); }
		return "[".substr(get_class($this),4)." ".$value."]"; }
	
	function value($value = null) {
		$value = null;
		while ($this->eachInner()) {
			$value .= $this->curInner->value(); }
		return $value; }
	
	function addInner($inner) {
		$this->firstInner = $this->lastInner = $this->curInner = $inner; 
		$inner->list = $this; }
			
	function eachInner() {
		if (!$this->eachInner) {
			if (!$this->to($this->firstInner)) { return false; }
			$this->eachInner = true; }
		else if (!$this->to($this->curInner->next)) { return $this->eachInner = false; }
		return $this->curInner; } }

class WispBoundingEmbeddedHTML extends WispToken {
	function transform($tokens) { $tokens->firstInner->addBefore(new WispEmbeddedHTMLOpen()); } }		

class WispNewline extends WispToken { 
	var $regex = "~\n[\t]*~";
	var $indent = null;
	function indent() { 
		return $this->indent === null ? preg_match_all("~\t~",$this->value,$results) : $this->indent; } }

class WispHeredocOpen extends WispToken { var $regex = "~<<<[^\n]+~"; var $container = 'WispHeredoc'; }
class WispHeredocClose extends WispToken {
	var $container = 'WispHeredoc';
	function transform($tokens) {
		$heredocOpen = null;
		while ($tokens->eachInner()) {
			if ($tokens->curInner->is('WispHeredocOpen')) { $heredocOpen = $tokens->curInner; }
			if ($tokens->curInner->is('WispToken') && $heredocOpen) {
				$regex = "~".substr($heredocOpen->value(), 3)."~";
				if (($newToken = $this->findToken($tokens->curInner,$regex)) && $tokens->curInner->prev->is('WispNewline') && $tokens->curInner->prev->indent() == 0) {
					$hereDocClose = $tokens->curInner->split($token = $this->newInstance($newToken['value']), $newToken['pos']);
					if (!$hereDocClose->next) { 
						$hereDocClose->addAfter(new WispNewline("\n")); }
					else if ($hereDocClose->next->is('WispNewline')) { 
						$hereDocClose->next->split(new WispNewline("\n"), 0); }
					$heredocOpen = null; } } } } }
class WispHeredoc extends WispListToken {
	function startCondition() { return $this->is('WispHeredocOpen'); }
	function endCondition() { 
		return $this->lastInner->prev && $this->lastInner->prev->is('WispHeredocClose') && $this->lastInner->is('WispNewline'); } }

class WispFunctionName extends WispToken { var $regex = "~function[\t ]+[\w]+~i"; }
class WispClass extends WispToken { var $regex = "~class[\t ]+[\w]+([\t ]+extends[\t ]+[\w]+)?([\t ]+implements[\t ]+([\w]+)([\t ]*,[\t ]*[\w]+)*)?~i"; }
class WispBracketOpen extends WispToken { var $regex =  "~\(~"; }
class WispBracketClose extends WispToken { var $regex = "~\)~"; }

class WispSingQuote extends WispToken { var $regex = "~(?<!\\\\)'~"; var $container = 'WispQuote'; }
class WispDoubQuote extends WispToken { var $regex = "~(?<!\\\\)\"~"; var $container = 'WispQuote'; }
class WispQuote extends WispListToken {
	function startCondition() { return $this->is('WispSingQuote') || $this->is('WispDoubQuote'); }
	function endCondition() { return $this->firstInner !== $this->lastInner && $this->lastInner->is(get_class($this->firstInner)); } }

class WispCommentOpen extends WispToken { var $regex = "~(//|#)~"; var $container = 'WispComment'; }
class WispComment extends WispListToken {
	function startCondition() { return $this->is('WispCommentOpen'); }
	function endCondition() { return $this->next->is('WispNewline'); } }

class WispBlockCommentOpen extends WispToken { var $regex = "~/\*~"; var $container = 'WispBlockComment'; }
class WispBlockCommentClose extends WispToken { var $regex = "~\*/~"; var $container = 'WispBlockComment'; }
class WispBlockComment extends WispListToken {
	function startCondition() { return $this->is('WispBlockCommentOpen'); }
	function endCondition() { return $this->lastInner->is('WispBlockCommentClose'); } }

class WispEmbeddedHTMLOpen extends WispToken { var $regex = "~\?>~";  var $container = 'WispEmbeddedHTML'; }
class WispEmbeddedHTMLClose extends WispToken { var $regex = "~<\?php~"; var $container = 'WispEmbeddedHTML'; }
class WispEmbeddedHTML extends WispListToken {
	function startCondition() { return $this->is('WispEmbeddedHTMLOpen'); }
	function endCondition() { return $this->lastInner->is('WispEmbeddedHTMLClose'); } }	

class WispWhitespace extends WispToken { var $regex = "~[\t ]+~"; }

class WispLiteral extends WispListToken {
	function transform($tokens) {
		$literal = false;
		$token = $tokens->firstInner;
		while ($token->next) {
			if ($literal) {
				if ($token->endCondition()) {
					$literal = false;
					$token = $token->next; }
				else { $token->consumeNext(); } }
			else {
				if ($token->container) {
					$literal = true;
					$token = $token->addBefore(new $token->container);
					$token->consumeNext(); }
				else { $token = $token->next; } } } } }

class WispBracket extends WispListToken {
	function transform($tokens) {
		$curBracket = null;
		$bracketDepth = 0;
		while ($tokens->eachInner()) {
			if ($bracketDepth > 0) {
				if ($tokens->curInner->is('WispBracketOpen')) { $bracketDepth++; }
				if ($tokens->curInner->is('WispBracketClose')) { $bracketDepth--; }
				$curBracket->consumeNext(); }
			else if ($tokens->curInner->is('WispBracketOpen')) { 
				$bracketDepth++;
				$tokens->curInner->addBefore($curBracket = $this->newInstance()); 
				$curBracket->consumeNext(); } } } }

class WispFunction extends WispListToken {
	function transform($tokens) {
		while ($tokens->eachInner()) { 
			if ($tokens->curInner->is('WispFunctionName')) {
				$tokens->curInner->addBefore($function = $this->newInstance());
				$function->consumeNext(); 
				$function->consumeNext(); } } } }

class WispLine extends WispListToken {
	var $indent = 0;
	var $isEmpty = true;
	function transform($tokens) {
		$tokens->firstInner->addBefore(new WispNewline());
		while ($tokens->eachInner()) {
			if ($tokens->curInner->is('WispNewline')) {
				if (isset($curLine)) $prevLine = $curLine;
				$curLine = $this->newInstance();
				$tokens->curInner->addBefore($curLine); 
				$curLine->indent = $tokens->curInner->indent(); }
			if (!($tokens->curInner->is('WispWhitespace') || $tokens->curInner->is('WispNewline') || $tokens->curInner->is('WispComment') || $tokens->curInner->is('WispBlockComment'))) { 
				$curLine->isEmpty = false; }
			$curLine->consumeNext();
			if ($curLine->lastInner->is('WispClass') || $curLine->lastInner->is('WispFunction') || $curLine->lastInner->is('WispEmbeddedHTML')) {
				$prevLine = $curLine;
				$curLine = $this->newInstance();
				$curLine->indent = $prevLine->indent;
				if (!$prevLine->lastInner->is('WispEmbeddedHTML')) $curLine->indent++;
				$curLine->addInner(new WispNewline);
				$curLine->isEmpty = false;
				$prevLine->addAfter($curLine);
				$this->to($curLine); } } }
		
	function getIndent($value,$indentSize) { return floor(preg_match_all("~ ~",$value,$results)/$indentSize); }
	
	function debug($depth) {
		if ($depth == 0) return $this->value();
		$value = null;
		while ($this->eachInner()) { 
			$value .= $this->curInner->debug($depth - 1); }
		return "[".substr(get_class($this),4)."(".$this->indent.") ".$value."]"; } }
		
class WispCurlyBraceOpen extends WispToken {
	function transform($tokens) {
		$endLine = new WispLine();
		$endLine->isEmpty = false;
		$tokens->lastInner->addAfter($endLine);
		while ($line = $tokens->eachInner()) {
			if (!$line->isEmpty) {
				if (isset($prevLine) && $line->indent > $prevLine->indent && $endToken = $prevLine->lastInner) {
					while ($endToken->prev && (($endToken->is('WispComment')) || $endToken->is('WispWhitespace'))) {
						$endToken = $endToken->prev; }
					for ($i = 1; $i <= $line->indent - $prevLine->indent; $i++) { $endToken->addAfter($this->newInstance(" {")); } }
				$prevLine = $line; } }
		$tokens->lastInner->remove(); } }

class WispSemicolon extends WispToken {
	function transform($tokens) {
		$endLine = new WispLine();
		$endLine->isEmpty = false;
		$tokens->lastInner->addAfter($endLine);
		while ($line = $tokens->eachInner()) {
			if (!$line->isEmpty) {
				if (isset($prevLine) && $line->indent <= $prevLine->indent && $endToken = $prevLine->lastInner) {
					while ($endToken->prev && ($endToken->is('WispComment') || $endToken->is('WispWhitespace') || $endToken->is('WispEmbeddedHTML'))) {
						$endToken = $endToken->prev; }
					if ($endToken->prev) { $endToken->addAfter($this->newInstance(";")); } }
				$prevLine = $line; } }
		$tokens->lastInner->remove(); } }

class WispCurlyBraceClose extends WispToken {
	function transform($tokens) {
		$startLine = new WispLine();
		$startLine->isEmpty = false;
		$tokens->firstInner->addBefore($startLine);
		$endLine = new WispLine();
		$endLine->isEmpty = false;
		$tokens->lastInner->addAfter($endLine);
		while ($line = $tokens->eachInner()) {
			if (!$line->isEmpty) {
				if (isset($prevLine) && $line->indent < $prevLine->indent) {
					$endToken = $prevLine->lastInner;
					while ($endToken && $endToken->prev && (($endToken->is('WispComment')) || $endToken->is('WispWhitespace'))) {
						$endToken = $endToken->prev; }
					for ($i = 1; $i <= $prevLine->indent - $line->indent; $i++) { $endToken->addAfter($this->newInstance(" }")); } }
				$prevLine = $line; } } 
		$startLine->remove(); 
		$endLine->remove(); } }