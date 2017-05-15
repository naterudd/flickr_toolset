<?php
/* flickr toolset - photo name
 * Written by Nathaniel Rudd (deafears@naterudd.com)
 * Project Home Page: http://github.com/naterudd/flickr_toolset
 * Copyright 2017 Nathaniel Rudd
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
require_once("getid3/getid3.php");

if (isset($argv)&&file_exists($argv[1])) {

	// Get exif date taken
	$getID3 = new getID3;
	$info=$getID3->analyze($argv[1]);
	if ($info['fileformat']=='mp4'&&isset($info['tags']['quicktime']['creation_date'][0])) {
		$file_date=strtotime($info['tags']['quicktime']['creation_date'][0]);
	} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['EXIF']['DateTimeOriginal'])) {
		$file_date=strtotime($info['jpg']['exif']['EXIF']['DateTimeOriginal']);
	} else {
		// No date found in the file, cancel out
		echo "No date found in the file, reverting to file creation time.\n";
		$file_date=(filemtime($argv[1])<filectime($argv[1]))?filemtime($argv[1]):filectime($argv[1]);
	}

	if (!$file_date) { 
		echo "File date still not valid.\n";
		exit;
	}
	
	// Calculate the file name
	$file_path=pathinfo($argv[1],PATHINFO_DIRNAME)!=""?pathinfo($argv[1],PATHINFO_DIRNAME)."/":"";
	$file_basename=pathinfo($argv[1],PATHINFO_BASENAME);
	$file_path=$file_path.date("Ymd_His_",$file_date).$file_basename;
	
	// Move the file
	$rename_success=rename($argv[1],$file_path);
	
	// Rename didn't work, cancel out
	if (!$rename_success) { 
		echo "Renaming was unsuccessful.\n";
		exit;		
	}

	// Change file creation to match exif date taken
	exec("SetFile -d '".date('m/d/Y H:i:s',$file_date)."' ".escapeshellarg($file_path));

	// Change file modification to match exif date taken
	touch($file_path,$file_date); // touch -t

}
