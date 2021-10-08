<?php

class Utils {
	public static function Stop($code = 200, $message = '', $flush = false, $content_type = 'application/json', $unlink = null) {
		if ($flush) ob_end_flush();
		http_response_code($code);
		header('content-type: ' . $content_type);
		if (!is_null($unlink) && file_exists($unlink)) @unlink($unlink);
		die($message);
	}

	public static function FormatBytes($size, $precision = 2) {
		$base = log(floatval($size)) / log(1024);
		$suffixes = array('', 'k', 'M', 'G', 'T');
		return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
	}
	
	// sort function used by directoryiterator - sort by file->ctime()
	public static function dSort($a, $b) {
		$col = 2; // 0=name, 1=size, 2=timestamp
		$x = $b[$col]; // x = b if decending order
		$y = $a[$col]; // y = b if ascending order
		if (strcmp($x,$y) < 0) return -1;
		elseif (strcmp($x,$y) > 0) return 1;
		else return 0;
	}

}