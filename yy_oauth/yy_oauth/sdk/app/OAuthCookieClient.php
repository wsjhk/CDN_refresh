<?php
require_once ('OAuthUdbKey.php');
require_once ('OAuthClient.php');
require_once ('OAuthUdbKeyMgr.php');
require_once ('OAuthUdbKeyRsaMgr.php');
require_once ('AESHelper.php');
require_once ('rsa.class.php');

/**
 * cookie 登录态验证客户端
 */
class OAuthCookieClient{
	function __construct($appid, $appkey, $cookie, $user_agent, $domain, $cache) {
		$this->validated = NULL;
		$this->access_token = NULL;
		
		$this->app_id = $appid;
		$this->app_key = $appkey;
		$this->user_agent = $user_agent;
		$this->domain = $domain;
		
		$this->udb_key_mgr = new OAuthUdbKeyMgrAES($appid, $appkey, $cache);
		$this->udb_key_rsa_mgr = new OAuthUdbKeyMgrRSA($appid, $appkey, $cache);
		$this->oauth_client = new OAuthClient($appid, $appkey);
		
		$this->username = NULL;
		$this->yyuid    = NULL;
		$this->oauth_cookie = NULL;
		$this->oauth_cookie_private = NULL;
		$this->oauth_udb_key = NULL;
		$this->oauth_udb_key_rsa = NULL;
		$this->acctinfo = NULL;
		$this->accttoken = NULL;
		$this->errorinfo = NULL;
		$this->oauth_cookie_new = NULL;
		$this->useWhoValid = NULL;    //1-oauthCookieOld(AES);2-oauthCookieNew(RSA);
		$this->appid_in_cookie = NULL; 
		$this->lgnSecLevel = NULL; 
		$this->ip = NULL; 
		$this->timestamp = NULL; 
		$this->mac = NULL; 
		$this->extinfo = NULL; 
		$this->isRemMe = "0"; //标示是否记住密码： 1-记住密码，0-不记住
		
		$cookie = array_change_key_case($cookie, CASE_LOWER );
		
		if(isset($cookie['oauthcookie'])){
			$this->oauth_cookie = $cookie['oauthcookie'];
		}
		if(isset($cookie['udb_l'])){
			$this->acctinfo = $cookie['udb_l'];
		}
		if(isset($cookie['udb_n'])){
			$this->accttoken = $cookie['udb_n'];
		}
		if(isset($cookie['yyuid'])){
			$this->yyuid = $cookie['yyuid'];
		}
		if(isset($cookie['username'])){
			$this->username = $cookie['username'];
		}
		if(isset($cookie['udb_oar'])){
			$this->oauth_cookie_new = $cookie['udb_oar'];
		}
	}
	
	public function validate(){
		// 已校验通过，直接返回true
		if (isset($this->validated)){
			return $this->validated;
		}
		
		if($this->oauth_client->isemergentSecureKey()){
			$this->udb_key_rsa_mgr->refreshSecureKey(); 
		}
		
		$this->validated = false;
		//step 1: check parameters
		if(!$this->__checkParameters()){
			return false;
		}
		$validResult = false;
		
		if( !$this->__isDiscardAESCookie() ){
			//step 2: get legal oauth AES udbkey
			$this->oauth_udb_key = $this->udb_key_mgr->getSecureKey();
			if(empty($this->oauth_udb_key) || !$this->oauth_udb_key->isLegalNew()){
				$this->__appendErrorInfo("get AES udbkey fail ".$this->udb_key_mgr->errorinfo);
				return false;
			}	
			
			//step 3: try to verify oauthcookieold by AES udbkey if it is not empty
			if(!empty($this->oauth_cookie)){
				if($this->__oauthUdbValidate()){
					$validResult = true;
					$this->useWhoValid = 1;
				}else{
					$this->__appendErrorInfo("old oauthcookie validate fail");
				}
			}			
		}
		
		//step 4: get legal oauth RSA udbkey
		$this->oauth_udb_key_rsa = $this->udb_key_rsa_mgr->getSecureKey();
		if(empty($this->oauth_udb_key_rsa) || !$this->oauth_udb_key_rsa->isLegalNew()){
			$this->__appendErrorInfo("get RSA udbkey fail ".$this->udb_key_rsa_mgr->errorinfo);
			return false;
		}
		//step 5: try to verify oauthcookienew by RSA udbkey if it is not empty
		if(!$validResult && !empty($this->oauth_cookie_new)){
			if($this->__oauthUdbValidateNew()){
				$validResult = true;
				$this->useWhoValid = 2;
			}else{
				$this->__appendErrorInfo("new oauthcookie validate fail");
			}
		}
		
		//step 6: ssoACLCheck权控验证 
		if($validResult){
			$validResult = $this->__ssoACLCheck();
		}
		
		if(!$validResult){
			return false;
		}
		$this->validated = true;
		
		//step 7: trigger to report version 
		$this->oauth_client->reportVersion($this->useWhoValid);		
		return true;
	}
	
