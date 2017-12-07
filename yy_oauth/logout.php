<?php
require_once(dirname(__FILE__)."/yy_oauth.php");
//删除yy登陆的公共cookie
echo '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js" type="text/javascript"></script>';
echo '<script src="http://res.udb.duowan.com/lgn/js/oauth/udbsdk/pcweb/udb.sdk.pcweb.popup.min.js"></script>';
$yy_oauth = new yy_oauth();
$deleteCookieURL = $yy_oauth->getDeleteCookieURL();
if(!empty($deleteCookieURL)){
	echo '<script type="text/javascript">UDB.sdk.PCWeb.deleteCrossmainCookie("'.$deleteCookieURL.'");</script>';
}