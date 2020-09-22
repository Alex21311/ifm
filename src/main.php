<?php

/* =======================================================================
 * Improved File Manager
 * ---------------------
 * License: This project is provided under the terms of the MIT LICENSE
 * http://github.com/misterunknown/ifm/blob/master/LICENSE
 * =======================================================================
 *
 * main
*/

error_reporting(E_ALL);
ini_set('display_errors', 0);

class IFMException extends Exception {
	public $forUser = false;
	public function __construct($message, $forUser = false, $code = 0, Exception $previous = null) {
		$this->forUser = $forUser;
		parent::__construct($message, $code, $previous);
	}
}

class IFM {
	private $defaultconfig = [
		// general config
		"auth" => 0,
		"auth_source" => 'inline;admin:$2y$10$0Bnm5L4wKFHRxJgNq.oZv.v7yXhkJZQvinJYR2p6X1zPvzyDRUVRC',
		"auth_ignore_basic" => 0,
		"root_dir" => "",
		"root_public_url" => "",
		"tmp_dir" => "",
		"timezone" => "",
		"forbiddenChars" => [],
		"dateLocale" => "en-US",
		"language" => "@@@vars:default_lang@@@",
		"selfoverwrite" => 0,

		// api controls
		"ajaxrequest" => 1,
		"chmod" => 1,
		"copymove" => 1,
		"createdir" => 1,
		"createfile" => 1,
		"edit" => 1,
		"delete" => 1,
		"download" => 1,
		"extract" => 1,
		"upload" => 1,
		"remoteupload" => 1,
		"rename" => 1,
		"zipnload" => 1,
		"createarchive" => 1,
		"search" => 1,
		"paging" => 1,

		// gui controls
		"showlastmodified" => 0,
		"showfilesize" => 1,
		"showowner" => 1,
		"showgroup" => 1,
		"showpermissions" => 2,
		"showhtdocs" => 0,
		"showhiddenfiles" => 1,
		"showpath" => 0,
		"contextmenu" => 1,
		"disable_mime_detection" => 0,
		"showrefresh" => 1,
		"forceproxy" => 0,
		"confirmoverwrite" => 1
	];

	private $config = [];
	private $templates = [];
	private $i18n = [];
	public $mode = "standalone";

	public function __construct($config=[]) {
		// load the default config
		$this->config = $this->defaultconfig;

		// load config from environment variables
		foreach (array_keys($this->config) as $key) {
			if (($value = getenv('IFM_' . strtoupper($key))) !== false) {
				if (is_numeric($value))
					$value = intval($value);
				$this->config[$key] = $value;
			}
		}

		// load config from passed array
		$this->config = array_merge($this->config, $config);



		if ($this->config['timezone'])
			date_default_timezone_set($this->config['timezone']);
	}

	/**
	 * This function contains the client-side application
	 */
	public function getApplication() {
		$this->getHTMLHeader();
		print '<div id="ifm"></div>';
		$this->getJS();
		print '<script>var ifm = new IFM(); ifm.init("ifm");</script>';
		$this->getHTMLFooter();
	}

	public function getInlineApplication() {
		$this->getCSS();
		print '<div id="ifm"></div>';
		$this->getJS();
	}

	public function getCSS() {
		echo <<<'f00bar'
			@@@ASSETS_CSS@@@
f00bar;
	}

	public function getJS() {
		echo <<<'f00bar'
			@@@ASSETS_JS@@@
f00bar;
	}

	public function getHTMLHeader() {
		print '<!DOCTYPE HTML>
		<html>
			<head>
				<title>IFM - improved file manager</title>
				<meta charset="utf-8">
				<meta http-equiv="X-UA-Compatible" content="IE=edge">
				<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0, shrink-to-fit=no">';
		$this->getCSS();
		print '</head><body>';
	}

	public function getHTMLFooter() {
		print '</body></html>';
	}

	/**
	 * main functions
	 */

	private function dispatch() {
		// APIs which do not need authentication
		switch ($_REQUEST['api']) {
			case "checkAuth":
				try {
					if ($this->checkAuth())
						$this->jsonResponse(["status" => "OK", "message" => "Authenticated"]);
				} catch (Exception $e) {
					$this->jsonResponse(["status" => "ERROR", "message" => "Not authenticated"]);
				}
				return;
			case "getConfig":
				$this->getConfig();
				return;
			case "getTemplates":
				$this->getTemplates();
				return;
			case "getI18N":
				$this->getI18N($_REQUEST);
				return;
			case "logout":
				unset($_SESSION);
				session_destroy();
				header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
				return;
		}

		// check authentication
		if (!$this->checkAuth())
			throw new IFMException("Not authenticated", true);

		// api requests which work without a valid working directory
		switch ($_REQUEST['api']) {
			case "getRealpath":
				if (isset($_REQUEST["dir"]) && $_REQUEST["dir"] != "")
					$this->jsonResponse(array("realpath" => $this->getValidDir($_REQUEST["dir"])));
				else
					$this->jsonResponse(array("realpath" => ""));
				return;
			case "getFiles":
				if (isset($_REQUEST["dir"]) && $this->isPathValid($_REQUEST["dir"]))
					$this->getFiles($_REQUEST["dir"]);
				else
					$this->getFiles("");
				return;
			case "getFolders":
				$this->getFolders($_REQUEST);
				return;
		}
		
		// checking working directory
		if (!isset($_REQUEST["dir"]) || !$this->isPathValid($_REQUEST["dir"]))
			throw new IFMException("Invalid working directory", true);

		$this->chDirIfNecessary($_REQUEST['dir']);
		switch ($_REQUEST["api"]) {
			case "createDir":	$this->createDir($_REQUEST); return;
			case "saveFile":	$this->saveFile($_REQUEST); return;
			case "getContent":	$this->getContent($_REQUEST); return;
			case "delete":		$this->deleteFiles($_REQUEST); return;
			case "rename":		$this->renameFile($_REQUEST); return;
			case "download":	$this->downloadFile($_REQUEST); return;
			case "extract":		$this->extractFile($_REQUEST); return;
			case "upload":		$this->uploadFile($_REQUEST); return;
			case "copyMove":	$this->copyMove($_REQUEST); return;
			case "changePermissions":	$this->changePermissions($_REQUEST); return;
			case "zipnload":	$this->zipnload($_REQUEST); return;
			case "remoteUpload":	$this->remoteUpload($_REQUEST); return;
			case "searchItems":	$this->searchItems($_REQUEST); return;
			case "getFolderTree":	$this->getFolderTree($_REQUEST); return;
			case "createArchive":	$this->createArchive($_REQUEST); return;
			case "proxy":		$this->downloadFile($_REQUEST, false); return;
			default:
				throw new IFMException("Invalid api action given", true);
				return;
		}
	}