	/**
	 * 验证cookie的同时，强制调用UDB后端服务验证accessToken时效性
	 * 敏感操作之前必须调用该方法验证
	 * 返回 true-有效，false-无效
	 */
	public function validAccessToken(){
		if($this->validate()){
			if($this->oauth_client->validAccessToken($this->access_token, $this->yyuid,$this->username)){
				return true;
			}else{
				$this->errorinfo = $this->oauth_client->errorinfo;
				return false;
			}
		}else{
			return false;
		}
	}
	/**
	 * 验证cookie的同时，增加验证当前消费ip和登录ip是否在同一C段
	 * 若登录ip或消费ip为空，则默认验证通过
	 * 返回 true-通过，false-失败
	 */
	public function validateEnhance(){
		if(!$this->validate()) return false;
		$c_ip = $this->__getIp();
		$s_c_ip = $c_ip;
		if(!empty($c_ip) && strstr($c_ip,".") && !empty($this->ip) && strstr($this->ip,".")){
			$c_ip = substr($c_ip,0,strrpos($s_c_ip,"."));
			$lgn_ip = substr($this->ip,0,strrpos($this->ip,"."));
			if(strcasecmp($c_ip, $lgn_ip) == 0) {
				return true;	
			} else {
				$this->__appendErrorInfo("user consumeip[".$s_c_ip."] unequal with lgnip[".$this->ip."] in c_phase");
				return false;
			}
		}else {
			return true;
		}
	}
	
	public function getUserName(){
		if($this->validated && !empty($this->username) && strcasecmp($this->username, "*") != 0){
			return $this->username;
		}
		return false;
	}
	
	public function getYYUID(){
		if($this->validated && !empty($this->yyuid)){
			return $this->yyuid;
		}
		return false;
	}
	public function getAcctinfo(){
		if(!empty($this->acctinfo)){
			return $this->acctinfo;
		}
		return false;
	}
	public function getAccttoken(){
		if(!empty($this->accttoken)){
			return $this->accttoken;
		}
		return false;
	}	
	public function getAccessToken(){
		if(!empty($this->access_token)){
			return $this->access_token;
		}
		return false;
	}
	public function getErrorinfo(){
		if(!empty($this->errorinfo)){
			return "appid=".$this->app_id.";yyuid=".$this->yyuid.";username=".$this->username.";oauthCookie=".
					$this->oauth_cookie.";oauthCookieNew=". $this->oauth_cookie_new.";udb_l=". 
					$this->acctinfo.";udb_n=". $this->accttoken.";errorinfo=".$this->errorinfo;
		}
		return false;
	}	
	private function __checkParameters(){
		if((empty($this->yyuid) && empty($this->username))){
			$this->__appendErrorInfo("both yyuid and username are empty");
			return false;
		}	
		if(empty($this->oauth_cookie) && empty($this->oauth_cookie_new)){
			$this->__appendErrorInfo("both oauthCookie and oauthCookieNew are empty");
			return false;
		}				
		return true;
	}
	
	/**
	 * verify oauthcookieold by AES udbkey
	 */
	private function __oauthUdbValidate(){
		if($this->__oauthUdbValidate_i($this->oauth_udb_key->hashkey)){
			return true;
		}
		if($this->__oauthUdbValidate_i($this->oauth_udb_key->hashkey2)){
			return true;
		}
		return false;
	}
	
	private function __oauthUdbValidate_i($key){
		$oauth_cookie = AESHelper::decrypt($this->oauth_cookie, $key);
		if(strcasecmp($key, $this->oauth_udb_key->hashkey2) == 0){
			if(count(explode(":", $oauth_cookie)) < 4){
				$this->__appendErrorInfo("oauthCookie not decoded by the recent key,".$this->__maskAESUdbkey());
			}
		}
		return $this->__oauthUdbValidate_comm($oauth_cookie);
	}
	
