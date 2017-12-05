<?php
//require_once("common.inc.php");
require_once("OAuth.php");
require_once("AESHelper.php");
require_once("webSdkConfig.php");

/**
 * oauth服务客户端，聚合所有http接口调用
 * 注意： php运行环境必须安装Alternative PHP Cache (APC)
 */
class OAuthClient{
	public static $UdbkeyFreshPeriod = 900;         // 15分钟 ：900
	public static $UdbAclFreshPeriod = 900;         // 刷新权限控制信息周期：15分钟   900
	public static $udbkeyEmergentFreshPeriod = 180; // 检查紧急换udbkey周期：3分钟  180
	public static $ReportVersionPeriod = 43200;       // 上报版本号周期：12小时  3600*12=43200
	public static $forcechkTokenPeriod = 43200;       // 查询强制验token周期：12小时  3600*12=43200
	public static $isDebug = false;                //测试开关 true-打开， false-关闭
	
	private static $requestTokenURL = "http://lgn.yy.com/lgn/oauth/initiate.do";
	private static $authorizeURL    = "https://lgn.yy.com/lgn/oauth/authorize.do";
	private static $accessTokenURL  = "http://lgn.yy.com/lgn/oauth/token.do";
	private static $validAccessTokenURL = "http://lgn.yy.com/lgn/oauth/tokenValid.do";
	private static $writeCookieURL  =  "https://lgn.yy.com/lgn/oauth/wck_n.do";
	private static $writeCookieURI  =  "/lgn/oauth/wck_n.do";
	private static $deleteCookieURL = "http://lgn.yy.com/lgn/oauth/dck.do";
	
	private static $getSecureKeyURL = "http://lgn.yy.com/message/oauth/getSecurekey.do";
	private static $changeAccesstokenURL = "http://lgn.yy.com/message/oauth/changeAccesstoken.do";

	private static $isemergentURL     = "http://lgn.yy.com/message/isemergentSecurekey.do";
	private static $isforcechkURL     = "http://lgn.yy.com/message/isforcechkAccesstoken.do";	
	private static $reportversionURL  = "http://lgn.yy.com/message/rptver_new.do";	
	private static $getSecureKeyRsaURL= "http://lgn.yy.com/message/oauth/getSecurekey.do?method=rsa";
	private static $getSsoaclURL      = "http://lgn.yy.com/message/getssoacl.do";
	private static $isDiscardAESURL   = "http://lgn.yy.com/message/isDiscardAESCookie.do?appid=";
	
	function __construct($appid, $appkey) {

		$this->app_id = $appid;
		$this->app_key = $appkey;
		$this->sig_method = new OAuthSignatureMethod_HMAC_SHA1();
		$this->app_consumer = new OAuthConsumer($this->app_id, $this->app_key, NULL);	
		$this->errorinfo = NULL;	
	}

	/**
	 * getRequestToken
	 *
	 * oauth_token
     * oauth_token_secret
	 * 
	 */
	function getRequestToken($callbackUrl){
		if(empty($callbackUrl)){
			throw new OAuthException("callbackUrl is empty", -1);
		}
		$req_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$requestTokenURL);
		$req_req->set_parameter("oauth_callback", $callbackUrl);
		$req_req->sign_request($this->sig_method, $this->app_consumer, NULL);
		
		// 请求request_token
		$headers = array($req_req->to_header());
		$response = $this->__requestServer($headers, self::$requestTokenURL);
		if (!$response){
			return False;
		}
		parse_str($response,$rt);
		
