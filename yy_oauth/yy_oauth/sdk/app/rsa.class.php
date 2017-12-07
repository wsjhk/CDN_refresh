<?php

/***************************************************************************************************************
**RSA加、解密类                                                                                               **
**必须要求PHP环境加载php_openssl模块                                                                          **
**相对1024RSA加密明文最大长度117字节，解密要求密文最大长度为128字节，所以在加密和解密的过程中需要分块进行     **
**相对512RSA加密明文最大长度53字节，解密要求密文最大长度为64字节，所以在加密和解密的过程中需要分块进行        **
**如果要生成公、私密钥需要了解相关配置参数，详见RSAFactory::generateKey()                                     **
***************************************************************************************************************/

/**
**RSA加、解密工厂类
**/
class RSAFactory
{
	/**
	**获取RSA公钥加、解密类
	**/
	public static function getRSAForPublicKey($sPublicKey)
	{
		$oRSAForPublicKey = new RSAForPublicKey();
		$oRSAForPublicKey -> setKey($sPublicKey);
		return $oRSAForPublicKey;
	}

	/**
	**获取RSA私钥加、解密类
	**/
	public static function getRSAForPrivateKey($sPrivateKey)
	{
		$oRSAForPrivateKey = new RSAForPrivateKey();
		$oRSAForPrivateKey -> setKey($sPrivateKey);
		return $oRSAForPrivateKey;
	}

	/**
	**RSA公、私密钥生成
	**/
	public static function generateRSAKey()
	{
		$aConfig = array(
			'digest_alg' => 'sha1',
			'private_key_bits' => RSAUtil::getKeyBitsLen(),
			'config' => 'E:/dev/php-5.4.34/extras/openssl.cnf',
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
			'encrypt_key' => false
		);

		$oResource = openssl_pkey_new($aConfig);
		openssl_pkey_export($oResource, $sPrivateKey, NULL, $aConfig);
		$aPublicKey = openssl_pkey_get_details($oResource);
		$sPublicKey = $aPublicKey['key'];

		return new RSAKey($sPrivateKey, $sPublicKey);
	}
}

/**
**RSA接口
**/
interface RSA
{
	//RSA加密方法
	public function encode($sData);
	//RSA解密方法
	public function decode($sData);
	//RSA设置密钥
	public function setKey($sKey);
	//RSA获取密钥
	public function getKey();
}

/**
**RSA抽象类
**/
abstract class RSAAbstract implements RSA
{
	//密钥
	protected $m_sKey = '';

	/**
	**获取密钥
	**/
	public function getKey()
	{
		return $this -> m_sKey;
	}

	/**
	**克隆方法
	**/
	public function __clone()
	{
		$this -> m_sKey = clone $this -> m_sKey;
	}

}

/**
**RSA公钥加、解密类
**/
class RSAForPublicKey extends RSAAbstract
{
	/**
	**RSA公钥加密
	**/
	public function encode($sData)
	{
		$sEncrypted = '';
		$aBlock = RSAUtil::chunkEncryptBlock($sData);
		foreach($aBlock as $sBlock)
		{
			$bResult = openssl_public_encrypt($sBlock, $sBlockEncrypted, parent::getKey());
			if(!$bResult) throw new RSAException('RSA公钥加密失败');
			$sEncrypted .= $sBlockEncrypted;
		}
		return RSAUtil::encrypt($sEncrypted);
	}

	/**
	**RSA公钥解密
	**/
	public function decode($sData)
	{
		$sDecrypted = '';
		$aBlock = RSAUtil::chunkDecryptBlock(RSAUtil::decrypt($sData));
		foreach($aBlock as $sBlock)
		{
			$bResult = openssl_public_decrypt($sBlock, $sBlockDecrypted, parent::getKey());
			if(!$bResult) throw new RSAException('RSA公钥解密失败');
			$sDecrypted .= $sBlockDecrypted;
		}
		return $sDecrypted;
	}

	/**
	**设置密钥
	**/
	public function setKey($sKey)
	{
		$this -> m_sKey = RSAUtil::formatPublicPEM($sKey);
	}

}

/**
**RSA私钥加、解密类
**/
class RSAForPrivateKey extends RSAAbstract
{
	/**
	**RSA私钥加密
	**/
	public function encode($sData)
	{
		$sEncrypted = '';
		$aBlock = RSAUtil::chunkEncryptBlock($sData);
		foreach($aBlock as $sBlock)
		{
			$bResult = openssl_private_encrypt($sBlock, $sBlockEncrypted, parent::getKey(), OPENSSL_PKCS1_PADDING);
			if(!$bResult) throw new RSAException('RSA私钥加密失败');
			$sEncrypted .= $sBlockEncrypted;
		}

		return RSAUtil::encrypt($sEncrypted);
	}

