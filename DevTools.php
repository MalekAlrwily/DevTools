<?php

/*
__PocketMine Plugin__
name=Development Tools
description=A collection of tools so development for PocketMine-MP is easier
version=0.1
author=shoghicp
class=DevTools
apiversion=4
*/

/*

Small Changelog
===============

0.1:
- PocketMine-MP Alpha_1.2.1 release


*/
		
class DevTools implements Plugin{
	public static $compileHeader = <<<HEADER
<?php
/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


Check the complete source code at
http://www.pocketmine.org/

PocketMine-MP {{version}} @ {{time}}
*/
define("POCKETMINE_COMPILE", true);

HEADER;

	private $api;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->console->register("compile", "Compiles PocketMine-MP into a standalone PHP file", array($this, "command"));
		$this->api->console->register("pmfplugin", "Creates a PMF version of a Plugin", array($this, "command"));
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "compile":
				if($issuer !== "console"){
					$output .= "Must be run on the console.\n";
					break;
				}
				if(defined("POCKETMINE_COMPILE") and POCKETMINE_COMPILE === true){
					$output .= "Must be run in a pure Source PocketMine-MP.\n";
					break;
				}
				if(strtolower($params[0]) === "deflate"){
					$deflate = "";
				}else{
					$deflate = false;
				}
				$this->compilePM($output, $deflate);
				break;
			case "pmfplugin":
				if($issuer !== "console"){
					$output .= "Must be run on the console.\n";
					break;
				}
				if(!isset($params[0])){
					$output .= "Usage: /pmf <PluginClassName> [identifier]\n";
					break;
				}
				$className = strtolower(trim($params[0]));
				if(isset($params[1])){
					$identifier = trim($params[1]);
				}else{
					$identifier = "";
				}
				$this->PMFPlugin($output, $className, $identifier);
				break;
		}
		return $output;
	}
	
	private function PMFPlugin(&$output, $className, $identifier = ""){
		$info = $this->api->plugin->getInfo($className);
		if($info === false){
			$output .= "The plugin class \"$className\" does not exist.\n";
			break;
		}
		$info = $info[0];
		$pmf = new PMF($info["name"].".pmf", true, 0x01);
		$pmf->write(chr(PMF_CURRENT_PLUGIN_VERSION));
		$pmf->write(Utils::writeShort(strlen($info["name"])).$info["name"]);
		$pmf->write(Utils::writeShort(strlen($info["version"])).$info["version"]);
		$pmf->write(Utils::writeShort(strlen($info["author"])).$info["author"]);
		$pmf->write(Utils::writeShort((int) $info["apiversion"]));
		$pmf->write(Utils::writeShort(strlen($info["class"])).$info["class"]);
		$pmf->write(Utils::writeShort(strlen($identifier)).$identifier);
		$extra = gzdeflate("", 9);
		$pmf->write(Utils::writeShort(strlen($extra)).$extra); //Extra data
		$code = "";
		$lastspace = true;
		$src = token_get_all("<?php ".$info["code"]);
		foreach($src as $index => $tag){
			if(!is_array($tag)){
				$code .= $tag;
			}else{
				switch($tag[0]){
					case T_COMMENT:
					case T_DOC_COMMENT:
					case T_OPEN_TAG:
					case T_CLOSE_TAG:
					case T_INLINE_HTML:
						break;
					case T_WHITESPACE:
						switch(str_replace("\t", "", $tag[1])){
							case " ":
							case "\r\n":
							case "\n":
								if($lastspace !== true){
									$code .= " ";
									$lastspace = true;
								}
								break;
						}
						break;
					default:
						$code .= $tag[1];
						$lastspace = false;
						break;
				}
			}
		}
		$code = gzdeflate($code, 9);
		$pmf->write($code);
	}
	
	private function compilePM(&$output, $deflate = false){
		$fp = fopen(FILE_PATH."PocketMine-MP_".MAJOR_VERSION.".php", "wb");
		$srcdir = realpath(FILE_PATH."src/");
		fwrite($fp, str_replace(array(
			"{{version}}",
			"{{time}}",
		), array(
			MAJOR_VERSION,
			microtime(true),
		), DevTools::$compileHeader));
		$inc = get_included_files();
		$inc[] = array_shift($inc);
		foreach($inc as $s){
			if(strpos(realpath(dirname($s)), $srcdir) === false and strtolower(basename($s)) !== "pocketmine-mp.php"){
				continue;
			}
			$n = realpath($s);
			console("insert ".$n);
			$buff = PHP_EOL."//---- ".basename($n)." @ ".sha1_file($n)." ----".PHP_EOL;
			$drop = false;
			$lastspace = true;
			$code = token_get_all(file_get_contents($n));
			foreach($code as $index => $tag){
				if(!is_array($tag)){
					if($drop === false){
						$buff .= $tag;
					}
				}else{
					switch($tag[0]){
						case T_COMMENT:
						case T_DOC_COMMENT:
							if(strpos($tag[1], "**REM_START**") !== false){
								$drop = true;
							}elseif(strpos($tag[1], "**REM_END**") !== false){
								$drop = false;
							}
							break;
						case T_OPEN_TAG:
						case T_CLOSE_TAG:
						case T_INLINE_HTML:
							break;
						case T_WHITESPACE:
							switch(str_replace("\t", "", $tag[1])){
								case " ":
								case "\r\n":
								case "\n":
									if($drop === false and $lastspace !== true){
										$buff .= " ";
										$lastspace = true;
									}
									break;
							}
							break;
						default:
							if($drop === false){
								$buff .= $tag[1];
								$lastspace = false;
							}
							break;
					}
				}
			}
			if($deflate === false){
				fwrite($fp, $buff);
			}else{
				$deflate .= $buff;
			}
		}
		if($deflate !== false){
			$data = gzdeflate($deflate, 9);
			fwrite($fp, PHP_EOL."//DEFLATE Compressed PocketMine-MP | ".round((strlen($data)/strlen($deflate))*100, 2)."% (".round(strlen($data)/1024, 2)."KB/".round(strlen($deflate)/1024, 2)."KB)".PHP_EOL."\$fp = fopen(__FILE__, \"r\");".PHP_EOL."fseek(\$fp, __COMPILER_HALT_OFFSET__);".PHP_EOL."eval(gzinflate(stream_get_contents(\$fp)));".PHP_EOL."__halt_compiler();".$data);
		}
		fclose($fp);
	}
	
	public function __destruct(){
	}

}