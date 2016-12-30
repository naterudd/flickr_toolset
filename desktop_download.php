<?php
/* flickr toolset - desktop download
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

$all_sets=$f->photosets_getList();
if ($all_sets===false) {
	$headers = array( "From: $desktop_download_email_from", "MIME-Version: 1.0", "Content-type: text/plain" );
	$rc = mail($desktop_download_email_to, "Desktop download error", print_r($f,true), implode("\r\n", $headers) );
}
if (count($all_sets['photoset'])) { foreach ($all_sets['photoset'] as $set) { 
	if (preg_match($desktop_download_search_expression,$set['title']['_content'])) {
		$photos = $f->photosets_getPhotos($set['id'],"url_o,media,rotation,date_taken",null,"photos");
		foreach ($photos['photoset']['photo'] as $photo) {
			$file_name=date("Ymd_His_",strtotime($photo['datetaken'])).$photo['title'].".".pathinfo($photo['url_o'],PATHINFO_EXTENSION);
			if($photo['media']=="photo"&&
			   !file_exists($desktop_download_path_both.$file_name)&&
			   !file_exists($desktop_download_path_omit.$file_name)
			) {
				if ($photo['width_o']>$photo['height_o']&&($photo['rotation']==0||$photo['rotation']==180)) {
					echo $desktop_download_path_horiz.$file_name."\n";
					file_put_contents($desktop_download_path_horiz.$file_name,file_get_contents($photo['url_o']));
				}
				echo $desktop_download_path_both.$file_name."\n";
				file_put_contents($desktop_download_path_both.$file_name,file_get_contents($photo['url_o']));
			}
		}
	}
}}

