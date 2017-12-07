<?php
require_once('config.php');

$callbackURL = $_REQUEST["callbackURL"];
$denyCallbackURL = $_REQUEST["denyCallbackURL"];

// $callbackURL = "http://127.0.0.1/oauth/demo/callback_1.php?xparam=1";
// $denyCallbackURL = "http://127.0.0.1/oauth/demo/callback_1.php?cancel=1";
session_start();

function getAuthorizeURL($callbackURL, $denyCallbackURL){
	global $appid;
	global $appkey;
	
	try {
		$retval = NULL;
		$uclient = new OAuthClient($appid, $appkey);
		$request_token = $uclient->getRequestToken($callbackURL);
		if(!$request_token){
			echo "get request token failed!";
			$retval = array(
				"success" => "0",
				"errMsg" => "get request token failed!"
			);
			return $retval;
		}
		
		$url = $uclient->getAuthorizeURL($request_token, $denyCallbackURL);
		if(!$url){
			echo "get authorize url failed!";
			$retval = array(
				"success" => "0",
				"errMsg" => "get authorize url failed!"
			);
			return $retval;
		}
		
		$_SESSION[REQUEST_TOKEN] = $request_token;
		$retval = array(
			"success" => "1",
			"url" => $url
		);
	} catch (OAuthException $e){
		$retval = array(
				"success" => $e->getCode(),
				"errMsg" => $e->getMessage()
			);
	}
	
	return $retval;
}

$url = json_encode(getAuthorizeURL($callbackURL, $denyCallbackURL));
echo $url;
?>