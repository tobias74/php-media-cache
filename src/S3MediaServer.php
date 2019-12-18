<?php

namespace PhpMediaCache;

class S3MediaServer
{
    public function __construct($config)
    {
        $this->configureDependencies($config);
    }

    public function listenForTranscodingJobs()
    {
        $this->getMediaCacheService()->listenForTranscodingJobs();
    }

    public function getImageData($id, $spec)
    {
        $imageUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedImage = $this->getMediaCacheService()->getCachedImage($imageUri, $id, $spec);

        return array(
          'fileNameInBucket' => $cachedImage->getId(),
        );
    }

    public function getVideoData($id, $flySpec)
    {
        $videoUrl = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedVideo = $this->getMediaCacheService()->getCachedVideo($videoUrl, $id, $flySpec);
        if ($cachedVideo->isScheduled()) {
            return array(
             'status' => 'scheduled',
            );
        } else {
            return array(
            'status' => 'done',
            'fileNameInBucket' => $cachedVideo->getId(),
          );
        }
    }

    public function deleteMedia($id)
    {
        $this->getMediaCacheService()->deleteCachedMedias($id);
    }

    public function getImageSpec()
    {
        $flySpec = new \PhpMediaCache\FlyImageSpecification();

        return $flySpec;
    }

    public function getVideoSpec()
    {
        $flySpec = new \PhpMediaCache\FlyVideoSpecification();

        return $flySpec;
    }

    protected function configureDependencies($config)
    {
        $dm = new \SugarLoaf\DependencyManager();
        $this->dependecyManager = $dm;

        $dm->registerService('S3ServiceForOriginalFiles', 'PhpS3Service\S3Service')
          ->appendUnmanagedParameter(array(
            'region' => $config['awsRegion'],
            'bucket' => $config['awsS3BucketOriginals'],
            'endpoint' => $config['awsS3Endpoint'],
            'key' => $config['awsKey'],
            'secret' => $config['awsSecret'],
          ));

        $dm->registerService('S3ServiceForTranscodedFiles', 'PhpS3Service\S3Service')
          ->appendUnmanagedParameter(array(
            'region' => $config['awsRegion'],
            'bucket' => $config['awsS3BucketTranscoded'],
            'endpoint' => $config['awsS3Endpoint'],
            'key' => $config['awsKey'],
            'secret' => $config['awsSecret'],
          ));

        // video transcoder
        $dm->registerSingleton('ListenForVideosWorker', '\Zeitfaden\CLI\ListenForVideosWorker')
         ->addManagedDependency('CachedMediaService', 'CachedMediaService');

        $dm->registerService('CreateInfrastructureWorker', '\Zeitfaden\CLI\CreateInfrastructureWorker')
         ->addManagedDependency('S3ServiceForOriginalFiles', 'S3ServiceForOriginalFiles')
         ->addManagedDependency('S3ServiceForTranscodedFiles', 'S3ServiceForTranscodedFiles');

        // Housekeeper
        $dm->registerService('AbandonnedFileSearchWorker', '\Zeitfaden\CLI\AbandonnedFileSearchWorker')
         ->addManagedDependency('S3ServiceForOriginalFiles', 'S3ServiceForOriginalFiles')
         ->addManagedDependency('S3ServiceForTranscodedFiles', 'S3ServiceForTranscodedFiles')
         ->addManagedDependency('MediaCacheService', 'CachedMediaService');

        $dm->registerSingleton('SqlProfiler', '\Tiro\Profiler');

        $dm->registerSingleton('PhpProfiler', '\Tiro\Profiler');

        $dm->registerService('CachedMediaService', '\PhpMediaCache\CachedMediaService')
         ->appendUnmanagedParameter(array(
           'mongoDbHost' => $config['mongoDbHost'],
           'mongoDbName' => $config['mongoDbName'],
           'rabbitMqHost' => $config['rabbitMqHost'],
           'rabbitQueueName' => $config['rabbitQueueName'],
         ))
         ->addManagedDependency('CacheFileService', 'S3ServiceForTranscodedFiles');
    }

    protected function getMediaCacheService()
    {
        return $this->dependecyManager->get('CachedMediaService');
    }

    protected function getS3ServiceForOriginalFiles()
    {
        return $this->dependecyManager->get('S3ServiceForOriginalFiles');
    }
}
