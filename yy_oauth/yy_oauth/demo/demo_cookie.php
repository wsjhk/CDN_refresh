<!DOCTYPE html>
<html lang="zh-cn">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Language" content="zh-cn" />
</head>
<body>
<?php
try {

	require_once ('config.php');
	require_once ('../sdk/app/OAuthCookieClient.php');
	require_once ('../sdk/app/OAuthCache.php');

	$user_agent = $_SERVER['HTTP_USER_AGENT'];
	$domain     = $_SERVER["HTTP_HOST"];
	
	$cookieClient = new OAuthCookieClient($appid, $appkey, $_COOKIE, $user_agent, $domain, new OAuthCache_File($key_file));
	if($cookieClient->validate()){
		echo "validate cookie success<br />";
		echo "username:". $cookieClient->getUserName()."<br />";
		echo "yyuid:". $cookieClient->getYYUID()."<br />";
		echo "access_token:". $cookieClient->getAccessToken()."<br />";
		echo "acctInfo:". $cookieClient->getAcctinfo()."<br />";
		echo "accttoken:". $cookieClient->getAccttoken()."<br />";
	} else {
		echo "validate cookie failed <br />";
		echo "errorinfo:". $cookieClient->getErrorinfo()."<br />";
	}
	// 打印cookie
	$cookie = array_change_key_case($_COOKIE, CASE_LOWER );
	if(isset($cookie['username'])){
		echo "username:".$cookie['username'];
		echo "<br />";
	}
	if(isset($cookie['oauthcookie'])){
		echo "oauthCookie:".$cookie['oauthcookie'];
		echo "<br />";
	}
	if(isset($cookie['udb_l'])){
		echo "acctinfo:".$cookie['udb_l'];
		echo "<br />";
	}
	if(isset($cookie['udb_n'])){
		echo "accttoken:".$cookie['udb_n'];
		echo "<br />";
	}	
} catch (Exception $e){
	var_dump($e);
}
	
?>
</body>
</html>