	/**
	 * verify oauthcookienew by RSA udbkey
	 */	
	private function __oauthUdbValidateNew(){
		$validResult = $this->__oauthUdbValidateNew_i($this->oauth_udb_key_rsa->hashkey);
		if(!$validResult){
			$validResult = $this->__oauthUdbValidateNew_i($this->oauth_udb_key_rsa->hashkey2);
		}
		return $validResult;
	}
	
	private function __oauthUdbValidateNew_i($key){
		$oRSAForPublicKey = RSAFactory::getRSAForPublicKey($key);
		try{
			$oauth_cookie = $oRSAForPublicKey->decode($this->oauth_cookie_new);
			return $this->__oauthUdbValidate_comm($oauth_cookie);
		}catch(RSAException $ex){
			if(strcasecmp($key, $this->oauth_udb_key_rsa->hashkey2) == 0){
				$this->__appendErrorInfo("oauthCookie not decoded by the recent key,".$this->__maskRSAUdbkey());
			}
			return false;
		}
		
	}
	
	/**
	 * 共通部分：验证业务
	 */
	private function __oauthUdbValidate_comm($oauth_cookie){
		$oauth_cookie_arr = explode(":", $oauth_cookie);//格式：username:appid:access_key:access_secret:yyuid:*
		if(count($oauth_cookie_arr)<4 ){
			//$this->__appendErrorInfo("number of oauth_cookie array is less than 4");
			return false;
		}
		$username = urldecode($oauth_cookie_arr[0]);
		$appid = $oauth_cookie_arr[1];
		$access_key = $oauth_cookie_arr[2];
		$access_secret = $oauth_cookie_arr[3];
		$yyuid = null;
		if(count($oauth_cookie_arr)>4 && !empty($oauth_cookie_arr[4])){
			$yyuid = $this->__mytrim($oauth_cookie_arr[4]); 
		}
		//step2:比较cookie的yyuid 与 解密出的yyuid是否一致,为了兼容新老sdk登录态，优先比较yyuid,如果不一致再比较username
		if(!empty($this->yyuid)){//优先比较yyuid
			if(strcasecmp($yyuid, $this->yyuid) != 0){
				$this->__appendErrorInfo("yyuid not equal which in oauthCookie");
				return false;
			}else{
				$this->username = $username;
			}
		}else{//若无yyuid则比较username
			if(strcasecmp($username, $this->username) != 0){
				$this->__appendErrorInfo("username not equal which in oauthCookie");
				return false;				
			}else{
				$this->yyuid = $yyuid;
			}
		}
		
		if(count($oauth_cookie_arr)>6 && !empty($oauth_cookie_arr[5])){
			$this->lgnSecLevel = $oauth_cookie_arr[5]; 
		}
		
		if(count($oauth_cookie_arr)>7 && !empty($oauth_cookie_arr[6])){
			$this->ip = $oauth_cookie_arr[6]; 
		}
		
		if(count($oauth_cookie_arr)>8 && !empty($oauth_cookie_arr[7])){
			$this->timestamp = $oauth_cookie_arr[7]; 
		}
		
		if(count($oauth_cookie_arr)>9 && !empty($oauth_cookie_arr[8])){
			$this->mac = $oauth_cookie_arr[8]; 
		}
		if(count($oauth_cookie_arr)>10 && !empty($oauth_cookie_arr[9])){
			$this->extinfo = $oauth_cookie_arr[9]; 
		}
		if(count($oauth_cookie_arr)>11 && !empty($oauth_cookie_arr[10])){
			$this->isRemMe = $oauth_cookie_arr[10]; 
		}	
				
		//step3: 判断公共域cookie是否有效，不区分appid
		$this->access_token = new OAuthConsumer($access_key, $access_secret);
		$this->appid_in_cookie = $appid;
		return true;
	}
	/**
	 * 权控信息验证
	 */
	private function __ssoACLCheck(){
		$ssoacl = $this->oauth_client->getSsoacl();
		if(empty($ssoacl) || $ssoacl == null){
			return true;
		}else{
			$denySSOAppidSet =  explode(",", $ssoacl ->denySSOAppid);
			$allowSSOAppidSet = explode(",", $ssoacl ->allowSSOAppid);
			//oauthcookie解密出的appid与当前业务系统appid相同，直接放过
			if(strcasecmp($this->app_id, $this->appid_in_cookie) == 0){
				return true;
			}
			if( strlen($ssoacl ->denySSOAppid) > 0 && count($denySSOAppidSet) > 0 &&
			    (in_array($this->appid_in_cookie,$denySSOAppidSet) ||
			     in_array("all",$denySSOAppidSet) )){
				$this->__appendErrorInfo("appidInCookie belongs to denySSOAppidSet:".$ssoacl ->denySSOAppid);
				return false;
			}
			if( strlen($ssoacl ->allowSSOAppid) > 0 && count($allowSSOAppidSet) > 0 &&
			    !(in_array($this->appid_in_cookie,$allowSSOAppidSet) || n_array("all",$allowSSOAppidSet) )
			   ){
				$this->__appendErrorInfo("appidInCookie not belongs to allowSSOAppidSet:".$ssoacl ->allowSSOAppid);
				return false;
			}
			return true;
		}
	}
	
