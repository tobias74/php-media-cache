<?php
namespace Zeitfaden\CachedMediaService;

class CachedMedia
{

  protected $fileType="";
  protected $serializedSpecification="";
  protected $status="";
  protected $entityId="";
  protected $pathToFile="";


  public function isRunning()
  {
    return ($this->status === 'running');
  }

  public function isScheduled()
  {
    return ($this->status === 'scheduled');
  }

  public function isDone()
  {
    return ($this->status === 'complete');
  }


  public function setSerializedSpecification($val)
  {
    $this->serializedSpecification = $val;
  }

  public function getSerializedSpecification()
  {
    return $this->serializedSpecification;
  }


  public function setStatus($val)
  {
    $this->status = $val;
  }

  public function getStatus()
  {
    return $this->status;
  }


  public function setFileType($val)
  {
    $this->fileType = $val;
  }

  public function getFileType()
  {
    return $this->fileType;
  }


  public function setEntityId($val)
  {
    $this->entityId = $val;
  }

  public function getEntityId()
  {
    return $this->entityId;
  }


  public function setPathToFile($val)
  {
    $this->pathToFile = $val;
  }

  public function getPathToFile()
  {
    return $this->pathToFile;
  }


}
