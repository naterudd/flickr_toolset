<?php
/* flickr toolset - video scan
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

// Check the necessary regular expression pattern
$result=@preg_match($video_scan_search_expression,"check");
if ($result===false) {
	echo "ERROR: The \$video_scan_search_expression in config.php is not a vaild regular expression pattern.\nA simple definition of the following will match all flickr albums.\n\$video_scan_search_expression='/.*/';\n";
	exit;
}

$f = new phpFlickr($api_key, $api_secret);
$f->setToken($my_token);

// Get list of sets
$all_sets=$f->photosets_getList($my_nsid);

$files=array();

// Foreach set
foreach ($all_sets['photoset'] as $set) { 

// Limit to only sets that are desired (default matches all sets)
if (preg_match($video_scan_search_expression,$set['title']['_content'])) {
	echo "Checking set: {$set['title']['_content']}\n";
	if (is_dir($video_scan_base_path.$set['title']['_content'])) {
		$dirHandle = opendir($video_scan_base_path.$set['title']['_content']);
		while($file = readdir($dirHandle)){
			if(pathinfo($file,PATHINFO_EXTENSION)=="mp4"&&!in_array($set['title']['_content']."/".$file, $video_scan_ignorable)){
				$files[$set['title']['_content']."/".$file]=1;
			}
		}
	}
}}

// Display files that were found in a way to easily add them to the igorne array
if(count($files)) { 
	echo "\$video_scan_ignorable=array(\n";
	$first=true;
	foreach ($files as $f=>$one) {
		echo (!$first)?",\n":"";
		$first=false;
		echo "\t\"$f\"";
	}
	echo "\n);\n";
} else {
	echo "No mp4 files found.\n";
}