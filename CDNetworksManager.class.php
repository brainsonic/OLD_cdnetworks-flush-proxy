<?php

class CDNetworksManager
{
  const APIHOSTNAME = 'openapi-beta.cdnetworks.com';
  protected $user;
  protected $pass;
  
  protected $sessionToken;
  
  protected $detailCacheFile = '/tmp/pads_cache.log';
  protected $persistantDetailCacheFile = '/srv/pads_cache.log';
  protected $tokenCacheFile = '/tmp/pads_token_cache.log';
  
  protected $cacheContent;
  
  public function setCredentials($user, $pass)
  {
    $this->user = $user;
    $this->pass = $pass;
  }
  
  /**
  * return sessionId
  */
  public function logIn($svcGroupName = 'Brainsonic')
  {
    $url = 'https://'.self::APIHOSTNAME.'/api/rest/login?user='.urlencode($this->user).'&pass='.urlencode($this->pass).'&output=json';
    $cnt = file_get_contents($url);
    $loginArray = json_decode($cnt, true);
    foreach($loginArray['loginResponse']['session'] as $k=>$sess)
    {
      if($sess['svcGroupName'] == $svcGroupName)
      {
	$this->sessionToken = $sess['sessionToken'];
	return $this->sessionToken;
      }
    }
  }
  
  public function loginIfNecessary($svcGroupName = 'Brainsonic')
  {
    if(empty($this->sessionToken))
    {
      $this->logIn($svcGroupName);
    }
    return $this->sessionToken;
  }
  
  public function getPadList()
  {
  
    if(file_exists($this->tokenCacheFile) && filemtime($this->tokenCacheFile) > (time() - 60*4))
    {
      return json_decode(file_get_contents($this->tokenCacheFile), true);
    }
  
  
    $this->loginIfNecessary();
  
    $url = "https://".self::APIHOSTNAME."/api/rest/getApiKeyList?sessionToken=".urlencode($this->sessionToken)."&output=json";
    $ch = curl_init();
    
    // configuration des options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 


    // exécution de la session
    $ret = curl_exec($ch);

    // fermeture des ressources
    curl_close($ch);


    $this->resultTxt = trim($ret);
    $this->result = @json_decode($this->resultTxt, true);
    
    
    $result = array();
    foreach($this->result['apiKeyInfo']['apiKeyInfoItem'] as $item)
    {
      if($item['type'] == "1")
      {
	$result[] = $item;
      }
    }
    
    file_put_contents($this->tokenCacheFile, json_encode($result));
    
    return $result;
  }
  
  public function addPad($padName, $origin, $copySettingFromPad, $description = null, $othersOpts = array())
  {
    $this->loginIfNecessary();
  
    $url = "https://".self::APIHOSTNAME."/api/rest/pan/site/add";
    $ch = curl_init();
    
    $data = $othersOpts;
    $data['sessionToken'] = $this->sessionToken;
    $data['output'] = 'json';
    $data['apiKey'] = "SERVICECATEGORY_CA";
    $data['pad'] = $padName;
    $data['origin'] = $origin;    
    $data['copy_settings_from'] = $copySettingFromPad;
    $data['description'] = $description;
    
    $dataString = http_build_query($data, null, '&');

    
    echo "\n".($url);
    echo "\n".($dataString);

    // configuration des options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 


    // exécution de la session
    $ret = curl_exec($ch);

    // fermeture des ressources
    curl_close($ch);


    $this->resultTxt = trim($ret);
    $this->result = @json_decode($this->resultTxt, true);
    return $this->result;
  }
  
