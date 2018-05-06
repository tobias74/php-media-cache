<?php 
namespace PhpMediaCache;


class FlyImageSpecification
{
  public $width;
  public $height;

  public function getHash()
  {
    return array(
      'width' => $this->width,
      'height' => $this->height,
    );
  }
  
  public function serialize()
  {
    return json_encode($this->getHash());
  }
  
  public function isOriginalSize()
  {
    return (($this->width === false) && ($this->height===false));
  }
}



