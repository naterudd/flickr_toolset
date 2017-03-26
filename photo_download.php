<?php
/* flickr toolset - photo download
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

ini_set("memory_limit", "256M");
require_once("config.php");
require_once("phpFlickr/phpFlickr.php");
require_once("getid3/getid3.php");

// if there are any arguments, save them into get
if (isset($argv)) {
	parse_str(implode("&",array_slice($argv,1)),$_GET);
}

// if there is a passed "search_expression" override the config variable
if (isset($_GET['search_expression'])) {
	$photo_download_search_expression = $_GET['search_expression'];
}

// Check the necessary regular expression pattern
$result=@preg_match($photo_download_search_expression,"check");
if ($result===false) {
	echo "ERROR: The \$photo_download_search_expression in config.php is not a vaild regular expression pattern.\nA simple definition of the following will match all flickr albums.\n\$photo_download_search_expression='/.*/';\n";
	exit;
}

$f = new phpFlickr($api_key, $api_secret);
$f->setToken($my_token);

$sets_in_flickr=array();

// Get list of sets
$all_sets=$f->photosets_getList($my_nsid);

// Foreach set
foreach ($all_sets['photoset'] as $set) { 

// Limit to only sets that are desired (default matches all sets)
if (preg_match($photo_download_search_expression,$set['title']['_content'])) {

	$files_in_flickr=array();
	$page_increment=0;

	do {
		$page_increment++;

		$photos = $f->photosets_getPhotos($set['id'],"original_format,url_o,media,date_taken,geo",NULL,NULL,$page_increment);
		$set_path = "{$photos['photoset']['title']}/";
		echo $set['title']['_content']."\n";
		
		// Is the folder missing for the set
		if (!is_dir($photo_download_base_path.$set_path)) {
			// Create the folder
			mkdir($photo_download_base_path.$set_path);
			chmod($photo_download_base_path.$set_path,0777);
		}


		// Foreach photo
		if (count($photos['photoset']['photo'])) { foreach ($photos['photoset']['photo'] as $photo) {
		
			// Get current file name
			$url_original="";
			$flickr_filename=$photo['title'].".missing";
			if ($photo['media']=="photo") {
				$flickr_filename=pathinfo($photo['url_o'],PATHINFO_BASENAME);
				$url_original=$photo['url_o'];
				$headers = get_headers($url_original,1);
			} else if ($photo['media']=="video") {
				$sizes=$f->photos_getSizes($photo['id']);
				foreach($sizes as $s) {
					if ($s['label']=="Video Original") {
						$url_original=$s['source'];
					}
				}
				$headers = get_headers($url_original,1);
				if(isset($headers["Content-Disposition"])) {
					// apparently sometimes content-disposition can sometimes be an array
					if (is_array($headers["Content-Disposition"])) { 
						$content_header = $headers["Content-Disposition"][0];
					} else {
						$content_header = $headers["Content-Disposition"];
					}
					if(preg_match('/.*filename=[\'\"]([^\'\"]+)/', $content_header, $matches)) { 
						// this catches filenames between Quotes
						$flickr_filename = $matches[1];
					} else if(preg_match("/.*filename=([^ ]+)/", $content_header, $matches)) { 
						// if filename is not quoted, we take all until the next space
						$flickr_filename = $matches[1];
					} 
				}
			}
			
			// Get flickr title
			$flickr_title=$photo['title'];
	
			// Get flickr date taken
			$flickr_date=strtotime($photo['datetaken']);
	
			// Get flickr geo
			$flickr_latitude=(float)$photo['latitude'];
			$flickr_longitude=(float)$photo['longitude'];	
	
			// Make new file name
			$flickr_extension=pathinfo($flickr_filename,PATHINFO_EXTENSION)==""?"missing":pathinfo($flickr_filename,PATHINFO_EXTENSION);
			$file_prename=date("Ymd_His_",$flickr_date).$flickr_title;
			$file_name=$file_prename.".".$flickr_extension;
			$file_path=$photo_download_base_path.$set_path.$file_name;
	 
			// Does the photo not exist in the folder
			$exists_ondisk=false;
			if (file_exists($file_path)) {
				$exists_ondisk=true;
			}
			if (key_exists($flickr_extension, $photo_download_acceptable_extension_replacements)) { if (count($photo_download_acceptable_extension_replacements[$flickr_extension])) {
				foreach ($photo_download_acceptable_extension_replacements[$flickr_extension] as $acceptable_extension) {
					if (file_exists($photo_download_base_path.$set_path.$file_prename.".".$acceptable_extension)) {
						$exists_ondisk=true;
						$file_path=$photo_download_base_path.$set_path.$file_prename.".".$acceptable_extension;
						$file_name=$file_prename.".".$acceptable_extension;
					}				
				}
			}}
			echo "  $file_name\n";

	 		// Does the original exist online
	 		$exists_online=true;
			if ((($flickr_extension=="missing")||($url_original==""||intval(substr($headers[0], 9, 3)) >= 400))&&$exists_ondisk===false) {
				$exists_online=false;
				$log['notfound'][]=array("https://www.flickr.com/photos/$my_nsid/{$photo['id']}",$file_path);
				echo "    Error no original found.\n";
			}
		
			// If the file isn't on disk and identified the flickr file properly
			if ($exists_ondisk===false&&$exists_online&&$flickr_extension!="missing") {
				//  Download file
				echo "    Need to download.\n";
				
				$file_pointer = fopen ($file_path, 'w+');
				$ch = curl_init($url_original);
				curl_setopt($ch, CURLOPT_FILE, $file_pointer); 
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_exec($ch); 
				curl_close($ch);
				fclose($file_pointer);
				chmod($file_path,0777);
			}
			
			// Only perform the following checks if the file is on the disk
			if (file_exists($file_path)) {

				// Does the file not register as a photo or video
				$type=mime_content_type($file_path);
				if (strstr($type,"video/")===false&&strstr($type,"image/")===false) {
					//  Create code for deletion (log as invalid file)
					$log["invalid"][]=$file_path;
					
					// Save file path to files_in_flickr array
					$files_in_flickr[]=$file_path;	
					
					//  Continue, skipping below
					continue;
				}
	
				// Get exif date taken
				$getID3 = new getID3;
				$info=$getID3->analyze($file_path);
				if ($info['fileformat']=='mp4'&&isset($info['tags']['quicktime']['creation_date'][0])) {
					$file_date=strtotime($info['tags']['quicktime']['creation_date'][0]);
				} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['EXIF']['DateTimeOriginal'])) {
					$file_date=strtotime($info['jpg']['exif']['EXIF']['DateTimeOriginal']);
				} else {
					// No date found in the file, set to the flickr date
					$file_date=$flickr_date;
				}
	
				// Does exif date not match Flickr date taken
				if ($file_date!=$flickr_date) {
					//  Check the number of sets this photo is include
					$photo_sets=array();
					$sets=$f->photos_getAllContexts($photo['id']);
					if (count($sets['set'])>1) { foreach ( $sets['set'] as $s ) {
						$photo_sets[]=$s['title'];
					}}
					//  Create code for deletion (log as bad date)				
					$log['mismatchdate'][]=array($file_path,$photo_sets);
				}

				// Change file creation to match exif date taken
				exec("SetFile -d '".date('m/d/Y H:i:s',$file_date)."' ".escapeshellarg($file_path));
	
				// Change file modification to match exif date taken
				touch($file_path,$file_date); // touch -t
	
				// Get exif geo
				if ($info['fileformat']=='mp4'&&isset($info['tags']['quicktime']['gps_latitude'][0])) {
					$file_latitude=(float)$info['tags']['quicktime']['gps_latitude'][0];
				} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['GPS']['computed']['latitude'])) {
					$file_latitude=(float)$info['jpg']['exif']['GPS']['computed']['latitude'];
				} else {
					$file_latitude="";
				}

				if ($info['fileformat']=='mp4'&&isset($info['tags']['quicktime']['gps_longitude'][0])) {
					$file_longitude=(float)$info['tags']['quicktime']['gps_longitude'][0];
				} elseif ($info['fileformat']=='jpg'&&isset($info['jpg']['exif']['GPS']['computed']['longitude'])) {
					$file_longitude=(float)$info['jpg']['exif']['GPS']['computed']['longitude'];
				} else {
					$file_longitude="";
				}
	
				// Does exif geo not match Flickr geo
				if (($file_latitude==""&&$flickr_latitude!=0)||($file_longitude==""&&$flickr_longitude!=0)||
					($file_latitude!=""&&abs(($flickr_latitude-$file_latitude)/$file_latitude)>0.000001)||
					($file_longitude!=""&&abs(($flickr_longitude-$file_longitude)/$file_longitude)>0.000001)) {
					//  Check the number of sets this photo is include
					$photo_sets=array();
					$sets=$f->photos_getAllContexts($photo['id']);
					if (count($sets['set'])>1) { foreach ( $sets['set'] as $s ) {
						$photo_sets[]=$s['title']['_content'];
					}}
					//  Create code for deletion (log as bad geo)
					$log['mismatchgeo'][]=array($file_path,$photo_sets);
				}
				
			}

			// Save file path to files_in_flickr array
			$files_in_flickr[]=$file_path;	

		}} else {

			// Error, no photos in the set
			print_r($photos);

		}
	
	} while ($page_increment<$photos['photoset']['pages']);	


	// Check folder for extraneous files
	$files_in_folder = glob($photo_download_base_path.$set_path.'*.*');
	$missing_files = array_diff($files_in_folder, $files_in_flickr);
	foreach ($missing_files as $m) {
		// Create code for deletion (log as extraneous)
		$log['extraneous_files'][]=$m;
	}

	// Does the count of photos in the set not match the count of valid files in the folder
	if (count($files_in_flickr)!=count($files_in_folder)) {
		// Log the set as an error
		$log['mismatchcount'][]=array("id"=>$set['id'],"desc"=>$set['title']['_content']." - ".count($files_in_flickr)." in flickr, ".count($files_in_folder)." in the folder");
	}
	
	$sets_in_flickr[]=substr($photo_download_base_path.$set_path,0,-1);
	
}} // end foreach set 

