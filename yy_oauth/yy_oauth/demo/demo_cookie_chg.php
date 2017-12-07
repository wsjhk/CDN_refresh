<!DOCTYPE html>
<html lang="zh-cn">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Language" content="zh-cn" />
</head>
<body>
<?php
//使用 http://cookie2.yy.com/oauth-php/demo/demo_cookie_chg.php访问改文件，做换accessToken测试
try {

	require_once ('config.php');
	require_once ('../sdk/app/OAuthCookieClient.php');
	require_once ('../sdk/app/OAuthCache.php');

	$user_agent = $_SERVER['HTTP_USER_AGENT'];
	$domain     = $_SERVER["HTTP_HOST"];
	//测试换accesstoken的场景
	$cookieClient2 = new OAuthCookieClient("1", "362F807B2CE16EB5CC8B625E9CB78683", $_COOKIE, $user_agent, $domain, new OAuthCache_File($key_file));
	if($cookieClient2->validate()){
		echo "validate cookie success<br />";
		echo "username:". $cookieClient2->getUserName()."<br />";
		echo "yyuid:". $cookieClient2->getYYUID()."<br />";
		echo "access_token:". $cookieClient2->getAccessToken()."<br />";
		echo "acctInfo:". $cookieClient2->getAcctinfo()."<br />";
		echo "accttoken:". $cookieClient2->getAccttoken()."<br />";
	} else {
		echo "validate cookie failed<br />";
		echo "errorinfo:". $cookieClient2->getErrorinfo()."<br />";
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