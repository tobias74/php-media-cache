<?php

namespace PhpMediaCache;

class CachedMediaService
{
    protected $cachedMediaDatabase;
    protected $cacheFileService;

    ////////////////START: this is the old method.... we need to do this
    protected function getSimpleFileType($fileType)
    {
        if ('image' == substr($fileType, 0, 5)) {
            return 'image';
        } elseif ('video' == substr($fileType, 0, 5)) {
            return 'video';
        } elseif ('application/pdf' === $fileType) {
            return 'pdf';
        } else {
            return 'unknown';
        }
    }

    public function scheduleTranscoding($mediaFilePath, $cachedMedia)
    {
        $fileTpye = $cachedMedia->getOriginalFileType();
        switch ($this->getSimpleFileType($fileType)) {
            case 'image':
                $this->scheduleImageTranscoding($mediaFilePath, $cachedMedia->getEntityId(), $cachedMedia->getSerializedSpecification());
                break;
            case 'video':
                $this->scheduleVideoTranscoding($mediaFilePath, $cachedMedia->getEntityId(), $cachedMedia->getSerializedSpecification());
                break;

            case 'pdf':

                break;

            default:
                throw new \Exception('unknown media type in schedule Trnscoding');
        }
    }

    ////////////END

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

    public function listenForImageTranscodingJobs()
    {
        $videoTranscoder = new ImageMediaTranscoder($this);
        $videoTranscoder->listenForTranscodingJobs();
    }

    public function listenForVideoTranscodingJobs()
    {
        $videoTranscoder = new VideoMediaTranscoder($this);
        $videoTranscoder->listenForTranscodingJobs();
    }

    // async images down here:

    public function isTrancodedImageDone($imageFile, $entityId, $flySpec)
    {
        $cachedDocument = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $flySpec->serialize());
        if ($cachedDocument->isDone()) {
            return true;
        } else {
            return false;
        }
    }

    public function getTranscodedMedia($externalId, $flySpec)
    {
        $cachedDocument = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($externalId, $flySpec->serialize());
        if ($cachedDocument->isDone()) {
            return $cachedDocument;
        } else {
            throw new \Exception('trancoded image not ready yet');
        }
    }

    public function scheduleMediaTranscoding($mediaFile, $cachedMedia)
    {
        $cachedMedia->setStatus('scheduled');
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

        $transcoder = new MediaTranscoder($this);
        $cachedTempName = $transcoder->scheduleTranscoding($mediaFile, $cachedMedia);

        return $cachedMedia;
    }

    public function createCachedMedia($entityId, $flySpec)
    {
        $cachedMedia = new CachedMedia();
        $cachedMedia->setStatus('initialized');
        $cachedMedia->setSerializedSpecification($flySpec->serialize());
        $cachedMedia->setEntityId($entityId);
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

        return $cachedMedia;
    }
}