	/**
	**RSA私钥解密
	**/
	public function decode($sData)
	{
		$sDecrypted = '';
		$aBlock = RSAUtil::chunkDecryptBlock(RSAUtil::decrypt($sData));
		foreach($aBlock as $sBlock)
		{
			$bResult = openssl_private_decrypt($sBlock, $sBlockDecrypted, parent::getKey());
			if(!$bResult) throw new RSAException('RSA私钥解密失败');
			$sDecrypted .= $sBlockDecrypted;
		}
		return $sDecrypted;
	}

	/**
	**设置密钥
	**/
	public function setKey($sKey)
	{
		$this -> m_sKey = RSAUtil::formatPrivatePEM($sKey);
	}
}

/**
**RSA异常类
**/
class RSAException extends Exception
{
}

/**
**RSA公、私密钥值对象
**/
class RSAKey
{
	private $m_sPrivateKey;
	private $m_sPublicKey;

	public function __construct($sPrivateKey, $sPublicKey)
	{
		$this -> setPrivateKey($sPrivateKey);
		$this -> setPublicKey($sPublicKey);
	}

	public function setPrivateKey($sPrivateKey)
	{
		$this -> m_sPrivateKey = $sPrivateKey;
	}

	public function getPrivateKey()
	{
		return $this -> m_sPrivateKey;
	}

	public function setPublicKey($sPublicKey)
	{
		$this -> m_sPublicKey = $sPublicKey;
	}

	public function getPublicKey()
	{
		return $this -> m_sPublicKey;
	}
}

/**
**转换工具
**/
class RSAUtil 
{
	private static $m_nKeyBitsLen = 512;

	/**
	**RSA内部加密方法
	**/	
	public static function encrypt($sData) 
	{
		return bin2hex(trim($sData));
	}

	/**
	**RSA内部解密方法
	**/
	public static function decrypt($sData) 
	{
		$sBin = '';
        for ($i = 0, $nLen = strlen($sData); $i < $nLen; $i += 2) 
		{
            $sBin .= pack('H*', substr($sData, $i, 2));
        }

        return $sBin;
		//return hex2bin(trim($sData));
	}

	/**
	**加密分块方法
	**/	
	public static function chunkEncryptBlock($sData)
	{
		$nBlockLen = 53;
		switch(self::getKeyBitsLen())
		{
			case 512 :
				$nBlockLen = 53;
			break;
			case 1024 :
				$nBlockLen = 117;
			break;
		}
		return self::chunkBlock($sData, $nBlockLen);
	}

	/**
	**解密分块方法
	**/	
	public static function chunkDecryptBlock($sData)
	{
		$nBlockLen = 64;
		switch(self::getKeyBitsLen())
		{
			case 512 :
				$nBlockLen = 64;
			break;
			case 1024 :
				$nBlockLen = 128;
			break;
		}
		return self::chunkBlock($sData, $nBlockLen);
	}

	/**
	**分块方法
	**/	
	private static function chunkBlock($sData, $nBlockLen)
	{
		return str_split($sData, $nBlockLen);
	}

	/**
	**格式化私有PEM
	**/	
	public static function formatPrivatePEM($sKey)
	{
		return self::formatPEM($sKey, 'RSA PRIVATE');
	}

	/**
	**格式化公有PEM
	**/
	public static function formatPublicPEM($sKey)
	{
		return self::formatPEM($sKey, 'PUBLIC');
	}

	/**
	**格式化PEM
	**/
	private static function formatPEM($sKey, $sPEMType)
	{
		$nTotal = preg_match('/^-----BEGIN '.$sPEMType.' KEY-----(.+?)-----END '.$sPEMType.' KEY-----$/s', $sKey);
		if($nTotal == 1) return $sKey;

		$sKey = chunk_split(base64_encode(self::decrypt($sKey)), 64, "\n");
		$sKey = '-----BEGIN '.$sPEMType.' KEY-----'."\n".$sKey.'-----END '.$sPEMType.' KEY-----';
		return $sKey;
	}

	/**
	**获取键长
	**/
	public static function getKeyBitsLen()
	{
		return self::$m_nKeyBitsLen;
	}
}
?>