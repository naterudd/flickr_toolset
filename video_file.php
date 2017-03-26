<?php
/* flickr toolset - video file
 * Written by Nathaniel Rudd (deafears@naterudd.com)
 * Project Home Page: http://github.com/naterudd/flickr_toolset
 * Copyright 2016 Nathaniel Rudd
 *
 * This file is part of flickr toolset.
 *
 * flickr toolset is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once("config.php");
require_once("phpFlickr/phpFlickr.php");

$f = new phpFlickr($api_key, $api_secret);
$f->setToken($my_token);

// Find the name of the set from the folder
$current_folder = basename(getcwd());

// Find all the downloaded videos in the folder
$files=array();
$dirHandle = opendir(getcwd());
while($file = readdir($dirHandle)){
	if(is_numeric(pathinfo($file,PATHINFO_FILENAME))){
		$files[$file]=1;
	}
}
// print_r($files);

if (!count($files)) {
	echo "No videos to file.\n";
	exit;
} else {
	echo count($files)." videos to file.\n";
}

// Look up each file in flickr for it's details
if (count($files)) { foreach ($files as $file=>$nothing) {
	$id=pathinfo($file,PATHINFO_FILENAME);
	$photo=$f->photos_getInfo($id);
	
	// Change the file name to the correct structure
	$new_name=date("Ymd_His_",strtotime($photo['photo']['dates']['taken'])).$photo['photo']['title']['_content'].".".pathinfo($file,PATHINFO_EXTENSION);
	$rename_result=rename($file,$new_name);
	
	if ($rename_result) {
		echo "$file becomes: $new_name\n";
		
		// Change file creation to match exif date taken
		exec("SetFile -d '".date('m/d/Y H:i:s',strtotime($photo['photo']['dates']['taken']))."' ".escapeshellarg($new_name));

		// Change file modification to match exif date taken
		touch($new_name,strtotime($photo['photo']['dates']['taken'])); // touch -t
	}
}}

if (function_exists("push_notify")) {
	push_notify("Video file script complete.");
}

