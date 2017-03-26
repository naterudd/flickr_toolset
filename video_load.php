<?php
/* flickr toolset - video load
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

// Find all the videos in the folder already
$files=array();
$dirHandle = opendir(getcwd());
while($file = readdir($dirHandle)){
	if(in_array(pathinfo($file,PATHINFO_EXTENSION), $video_load_acceptable_extensions)){
		$files[substr($file, 16, -4)][pathinfo($file,PATHINFO_EXTENSION)]=1;
	}
}

// Remove items from the $files array if already downloaded another extension
foreach ($files as $title=>$file) {
	$has_non_mp4=false;
	foreach ($video_load_acceptable_extensions as $ext) {
		if ($ext!="mp4"&&key_exists($ext, $file)) { $has_non_mp4=true; }
	}
	if ($has_non_mp4==false) {
		unset($files[$title]);
	}
}

// Find the set_id for the set we are looking for
$all_sets=$f->photosets_getList();
if ($all_sets===false) {
	echo "Video load error: unable to find sets.\n";
}
if (count($all_sets['photoset'])) { foreach ($all_sets['photoset'] as $set) { 
	if (trim($set['title']['_content'])==$current_folder) {
		$set_id=$set['id']."\n";
		break;
	}
}}

if (!isset($set_id)) {
	echo "The name of this folder was not found as an album in flickr.\n";
	exit;
}

// Find all the videos that are in the set
$i=0;$videos_needing_download=array();
do {
	$i++;
	$photos=$f->photosets_getPhotos($set_id,"date_taken,date_upload",NULL,NULL,$i,"video");
	if (count($photos['photoset']['photo'])) { foreach($photos['photoset']['photo'] as $id=>$p) {
		if (!key_exists($p['title'], $files)) {
			$videos_needing_download[]=$p;
		}
	}}
} while ($i<$photos['photoset']['pages']);


// Are there any files to download
if (count($videos_needing_download)==0) {
	echo "No videos found to download.\n";
	exit;
}

// Open the videos in the browser
$first=true;
if (count($videos_needing_download)) { foreach ($videos_needing_download as $key=>$v) {
	if ($first) { 
		$first=false;
		echo "Downloading now: https://www.flickr.com/video_download.gne?id={$v['id']}, ".($key+1)." of ".count($videos_needing_download)."\n"; 
	} else {
		echo "Next to download at ".date("H:i:s",time()+$video_load_delay_between).": https://www.flickr.com/video_download.gne?id={$v['id']}, ".($key+1)." of ".count($videos_needing_download)."\n"; 
		sleep($video_load_delay_between); 
	}
// 	exec("open -a $video_load_application \"https://www.flickr.com/photos/$my_nsid/{$v['id']}\"");
	exec("open -g -a $video_load_application \"https://www.flickr.com/video_download.gne?id={$v['id']}\"");
}}

if (function_exists("push_notify")) {
	push_notify("Video load script complete.");
}

