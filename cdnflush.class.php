<?php
class CDNetworkFlush
{
  const FLUSH_URL = 'https://openapi.us.cdnetworks.com/purge/rest/doPurge?';
  protected $pad;
  protected $paths;
  protected $mailTo;
  
  protected $flushType;
  
  protected $user;
  protected $pass;
  
  protected $resultTxt;
  protected $result;

  /**
  * @param $pad string pad name
  * @param $paths array paths (/toto, /tata, /*, /toto*)
  * @param $mailTo array emails
  */
  public function __construct($pad, $paths, $mailTo = array())
  {
    $this->pad = $pad;
    $this->paths = $paths;
    $this->mailTo = $mailTo;
  }
  
  public function setCredentials($user, $pass)
  {
    $this->user = $user;
    $this->pass = $pass;
  }
  
  protected function normalizeData()
  {
    if( ! is_array($this->paths))
    {
      $this->paths = array($this->paths);
    }
    
    foreach($this->paths as $k=>$path)
    {
      $host = parse_url($path, PHP_URL_HOST);
      if($host)
      {
        $path = str_ireplace('http://'.$host, '', $path);
        $path = str_ireplace('https://'.$host, '', $path);
        $this->paths[$k] = $path;
      }
    }
    $this->paths = array_values(array_unique($this->paths));
    
    $flushType = "item";
    foreach($this->paths as $path)
    {
      if(strpos($path, '*') !== false)
      {
	      $flushType = 'wildcard';
      }
    }
    if(count($this->paths) == 1 && $this->paths[0] == '/*') 
    {
      $flushType = 'all';
      $this->paths = array();
    }
    $this->flushType = $flushType;
  }
  
  protected function constructQueryString()
  {    
    $this->normalizeData();
    
    $data = array();
    $data['user'] = $this->user;
    $data['pass'] = $this->pass;
    $data['pad'] = $this->pad;
    $data['type'] = $this->flushType;
    $data['output'] = "json";

    $dataString = http_build_query($data, null, '&');
    if(is_array($this->paths))
    {
      foreach($this->paths as $path)
      {
	      $dataString .= '&path='.urlencode($path);
      }
    }
    if(is_array($this->mailTo))
    {
      foreach($this->mailTo as $mailT)
      {
	      $dataString .= '&mailTo='.urlencode($mailT);
      }
    }
    if(class_exists('bsLogger'))
    {
      bsLogger::debug("flush query string : ".$dataString);
    }
    return $dataString;
  }


  public function callFlush()
  {

    $dataString = $this->constructQueryString();
    $ch = curl_init();

    // configuration des options
    curl_setopt($ch, CURLOPT_URL, self::FLUSH_URL);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


    // exécution de la session
    $ret = curl_exec($ch);

    // fermeture des ressources
    curl_close($ch);
    return $ret;
  }

  public function doFlush()
  {
    // exécution de la session
    $ret = $this->callFlush();

    $this->resultTxt = trim($ret);
    $this->result = @json_decode($this->resultTxt, true);

    return $this->result['resultCode'] == 200;
  }


  public function forwardFlush()
  {
    $ret = $this->callFlush();

    return $ret;
  }
  
  public function getDetails()
  {
    return trim(trim($this->result['details'], '"'), ']');
  }
}