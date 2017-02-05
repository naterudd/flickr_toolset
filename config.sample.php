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

$photo_download_email_from=str_replace("@", "+downloader@", $email_address); // or a specific address "downloader@doamin.com";
$photo_download_email_to=str_replace("@", "+downloader@", $email_address); // or a specific address "myaddress@doamin.com";, or the above variable $email_address;


// MARK Photo upload settings
$photo_upload_base_path="upload/photos/";

$photo_upload_email_from=str_replace("@", "+uploader@", $email_address); // or a specific address "uploader@doamin.com";
$photo_upload_email_to=str_replace("@", "+uploader@", $email_address); // or a specific address "myaddress@doamin.com";, or the above variable $email_address;
