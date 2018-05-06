<?php
namespace PhpMediaCache;



class FlyVideoSpecification
{
  public $format;
  public $quality;
  
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




