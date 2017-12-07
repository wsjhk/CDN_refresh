<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>to delete public domains' cookies</title>
<!-- 嵌入sdk所需样式和Javascript文件  -->
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js" type="text/javascript"></script>
<script src="http://res.udb.duowan.com/lgn/js/oauth/udbsdk/pcweb/udb.sdk.pcweb.popup.min.js"></script>

</head>
<body>
<?php
require_once ('config.php');
$uclient = new OAuthClient($appid, $appkey);
$deleteCookieURL = $uclient->getDeleteCookieURL();

if(!empty($deleteCookieURL)){
	echo '<script type="text/javascript">UDB.sdk.PCWeb.deleteCrossmainCookie("'.$deleteCookieURL.'");</script>';
}

?>

<p align="center"><h4><font style="font-size:18px;color:blue">注销,清除公共域COOKIE</font></h4></p>
</body>
</html>