<?php

namespace PhpMediaCache;

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
        if (!$this->cachedMediaDatabase) {
            $this->cachedMediaDatabase = new CachedMediaDatabase();
            $this->cachedMediaDatabase->setMongoDbHost($this->config['mongoDbHost']);
            $this->cachedMediaDatabase->setMongoDbName($this->config['mongoDbName']);
        }

        return $this->cachedMediaDatabase;
    }

    public function setCacheFileService($val)
    {
        $this->cacheFileService = $val;
    }

    public function getCacheFileService()
    {
        return $this->cacheFileService;
    }

    public function getExternalUriForMedia($cachedMedia)
    {
        return $this->getCacheFileService()->getExternalUri($cachedMedia->getId());
    }

    public function getCachedPdfImage($pdfUri, $entityId, $flySpec)
    {
        try {
            $cachedImage = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $flySpec->serialize());
        } catch (\Exception $e) {
            $cachedImage = new CachedMedia();
            $cachedImage->setFileType('image/png');
            $cachedImage->setStatus('in_progress');
            $this->getCachedMediaDatabase()->updateCachedMedia($cachedImage);

            $imageResizer = new PdfToImageConverter();
            $cachedImageTempName = $imageResizer->createCachedImage($pdfUri, $flySpec);
            $relativeFilePath = $this->getCacheFileService()->storeFile($cachedImageTempName, $cachedImage->getId());
            unlink($cachedImageTempName);

            $cachedImage->setStatus('complete');
            $cachedImage->setSerializedSpecification($flySpec->serialize());
            $cachedImage->setEntityId($entityId);
            $this->getCachedMediaDatabase()->updateCachedMedia($cachedImage);
        }

        return $cachedImage;
    }

    public function getCachedImage($imageUri, $entityId, $flySpec)
    {
        try {
            $cachedImage = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $flySpec->serialize());
        } catch (\Exception $e) {
            $cachedImage = new CachedMedia();
            $cachedImage->setFileType('image/png');
            $cachedImage->setStatus('in_progress');
            $this->getCachedMediaDatabase()->updateCachedMedia($cachedImage);

            $imageResizer = new ImageResizer();
            $cachedImageTempName = $imageResizer->createCachedImage($imageUri, $flySpec);
            $relativeFilePath = $this->getCacheFileService()->storeFile($cachedImageTempName, $cachedImage->getId());
            unlink($cachedImageTempName);

            $cachedImage->setStatus('complete');
            $cachedImage->setSerializedSpecification($flySpec->serialize());
            $cachedImage->setEntityId($entityId);
            $this->getCachedMediaDatabase()->updateCachedMedia($cachedImage);
        }

        return $cachedImage;
    }

    public function getCachedVideo($videoFile, $entityId, $flySpec)
    {
        try {
            $cachedVideoDocument = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $flySpec->serialize());

            return $cachedVideoDocument;
        } catch (\Exception $e) {
            error_log('did not get any video... now making it.');

            $cachedVideo = new CachedMedia();
            $cachedVideo->setStatus('scheduled');
            $cachedVideo->setSerializedSpecification($flySpec->serialize());
            $cachedVideo->setEntityId($entityId);
            $this->getCachedMediaDatabase()->updateCachedMedia($cachedVideo);

            $videoTranscoder = new VideoTranscoder($this);
            $cachedVideoTempName = $videoTranscoder->scheduleVideoTranscoding($videoFile, $entityId, $flySpec);

            return $cachedVideo;
        }
    }

    public function getMediaById($mediaId)
    {
        $this->getCachedMediaDatabase()->getById($mediaId);
    }

    public function deleteCachedMedias($entityId)
    {
        try {
            $cachedMedias = $this->getCachedMediaDatabase()->getCachedMediaByEntityId($entityId);
        } catch (\Exception $e) {
            error_log('we tried to deleted cached medias, but did not find any?');
            $cachedMedias = array();
        }

        foreach ($cachedMedias as $cachedMedia) {
            $this->getCacheFileService()->deleteFile($cachedMedia->getId());
            $this->getCachedMediaDatabase()->deleteCachedMedia($cachedMedia);
        }
    }

    public function listenForTranscodingJobs()
    {
        $videoTranscoder = new VideoTranscoder($this);
        $videoTranscoder->listenForTranscodingJobs();
    }
}