		$oauth_token = $rt['oauth_token'];
		$oauth_token_secret = $rt['oauth_token_secret'];
		if(!empty($oauth_token) && !empty($oauth_token_secret) ){
			return new OAuthConsumer($oauth_token, $oauth_token_secret);
		}else{
			return False;
		}
	}
	
	function getAuthorizeURL($request_token, $denyCallbackUrl){
		$endpoint = self::$authorizeURL;
		$token = $request_token->key;
		$auth_url = $endpoint ."?oauth_token=$token&denyCallbackURL=".urlencode($denyCallbackUrl);
		return $auth_url;
	}

	/**
	 * getAccessToken
	 *
	 * oauth_token
     * oauth_token_secret
	 * 
	 */
	function getAccessToken($request_token, $oauth_verifier, &$oauth_mckey4cookie = NULL){//
		$acc_req = OAuthRequest::from_consumer_and_token($this->app_consumer, $request_token, "POST", self::$accessTokenURL);
		$acc_req->set_parameter("oauth_verifier", $oauth_verifier);
		$acc_req->sign_request($this->sig_method, $this->app_consumer, $request_token);
		
		// 请求access_token
		$headers = array($acc_req->to_header());
		$response = $this->__requestServer($headers, self::$accessTokenURL);
		if (!$response){
			return False;
		}
		parse_str($response,$rt);
		
		$this->access_token = $rt['oauth_token'];
		$this->access_token_secret = $rt['oauth_token_secret'];
		$this->uprofile_username = $rt['username'];
		$this->uprofile_yyuid = $rt['yyuid'];
		
		if (isset($oauth_mckey4cookie)){
			$oauth_mckey4cookie = $rt['oauth_mckey4cookie'];
		}
		if(!empty($this->access_token) && !empty($this->access_token_secret)){
			return new OAuthConsumer($this->access_token, $this->access_token_secret);
		} else {
			return False;
		}
	}
	
	function getUserProfile($accesstoken) {
		if(empty($accesstoken) || $accesstoken!=$this->access_token)
			return false;
	
		return array($this->uprofile_username, $this->uprofile_yyuid);
	}
	
	function validAccessToken($access_token, $yyuid, $username){
		 $this->errorinfo = NULL;
		if(empty($access_token) || (empty($yyuid) && empty($username))){
			$this->errorinfo = "parameter[access_token or yyuid] is empty";
			return False;
		}
		try {
			$parameters = array();
			if(!empty($yyuid)){
				$parameters["yyuid"] = $yyuid;
			}else{
				$parameters["username"] = $username;
			}
			$acc_req = OAuthRequest::from_consumer_and_token($this->app_consumer, $access_token, "POST", self::$validAccessTokenURL,$parameters);
			$acc_req->set_parameter("oauth_verifier", "0");
			$acc_req->sign_request($this->sig_method, $this->app_consumer, $access_token);
			// 请求access_token
			$headers = array($acc_req->to_header());
			$response = $this->__requestServer($headers, self::$validAccessTokenURL, $parameters);
			if (!$response){
				return False;
			}
			parse_str($response,$rt);
			if(isset($rt["validtoken"]) && $rt["validtoken"] == 1){
				return True;
			}
			$this->errorinfo = $response;
			return False;
		} catch (OAuthException $e){
//			var_dump($e);
			$this->errorinfo = $e->getMessage();
			return False;
		}
	}
	
	/**
	 * reqDomainArray 要写cookie的域名数组 
	 */
	function getWriteCookieURL($access_token, $yyuid, $oauth_mckey4cookie,$reqDomainArray){
		if(empty($access_token) or empty($yyuid) or empty($oauth_mckey4cookie)){
			return false;
		}
		$sig_key = $this->app_key.'_'.$access_token->secret;
		$sig_content = $this->app_id.'_'.$access_token->key.'_'.$oauth_mckey4cookie.'_'.urlencode($yyuid);
		$signature = base64_encode(hash_hmac('sha1', $sig_content, $sig_key, true));
		
		$reqDomainArray = $this->getDefaultDomainArray($reqDomainArray);
		$cookieURL = "https://".$reqDomainArray[0].self::$writeCookieURI;
		$cookieURL = $cookieURL.'?oauth_mckey4cookie='.$oauth_mckey4cookie.'&oauth_signature='.urlencode($signature);
		for ($i = 1;$i < count($reqDomainArray); $i++){
			$reqDomain = $reqDomainArray[$i];
			if($i == 1){
				$cookieURL = $cookieURL.'&reqDomainList='.$reqDomain;
			}else{
				$cookieURL = $cookieURL.','.$reqDomain;
			}
		}
		
		return $cookieURL;
	}
	
	/**
	 * 获取默认写cookie域名数组：array(DOMAIN_DUOWAN,DOMAIN_YY,DOMAIN_KUAIKUAI)
	 */
	function getDefaultDomainArray($reqDomainArray){
		if(count($reqDomainArray) > 0){
			return $reqDomainArray;
		}else{
			return array(DOMAIN_YY,DOMAIN_DUOWAN,DOMAIN_KUAIKUAI);
		}
	}
	
	function getDeleteCookieURL(){
		$timestamp = time()*1000;
		$sig_content = $this->app_id."_".$timestamp;
		$signature = base64_encode(hash_hmac('sha1', $sig_content, $this->app_key, true));
		
		$deleteCookieURL = self::$deleteCookieURL."?appid=".$this->app_id.'&oauth_mckey4cookie='.$timestamp.'&oauth_signature='.urlencode($signature);
		return $deleteCookieURL;
	}
	
	function callApi($access_token, $url, $username, $parameters){//
		if(empty($username)){
			return False;
		}
		
		$parameters = ($parameters) ?  $parameters : array();
		$parameters["username"] = $username;
		$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, $access_token, "POST", $url, $parameters);
		$api_req->set_parameter("oauth_verifier", "0");
		$api_req->sign_request($this->sig_method, $this->app_consumer, $access_token);
		
		// 请求api
		
		$headers = array($api_req->to_header());
		$response = $this->__requestServer($headers, $url, $parameters);
		if (!$response){
			return False;
		}
		parse_str($response,$rt);
		return $rt;
	}
	/**
	 * get AES udbkey,udbkey和secureKey是同一个概念
	 */
	function getSecureKey(){
		$parameters = array(
			"appid" => $this->app_id,
			"type" => SDK_TYPE,
			"version" => SDK_VERSION			
		);
		
		$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$getSecureKeyURL,$parameters);
		//$api_req->set_parameter("oauth_verifier", "0");
		$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
		
		// 请求api
		$headers = array($api_req->to_header());
		$response = $this->__requestServer($headers, self::$getSecureKeyURL, $parameters);
		if(self::$isDebug)echo "getSecureKey:".$response."<br />";
		if (!$response){
			return False;
		}
		if((strcasecmp($response, "-1") == 0)){
			$this->errorinfo = "yourIp no privilege to visit /message/oauth/getSecurekey.do";
			return false;
		}
		parse_str($response,$rt_ciper);
		if (empty($rt_ciper["securekeyinfo"])){
			return False;
		}
		
		$plaintext = AESHelper::decrypt($rt_ciper["securekeyinfo"], $this->app_key);
		$rt = explode(";", $plaintext);
		if(count($rt) < 3){
			return false;
		}
		return $rt;
	}
	/**
	 * get RSA udbkey,udbkey和secureKey是同一个概念
	 */	
	function getSecureKeyRsa(){
		$parameters = array(
			"appid" => $this->app_id,
			"type" => SDK_TYPE,
			"version" => SDK_VERSION			
		);
		
		$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$getSecureKeyRsaURL,$parameters);
		//$api_req->set_parameter("oauth_verifier", "0");
		$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
		
		// 请求api
		$headers = array($api_req->to_header());
		$response = $this->__requestServer($headers, self::$getSecureKeyRsaURL, $parameters);
		if(self::$isDebug)echo "getSecureKeyRsa:".$response."<br />";
		if (!$response){
			return False;
		}
		if((strcasecmp($response, "-1") == 0)){
			$this->errorinfo = "yourIp no privilege to visit /message/oauth/getSecurekey.do";
			return false;
		}
		parse_str($response,$rt_ciper);
		
		if (empty($rt_ciper["securekeyinfo"])){
			return False;
		}
		
		$plaintext = AESHelper::decrypt($rt_ciper["securekeyinfo"], $this->app_key);
		$rt = explode(";", $plaintext);
		if(count($rt) < 3){
			return false;
		}
		return $rt;
	}
	
	function changeAccesstoken($appid_in_cookie, $access_key_in_cookie, $yyuid_in_cookie){
		
		try {
			$parameters = array(
				"appid"                 => $this->app_id,
				"appidInCookie"         => $appid_in_cookie,
				"accesstokenInCookie"   => $access_key_in_cookie,
				"usernameInCookie"      => $yyuid_in_cookie,
			);
			
			$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$changeAccesstokenURL,$parameters);
			$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
			
			// 请求api
			$headers = array($api_req->to_header());
			$response = $this->__requestServer($headers, self::$changeAccesstokenURL, $parameters);
			
			if (!$response){
				return False;
			}
			parse_str($response,$raw_rt);
			if (empty($raw_rt["tokeninfo"])){
				return False;
			}
			$rt = explode(":", $raw_rt["tokeninfo"]);
			return $rt;
		} catch (Exception $e){
//			var_dump($e);
			return False;
		}
	}

	/**
	 * 检查是否要紧急更新udbkey，每3分钟询问一次后端服务
	 */
	function isemergentSecureKey(){
		$storeKey = "sdk_isemergent_flag";
		$storeValue = apc_fetch($storeKey); 
		if(empty($storeValue)){
			apc_store($storeKey, "1", self::$udbkeyEmergentFreshPeriod);
			try {
				$parameters = array(
					"appid" => $this->app_id
				);
				$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$isemergentURL,$parameters);
				$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
				
				// 请求api
				$headers = array($api_req->to_header());
				$response = $this->__requestServer($headers, self::$isemergentURL, $parameters);
				if(self::$isDebug) echo "isemergentSecureKey:".$response."<br />";
				if (!$response){
					return false;
				}
				if(strcasecmp($response, "true") == 0){
					return true;
				}
			} catch (Exception $e){
				return false;
			}	
			return false;
		}else{
			return false;
		}
	}
	/**
	 * 检查是否要强制验证accesstoken时效性，每12小时询问一次后端服务
	 */
	function isforcechkAccesstoken(){
		$storeKey = "sdk_isforcechk_flag";
		$storeValue = apc_fetch($storeKey); 
		$infoKey = "sdk_isforcechk_info";
		if(empty($storeValue)){
			apc_store($storeKey, "1", self::$forcechkTokenPeriod);//每12小时查询1次后端服务
			$infoValue = apc_fetch($infoKey);
			if(empty($infoValue)){
				$infoValue = "false";
			}
			try {
				$parameters = array(
					"appid" => $this->app_id
				);
				$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$isforcechkURL,$parameters);
				$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
				
				// 请求api
				$headers = array($api_req->to_header());
				$response = $this->__requestServer($headers, self::$isforcechkURL, $parameters);
				if(self::$isDebug) echo "isforcechkAccesstoken:".$response."<br />";
				if (!$response){
					$infoValue = "false";
				}else if(strstr($response, "true")){
					$infoValue = "true";
				}
			} catch (Exception $e){
			}
			apc_store($infoKey, $infoValue);
			return (strcasecmp($storeValue, "true") == 0);
		}else{
			return (strcasecmp(apc_fetch($infoKey), "true") == 0);
		}
		
	}
	/**
	 * 上报sdk版本信息,每12小时上报一次
	 */
	function reportVersion($useWhoValid){
		$storeKey = "sdk_rptver_flag";
		$storeValue = apc_fetch($storeKey); 
		if(empty($storeValue)){
			apc_store($storeKey, "1", self::$ReportVersionPeriod);//每12小时上报一次
			try {
				$cookieEncryptType =($useWhoValid == 2)?"RSA":"AES";
				$parameters = array(
					"appid" => $this->app_id,
					"type" => SDK_TYPE,
					"version" => SDK_VERSION,
					"cookieEncryptType" => $cookieEncryptType
				);
				$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$reportversionURL,$parameters);
				$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
				
				$headers = array($api_req->to_header());
				$response = $this->__requestServer($headers, self::$reportversionURL, $parameters);
				if(self::$isDebug)echo "reportVersion:".$response."<br />";
				if (!$response){
					return false;
				}
			} catch (Exception $e){
				return false;
			}
			return true;
		}else{
			return true;
		}
	}
	
	/**
	 * 查询权限控制信息,返回数组{verid,黑名单列表,白名单列表,废弃AES标记}
	 */
	public function getSsoacl(){
		
		$storeKey =  "sdk_getssoacl_flag";
		$storeValue = apc_fetch($storeKey); 
		$infoKey = "sdk_getssoacl_info";
		if(empty($storeValue)){
			apc_store($storeKey, "1", self::$UdbAclFreshPeriod);//每15分钟查询1次后端服务
			$infoValue = apc_fetch($infoKey); 
			
			$ssoaclBean = null;//存储SsoAclBean
			$lastVersion = "";
			if(!empty($infoValue)){
				$ssoaclBean = unserialize($infoValue);
				$lastVersion = $ssoaclBean ->verid;
			}
			try {
				$ssoacl = $this->__querySsoacl($lastVersion);
				$discardAESCookie = $this->__queryDiscardAESCookie();
				if($ssoacl && count($ssoacl) >= 3 && strcmp($ssoacl[0],$lastVersion) != 0){
					$ssoaclBean = new SsoAclBean();
					$ssoaclBean ->verid = $ssoacl[0];
					$ssoaclBean ->denySSOAppid = $ssoacl[1];
					$ssoaclBean ->allowSSOAppid = $ssoacl[2];
					$ssoaclBean ->discardAES = $discardAESCookie;
				}
			} catch (Exception $e){
				//查询权控失败，继续使用apc缓存的信息
			}
			apc_store($infoKey, serialize($ssoaclBean));
			return $ssoaclBean;
		}else {
			$infoValue = apc_fetch($infoKey); 
			if(!empty($infoValue)){
				return unserialize($infoValue);
			}else{
				return false;
			}
		}
	}	
		/**
	 * 检查是否废弃AES密钥，0-不废弃，1-废弃
	 */
	private function __querySsoacl($lastVersion){
		$parameters = array(
			"appid" => $this->app_id,
			"verid" => $lastVersion
		);
		$api_req = OAuthRequest::from_consumer_and_token($this->app_consumer, NULL, "POST", self::$getSsoaclURL,$parameters);
		$api_req->sign_request($this->sig_method, $this->app_consumer, NULL);
		
		// 请求api
		$headers = array($api_req->to_header());
		$response = $this->__requestServer($headers, self::$getSsoaclURL, $parameters);
		if(self::$isDebug)echo "getSsoacl:".$response."<br />";
		if (!$response){
			return false;
		}
		parse_str($response,$rt_ciper);
		
		if (empty($rt_ciper["cookieacl"])){
			$this->errorinfo = $rt_ciper["errMsg"];
			return false;
		}
		/*
		 * 格式：verid;黑名单列表;白名单列表;*
		 *  黑白名单列表采用英文逗号","分隔，特殊者为all
		 */
		$plaintext = AESHelper::decrypt($rt_ciper["cookieacl"], $this->app_key);
		if(self::$isDebug)echo "getSsoacl plaintext:".$plaintext."<br />";
		$rt = explode(";", $plaintext);
		if(count($rt) < 3){
			return false;
		}
		return $rt;
	}
	
	/**
	 * 检查是否废弃AES密钥，0-不废弃，1-废弃
	 */
	private function __queryDiscardAESCookie(){
		$parameters = array();
		$headers = array();
		$result = "0";
		$response = $this->__requestServer($headers, self::$isDiscardAESURL.$this->app_id, $parameters);
		if(self::$isDebug) echo "__queryDiscardAESCookie:".$response."<br />";
		if ($response && strcmp($response,"1") == 0){
			$result = "1";
		}
		return $result;
	}
	
	private function __requestServer($headers, $url, $parameters = array()){
		// echo "headers:".$headers[0]."<br />";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		//设置curl默认访问为IPv4
		if(defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
			curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		}
				
		 $SSL = substr($url, 0, 8) == "https://" ? true : false;  
		if($SSL){
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书  
        	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); // 检查证书中是否设置域名  
		}
		
		$response = curl_exec($ch);
		$curl_errno=curl_errno($ch); 
		$curl_error=curl_error($ch);		
		$http_code        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$http_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		
		if($curl_errno > 0){
			$this->errorinfo = "call http fail errno:".$curl_errno.", errmsg:".$curl_error;
			throw new OAuthException("call http fail errno:".$curl_errno.", errmsg:".$curl_error, -1);
		}
		if(empty($http_code)){
			$this->errorinfo = "no http code was returned";
			throw new OAuthException("no http code was returned", -1);
		}
		
		if($http_code != 200){
			$http_header = substr($response, 0,strpos($response,"\n"));
			list($protocal, $http_code, $http_code_message) = explode(' ', $http_header, 3);
			throw new OAuthException($http_code_message, $http_code);
		}
		$response = substr($response, $http_header_size);
		return $response;
	}
}

?>
