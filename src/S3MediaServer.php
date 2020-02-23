<?php

namespace PhpMediaCache;

class S3MediaServer
{
    public function __construct($config)
    {
        $this->configureDependencies($config);
    }

    public function listenForImageTranscodingJobs()
    {
        $this->getMediaCacheService()->listenForImageTranscodingJobs();
    }

    public function listenForVideoTranscodingJobs()
    {
        $this->getMediaCacheService()->listenForVideoTranscodingJobs();
    }

    public function listenForPdfTranscodingJobs()
    {
        $this->getMediaCacheService()->listenForPdfTranscodingJobs();
    }

    protected function mapCachedMedia($cachedMedia)
    {
        if ($cachedMedia->isScheduled()) {
            return array(
             'status' => 'scheduled',
            );
        } else {
            return array(
            'status' => 'done',
            'fileNameInBucket' => $cachedMedia->getId(),
          );
        }
    }

    public function transcodePdf($id, $spec)
    {
        $pdfUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedImage = $this->getMediaCacheService()->transcodePdfUsingCache($pdfUri, $id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function transcodeImage($id, $spec)
    {
        $imageUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedImage = $this->getMediaCacheService()->transcodeImageUsingCache($imageUri, $id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function transcodeVideo($id, $flySpec)
    {
        $videoUrl = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedVideo = $this->getMediaCacheService()->transcodeVideoUsingCache($videoUrl, $id, $flySpec);

        return $this->mapCachedMedia($cachedVideo);
    }

    public function getCachedMediaData($id, $spec)
    {
        $cachedImage = $this->getMediaCacheService()->getCachedMedia($id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function deleteMedia($id)
    {
        $this->getMediaCacheService()->deleteCachedMedias($id);
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