// Check base for extraneous folders
$folders_in_base = glob($photo_download_base_path.'*');
$missing_folders = array_diff($folders_in_base, $sets_in_flickr);
foreach ($missing_folders as $m) { if ($m!="/volume1/photo/@eaDir") {
	// Create code for deletion (log as extraneous)
	$log['extraneous_folders'][]=$m;
}}

// Does the count of sets not match the count of folders in the base
if (count($sets_in_flickr)!=(count($folders_in_base)-1)) {
	// Log the folder count as an error
	$log['mismatchcount'][]=array("id"=>0,"desc"=>"*Folders - ".count($sets_in_flickr)." in flickr, ".count($folders_in_base)." in the base");
}

// print_r($log); // leave this line for debugging

// Mail off the log
$invalid="";$notfound="";$extraneous_folders="";$extraneous_files="";$mismatchdate="";$mismatchgeo="";$mismatchcount="";
if (count($log)) { foreach ($log as $code=>$l) {
	if ($code=="invalid") { 
		foreach ($l as $file) { $invalid.="rm ".escapeshellarg($file).";<br/>\n"; }
	} else if ($code=="notfound") { 
		foreach ($l as $file) { $notfound.="<a href='{$file[0]}'>Item in Flickr</a>, should be: ".escapeshellarg($file[1]."/").";<br/>\n"; }
	} else if ($code=="extraneous_folders") { 
		foreach ($l as $file) { $extraneous_folders.="rm -r ".escapeshellarg($file."/").";<br/>\n"; }
	} else if ($code=="extraneous_files") { 
		foreach ($l as $file) { $extraneous_files.="rm ".escapeshellarg($file).";<br/>\n"; }
	} else if ($code=="mismatchdate") { 
		foreach ($l as $item) { 
			$mismatchdate.="rm ".escapeshellarg($item[0]).";<br/>\n";
			if (count($item[1])) {
				$mismatchdate.="# ";
				foreach ($item[1] as $i) {
					$mismatchdate.="$i  ";
				}
				$mismatchdate.="<br/>\n";
			}
		}
	} else if ($code=="mismatchgeo") { 
		foreach ($l as $item) { 
			$mismatchgeo.="rm ".escapeshellarg($item[0]).";<br/>\n";
			if (count($item[1])) {
				$mismatchgeo.="# ";
				foreach ($item[1] as $i) {
					$mismatchgeo.="$i  ";
				}
				$mismatchgeo.="<br/>\n";
			}
		}
	} else if ($code=="mismatchcount") { 
		foreach ($l as $set) { $mismatchcount.="<a href='https://www.flickr.com/photos/naterudd/albums/{$set['id']}'>{$set['desc']}</a><br/>\n"; }
	}
}}
if ($invalid!=""||$extraneous_folders!=""||$extraneous_files!=""||$mismatchdate!=""||$mismatchgeo!=""||$mismatchcount!="") {
	$body=($invalid!="")?"<br/><div style='color:#900;font-size:1.1em;font-weight:bold;'>Invalid</div>\n<div>$invalid</div>\n":"";
	$body=($notfound!="")?"<br/><div style='color:#900;font-size:1.1em;font-weight:bold;'>Not Found</div>\n<div>$notfound</div>\n":"";
	$body.=($extraneous_folders!="")?"<br/><div style='color:#900;font-size:1.1em;font-weight:bold;'>Extraneous Folders</div>\n<div>$extraneous_folders</div>\n":"";
	$body.=($extraneous_files!="")?"<br/><div style='color:#900;font-size:1.1em;font-weight:bold;'>Extraneous Files</div>\n<div>$extraneous_files</div>\n":"";
	$body.=($mismatchcount!="")?"<br/><div style='color:#900;font-size:1.1em;font-weight:bold;'>Mismatch Count</div>\n<div>$mismatchcount</div>\n":"";
	$body.=($mismatchdate!="")?"<br/><div style='color:#c60;font-size:1.1em;font-weight:bold;'>Mismatch Date</div>\n<div>$mismatchdate</div>\n":"";
	$body.=($mismatchgeo!="")?"<br/><div style='color:#c60;font-size:1.1em;font-weight:bold;'>Mismatch Geo</div>\n<div>$mismatchgeo</div>\n":"";
	$headers = array( "From: $photo_download_email_from", "MIME-Version: 1.0", "Content-type: text/html" );
	$rc = mail($photo_download_email_to, "Flickr Download Results", $body, implode("\r\n", $headers) );
}