	public function run($mode="standalone") {
		try {
			if (!is_dir(realpath($this->config['root_dir'])) || !is_readable(realpath($this->config['root_dir'])))
				throw new IFMException("Cannot access root_dir.", false);

			chdir(realpath($this->config['root_dir']));

			$this->mode = $mode;
			if (isset($_REQUEST['api']) || $mode == "api")
				$this->dispatch();
			elseif ($mode == "standalone")
				$this->getApplication();
			else
				$this->getInlineApplication();
		} catch (IFMException $e) {
			$this->jsonResponse(["status" => "ERROR", "message" => $e->getMessage()]);
		} catch (Exception $e) {
			$this->jsonResponse(["status" => "ERROR", "message" => $e->getMessage()]);
		}
	}

	/**
	 * api functions
	 */

	private function getI18N($lang="en") {
		$i18n = [];
		@@@vars:languageincludes@@@
		$this->i18n = $i18n;
		
		if (in_array($lang, array_keys($i18n)))
			// Merge english with the language in case of missing keys
			$this->jsonResponse(array_merge($i18n['en'], $i18n[$lang]));
		else
			$this->jsonResponse($i18n['en']);
	}

	private function getTemplates() {
		// templates
		$templates = [];
		$templates['app'] = <<<'f00bar'
@@@file:src/templates/app.html@@@
f00bar;
		$templates['login'] = <<<'f00bar'
@@@file:src/templates/login.html@@@
f00bar;
		$templates['filetable'] = <<<'f00bar'
@@@file:src/templates/filetable.html@@@
f00bar;
		$templates['footer'] = <<<'f00bar'
@@@file:src/templates/footer.html@@@
f00bar;
		$templates['task'] = <<<'f00bar'
@@@file:src/templates/task.html@@@
f00bar;
		$templates['ajaxrequest'] = <<<'f00bar'
@@@file:src/templates/modal.ajaxrequest.html@@@
f00bar;
		$templates['copymove'] = <<<'f00bar'
@@@file:src/templates/modal.copymove.html@@@
f00bar;
		$templates['createdir'] = <<<'f00bar'
@@@file:src/templates/modal.createdir.html@@@
f00bar;
		$templates['createarchive'] = <<<'f00bar'
@@@file:src/templates/modal.createarchive.html@@@
f00bar;
		$templates['deletefile'] = <<<'f00bar'
@@@file:src/templates/modal.deletefile.html@@@
f00bar;
		$templates['extractfile'] = <<<'f00bar'
@@@file:src/templates/modal.extractfile.html@@@
f00bar;
		$templates['file'] = <<<'f00bar'
@@@file:src/templates/modal.file.html@@@
f00bar;
		$templates['file_editoroptions'] = <<<'f00bar'
@@@file:src/templates/modal.file_editoroptions.html@@@
f00bar;
		$templates['remoteupload'] = <<<'f00bar'
@@@file:src/templates/modal.remoteupload.html@@@
f00bar;
		$templates['renamefile'] = <<<'f00bar'
@@@file:src/templates/modal.renamefile.html@@@
f00bar;
		$templates['search'] = <<<'f00bar'
@@@file:src/templates/modal.search.html@@@
f00bar;
		$templates['searchresults'] = <<<'f00bar'
@@@file:src/templates/modal.searchresults.html@@@
f00bar;
		$templates['uploadfile'] = <<<'f00bar'
@@@file:src/templates/modal.uploadfile.html@@@
f00bar;
		$templates['uploadconfirmoverwrite'] = <<<'f00bar'
@@@file:src/templates/modal.uploadconfirmoverwrite.html@@@
f00bar;
		$this->jsonResponse($templates);
	}

	private function getFiles($dir) {
		$this->chDirIfNecessary($dir);

		unset($files); unset($dirs); $files = []; $dirs = [];

		if ($handle = opendir(".")) {
			while (false !== ($result = readdir($handle))) {
				if ($result == basename($_SERVER['SCRIPT_NAME']) && $this->getScriptRoot() == getcwd())
					continue;
				elseif (($result == ".htaccess" || $result==".htpasswd") && $this->config['showhtdocs'] != 1)
					continue;
				elseif ($result == ".")
					continue;
				elseif ($result != ".." && substr($result, 0, 1) == "." && $this->config['showhiddenfiles'] != 1)
					continue;
				else {
					$item = $this->getItemInformation($result);
					if ($item['type'] == "dir")
						$dirs[] = $item;
					else
						$files[] = $item;
				}
			}
			closedir($handle);
		}
		usort($dirs, [$this, "sortByName"]);
		usort($files, [$this, "sortByName"]);

		$this->jsonResponse(array_merge($dirs, $files));
	}

