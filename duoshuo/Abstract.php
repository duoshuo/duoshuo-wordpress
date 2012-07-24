<?php
class Duoshuo_Abstract {
	const DOMAIN = 'duoshuo.com';
	const STATIC_DOMAIN = 'static.duoshuo.com';
	const VERSION = '0.7.2';
	
	/**
	 * 
	 * @var string
	 */
	public $shortName;
	
	/**
	 * 
	 * @var string
	 */
	public $secret;
	
	public function oauthConnect(){
			if (!isset($_GET['code']))
			return false;
		
		$oauth = new Duoshuo_Client($this->shortName, $this->secret);
		
		$keys = array(
			'code'	=> $_GET['code'],
			'redirect_uri' => 'http://duoshuo.com/login-callback/',
		);
		
		$token = $oauth->getAccessToken('code', $keys);
		
		if ($token['code'] != 0)
			return false;
		
		$this->userLogin($token);
	}
	
	public function oauthBind(){
		if (!isset($_GET['code']))
			return false;
		
		$oauth = new Duoshuo_Client($this->shortName, $this->secret);
		
		$keys = array(
			'code'	=> $_GET['code'],
			'redirect_uri' => 'http://duoshuo.com/login-callback/weibo/',
		);
		
		$token = $oauth->getAccessToken('code', $keys);
		
		if ($token['code'] != 0)
			return false;
		
		$this->userBind($token);
	}
	
	public function remoteAuth($user_data){
		$message = base64_encode(json_encode($user_data));
	    $time = time();
	    return $message . ' ' . self::hmacsha1($message . ' ' . $time, $this->secret) . ' ' . $time;
	}
	
	function rfc3339_to_mysql($string){
		if (method_exists('DateTime', 'createFromFormat')){	//	php 5.3.0
			return DateTime::createFromFormat(DateTime::RFC3339, $string)->format('Y-m-d H:i:s');
		}
		else{
			$timestamp = strtotime($string);
			return gmdate('Y-m-d H:i:s', $timestamp  + $this->timezone() * 3600);
		}
	}
	
	function rfc3339_to_mysql_gmt($string){
		if (method_exists('DateTime', 'createFromFormat')){	//	php 5.3.0
			return DateTime::createFromFormat(DateTime::RFC3339, $string)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		}
		else{
			$timestamp = strtotime($string);
			return gmdate('Y-m-d H:i:s', $timestamp);
		}
	}
	
	// from: http://www.php.net/manual/en/function.sha1.php#39492
	// Calculate HMAC-SHA1 according to RFC2104
	// http://www.ietf.org/rfc/rfc2104.txt
	static function hmacsha1($data, $key) {
		if (function_exists('hash_hmac'))
			return hash_hmac('sha1', $data, $key);
		
	    $blocksize=64;
	    $hashfunc='sha1';
	    if (strlen($key)>$blocksize)
	        $key=pack('H*', $hashfunc($key));
	    $key=str_pad($key,$blocksize,chr(0x00));
	    $ipad=str_repeat(chr(0x36),$blocksize);
	    $opad=str_repeat(chr(0x5c),$blocksize);
	    $hmac = pack(
	                'H*',$hashfunc(
	                    ($key^$opad).pack(
	                        'H*',$hashfunc(
	                            ($key^$ipad).$data
	                        )
	                    )
	                )
	            );
	    return bin2hex($hmac);
	}
}
