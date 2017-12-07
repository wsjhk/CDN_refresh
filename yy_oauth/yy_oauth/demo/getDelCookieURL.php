<?php
require_once ('config.php');

$uclient = new OAuthClient($appid, $appkey);
$deleteCookieURL = $uclient->getDeleteCookieURL();

$retval = NULL;
if(!empty($deleteCookieURL)){
	$retval = array(
		"success" => "1",
		"delCookieURL" => $deleteCookieURL
	);
}else{
	$retval = array(
		"success" => "0",
		"errMsg" => "get deleteCookieURL failed!"
	);
}

$json = json_encode($retval);

echo $json;
?>