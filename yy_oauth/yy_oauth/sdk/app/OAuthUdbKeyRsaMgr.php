<?php
require_once ('AESHelper.php');
require_once ('rsa.class.php');

/**
 * RSA udbkey 管理器
 * 首先尝试从本地缓存文件读取，如果没有或者读取udbkey已失效则会从远程服务重新获取，然后保存至本地缓存文件。
 * 缓存文件目录对应demo/config.php文件中$key_file
 */
abstract class OAuthUdbKeyMgrAbstract {
	
	protected static $udbKeyPasswd = "51b7cded89665b21899b3232ce1ff460575db680";
	public static $UdbkeyFreshPeriod = 300;         // 5分钟 ：300
	public static $isDebug = false;                //测试开关 true-打开， false-关闭
	
	function __construct($appid, $appkey, $cache,$udbkeyname){
		$this->cache = $cache;
		$this->app_id = $appid;
		$this->app_key = $appkey;
		$this->udbKeyName = $udbkeyname;
		
		$this->udb_key_cache = null;
		$this->need_save = false;
		$this->errorinfo = null;
	}
	/**
	 * 获取udbkey
	 */
	public function getSecureKey(){
		$this->__freshSecureKey();
		if (empty($this->udb_key_cache) && !$this->__initUdbKeyCache()){
			return false;
		}
		return $this->udb_key_cache->secure_key;
	}
	
	/**
	 * 定期检查本地文件缓存udbkey，若过期则调用后端服务刷新
	 */
	private function __freshSecureKey(){
		$storeKey = "freshSecureKey_".$this->udbKeyName;
		$storeValue = apc_fetch($storeKey); 
		if(empty($storeValue)){
			apc_store($storeKey, "1", self::$UdbkeyFreshPeriod);
			$this->udb_key_cache = new OAuthUdbKeyCache();
			//step1: get from local cache file
			$key = $this->__getKeyFromFile();
			$to_update_secure_key = true;
			if($key){
				if(!empty($key->secure_key) && $key->secure_key->isLegal()){
					$this->udb_key_cache->secure_key = $key->secure_key;
					$to_update_secure_key = false;
				}
			}
			//step2: get from remote udb service
			if($to_update_secure_key){
				$this->udb_key_cache->secure_key = $this->__getSecureKey();
				//if(self::$isDebug)echo "success __freshSecureKey :".$this->udbKeyName."<br />";
			}
			//step3:save to local cache file [instance of OAuthUdbKeyCache]
			if (!empty($this->udb_key_cache->secure_key) && $this->udb_key_cache->secure_key->isLegal()) {
				$this->__saveKeyToFile();
				return true;
			}
			return false;	
		}else{
			return false;
		}
	}
	
	private function __initUdbKeyCache(){
		if(!empty($this->udb_key_cache)){
			return true;
		}
		
		$this->udb_key_cache = new OAuthUdbKeyCache();
		//step1: get from local cache file
		$key = $this->__getKeyFromFile();
		$to_update_secure_key = true;
		if($key){
			if(!empty($key->secure_key) && $key->secure_key->isLegalNew()){
				$this->udb_key_cache->secure_key = $key->secure_key;
				$to_update_secure_key = false;
			}
		}
		//step2: get from remote udb service
		if($to_update_secure_key){
			$this->udb_key_cache->secure_key = $this->__getSecureKey();
		}
		//step3:save to local cache file [instance of OAuthUdbKeyCache]
		if (!empty($this->udb_key_cache->secure_key) && $this->udb_key_cache->secure_key->isLegalNew()) {
			$this->__saveKeyToFile();
			return true;
		}
		return false;
	}
	
	protected function __saveKeyToFile(){
		if(empty($this->cache) || !$this->need_save || empty($this->udb_key_cache)){
			return false;
		}
		
		$s = serialize($this->udb_key_cache);
		$cs = AESHelper::encrypt($s, self::$udbKeyPasswd);
		
		return $this->cache->setValue($this->udbKeyName, $cs);
	}
	
	private function __getKeyFromFile(){
		if(empty($this->cache)){
			return false;
		}
		
		$cs = $this->cache->getValue($this->udbKeyName);
		if(empty($cs)){
			return false;
		}
		$s = AESHelper::decrypt($cs, self::$udbKeyPasswd);		
		return unserialize($s);
	}

	abstract protected function __getSecureKey();
}

/**
**RSA公钥加、解密类
**/
class OAuthUdbKeyMgrRsa extends OAuthUdbKeyMgrAbstract{
	function __construct($appid, $appkey, $cache){
		parent::__construct( $appid, $appkey, $cache,"UDBKEYRSA" );
	}
	protected function __getSecureKey(){
		$uclient = new OAuthClient($this->app_id, $this->app_key);
		$oauthKeyArr = $uclient->getSecureKeyRsa();
		if(!$oauthKeyArr){
			$this->errorinfo = $uclient->errorinfo; 
			return false;	
		}
		$this->need_save = true;
		return new OAuthUdbKey($oauthKeyArr);
	}
		/**
	 * 刷新udbkey
	 */
	public function refreshSecureKey(){
		if (!$this->__refreshUdbKeyCache()){
			return false;
		}
		return true;
	}
	private function __refreshUdbKeyCache(){	
		$this->udb_key_cache = new OAuthUdbKeyCache();
		//step1: get from remote udb service
		$this->udb_key_cache->secure_key = $this->__getSecureKey();
		//step2:save to local cache file [instance of OAuthUdbKeyCache]
		if (!empty($this->udb_key_cache->secure_key) && $this->udb_key_cache->secure_key->isLegal()) {
			parent::__saveKeyToFile();
			return true;
		}
		return false;
	}
	
}

/**
**RSA公钥加、解密类
**/
class OAuthUdbKeyMgrAES extends OAuthUdbKeyMgrAbstract{
	function __construct($appid, $appkey, $cache){
		parent::__construct( $appid, $appkey, $cache,"UDBKEYAES");
		
	}
	protected function __getSecureKey(){
		$uclient = new OAuthClient($this->app_id, $this->app_key);
		$oauthKeyArr = $uclient->getSecureKey();
		if(!$oauthKeyArr){
			$this->errorinfo = $uclient->errorinfo; 
			return false;	
		}
		$this->need_save = true;
		return new OAuthUdbKey($oauthKeyArr);
	}
}
?>