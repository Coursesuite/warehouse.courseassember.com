<?php

define('APP',true);
require_once('../vendor/autoload.php');

header ("Access-Control-Allow-Origin: ". getenv("ORIGIN_URL"));
header ("Access-Control-Allow-Headers: *");
header ("Access-Control-Allow-Methods: GET, OPTIONS, POST");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    die();
}

// validate the basic request
$verifier = Licence::validate(Request::get("hash"));
if (!$verifier->valid) Utils::Stop(400, 'Bad method');

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (is_dir($dir."/".$object)) {
					rrmdir($dir."/".$object);
				} else {
					unlink($dir."/".$object);
				}
			}
		}
		rmdir($dir);
	}
}

// ensure the client workding dir is valid
$workingdir = realpath("./data/{$verifier->hash}/");
if (!$workingdir) $workingdir = realpath('.') . "/data/{$verifier->hash}";
if (!file_exists($workingdir)) mkdir ($workingdir, 0777, true);
if (!file_exists($workingdir)) Utils::Stop(403, 'Permissions error');

// THIS IS THE PREVIEW ROOT
// https:/.warehouse.courseassembler.com/data/hashfolder/preview/
$folder = "{$workingdir}/preview";

// if we are doing a file upload ...
if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {

	// clean out previous working folder
	// echo PHP_EOL, "Folder ", file_exists($folder) ? "exists" : "missing";
	if (file_exists($folder)) {
		rrmdir($folder);
	}

	$name = $_FILES['file']['name'];
	$path = $_FILES['file']['tmp_name'];
	$extension = pathinfo($folder . basename($name), PATHINFO_EXTENSION);

	//extract the upload
	if ("zip" === $extension) {
		$zipArchive = new \ZipArchive();
		$result = $zipArchive->open($path);
		if ($result === TRUE) {

			// recreate the working folder
			mkdir ($folder, 0777, true);

			// extract the zip
			$zipArchive->extractTo($folder);
			$zipArchive->close();
		}
	}

	// this finishes the POST
	die();
}

// check this upload is correct
if (!file_exists($folder . "/doc.ninja")) die("Not a course-assembler package.");
if (!file_exists($folder . "/imsmanifest.xml")) die("imsmanifest.xml was missing.");

// find the page properties to render
$manifest = file_get_contents($folder . "/imsmanifest.xml");
$manifest = str_replace("adlcp:", "", $manifest); // avoid namespace glitches
$xmlDoc = simplexml_load_string ($manifest);
$href = $xmlDoc->resources[0]->resource[0]->attributes()->href[0]->__toString();
$outname = $xmlDoc->organizations[0]->organization[0]->item[0]->title[0]->__toString();

?><!DOCTYPE html>

<html lang="en" class="no-js">

<head>
	<meta charset="utf-8">
	<title>Scorm Previewer</title>
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<link rel="stylesheet" href="style.css" />
	<script src="scorm.js" type="text/javascript"></script>
</head>

<body>
	<iframe src='<?php echo "./data/{$verifier->hash}/preview/{$href}"; ?>' allowfullscreen='true' webkitallowfullscreen='true' mozallowfullscreen='true' width='100%' height='100%' frameborder='0'></iframe>
</body>
</html>