<?php
require_once ('config.php');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Language" content="zh-cn" />
<title>弹层模式</title>
<!-- 嵌入sdk所需样式和Javascript文件  -->
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
<script type="text/javascript" src="http://res.udb.duowan.com/lgn/js/oauth/udbsdk/pcweb/udb.sdk.pcweb.popup.min.js"></script>


<script type="text/javascript">
	 //sdk模式登录的要点在于：
	 //①响应用户登录请求事件，唤醒udb请求登录，在唤醒中需要通信udb，获取a.请求token，b.登录url；
	 //②响应用户登录成功回调事件，在响应中需要通信udb，获取a.访问token，b.用户信息，c.写cookie连接（可完成写公共域cookie）；
	 function sdklogin() {
		 UDB.sdk.PCWeb.popupOpenLgn(
			'http://cookie.yy.com/oauth-php/demo/getAuthorizeURL.php',
			'http://cookie.yy.com/oauth-php/demo/callback.php', 
			'http://cookie.yy.com/oauth-php/demo/denyCallback.php');			
	 }
	 
	 //删除cookie的要点在于：
	 //①获取到删除cookie的连接；
	 //②调用UDB.sdk.QLogin.deleteCrossmainCookieWithCallBack；
	 //③在②的callback中跳转；
	 //注：
	 //①关于具体如何到达删除cookie，是自己系统而定，这里只是给出一个简单样例，便于展示；
	 //②在真实系统中，您也可以直接将删除cookie融合到您的退出操作中，在您退出的后台动作中，发出删除cookie的请求
	 function deleteCookie() {
		var getDelCookieURL = 'getDelCookieURL.php';
		$.post(
			getDelCookieURL, 
			function(data) {
				if("1" != data.success) {
					alert(data.errMsg);
					return;
				}
				UDB.sdk.PCWeb.deleteCrossmainCookieWithCallBack(
						data.delCookieURL,
						function(){
							alert('delete cookie done.you could refresh or redict.');
							top.location.href="demo.php";
						}
				);
			},
			"json");
	 }
</script>

</head>
<body>
<div align="center">
<?php
	session_start();
	if (isset($_SESSION[USERNAME]) && isset($_SESSION[ACCESS_TOKEN])){
		$username       = $_SESSION[USERNAME];
		$uid            = $_SESSION[YYUID];
		$access_token   = $_SESSION[ACCESS_TOKEN];
		$writeCookieURL = $_SESSION[WRITECOOKIEURL];
		unset($_SESSION[WRITECOOKIEURL]);
	}
	
	// 打印cookie
	$cookie = array_change_key_case($_COOKIE, CASE_LOWER );
	if(isset($cookie['username'])){
		echo "username:".$cookie['username'];
		echo "<br />";
	}
	if(isset($cookie['password'])){
		echo "password:".$cookie['password'];
		echo "<br />";
	}
	if(isset($cookie['osinfo'])){
		echo "osinfo:".$cookie['osinfo'];
		echo "<br />";
	}
	if(isset($cookie['udbloginflag'])){
		echo "udbloginflag:".$cookie['udbloginflag'];
		echo "<br />";
	}
	if(isset($cookie['oauthcookie'])){
		echo "oauthCookie:".$cookie['oauthcookie'];
		echo "<br />";
	}
	if(isset($cookie['oauthcookieprivate'])){
		echo "oauthCookiePrivate:".$cookie['oauthcookieprivate'];
		echo "<br />";
	}
	
?>
<?php
if(empty($username) || empty($access_token)){
?>
<p>
	演示本 demo 时候请注意：http://cookie.yy.com/oauth-php/ 应是可用的！请根据本地环境调整！
</p>
<p>
	<button onclick="sdklogin();">
		<h2><font style="font-size:18px;color:blue">登录&gt;&gt;</font></h2>
	</button>	
	<button onclick="deleteCookie();">
		<h2><font style="font-size:18px;color:blue">测试退出 &gt;&gt;</font></h2>
	</button>
</p>
<?php
} else {
	
	echo '<button onclick="deleteCookie();"><font style="font-size:14px;color:red">删除COOKIE&gt;&gt;</font></button><br/><br/><hr/>';
	
	echo "<b>登录成功</b><br />";
	echo "<font color='green'>username</font> -> ".$username."<br />";
	echo "<font color='blue'>uid</font> -> ".$uid."<br />";
	echo "<font color='blue'>token</font> -> ".$access_token->__toString()."<br />";
	echo "<font color='blue'>cookieUrl</font> -> ".$writeCookieURL."<br /><hr/>";
	//getEmailByUsername($username, $access_token)."<br />";
	//getUserinfoByUsername($username, $access_token)."<br />";
}
?>
<div></div>
<div id="login_info"></div>
<div style="width:100%; height:600px;">
	sdk demo 测试
</div>

</div>

<?php
	// 方便测试, 登录后只一次生效
	session_unset(); 
?>
<!-- 写cookie请求  -->
<script type="text/javascript">
	var username = "";
	if(username != null && username != "") {
		alert("检测到cookie，并通过校验，可认为该用户["+username+"]已经登录了！");
		var msgDiv = document.getElementById("login_info");
		msgDiv.innerHTML = "<font color=\"red\">我已经登录了哦.["+username+"]</font><br>[accesstoken:${accesstoken},tokensecret:${tokensecret}]";
	}
</script>
</body>

</html>