	private function getItemInformation($name) {
		$item = array();
		$item["name"] = $name;
		if (is_dir($name)) {
			$item["type"] = "dir";
			if( $name == ".." )
				$item["icon"] = "icon icon-up-open";
			else 
				$item["icon"] = "icon icon-folder-empty";
		} else {
			$item["type"] = "file";
			if (in_array(substr($name, -7), array( ".tar.gz", ".tar.xz" )))
				$type = substr($name, -6);
			elseif( substr( $name, -8 ) == ".tar.bz2" )
				$type = "tar.bz2";
			else
				$type = substr( strrchr( $name, "." ), 1 );
			$item["icon"] = $this->getTypeIcon( $type );
			$item["ext"] = strtolower($type);
			if (!$this->config['disable_mime_detection'])
				$item["mime_type"] = mime_content_type($name);
		}
		if ($this->config['showlastmodified'] == 1)
			$item["lastmodified"] = filemtime($name);
		if ($this->config['showfilesize'] == 1) {
			if ($item['type'] == "dir") {
				$item['size_raw'] = 0;
				$item['size'] = "";
			} else {
				$item["size_raw"] = filesize($name);
				if ($item["size_raw"] > 1073741824) $item["size"] = round(($item["size_raw"]/1073741824 ), 2) . " GB";
				elseif($item["size_raw"]>1048576)$item["size"] = round(($item["size_raw"]/1048576), 2) . " MB";
				elseif($item["size_raw"]>1024)$item["size"] = round(($item["size_raw"]/1024), 2) . " KB";
				else $item["size"] = $item["size_raw"] . " Byte";
			}
		}
		if ($this->config['showpermissions'] > 0) {
			if ($this->config['showpermissions'] == 1)
				$item["fileperms"] = substr(decoct(fileperms($name)), -3);
			elseif ($this->config['showpermissions'] == 2)
				$item["fileperms"] = $this->filePermsDecode(fileperms($name));
			if ($item["fileperms"] == "")
				$item["fileperms"] = " ";
			$item["filepermmode"] = ($this->config['showpermissions'] == 1) ? "short" : "long";
		}
		if ($this->config['showowner'] == 1) {
			if (function_exists("posix_getpwuid") && fileowner($name) !== false) {
				$ownerarr = posix_getpwuid(fileowner($name));
				$item["owner"] = $ownerarr['name'];
			} else $item["owner"] = false;
		}
		if ($this->config['showgroup'] == 1) {
			if (function_exists("posix_getgrgid") && filegroup($name) !== false) {
				$grouparr = posix_getgrgid(filegroup($name));
				$item["group"] = $grouparr['name'];
			} else $item["group"] = false;
		}
		return $item;
	}

	private function getConfig() {
		$ret = $this->config;
		$ret['inline'] = ( $this->mode == "inline" ) ? true : false;
		$ret['isDocroot'] = ($this->getRootDir() == $this->getScriptRoot());

		foreach (["auth_source", "root_dir"] as $field)
			unset($ret[$field]);

		$this->jsonResponse($ret);
	}

	private function getFolders( $d ) {
		if (!isset($d['dir']))
			$d['dir'] = $this->getRootDir();

		if (!$this->isPathValid($d['dir']))
			echo "[]";
		else {
			$ret = [];
			foreach (glob($this->pathCombine($d['dir'], "*"), GLOB_ONLYDIR) as $dir) {
				array_push( $ret, [
					"text" => htmlspecialchars(basename($dir)),
					"lazyLoad" => true,
					"dataAttr" => ["path" => $dir]
				]);
			}
			sort( $ret );
			if( $this->getScriptRoot() == realpath( $d['dir'] ) )
				$ret = array_merge(
					[
						0 => [
							"text" => "/ [root]",
							"dataAttr" => ["path" => $this->getRootDir()]
						]
					],
					$ret
				);
			$this->jsonResponse($ret);
		}
	}

	private function searchItems($d) {
		if ($this->config['search'] != 1) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
			return;
		}

