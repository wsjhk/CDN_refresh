<?php
require_once('config.php');
require_once '../sdk/app/webSdkConfig.php';
$oauth_token     = $_REQUEST["oauth_token"];
$oauth_verifier  = $_REQUEST["oauth_verifier"];

/*
	2012-07-25号后失效，$_REQUEST 中得不到对应值
	$username        = $_REQUEST["username"];
	$uid             = $_REQUEST["yyuid"];
*/

if(empty($oauth_token)){
	echo "wrong callback";
	exit(1);
}

session_start();
$request_token = $_SESSION[REQUEST_TOKEN];
if($oauth_token != $request_token->key){
	echo "mismatch callback";
	exit(1);
}
$message = ""; 
try {
	$uclient = new OAuthClient($appid, $appkey);

	// for writecookie
	$oauth_mckey4cookie = '';
	$access_token = $uclient->getAccessToken($request_token, $oauth_verifier, $oauth_mckey4cookie);
	if(!$access_token){
		echo "get access token failed.";
		exit(1);
	}
	$message = $message."getAccessToken success access_token=$access_token; \n";
	// 获取 username， yyuid 的新方法
	$profile = $uclient->getUserProfile($access_token->key);
	$username = $profile[0]; //将废除username，强烈推荐使用uid（即yyuid）
	$uid = $profile[1];
	$message = $message."getUserProfile success $uid $username ;\n";
	
	//要写cookie的域名数组，取值来自webSdkConfig.php域名常量定义,如:DOMAIN_DUOWAN、DOMAIN_YY、DOMAIN_YY_CLOUDS， 
	//数组为空时默认写yy、duowan、kuaikuai三个域名的cookie.  特别注意：业务系统域名所属顶级域名请放在首位
	$reqDomainArray = array(DOMAIN_YY,DOMAIN_DUOWAN);
	$writeCookieURL = $uclient->getWriteCookieURL($access_token, $uid, $oauth_mckey4cookie,$reqDomainArray);
	
	unset($_SESSION[REQUEST_TOKEN]);
	$_SESSION[ACCESS_TOKEN] = $access_token;
	$_SESSION[USERNAME]     = $username;
	$_SESSION[YYUID]        = $uid;
	$_SESSION[WRITECOOKIEURL] = $writeCookieURL;
	
	// 写 cookie js
	echo "<html>";
	echo "<head>";
	echo "<script language=\"JavaScript\" type=\"text/javascript\">";
	echo "function udb_callback(){self.parent.UDB.sdk.PCWeb.writeCrossmainCookieWithCallBack('".$writeCookieURL."',function(){self.parent.document.location.reload(-1);});};udb_callback();";
	echo "</script>";
	echo "</head>";
	echo "<body>";
	echo "</body></html>";
} catch (OAuthException $e){
	var_dump($e);
	echo "Caught exception: ".$message, $e->getMessage();
}
?>
