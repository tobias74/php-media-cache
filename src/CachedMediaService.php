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
                $strategy = new Strategies\ImageStrategy();
                break;
            case 'video':
                $strategy = new Strategies\VideoStrategy();
                break;
            case 'pdf':
                $strategy = new Strategies\PdfStrategy();
                break;
            default:
                throw new \Exception('unknown media type in schedule Trnscoding');
        }

        $transcodingQueue = new MediaTranscodeingQueue($this, $strategy);
        $transcodingQueue->scheduleTranscoding($mediaFilePath, $cachedMedia->getEntityId(), $cachedMedia->getSerializedSpecification());
    }

    public function listenForImageTranscodingJobs()
    {
        $transcodingQueue = new MediaTranscodeingQueue($this, new Strategies\ImageStrategy());
        $transcodingQueue->listenForTranscodingJobs();
    }

    public function listenForVideoTranscodingJobs()
    {
        $transcodingQueue = new MediaTranscodeingQueue($this, new Strategies\VideoStrategy());
        $transcodingQueue->listenForTranscodingJobs();
    }

    public function listenForPdfTranscodingJobs()
    {
        $transcodingQueue = new MediaTranscodeingQueue($this, new Strategies\PdfStrategy());
        $transcodingQueue->listenForTranscodingJobs();
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

    public function getCachedMediaDatabase()
    {
        if (!$this->cachedMediaDatabase) {
            $this->cachedMediaDatabase = new CachedMediaDatabase();
            $this->cachedMediaDatabase->setMongoDbHost($this->config['mongoDbHost']);
            $this->cachedMediaDatabase->setMongoDbName($this->config['mongoDbName']);
        }

        return $this->cachedMediaDatabase;
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

    public function isTrancodingDone($imageFile, $entityId, $flySpec)
    {
        $cachedDocument = $this->getTranscodedMedia($entityId, $flySpec);
        if ($cachedDocument->isDone()) {
            return true;
        } else {
            return false;
        }
    }

    public function getTranscodedMedia($externalId, $flySpec)
    {
        $cachedDocument = $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($externalId, json_encode($flySpec));
        if ($cachedDocument->isDone()) {
            return $cachedDocument;
        } else {
            throw new \Exception('trancoded image not ready yet');
        }
    }

    public function createCachedMedia($entityId, $flySpec)
    {
        $cachedMedia = new CachedMedia();
        $cachedMedia->setStatus('initialized');
        $cachedMedia->setSerializedSpecification(json_encode($flySpec));
        $cachedMedia->setEntityId($entityId);
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

        return $cachedMedia;
    }

    public function storeTranscodedFile($entityId, $flySpec, $transcodedFilePath)
    {
        $cachedMedia = $this->getTranscodedMedia($entityId, $flySpec);
        $this->getCacheFileService()->storeFile(
            $transcodedFilePath,
            $cachedMedia->getId()
        );
    }

    public function advanceToDone($entityId, $flySpec)
    {
        $cachedMedia = $this->getTranscodedMedia($entityId, $flySpec);
        $this->advanceMediaToDone($cachedMedia);
    }

    public function advanceToScheduled($entityId, $flySpec)
    {
        $cachedMedia = $this->getTranscodedMedia($entityId, $flySpec);
        $this->advanceMediaToScheduled($cachedMedia);
    }

    public function advanceToCurrentlyTranscoding($entityId, $flySpec)
    {
        $cachedMedia = $this->getTranscodedMedia($entityId, $flySpec);
        $this->advanceMediaToCurrentlyTranscoding($cachedMedia);
    }

    protected function advanceMediaToScheduled($cachedMedia)
    {
        $cachedMedia->setStatus('scheduled');
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
    }

    protected function advanceMediaToCurrentlyTranscoding($cachedMedia)
    {
        if (!$cachedMedia->isScheduled()) {
            throw new \Exception('we found a cache-item that was not scheduled but in the queue? '.$cachedMedia->getEntityId());
        } else {
            $cachedMedia->setStatus('currently_transcoding');
            $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
        }
    }

    protected function advanceMediaToDone($cachedMedia)
    {
        $cachedMedia->setStatus('complete');
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
    }
}
