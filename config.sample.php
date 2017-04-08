<?php

// MARK Global variables
$api_key="";
$api_secret="";
$my_token="";
$my_nsid="";
$email_address="myaddress@domain.com";

// Not sure why this is needed, but I have needed to insert this line
stream_context_set_default( [ 'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, ], ]);


// MARK Desktop download settings
$desktop_download_path_both = "desktops/both/";
$desktop_download_path_horiz = "desktops/horiz/";
$desktop_download_path_omit = "desktops/omit/";
$desktop_download_search_expression="/^Desktops/";

$desktop_download_email_from=str_replace("@", "+desktops@", $email_address); // or a specific address "desktops@doamin.com";
$desktop_download_email_to=str_replace("@", "+desktops@", $email_address); // or a specific address "myaddress@doamin.com";, or the above variable $email_address;


// MARK Photo download settings
$photo_download_base_path = "download/photos/";
$photo_download_search_expression="/.*/";

$photo_download_acceptable_extension_replacements=array(
	"missing"=>array("mov","mp4","jpg"), //if for whatever reason you can't download from flickr, what extensions are acceptable so as not to throw an error
	"mp4"=>array("mov") //a file on disk with an mov extension can stand in for a flickr file with an mp4 extension
);
$photo_download_acceptable_extraneous_files = array(
//	"set name" => array($photo_download_base_path."set name/file name")
);

$photo_download_email_from=str_replace("@", "+downloader@", $email_address); // or a specific address "downloader@doamin.com";
$photo_download_email_to=str_replace("@", "+downloader@", $email_address); // or a specific address "myaddress@doamin.com";, or the above variable $email_address;


// MARK Photo upload settings
$photo_upload_base_path="upload/photos/";
$photo_upload_acceptable_formats=array('jpg'=>1,'mp4'=>1,'png'=>1,'quicktime'=>1);

$photo_upload_email_from=str_replace("@", "+uploader@", $email_address); // or a specific address "uploader@doamin.com";
$photo_upload_email_to=str_replace("@", "+uploader@", $email_address); // or a specific address "myaddress@doamin.com";, or the above variable $email_address;


// MARK Video load settings
$video_load_acceptable_extensions=array("mp4","mov","avi");
$video_load_application="\"Google Chrome\"";
$video_load_delay_between=180; // seconds between calls to flickr


// MARK Video Scan settings
$video_scan_base_path=$photo_download_base_path;
$video_scan_search_expression=$photo_download_search_expression;
$video_scan_ignorable=array();

// function push_notify($message) {
// 	$event="flickr_toolset";
// 	$key="your-ifttt-maker-key";
// 	curl_setopt_array($ch = curl_init(), array(
// 	  CURLOPT_URL => "https://maker.ifttt.com/trigger/$event/with/key/$key",
// 	  CURLOPT_POSTFIELDS => array(
// 		"value1" => "$message",
// 	  ),
// 	  CURLOPT_SAFE_UPLOAD => true,
// 	));
// 	curl_exec($ch);
// 	curl_close($ch);
// }