  public function getPadApiKey($padName)
  {
    foreach($this->getPadList() as $pad)
    {
      if($pad['serviceName'] == $padName)
      {
	return $pad['apiKey'];
      }
    }
  }
  
  
  public function editPad($padName, array $edits, $apiKey = null)
  {
    $this->loginIfNecessary();
    
    if($apiKey == null)
    {
      $apiKey = $this->getPadApiKey($padName);
    }
  
    $url = "https://".self::APIHOSTNAME."/api/rest/pan/site/edit";
    $ch = curl_init();
    
    $data = $edits;
    $data['sessionToken'] = $this->sessionToken;
    //$data['output'] = 'json';
    $data['apiKey'] = $apiKey;
    $data['pad'] = $padName;
    
    $dataString = http_build_query($data, null, '&');

    echo "\n".($url);
    echo "\n".($dataString);

    // configuration des options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 


    // exécution de la session
    $ret = curl_exec($ch);

    // fermeture des ressources
    curl_close($ch);


    $this->resultTxt = trim($ret);
    $this->result = @json_decode($this->resultTxt, true);
    return $this->result;
  }
  
  public function searchPads(array $searches)
  {
    $result = $this->getPadListWithOrigin();
    foreach($result as $padName=>$origin)
    {
      foreach($searches as $search)
      {
	if(stripos($padName, $search) !== false || stripos($origin, $search) !== false)
	{
	 //ok
	}else{
	  unset($result[$padName]);
	}
      }
    }
    return $result;
  }
  
  public function getPadListWithOrigin()
  {
    $res = array();
    foreach($this->getCacheContent() as $padName=>$data)
    {
      if( is_array($data) && ! array_key_exists('origin', $data))
      {
        //var_dump($padName);
	//var_dump($data);
      }else{
	$res[$padName] = $data['origin'];
      }
    }
    return $res;
  }
  
  
  public function viewPad($padName, $apiKey = null, $prod = true)
  {
    $this->loginIfNecessary();
    
    if($apiKey == null)
    {
      $apiKey = $this->getPadApiKey($padName);
    }
  
    $url = "https://".self::APIHOSTNAME."/api/rest/pan/site/view";
    $ch = curl_init();
    
    $data = array();
    $data['sessionToken'] = $this->sessionToken;
    $data['output'] = 'json';
    $data['apiKey'] = $apiKey;
    $data['pad'] = $padName;
    
    $dataString = http_build_query($data, null, '&');

    // configuration des options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 


    // exécution de la session
    $ret = curl_exec($ch);

    // fermeture des ressources
    curl_close($ch);


    $this->resultTxt = trim($ret);
    $this->result = @json_decode($this->resultTxt, true);
    
    $res = $this->result['PadConfigResponse']['data']['data'];
    
    if(empty($res))
    {
      var_dump($dataString);
      var_dump($this->result);
      return null;
    }
    
    
    $cnt = $this->getCacheContent();
    $cnt[$padName] = $res;
    
    $this->setCacheContent($cnt);
    
    return $res;
  }
  
  public function getCacheContent()
  {
    if( ! empty($this->cacheContent))
    {
      return $this->cacheContent;
    }
    $cnt = @file_get_contents($this->detailCacheFile);
    if( ! $cnt)
    {
      $cnt = @file_get_contents($this->persistantDetailCacheFile);
    }
    if($cnt)
    {
      $cnt = @json_decode($cnt, true);
    }
    if( ! is_array($cnt))
    {
      $cnt = array();
    }
    $this->cacheContent = $cnt;
    return $cnt;
  }
  
  
  public function setCacheContent($cnt)
  {
    $this->cacheContent = $cnt;
    if (version_compare(PHP_VERSION, '5.4') >= 0) {
      @file_put_contents($this->detailCacheFile, json_encode($cnt, JSON_PRETTY_PRINT));
    }else{
      @file_put_contents($this->detailCacheFile, json_encode($cnt));
    }
    @copy($this->detailCacheFile, $this->persistantDetailCacheFile);
  }
  
  public function viewPadFromCache($padName, $apiKey = null, $prod = true)
  {
    $cnt = $this->getCacheContent();
    if( array_key_exists($padName, $cnt) && ! empty($cnt[$padName]))
    {
      return $cnt[$padName];
    }
    return $this->viewPad($padName, $apiKey, $prod);
  }
  
  
  public function deactivatePad($padName)
  {
    return $this->editPad($padName, array('status'=>0));
  }
}