	/**
	 * 是否废弃AES oauth_cookie
	 * true - 废弃，false - 不废弃
	 */
	private function __isDiscardAESCookie(){
		$ssoacl = $this->oauth_client->getSsoacl();
		if(!empty($ssoacl) && count($ssoacl) >= 4 && strcmp($ssoacl[3],"1") == 0){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * 添加错误日志
	 */
	private function __appendErrorInfo($errinfo){
		if(empty($this->errorinfo)){
			$this->errorinfo = $errinfo;
		}else{
			$this->errorinfo = $this->errorinfo."<br/>".$errinfo;
		}
	}
	
	/**
	 * 过滤yyuid中乱码信息，仅返回数字字符
	 */
	private function __mytrim($yyuid){
		$retYyuid = "";
		for($i=0;$i<strlen($yyuid);$i++){  
			$tmp = substr($yyuid,$i,1);
			if(is_numeric($tmp)){
				$retYyuid = $retYyuid.$tmp;
			}
		} 
		return $retYyuid;
	}
	/**
	 * to delete
	 */
	private function __getOAuthCookie(){
		return $this->username.":".
			$this->app_id.":".
			$this->access_token->key.":".
			$this->access_token->secret.":".
			$this->yyuid.":*";
	}
	
	/**
	 * 掩码AES版udbkey，供打印日志
	 */
	private function __maskAESUdbkey() {
		$sb = "";
		try {
			$sb = $sb."aes firstkey:";
			$sb = $sb.(empty($this->oauth_udb_key->hashkey)? "":substr($this->oauth_udb_key->hashkey, 0,8)."***");
			$sb = $sb.",secondkey:";
			$sb = $sb.(empty($this->oauth_udb_key->hashkey2)? "":substr($this->oauth_udb_key->hashkey2, 0,8)."***");
		} catch(Exception $ex) {
		}
		return $sb;
	}
	/**
	 * 掩码RSA版udbkey，供打印日志
	 */	
	private function __maskRSAUdbkey() {
		$sb = "";
		try {
			$sb = $sb."rsa firstkey:";
			$sb = $sb.(empty($this->oauth_udb_key_rsa->hashkey)? "":"***".substr($this->oauth_udb_key_rsa->hashkey, 168,188));
			$sb = $sb.",secondkey:";
			$sb = $sb.(empty($this->oauth_udb_key_rsa->hashkey2)? "":"***".substr($this->oauth_udb_key_rsa->hashkey2, 168,188));
		} catch(Exception $ex) {
		}
		return $sb;
	}
	
    /**
	 * 获取客户IP
	 */
	private function __getIp() {
		if (getenv('HTTP_CLIENT_IP')) { 
			$ip = getenv('HTTP_CLIENT_IP'); 
		} else if (getenv('HTTP_X_FORWARDED_FOR')) { 
			$ip = getenv('HTTP_X_FORWARDED_FOR'); 
		} else if (getenv('HTTP_X_FORWARDED')) { 
			$ip = getenv('HTTP_X_FORWARDED'); 
		} else if (getenv('HTTP_FORWARDED_FOR')) { 
			$ip = getenv('HTTP_FORWARDED_FOR'); 
		} else if (getenv('HTTP_FORWARDED')) { 
			$ip = getenv('HTTP_FORWARDED'); 
		} else { 
			$ip = $_SERVER['REMOTE_ADDR']; 
		} 
		return $ip; 
	}
}
?>
