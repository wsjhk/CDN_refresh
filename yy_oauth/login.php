<?php
require_once(dirname(__FILE__)."/yy_oauth.php");

array_shift($argv);
foreach($argv as $k => $v){
	list($key, $val) = explode('=', $v);
	$$key = $val;
}
isset($do) ? '' : $do = '';
isset($oauth_token) ? '' : $oauth_token = '';
isset($oauth_verifier) ? '' : $oauth_verifier = '';
if( $do == 'yy_oauth'){
	$yy_oauth = new yy_oauth();
	$oauth_info =  $yy_oauth->callback($oauth_token, $oauth_verifier);
	if ($oauth_info['status'] == 0 ) {
		echo $oauth_info['msg'];
		exit;
	}
	$yy_user = $oauth_info['msg'];
	$yyuid = $yy_user[1];
	echo $yy_user[0].'-'.$yy_user[1];
        exit;
}
//放弃响应
elseif( $do == 'yy_denyCallback'){
	$yy_oauth = new yy_oauth();
	$yy_user =  $yy_oauth->denyCallback();
	exit;
}
 else {
	//采用yy登陆整合
	$yy_oauth = new yy_oauth();
	$authrizeurl =  $yy_oauth->getAuthorizeURL();
	$login_url = $authrizeurl['url'];
	echo $login_url;
	exit;
}

//$smarty->display("login.html");
