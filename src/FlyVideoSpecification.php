<?php
namespace Zeitfaden\CachedMediaService;



class FlyVideoSpecification
{
  protected $mode='none';
  public $format;
  public $quality;
  
  public function getFileExtension()
  {
    return $this->format;
  }
  
  public function getMode()
  {
    return $this->mode;
  }
  
  public function setMode($val)
  {
    $this->mode = $val;
  }
  
  public function getHash()
  {
    return array(
      'quality' => $this->quality,
      'format' => $this->format
    );  
  }
  
  public function serialize()
  {
    return serialize($this->getHash());
  }
  
}




