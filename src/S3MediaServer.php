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
                'entityId' => $cachedMedia->getEntityId(),
                'status' => 'scheduled',
            );
        } else {
            return array(
                'entityId' => $cachedMedia->getEntityId(),
                'status' => 'done',
                'fileNameInBucket' => $cachedMedia->getId(),
              );
        }
    }

    public function transcodePdf($id, $spec)
    {
        $pdfUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id, '+7 days');
        $cachedImage = $this->getMediaCacheService()->transcodePdfUsingQueue($pdfUri, $id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function transcodeImage($id, $spec)
    {
        $imageUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id, '+7 days');
        $cachedImage = $this->getMediaCacheService()->transcodeImageUsingQueue($imageUri, $id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function transcodeVideo($id, $flySpec)
    {
        $videoUrl = $this->getS3ServiceForOriginalFiles()->getExternalUri($id, '+7 days');
        $cachedVideo = $this->getMediaCacheService()->transcodeVideoUsingQueue($videoUrl, $id, $flySpec);

        return $this->mapCachedMedia($cachedVideo);
    }

    public function transcodePdfSync($id, $spec)
    {
        $pdfUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedImage = $this->getMediaCacheService()->transcodePdfSync($pdfUri, $id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function transcodeImageSync($id, $spec)
    {
        $imageUri = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedImage = $this->getMediaCacheService()->transcodeImageSync($imageUri, $id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function transcodeVideoSync($id, $flySpec)
    {
        $videoUrl = $this->getS3ServiceForOriginalFiles()->getExternalUri($id);
        $cachedVideo = $this->getMediaCacheService()->transcodeVideoSync($videoUrl, $id, $flySpec);

        return $this->mapCachedMedia($cachedVideo);
    }

    public function getCachedMediaData($id, $spec)
    {
        $cachedImage = $this->getMediaCacheService()->getCachedMedia($id, $spec);

        return $this->mapCachedMedia($cachedImage);
    }

    public function getCachedMediasData($ids, $spec)
    {
        $cachedMedias = $this->getMediaCacheService()->getCachedMedias($ids, $spec);
        $results = [];
        foreach ($cachedMedias as $cachedMedia) {
            $results[$cachedMedia->getEntityId()] = $this->mapCachedMedia($cachedMedia);
        }

        return $results;
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
           'rabbitMqPort' => $config['rabbitMqPort'],
           'rabbitMqUser' => $config['rabbitMqUser'],
           'rabbitMqPassword' => $config['rabbitMqPassword'],
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
