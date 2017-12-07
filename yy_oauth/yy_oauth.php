<?php
require_once dirname(__FILE__).'/yy_oauth/sdk/app/OAuthClient.php';
require_once dirname(__FILE__).'/yy_oauth/sdk/app/webSdkConfig.php';
define("REQUEST_TOKEN", "REQUEST_TOKEN");
define("ACCESS_TOKEN", "ACCESS_TOKEN");
define("USERNAME", "USERNAME");
define("YYUID", "YYUID");
define("WRITECOOKIEURL", "WRITECOOKIEURL");

class yy_oauth
{
	private $appid = '5855';
	private $appkey = 'HBzcXYseDk5ZDd6ve7fiWKbCwAFahhb1';
	private $key_file = '';
	private $callbackURL = 'http://resource.gop.yy.com:5000/yy_cdnoauth';//登陆回调url
	private $denyCallbackURL = 'http://resource.gop.yy.com:5000/yy_denyCallback';//退出url

	//生成authorizeurl
	public function  getAuthorizeURL(){		
		
		try {
			$retval = NULL;
			$uclient = new OAuthClient($this->appid, $this->appkey);
			$request_token = $uclient->getRequestToken($this->callbackURL);
			if(!$request_token){
				$retval = array(
					"success" => "0",
					"errMsg" => "get request token failed!"
				);
				return $retval;
			}
			
			$url = $uclient->getAuthorizeURL($request_token, $this->denyCallbackURL);
			if(!$url){
				$retval = array(
					"success" => "0",
					"errMsg" => "get authorize url failed!"
				);
				return $retval;
			}
			
			$_SESSION[REQUEST_TOKEN] = serialize($request_token);
			file_put_contents(dirname(__FILE__).'/session/review_session.txt',serialize($request_token));
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


	//回调 或许对应的信息
	public function  callback($oauth_token, $oauth_verifier){
		$msg['status'] = 0;
		$msg['msg'] = 'false';
		if(empty($oauth_token)){
			$msg['msg'] = "wrong callback";
			return $msg;
		}

		$request_token = unserialize($_SESSION[REQUEST_TOKEN]);
		$request_token = unserialize(file_get_contents(dirname(__FILE__).'/session/review_session.txt'));
		if($oauth_token != $request_token->key){
			$msg['msg'] =  "mismatch callback";
			return $msg;
		}
		$message = ""; 
		try {
			$uclient = new OAuthClient($this->appid, $this->appkey);

			// for writecookie
			$oauth_mckey4cookie = '';
			$access_token = $uclient->getAccessToken($request_token, $oauth_verifier, $oauth_mckey4cookie);
			if(!$access_token){
				$msg['msg'] = "get access token failed.";
				return $msg;
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
			/*echo "<html>";
			echo "<head>";
			echo "<script language=\"JavaScript\" type=\"text/javascript\">";
			echo "function udb_callback(){self.parent.UDB.sdk.PCWeb.writeCrossmainCookieWithCallBack('".$writeCookieURL."',function(){self.parent.document.location.reload(-1);});};udb_callback();";
			echo "</script>";
			echo "</head>";
			echo "<body>";
			echo "</body></html>";*/
			$msg['status'] = 1;
			$msg['msg'] = $profile;
			return $msg;

		} catch (OAuthException $e){
			$msg['msg'] = $message.$e->getMessage();
			return $msg;
		}
	}

	//取消响应放弃登录时关闭
	public function denyCallback(){
		echo "<html>";
		echo "<head>";
		echo "<script language=\"JavaScript\" type=\"text/javascript\">";
		echo "self.parent.UDB.sdk.PCWeb.popupCloseLgn();";
		echo "</script>";
		echo "</head>";
		echo "<body>";
		echo "</body></html>";
	}

	//获取退出url
	public function getDeleteCookieURL(){
		$uclient = new OAuthClient($this->appid, $this->appkey);
		return $uclient->getDeleteCookieURL();
	}
	
	


}
