<?php
define('APP',true);

$e = getenv("ORIGIN_URL");
// $e = "http://127.0.0.1:50974"; // localhost

header ("Access-Control-Allow-Origin: " . $e);
header ("Access-Control-Allow-Headers: *");
header ("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    die();
}

require_once('../vendor/autoload.php');

// validate the basic request
$verifier = Licence::validate(Request::get("hash"));
if (!$verifier->valid) Utils::Stop(400, 'Bad method');

// ensure the client workding dir is valid
$workingdir = realpath("./data/{$verifier->hash}/");
if (!$workingdir) $workingdir = realpath('.') . "/{$verifier->hash}";
if (!file_exists($workingdir)) mkdir ($workingdir, 0777, true);
if (!file_exists($workingdir)) Utils::Stop(403, 'Permissions error');

// perform the requested action
$result = new stdClass();
$action = Request::post("action");
switch ($action) {
	case "listzips":
		$dir = "{$workingdir}/published/";
		$rows = [];
		if (file_exists($dir)) {
			$zips = new DirectoryIterator($dir);
			foreach ($zips as $zip) {
				if (!$zip->isDot() && !$zip->isDir()) {
					$rows[] = [
						$zip->getFilename(),
						$zip->getSize(),
						$zip->getCTime()
					];
				}
			}
			usort($rows, 'Utils::dSort'); // static::dSort
			$result = [];
			forEach ($rows as $row) {
				$result[] = [
					"filename" => $row[0],
					"size" => Utils::FormatBytes($row[1]),
					"date" => date("D d M Y H:i:s", $row[2])
				];
			}
		}
		Utils::Stop(200, json_encode($result), false, "application/json");
		break;

	case "listthemes":
		$base = Request::post("base");
		$userpath = "{$workingdir}/{$base}";
		$result = [];
		if (file_exists($userpath)) {
			foreach (glob("{$userpath}/*.theme") as $theme) {
				$text = file_get_contents($theme);
				$key = substr(basename($theme), 0, -6);
				$obj = new stdClass();
				$obj->key = $key;
				$obj->image = 'img/user-preset.jpg';
				$obj->theme = base64_encode($text);
				$result[] = $obj;
			}
		}
		Utils::Stop(200, json_encode($result), false, "application/json");
		break;

	case "storetheme":
		$data = Request::post("data", false, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_ENCODE_HIGH);
		if (!empty($data)) {
			$theme = Request::regex("theme"); // default [a-z]
			$name = Request::regex("selection","/[^a-z0-9-]/i");
			if (substr($name,-5)!=="-copy") $name .= "-copy";
			if (!file_exists("{$workingdir}/{$theme}")) mkdir("{$workingdir}/{$theme}", 02777, true);
			file_put_contents("{$workingdir}/{$theme}/{$name}.theme", $data);
			$result->key = "{$name}";
			Utils::Stop(200, json_encode($result), false, "application/json");
		}
		Utils::Stop(400);
		break;

	case "loadtheme":
		$theme = Request::regex("theme");
		$name = Request::regex("name","/[^a-z0-9-]/i");
		if (file_exists("{$workingdir}/{$theme}/{$name}.theme")) {
			Utils::Stop(200, file_get_contents("{$workingdir}/{$theme}/{$name}.theme"));
		}
		Utils::Stop(404);
		break;

	case "storecourse":
		$result->ok = false;
		if (!isset($_FILES['file'])) {
			$result->error = "File upload not present";
			Utils::Stop(400, json_encode($result), false, "application/json");
		}
		if (!file_exists("{$workingdir}/published")) mkdir("{$workingdir}/published", 02777, true);
		$name =  basename($_FILES["file"]["name"]);

		// uh oh, we already have this course name, better make a copy
		if (file_exists("{$workingdir}/published/{$name}")) {
			$name = pathinfo($name, PATHINFO_FILENAME) . "-" . date("YmdHis", strtotime("now")) . ".zip";
		}

		if (move_uploaded_file($_FILES['file']['tmp_name'], "{$workingdir}/published/{$name}")) {
			// TODO: check if file is already writable by www-data, so we can remove it
			@chmod("{$workingdir}/published/{$name}",02775);
			$result->ok = true;
			$result->filesize = Utils::formatBytes(filesize("{$workingdir}/published/{$name}"));
			$result->name = $name;
			$result->date = date("D d M Y H:i:s", time());
		} else {
			$result->error = "Unable to process upload";
		}
		Utils::Stop(200, json_encode($result), false, "application/json");
		break;

	case "removecourse":
		$result->ok = false;
		$name = Request::post("name");
		// remove some potential hacks - will invalidate the name, which prevents it from being found
		$name = str_replace(['..','/','\\','.php','.htaccess','.js','.css'],'',$name);
		if (file_exists("{$workingdir}/published/{$name}")) {
			unlink ("{$workingdir}/published/{$name}");
			$result->ok = true;
		}
		Utils::Stop(200, json_encode($result), false, "application/json");
		break;

	case "loadcourse":
		$name = Request::post("name");
		$name = str_replace(['..','/','\\'],'',$name);
		$path = "{$workingdir}/published/{$name}";
		if (file_exists($path)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public");
            header("Content-Type: ".mime_content_type($path)); //application/zip");
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:".filesize($path));
            header("Content-Disposition: attachment; filename={$name}");
            readfile($path);
            die();
        } else {
        	Utils::Stop(404, "File not found");
        }
        break;

}