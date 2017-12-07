<?php 
namespace Zeitfaden\CachedMediaService;


class FlyImageSpecification
{
  const TOUCH_BOX_FROM_INSIDE = "touch from inside";
  const SQUARE = "square";
  const TOUCH_FROM_INSIDE_TO_4_3 = "new aspect raiosquare";
  const TOUCH_BOX_FROM_OUTSIDE = "nasdasdew aspect raiosquare";
  
  protected $useOriginalSize = false;
  protected $maximumWidth;
  protected $maximumHeight;
  protected $mode;
  
  public function getFileExtension()
  {
    return "png";
  }
  
  public function getMode()
  {
    return $this->mode;
  }
  
  public function setMode($val)
  {
    $this->mode = $val;
  }
  
  public function getMaximumWidth()
  {
    return $this->maximumWidth;
  }
  
  public function setMaximumWidth($val)
  {
    $this->maximumWidth = $val;
  }
  
  public function getMaximumHeight()
  {
    return $this->maximumHeight;
  }
  
  public function setMaximumHeight($val)
  {
    $this->maximumHeight = $val;
  }
  
  public function useOriginalSize()
  {
    $this->useOriginalSize = true;
  }
  
  public function isOriginalSize()
  {
    return $this->useOriginalSize;
  }
  
  public function getHash()
  {
    return array(
      'maximumWidth' => $this->maximumWidth,
      'maximumHeight' => $this->maximumHeight,
      'mode' => $this->mode
    );
  }
  public function serialize()
  {
    return json_encode($this->getHash());
  }
  
}