		if (strpos($d['pattern'], '/') !== false) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['pattern_error_slashes']]);
			exit(1);
		}

		try {
			$results = $this->searchItemsRecursive($d['pattern']);
			$this->jsonResponse($results);
		} catch (Exception $e) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['error'] . " " . $e->getMessage()]);
		}
	}

	private function searchItemsRecursive( $pattern, $dir="" ) {
		$items = [];
		$dir = $dir ?? '.';

		foreach (glob($this->pathCombine($dir, $pattern)) as $result)
			array_push($items, $this->getItemInformation( $result ) );

		foreach (glob($this->pathCombine($dir, '*'), GLOB_ONLYDIR) as $subdir)
			$items = array_merge($items, $this->searchItemsRecursive($pattern, $subdir));

		return $items;
	}

	private function getFolderTree( $d ) {
		$this->jsonResponse(
			array_merge(
				[
					0 => [
						"text" => "/ [root]",
						"nodes" => [],
						"dataAttributes" => ["path" => $this->getRootDir()]
					]
				],
				$this->getFolderTreeRecursive($d['dir'])
			)
		);
	}

	private function getFolderTreeRecursive($start_dir) {
		$ret = [];
		$start_dir = realpath($start_dir);
		if ($handle = opendir($start_dir)) {
			while (false !== ($result = readdir($handle))) {
				if (is_dir($this->pathCombine($start_dir, $result)) && $result != "." && $result != ".." ) {
					array_push($ret, [
						"text" => htmlspecialchars($result),
						"dataAttributes" => ["path" => $this->pathCombine($start_dir, $result)],
						"nodes" => $this->getFolderTreeRecursive($this->pathCombine($start_dir, $result))
					]);
				}
			}
		}
		sort($ret);
		return $ret;
	}

	private function copyMove($d) {
		if ($this->config['copymove'] != 1) {
			$this->jsonResponse( array( "status" => "ERROR", "message" => $this->l['nopermissions'] ) );
			exit(1);
		}

		if (!isset($d['destination']) || !$this->isPathValid(realpath($d['destination']))) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_dir']]);
			exit(1);
		}

		if (!is_array($d['filenames'])) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_params']]);
			exit(1);
		}

		if (!in_array($d['action'], ['copy', 'move'])) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_action']]);
			exit(1);
		}

		$err = []; $errFlag = -1; // -1 -> all errors; 0 -> at least some errors; 1 -> no errors
		foreach ($d['filenames'] as $file) {
			if (!file_exists($file) || $file == ".." || !$this->isFilenameValid($file)) {
				array_push($err, $file);
			}
			if ($d['action'] == "copy") {
				if ($this->xcopy($file, $d['destination']))
					$errFlag = 0;
				else
					array_push($err, $file);
			} elseif ($d['action'] == "move") {
				if (rename($file, $this->pathCombine($d['destination'], basename($file))))
					$errFlag = 0;
				else
					array_push($err, $file);
			}
		}
		$action = ($d['action'] == "copy") ? "copied" : "moved";
		if (empty($err)) {
			$this->jsonResponse([
				"status" => "OK",
				"message" => ($d['action'] == "copy" ? $this->l['copy_success'] : $this->l['move_success'] ),
				"errflag" => "1"
			]);
		} else {
			$errmsg = ($d['action'] == "copy" ? $this->l['copy_error'] : $this->l['move_error'] ) . "<ul>";
			foreach ($err as $item)
				$errmsg .= "<li>".$item."</li>";
			$errmsg .= "</ul>";
			$this->jsonResponse(["status" => "OK", "message" => $errmsg, "flag" => $errFlag]);
		}
	}

	// creates a directory
	private function createDir($d) {
		if ($this->config['createdir'] != 1) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
			exit(1);
		}

		if ($d['dirname'] == "" || !$this->isFilenameValid($d['dirname']))
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_dir']]);
		else {
			if (@mkdir($dn))
				$this->jsonResponse(["status" => "OK", "message" => $this->l['folder_create_success']]);
			else
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['folder_create_error']]);
		}
	}

	// save a file
	private function saveFile($d) {
		if (
			(file_exists($this->pathCombine($d['dir'], $d['filename'])) && $this->config['edit'] != 1 )
			|| (!file_exists($this->pathCombine($d['dir'], $d['filename'])) && $this->config['createfile'] != 1)
		) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
			exit(1);
		}

		if (isset($d['filename']) && $this->isFilenameValid($d['filename'])) {
			if (isset($d['content'])) {
				// work around magic quotes
				$content = get_magic_quotes_gpc() == 1 ? stripslashes($d['content']) : $d['content'];
				if (@file_put_contents($d['filename'], $content) !== false)
					$this->jsonResponse(["status" => "OK", "message" => $this->l['file_save_success']]);
				else
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_save_error']]);
			} else
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_save_error']]);
		} else
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
	}

	// gets the content of a file
	// notice: if the content is not JSON encodable it returns an error
	private function getContent(array $d) {
		if ($this->config['edit'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['npermissions']]);
		else {
			if (isset($d['filename']) && $this->isFilenameAllowed($d['filename']) && file_exists($d['filename']) && is_readable($d['filename'])) {
				$content = @file_get_contents($d['filename']);
				if (function_exists("mb_check_encoding") && !mb_check_encoding($content, "UTF-8"))
					$content = utf8_encode($content);
				$this->jsonResponse(["status" => "OK", "data" => ["filename" => $d['filename'], "content" => $content]]);
			} else
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_not_found']]);
		}
	}

	// deletes a bunch of files or directories
	private function deleteFiles(array $d) {
		if ($this->config['delete'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		else {
			$err = array(); $errFLAG = -1; // -1 -> no files deleted; 0 -> at least some files deleted; 1 -> all files deleted
			foreach ($d['filenames'] as $file) {
				if ($this->isFilenameAllowed($file)) {
					if (is_dir($file)) {
						$res = $this->rec_rmdir($file);
						if ($res != 0)
							array_push($err, $file);
						else
							$errFLAG = 0;
					} else {
						if (@unlink($file))
							$errFLAG = 0;
						else
							array_push($err, $file);
					}
				} else {
					array_push($err, $file);
				}
			}
			if (empty($err))
				$this->jsonResponse(["status" => "OK", "message" => $this->l['file_delete_success'], "errflag" => "1"]);
			else {
				$errmsg = $this->l['file_delete_error'] . "<ul>";
				foreach ($err as $item)
					$errmsg .= "<li>".$item."</li>";
				$errmsg .= "</ul>";
				$this->jsonResponse(["status" => "ERROR", "message" => $errmsg, "flag" => $errFLAG]);
			}
		}
	}

	// renames a file
	private function renameFile(array $d) {
		if ($this->config['rename'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		elseif (!$this->isFilenameValid($d['filename']))
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
		elseif (!$this->isFilenameValid($d['newname']))
			$this->jsonResponse( array( "status" => "ERROR", "message" => $this->l['invalid_filename'] ) );
		else {
			if (@rename($d['filename'], $d['newname']))
				$this->jsonResponse(["status" => "OK", "message" => $this->l['file_rename_success']]);
			else
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_rename_error']]);
		}
	}

	// provides a file for downloading
	private function downloadFile(array $d, $forceDL=true) {
		if ($this->config['download'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		elseif (!$this->isFilenameValid($d['filename']))
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
		else {
			if (!is_file($d['filename']))
				http_response_code(404);
			else 
				$this->fileDownload(["file" => $d['filename'], "forceDL" => $forceDL]);
		}
	}

	// extracts a zip-archive
	private function extractFile(array $d) {
		$restoreIFM = false;
		$tmpSelfContent = null;
		$tmpSelfChecksum = null;
		if ($this->config['extract'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		else {
			if (!file_exists($d['filename'])) {
				$this->jsonResponse(["status" => "ERROR","message" => $this->l['invalid_filename']]);
				exit(1);
			}

			if (!isset($d['targetdir']) || trim($d['targetdir']) == "")
				$d['targetdir'] = "./";

			if (!$this->isPathValid($d['targetdir'])) {
				$this->jsonResponse(["status" => "ERROR","message" => $this->l['invalid_dir']]);
				exit(1);
			}

			if (!is_dir($d['targetdir']) && !mkdir($d['targetdir'], 0777, true)) {
				$this->jsonResponse(["status" => "ERROR","message" => $this->l['folder_create_error']]);
				exit(1);
			}

			if (realpath($d['targetdir']) == substr($this->getScriptRoot(), 0, strlen(realpath($d['targetdir'])))) {
				$tmpSelfContent = tmpfile();
				fwrite($tmpSelfContent, file_get_contents(__FILE__));
				$tmpSelfChecksum = hash_file("sha256", __FILE__);
				$restoreIFM = true;
			}

			if (substr(strtolower($d['filename']), -4) == ".zip") {
				if (!IFMArchive::extractZip($d['filename'], $d['targetdir']))
					$this->jsonResponse(["status" => "ERROR","message" => $this->l['extract_error']]);
				else
					$this->jsonResponse(["status" => "OK","message" => $this->l['extract_success']]);
			} else {
				if (!IFMArchive::extractTar($d['filename'], $d['targetdir']))
					$this->jsonResponse(["status" => "ERROR","message" => $this->l['extract_error']]);
				else
					$this->jsonResponse(["status" => "OK","message" => $this->l['extract_success']]);
			} 

			if ($restoreIFM) {
				if ($tmpSelfChecksum != hash_file("sha256", __FILE__)) {
					rewind( $tmpSelfContent );
					$fh = fopen( __FILE__, "w" );
					while (!feof($tmpSelfContent))
						fwrite($fh, fread($tmpSelfContent, 8196));
					fclose($fh);
				}
				fclose($tmpSelfContent);
			}
		}
	}

	// uploads a file
	private function uploadFile(array $d) {
		if($this->config['upload'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		elseif (!isset($_FILES['file']))
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_upload_error']]);
		else {
			$newfilename = (isset($d["newfilename"]) && $d["newfilename"]!="") ? $d["newfilename"] : $_FILES['file']['name'];
			if (!$this->isFilenameValid($newfilename))
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
			else {
				if ($_FILES['file']['tmp_name']) {
					if (is_writable(getcwd())) {
						if (move_uploaded_file($_FILES['file']['tmp_name'], $newfilename))
							$this->jsonResponse(["status" => "OK", "message" => $this->l['file_upload_success'], "cd" => $d['dir']]);
						else
							$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_upload_error']]);
					} else
						$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_upload_error']]);
				} else
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_not_found']]);
			}
		}
	}

	// change permissions of a file
	private function changePermissions(array $d) {
		if ($this->config['chmod'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		elseif (!isset($d["chmod"]) || $d['chmod'] == "" )
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['permission_parse_error']]);
		elseif (!$this->isPathValid($this->pathCombine($d['dir'], $d['filename'])))
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		else {
			$chmod = $d["chmod"]; $cmi = true;
			if (!is_numeric($chmod)) {
				$cmi = false;
				$chmod = str_replace(" ", "", $chmod);

				if (strlen($chmod) == 9) {
					$cmi = true;
					$arr = [substr($chmod, 0, 3), substr($chmod, 3, 3), substr($chmod, 6, 3)];
					$chtmp = "0";
					foreach ($arr as $right) {
						$rtmp = 0;
						if (substr($right, 0, 1) == "r") $rtmp = $rtmp + 4; elseif (substr($right, 0, 1) <> "-") $cmi = false;
						if (substr($right, 1, 1) == "w") $rtmp = $rtmp + 2; elseif (substr($right, 1, 1) <> "-") $cmi = false;
						if (substr($right, 2, 1) == "x") $rtmp = $rtmp + 1; elseif (substr($right, 2, 1) <> "-") $cmi = false;
						$chtmp = $chtmp . $rtmp;
					}
					$chmod = intval($chtmp);
				}
			} else
				$chmod = "0" . $chmod;

			if ($cmi) {
				try {
					chmod($d["filename"], (int)octdec($chmod));
					$this->jsonResponse(["status" => "OK", "message" => $this->l['permission_change_success']]);
				} catch (Exception $e) {
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['permission_change_error']]);
				}
			} else
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['permission_parse_error']]);
		}
	}

	// zips a directory and provides it for downloading
	// it creates a temporary zip file in the current directory, so it has to be as much space free as the file size is
	private function zipnload(array $d) {
		if ($this->config['zipnload'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermission']]);
		else {
			if (!file_exists($d['filename']))
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['folder_not_found']]);
			elseif (!$this->isPathValid($d['filename']))
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_dir']]);
			elseif ($d['filename'] != "." && !$this->isFilenameValid($d['filename']))
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
			else {
				unset($zip);
				if ($this->isAbsolutePath($this->config['tmp_dir']))
					$dfile = $this->pathCombine($this->config['tmp_dir'], uniqid("ifm-tmp-") . ".zip"); // temporary filename
				else
					$dfile = $this->pathCombine(__DIR__, $this->config['tmp_dir'], uniqid("ifm-tmp-") . ".zip"); // temporary filename

				try {
					IFMArchive::createZip(realpath($d['filename']), $dfile, array($this, 'isFilenameValid'));
					if ($d['filename'] == ".") {
						if (getcwd() == $this->getScriptRoot())
							$d['filename'] = "root";
						else
							$d['filename'] = basename(getcwd());
					}
					$this->fileDownload(["file" => $dfile, "name" => $d['filename'] . ".zip", "forceDL" => true]);
				} catch (Exception $e) {
					echo $this->l['error'] . " " . $e->getMessage();
				} finally {
					if (file_exists($dfile))
						@unlink($dfile);
				}
			}
		}
	}

	// uploads a file from an other server using the curl extention
	private function remoteUpload(array $d) {
		if ($this->config['remoteupload'] != 1)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
		elseif (!isset($d['method']) || !in_array($d['method'], ["curl", "file"]))
			$this->jsonResponse(["status" => "error", "message" => $this->l['invalid_params']]);
		elseif ($d['method'] == "curl" && $this->checkCurl() == false)
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['error']." cURL extention not installed."]);
		elseif ($d['method'] == "curl" && $this->checkCurl() == true) {
			$filename = (isset($d['filename']) && $d['filename'] != "") ? $d['filename'] : "curl_".uniqid();
			$ch = curl_init();
			if ($ch) {
				if ($this->isFilenameValid($filename) == false)
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
				elseif (filter_var($d['url'], FILTER_VALIDATE_URL) === false)
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_url']]);
				else {
					$fp = fopen($filename, "w");
					if ($fp) {
						if (
							!curl_setopt($ch, CURLOPT_URL, urldecode($d['url']))
							|| !curl_setopt($ch, CURLOPT_FILE, $fp)
							|| !curl_setopt($ch, CURLOPT_HEADER, 0)
							|| !curl_exec($ch)
						)
							$this->jsonResponse(["status" => "ERROR", "message" => $this->l['error']." ".curl_error( $ch )]);
						else
							$this->jsonResponse(["status" => "OK", "message" => $this->l['file_upload_success']]);
						curl_close($ch);
						fclose($fp);
					} else 
						$this->jsonResponse(["status" => "ERROR", "message" => $this->l['file_open_error']]);
				}
			} else
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['error']." curl init"]);
		} elseif ($d['method'] == 'file') {
			$filename = (isset($d['filename']) && $d['filename'] != "") ? $d['filename'] : "curl_".uniqid();
			if ($this->isFilenameValid($filename) == false)
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
			else {
				try {
					file_put_contents($filename, file_get_contents($d['url']));
					$this->jsonResponse(["status" => "OK", "message" => $this->l['file_upload_success']]);
				} catch (Exception $e) {
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['error'] . " " . $e->getMessage()]);
				}
			}
		} else
			$this->jsonResponse(["status" => "error", "message" => $this->l['invalid_params']]);
	}

	private function createArchive($d) {
		if ($this->config['createarchive'] != 1) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['nopermissions']]);
			return false;
		}

		if (!$this->isFilenameValid($d['archivename'])) {
			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
			return false;
		}

		$filenames = array();
		foreach ($d['filenames'] as $file)
			if (!$this->isFilenameValid($file)) {
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['invalid_filename']]);
				exit(1);
			} else 
				array_push($filenames, realpath($file));
		switch ($d['format']) {
			case "zip":
				if (IFMArchive::createZip($filenames, $d['archivename']))
					$this->jsonResponse(["status" => "OK", "message" => $this->l['archive_create_success']]);
				else
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['archive_create_error']]);
				break;
			case "tar":
			case "tar.gz":
			case "tar.bz2":
				if (IFMArchive::createTar($filenames, $d['archivename'], $d['format']))
					$this->jsonResponse(["status" => "OK", "message" => $this->l['archive_create_success']]);
				else
					$this->jsonResponse(["status" => "ERROR", "message" => $this->l['archive_create_error']]);
				break;
			default:
				$this->jsonResponse(["status" => "ERROR", "message" => $this->l['archive_invalid_format']]);
				break;
		}
	}

	/*
	   help functions
	 */

	private function log($d) {
		file_put_contents($this->pathCombine($this->getRootDir(), "debug.ifm.log"), (is_array($d) ? print_r($d, true)."\n" : $d."\n"), FILE_APPEND);
	}

	private function jsonResponse($array) {
		$this->convertToUTF8($array);
		$json = json_encode($array);
		if ($json === false) {
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					echo ' - No errors';
					break;
				case JSON_ERROR_DEPTH:
					echo ' - Maximum stack depth exceeded';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					echo ' - Underflow or the modes mismatch';
					break;
				case JSON_ERROR_CTRL_CHAR:
					echo ' - Unexpected control character found';
					break;
				case JSON_ERROR_SYNTAX:
					echo ' - Syntax error, malformed JSON';
					break;
				case JSON_ERROR_UTF8:
					echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
					break;
				default:
					echo ' - Unknown error';
					break;
			}

			$this->jsonResponse(["status" => "ERROR", "message" => $this->l['json_encode_error']." ".$err]);
		} else
			echo $json;
	}

	private function convertToUTF8(&$item) {
		if (is_array($item))
			array_walk($item, [$this, 'convertToUTF8']);
		else
			if (function_exists("mb_check_encoding") && !mb_check_encoding($item, "UTF-8"))
				$item = utf8_encode($item);
	}

	private function checkAuth() {
		if ($this->config['auth'] == 0)
			return true;

		$credentials_header = $_SERVER['HTTP_X_IFM_AUTH'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? false;
		if ($credentials_header && !$this->config['auth_ignore_basic']) {
			$cred = explode(":", base64_decode(str_replace("Basic ", "", $credentials_header)));
			if (count($cred) == 2 && $this->checkCredentials($cred[0], $cred[1]))
				return true;
		}

		if (session_status() !== PHP_SESSION_ACTIVE)
			session_start();

		if (!isset($_SESSION['ifmauth']) || $_SESSION['ifmauth'] !== true) {
			$login_failed = false;
			if (isset($_POST["inputLogin"]) && isset($_POST["inputPassword"])) {
				if ($this->checkCredentials($_POST["inputLogin"], $_POST["inputPassword"])) {
					$_SESSION['ifmauth'] = true;
				} else {
					$_SESSION['ifmauth'] = false;
					$login_failed = true;
				}
			}

			if (isset($_SESSION['ifmauth']) && $_SESSION['ifmauth'] === true)
				return true;
			else {
				if ($login_failed === true)
					throw new IFMException("Authentication failed: Wrong credentials", true);
				else
					throw new IFMException("Not authenticated" , true);
			}
		} else
			return true;
	}

	private function checkCredentials($user, $pass) {
		list($src, $srcopt) = explode(";", $this->config['auth_source'], 2);
		switch ($src) {
			case "inline":
				list($uname, $hash) = explode(":", $srcopt);
				$htpasswd = new Htpasswd();
				return $htpasswd->verifyPassword($pass, $hash) ? ($uname == $user) : false;
				break;
			case "file":
				if (@file_exists($srcopt) && @is_readable($srcopt)) {
					$htpasswd = new Htpasswd($srcopt);
					return $htpasswd->verify($user, $pass);
				} else {
					trigger_error("IFM: Fatal: Credential file does not exist or is not readable");
					return false;
				}
				break;
			case "ldap":
				$authenticated = false;
				$ldapopts = explode(";", $srcopt);
				if (count($ldapopts) === 3) {
					list($ldap_server, $rootdn, $ufilter) = explode(";", $srcopt);
				} else {
					list($ldap_server, $rootdn) = explode(";", $srcopt);
					$ufilter = false;
				}
				$u = "uid=" . $user . "," . $rootdn;
				if (!$ds = ldap_connect($ldap_server)) {
					trigger_error("Could not reach the ldap server.", E_USER_ERROR);
					return false;
				}
				ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
				if ($ds) {
					$ldbind = @ldap_bind($ds, $u, $pass);
					if ($ldbind) {
						if ($ufilter) {
							if (ldap_count_entries($ds, ldap_search($ds, $rootdn, $ufilter)) > 0) {
								$authenticated = true;
							} else {
								trigger_error("User not allowed.", E_USER_ERROR);
								$authenticated = false;
							}
						} else
							$authenticated = true;
					} else {
						trigger_error(ldap_error($ds), E_USER_ERROR);
						$authenticated = false;
					}
					ldap_unbind($ds);
				} else
					$authenticated = false;
				return $authenticated;
				break;
		}
		return false;
	}

	private function filePermsDecode($perms) {
		$oct = str_split(strrev(decoct($perms)), 1);
		$masks = array( '---', '--x', '-w-', '-wx', 'r--', 'r-x', 'rw-', 'rwx' );
		return(
			sprintf(
				'%s %s %s',
				array_key_exists($oct[2], $masks) ? $masks[$oct[2]] : '###',
				array_key_exists($oct[1], $masks) ? $masks[$oct[1]] : '###',
				array_key_exists($oct[0], $masks) ? $masks[$oct[0]] : '###')
		);
	}

	private function isAbsolutePath($path) {
		if ($path === null || $path === '')
			return false;
		return $path[0] === DIRECTORY_SEPARATOR || preg_match('~^[A-Z]:(?![^/\\\\])~i',$path) > 0;
	}

	private function getRootDir() {
		if ($this->config['root_dir'] == "")
			return realpath($this->getScriptRoot());
		elseif ($this->isAbsolutePath($this->config['root_dir']))
			return realpath($this->config['root_dir']);
		else
			return realpath($this->pathCombine($this->getScriptRoot(), $this->config['root_dir']));
	}

	private function getScriptRoot() {
		return dirname(__FILE__);
	}

	private function getValidDir($dir) {
		if (!$this->isPathValid($dir) || !is_dir($dir))
			return "";
		else {
			$rpDir = realpath($dir);
			$rpConfig = $this->getRootDir();
			if ($rpConfig == "/")
				return $rpDir;
			elseif ($rpDir == $rpConfig)
				return "";
			else {
				$part = substr($rpDir, strlen($rpConfig));
				$part = (in_array(substr($part, 0, 1), ["/", "\\"])) ? substr($part, 1) : $part;
				return $part;
			}
		}
	}


	private function isPathValid($dir) {
		/**
		 * This function is also used to check non-existent paths, but the PHP realpath function returns false for
		 * nonexistent paths. Hence we need to check the path manually in the following lines.
		 */
		$tmp_d = $dir;
		$tmp_missing_parts = array();
		while (realpath($tmp_d) === false) {
			$tmp_i = pathinfo($tmp_d);
			array_push($tmp_missing_parts, $tmp_i['filename']);
			$tmp_d = dirname($tmp_d);
			if ($tmp_d == dirname($tmp_d))
				break;
		}
		$rpDir = $this->pathCombine(realpath($tmp_d), implode("/", array_reverse($tmp_missing_parts)));
		$rpConfig = $this->getRootDir();
		if (!is_string($rpDir) || !is_string($rpConfig)) // can happen if open_basedir is in effect
			return false;
		elseif ($rpDir == $rpConfig)
			return true;
		elseif (0 === strpos($rpDir, $rpConfig))
			return true;
		else
			return false;
	}

	private function chDirIfNecessary($d) {
		if (substr(getcwd(), strlen($this->getScriptRoot())) != $this->getValidDir($d) && !empty($d))
			chdir($d);
	}

	private function getTypeIcon($type) {
		$type = strtolower($type);
		switch ($type) {
			case "aac": case "aiff": case "mid": case "mp3": case "wav": return 'icon icon-file-audio'; break;
			case "ai": case "bmp": case "eps": case "tiff": case "gif": case "jpg": case "jpeg": case "png": case "psd": case "svg": return 'icon icon-file-image'; break;
			case "avi": case "flv": case "mp4": case "mpg": case "mkv": case "mpeg": case "webm": case "wmv": case "mov": return 'icon icon-file-video'; break;
			case "c": case "cpp": case "css": case "dat": case "h": case "html": case "java": case "js": case "php": case "py": case "sql": case "xml": case "yml": case "json": return 'icon icon-file-code'; break;
			case "doc": case "docx": case "odf": case "odt": case "rtf": return 'icon icon-file-word'; break;
			case "txt": case "log": return 'icon icon-doc-text'; break;
			case "ods": case "xls": case "xlsx": return 'icon icon-file-excel'; break;
			case "odp": case "ppt": case "pptx": return 'icon icon-file-powerpoint'; break;
			case "pdf": return 'icon icon-file-pdf'; break;
			case "tgz": case "zip": case "tar": case "tgz": case "tar.gz": case "tar.xz": case "tar.bz2": case "7z": case "rar": return 'icon icon-file-archive';
			default: return 'icon icon-doc';
		}
	}

	private function rec_rmdir($path) {
		if (!is_dir($path))
			return -1;

		$dir = @opendir($path);
		if (!$dir)
			return -2;
		while (($entry = @readdir($dir)) !== false) {
			if ($entry == '.' || $entry == '..') continue;
			if (is_dir($path . '/' . $entry)) {
				$res = $this->rec_rmdir($path.'/'.$entry);
				if ($res == -1) {
					@closedir($dir);
					return -2;
				} else if( $res == -2 ) {
					@closedir($dir);
					return -2;
				} else if ($res == -3) {
					@closedir($dir);
					return -3;
				} else if ($res != 0) {
					@closedir($dir);
					return -2;
				}
			} else if (is_file($path.'/'.$entry) || is_link($path.'/'.$entry)) {
				$res = @unlink($path.'/'.$entry);
				if (!$res) {
					@closedir($dir);
					return -2;
				}
			} else {
				@closedir($dir);
				return -3;
			}
		}
		@closedir($dir);
		$res = @rmdir($path);
		if (!$res)
			return -2;
		return 0;
	}

	private function xcopy($source, $dest) {
		$isDir = is_dir($source);
		if ($isDir)
			$dest = $this->pathCombine($dest, basename($source));
		if (!is_dir($dest))
			mkdir($dest, 0777, true);
		if (is_file($source))
			return copy($source, $this->pathCombine($dest, basename($source)));

		chdir($source);
		foreach (glob('*') as $item)
			$this->xcopy($item, $dest);
		chdir('..');
		return true;
	}

	// combines two parts to a valid path
	private function pathCombine(...$parts) {
		$ret = "";
		foreach ($parts as $part)
			if (trim($part) != "")
				$ret .= (empty($ret) ? rtrim($part,"/") : trim($part, '/'))."/";
		return rtrim($ret, "/");
	}

	// check if filename is allowed
	public function isFilenameValid($f) {
		if (!$this->isFilenameAllowed($f))
			return false;
		if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
			// windows-specific limitations
			foreach (['\\', '/', ':', '*', '?', '"', '<', '>', '|'] as $char)
				if (strpos($f, $char) !== false)
					return false;
		} else {
			// *nix-specific limitations
			foreach (['/', '\0'] as $char)
				if (strpos($f, $char) !== false)
					return false;
		}
		// custom limitations
		foreach ($this->config['forbiddenChars'] as $char)
			if (strpos($f, $char) !== false)
				return false;
		return true;
	}

	private function isFilenameAllowed($f) {
		if ($this->config['showhtdocs'] != 1 && substr($f, 0, 3) == ".ht")
			return false;
		elseif ($this->config['showhiddenfiles'] != 1 && substr($f, 0, 1) == ".")
			return false;
		elseif ($this->config['selfoverwrite'] != 1 && getcwd() == $this->getScriptRoot() && $f == basename( __FILE__ ))
			return false;
		else
			return true;
	}

	// sorting function for file and dir arrays
	private function sortByName($a, $b) {
		if (strtolower($a['name']) == strtolower($b['name']))
			return 0;
		return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
	}

	// is cURL extention avaliable?
	private function checkCurl() {
		if( !function_exists("curl_init")
			|| !function_exists("curl_setopt")
			|| !function_exists("curl_exec")
			|| !function_exists("curl_close")
		)
			return false;
		else
			return true;
	}

	private function fileDownload(array $options) {
		if (!isset($options['name']) || trim($options['name']) == "")
			$options['name'] = basename($options['file']);

		if (isset($options['forceDL']) && $options['forceDL']) {
			$content_type = "application/octet-stream";
			header('Content-Disposition: attachment; filename="'.$options['name'].'"');
		} else
			$content_type = mime_content_type( $options['file'] );

		header('Content-Type: '.$content_type);
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: '.filesize($options['file']));

		$file_stream = fopen($options['file'], 'rb');
		$stdout_stream = fopen('php://output', 'wb');

		stream_copy_to_stream($file_stream, $stdout_stream);

		fclose($file_stream);
		fclose($stdout_stream);
	}
}
