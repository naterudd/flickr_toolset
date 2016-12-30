<?php
/* flickr toolset - photo upload
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
require_once("getid3/getid3.php");

$f = new phpFlickr($api_key, $api_secret);
$f->setToken($my_token);

// MARK Gather Sets
$i=0;
do {
	echo "Getting list of exisiting sets\n";
	$all_sets=$f->photosets_getList();
	if ($all_sets===false) { 
		$headers = array( "From: uploader@naterudd.com", "MIME-Version: 1.0", "Content-type: text/plain" );
		$rc = mail("deafears@naterudd.com", "Upload gather sets error, try $i", print_r($f,true), implode("\r\n", $headers) );
		sleep(60); 
		$i++;
	}
} while ($all_sets===false&&$i<5);
if ($all_sets===false) {
	$headers = array( "From: uploader@naterudd.com", "MIME-Version: 1.0", "Content-type: text/plain" );
	$rc = mail("deafears@naterudd.com", "Upload gather sets error, complete failure", print_r($f,true), implode("\r\n", $headers) );
	exit;
}
$touched_sets=array();

$files=readDirs($photo_upload_base_path);
sort($files);
chdir("/");

foreach ($files as $file) {
	//MARK Upload a file

	$filename = basename($file);
	echo "$filename";

	// MARK - Gather file information
	$getID3 = new getID3;
	$info=$getID3->analyze($file);
	$tag_error=false;
	
		// MARK -- Check video length
		if ($info['fileformat']=='mp4'||$info['fileformat']=='quicktime') { if ((float)$info['playtime_seconds']>=180) {
			echo " - VIDEO TOO LONG\n";
			$log[$filename]['error']="Video too long.";
			continue;
		}}

		// MARK -- Gather date
		if (($info['fileformat']=='mp4'||$info['fileformat']=='quicktime')&&isset($info['tags']['quicktime']['creation_date'][0])) {
			$date=strtotime($info['tags']['quicktime']['creation_date'][0]);
		} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['EXIF']['DateTimeOriginal'])) {
			$date=strtotime($info['jpg']['exif']['EXIF']['DateTimeOriginal']);
		} else {
			$date=(filemtime($file)<filectime($file))?filemtime($file):filectime($file);
			if ($info['fileformat']!="png") {
				$log[$filename]['minorerror'][]="Can't find a date in file tags, used ".date("Y-m-d H:i:s",$date);
				$tag_error=true;
			}
		}
	
		// MARK -- Gather Latitude
		if (($info['fileformat']=='mp4'||$info['fileformat']=='quicktime')&&isset($info['tags']['quicktime']['gps_latitude'][0])) {
			$latitude=(float)$info['tags']['quicktime']['gps_latitude'][0];
		} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['GPS']['computed']['latitude'])) {
			$latitude=(float)$info['jpg']['exif']['GPS']['computed']['latitude'];
		} else {
			$latitude="";
			if ($info['fileformat']!="png") {
				$log[$filename]['minorerror'][]="Can't find latitude in file tags.";
				$tag_error=true;
			}
		}
	
		// MARK -- Gather Longitude
		if (($info['fileformat']=='mp4'||$info['fileformat']=='quicktime')&&isset($info['tags']['quicktime']['gps_longitude'][0])) {
			$longitude=(float)$info['tags']['quicktime']['gps_longitude'][0];
		} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['GPS']['computed']['longitude'])) {
			$longitude=(float)$info['jpg']['exif']['GPS']['computed']['longitude'];
		} else {
			$longitude="";
			if ($info['fileformat']!="png") {
				$log[$filename]['minorerror'][]="Can't find longitude in file tags.";
				$tag_error=true;
			}
		}

					
	// MARK - Begin upload
	$id=$f->sync_upload($file);
	// print_r($f->error_msg);
	
	$photo_info=$f->photos_getInfo($id);
	if ($photo_info!==false) {
		// MARK - Successful upload
		echo " - Successful upload";
	
		if ($date!=""&&$date!=strtotime($photo_info['photo']['dates']['taken'])) {
			// MARK -- Updating date
			$result=$f->photos_setDates($id, null,date('Y-m-d H:i:s',$date));
			if ($result!="1") {
				$log[$filename]['minorerror'][]="Updating date failed, should be ".date('Y-m-d H:i:s',$date).".";
				echo " - Update date failed, should be: ".date('Y-m-d H:i:s',$date);
			}
		}
	
		$geo_info=$f->photos_geo_getLocation($id);
		if ($latitude!=""||$longitude!="") {
			if (($latitude!=""&&abs(((float)$geo_info['location']['latitude']-$latitude)/$latitude)>0.000001)||
				($longitude!=""&&abs(((float)$geo_info['location']['longitude']-$longitude)/$longitude)>0.000001)) {
				// MARK -- Updating geo information
				$result=$f->photos_geo_setLocation($id,$latitude,$longitude);
				if ($result['stat']!="ok") {
					$log[$filename]['minorerror'][]="Updating geo information failed, should be lat:$latitude, lon:$longitude.";
					echo " - Update geo information failed, should be: $latitude,$longitude";
				}
			}
		}
	
		// MARK -- Add to the set
		$set_id="";
		foreach($all_sets['photoset'] as $set) { if ($set['title']==date('ym',$date)) { 
			// does the set exist
			$set_id=$set['id']; break;
		}}
		if ($set_id=="") {
			$result=$f->photosets_create(date('ym',$date),"",$id);
			if (!key_exists("id", $result)) {
				$log[$filename]['minorerror'][]="Create photoset failed, should be ".date('ym',$date).".";
				echo " - Create set failed, should be: ".date('ym',$date);	
			} else {
				$set_id=$result['id'];
				$all_sets['photoset'][]=array("id"=>$set_id,"title"=>date('ym',$date));
			}
		} else {
			$result=$f->photosets_addPhoto($set_id,$id);
			if ($result!="1") {
				$log[$filename]['minorerror'][]="Add to photoset failed, should be in ".date('ym',$date).".";
				echo " - Add to set failed, should be: ".date('ym',$date);	
			}
		}
		if (count($touched_sets)) { 
			if (!key_exists($set_id, $touched_sets)) { $touched_sets[$set_id]=date('ym',$date);	}
		} else { 
			$touched_sets[$set_id]=date('ym',$date);
		}
		
		// MARK -- Finish logging
		$log[$filename]['page']=$photo_info['photo']['urls']['url'][0]['_content'];
		
		// MARK -- Move the file
		$path_parts=pathinfo($file);
		if (!is_dir($path_parts['dirname']."/completed")) {
			mkdir($path_parts['dirname']."/completed",0777);
		}
		rename($file, $path_parts['dirname']."/completed/".$filename);
	
		echo "\n";
	} else {
		// MARK - Error uploading
		echo " - ERROR UPLOADING\n";
		$log[$filename]['error']="Upload Failure.";
	}
	
}


// MARK Reorganizing sets
if(count($touched_sets)) { foreach($touched_sets as $set_id=>$title) {
	echo "Reorganizing set $title\n";
	$i=0;$all_photos=array();
	do {
		$i++;
		$photos=$f->photosets_getPhotos($set_id,"date_taken,date_upload",NULL,NULL,$i);
		if (count($photos['photoset']['photo'])) { foreach($photos['photoset']['photo'] as $id=>$p) {
			$all_photos[]=$p;
		}}
	} while ($i<$photos['photoset']['pages']);	
	usort($all_photos,'datetaken_cmp');
	$order=array();
	foreach($all_photos as $p) {
		$order[]=$p['id'];
	}
	$order=implode(",", $order);
	$result=$f->photosets_reorderPhotos($set_id,"$order");
}}

// MARK Send off the log
$success="";$failure="";$minor="";$unexpected_types="";
if (count($log)) { foreach ($log as $f=>$l) {
	if (key_exists("error", $l)) { 
		$failure.="$f - <span style='color:#900;'>{$l['error']}</span><br/>\n";
	} else if  (key_exists("minorerror", $l)) { if (count($l['minorerror'])) { 
		$minor.="<a href='{$l['page']}'>$f</a><br/><blockquote>".implode("<br/>", $l['minorerror'])."</blockquote>\n";
	}} else if ($f=="unexpected_types") {
		$unexpected_types=implode(",",array_flip($l));
	} else { $success.="<a href='{$l['page']}'>$f</a><br/>\n";}
}}
if ($success!=""||$failure!=""||$minor!=""||$unexpected_types!="") {
	$body=($failure!="")?"<br/><div style='color:#900;font-size:1.1em;font-weight:bold;'>Failure</div>\n<div>$failure</div>\n":"";
	$body.=($minor!="")?"<br/><div style='color:#c60;font-size:1.1em;font-weight:bold;'>Minor</div>\n<div>$minor</div>\n":"";
	$body.=($unexpected_types!="")?"<br/><div style='color:#999;font-size:1.1em;font-weight:bold;'>Unexpected Types</div>\n<div>$unexpected_types</div>\n":"";
	$body.=($success!="")?"<br/><div style='color:#090;font-size:1.1em;font-weight:bold;'>Success</div>\n<div>$success</div>\n":"";
	$headers = array( "From: $photo_upload_email_from", "MIME-Version: 1.0", "Content-type: text/html" );
	$rc = mail($photo_upload_email_to, "Flickr Upload Results", $body, implode("\r\n", $headers) );
}


function readDirs($photo_upload_base_path){
	global $log;
	$acceptable_formats=array('jpg'=>1,'mp4'=>1,'png'=>1,'quicktime'=>1);
	$return_array=array();
	$dirHandle = opendir($photo_upload_base_path);
	while($file = readdir($dirHandle)){
		$getID3 = new getID3;
		$info=$getID3->analyze($photo_upload_base_path."/".$file);
		if(is_dir($photo_upload_base_path."/".$file) && $file!='.' && $file!='..' && $file!='completed'){
			$return_array=array_merge_recursive($return_array, readDirs($photo_upload_base_path."/".$file));
		} else if (key_exists('fileformat', $info)) { 
			if (key_exists($info['fileformat'],$acceptable_formats)) {
				$return_array[]=$photo_upload_base_path."/".$file;
			} else {
				if (count($log['unexpected_types'])) { if (!key_exists($info['fileformat'], $log['unexpected_types'])) {
					$log['unexpected_types'][$info['fileformat']]=1;
				}} else {
					$log['unexpected_types'][$info['fileformat']]=1;
				}
			}
		}
	}
	return $return_array;
}

function datetaken_cmp($a, $b){
    if (strtotime($a['datetaken']) == strtotime($b['datetaken'])) { 
    	if (strcmp($a['title'], $b['title'])==0) { return (strtotime($a['dateupload']) < strtotime($b['dateupload'])) ? -1 : 1; }
    	return strcmp($a['title'], $b['title']);
    }
    return (strtotime($a['datetaken']) < strtotime($b['datetaken'])) ? -1 : 1;
}