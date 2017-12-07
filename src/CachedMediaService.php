<?php

namespace Zeitfaden\CachedMediaService;

class CachedMediaService
{
  
  protected $cachedMediaDatabase;
  protected $cacheFileService;
  
  public function __construct($config)
  {
    $this->config = $config;
  }

  public function getConfig()
  {
    return $this->config;  
  }
  
  public function getCachedMediaDatabase()
  {
      if (!$this->cachedMediaDatabase)
      {
        $this->cachedMediaDatabase = new CachedMediaDatabase();
        $this->cachedMediaDatabase->setMongoDbHost($this->config['mongoDbHost']);
        $this->cachedMediaDatabase->setMongoDbName($this->config['mongoDbName']);
      }
      return $this->cachedMediaDatabase;
  }

  public function getCacheFileService()
  {
      if (!$this->cacheFileService)
      {
        $this->cacheFileService = new \PhpFileService\FileService();
      }
      return $this->cacheFileService;
  }

  
  public function setStoragePath($path)
  {
    $this->getCacheFileService()->setStoragePath($path);
  }

  public function setShardPath($path)
  {
    $this->getCacheFileService()->setShardPath($path);
  }


  public function getCachedImage($imageFile, $entityId, $flySpec)
  {
    try 
    {
      $cachedImageDocument = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $flySpec->serialize());
      $absoluteFilePath = $this->getCacheFileService()->getAbsoluteFilePath($cachedImageDocument->getPathToFile());
    }
    catch (\Exception $e)
    {
      error_log('did not get any image... now making it.');
  
      $imageResizer = new ImageResizer();
      $cachedImageTempName = $imageResizer->createCachedImage($imageFile, $entityId, $flySpec);
      
      $relativeFilePath = $this->getCacheFileService()->storeFileByPath($cachedImageTempName);

      $cachedImage = new CachedMedia();
      $cachedImage->setFileType('image/png');
      $cachedImage->setStatus('complete');
      $cachedImage->setSerializedSpecification($flySpec->serialize());
      $cachedImage->setEntityId($entityId);
      $cachedImage->setPathToFile($relativeFilePath);
      
      $this->getCachedMediaDatabase()->updateCachedMedia($cachedImage);

      $absoluteFilePath = $this->getCacheFileService()->getAbsoluteFilePath($relativeFilePath);
    }

    return $absoluteFilePath;

  }


  public function getCachedVideo($videoFile, $entityId, $flySpec)
  {
    try 
    {
      $cachedVideoDocument = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $flySpec->serialize());
      if ($cachedVideoDocument->isScheduled())
      {
        $absoluteFilePath = "video_scheduled_but_not_ready_yet";
      }
      else 
      {
        $absoluteFilePath = $this->getCacheFileService()->getAbsoluteFilePath($cachedVideoDocument->getPathToFile());
      }
      
    }
    catch (\Exception $e)
    {
      error_log('did not get any video... now making it.');

      $cachedVideo = new CachedMedia();
      $cachedVideo->setStatus('scheduled');
      $cachedVideo->setSerializedSpecification($flySpec->serialize());
      $cachedVideo->setEntityId($entityId);
      $this->getCachedMediaDatabase()->updateCachedMedia($cachedVideo);

      $videoTranscoder = new VideoTranscoder($this);
      $cachedVideoTempName = $videoTranscoder->scheduleVideoTranscoding($videoFile, $entityId, $flySpec);
      
      $absoluteFilePath = "video_was_just_scheduled";
    }

    return $absoluteFilePath;

  }


  public function deleteCachedMedias($entityId)
  {
    $cachedMedias = $this->getCachedMediaDatabase()->getCachedMediaByEntityId($entityId);

    foreach ($cachedMedias as $cachedMedia)
    {
      $this->getCacheFileService()->deleteFile($cachedMedia->getPathToFile());
      $this->getCachedMediaDatabase()->deleteCachedMedia($cachedMedia);
    }

  }

  public function listenForTranscodingJobs()
  {
      $videoTranscoder = new VideoTranscoder($this);
      $videoTranscoder->listenForTranscodingJobs();
  }
  
  
}
