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

    protected function getStrategyByType($type)
    {
        switch ($type) {
            case 'image':
                $strategy = new Strategies\ImageStrategy($this, $this->getConfig());
                break;
            case 'video':
                $strategy = new Strategies\VideoStrategy($this, $this->getConfig());
                break;
            case 'pdf':
                $strategy = new Strategies\PdfStrategy($this, $this->getConfig());
                break;
            default:
                throw new \Exception('unknown media type in schedule Trnscoding');
        }

        return $strategy;
    }

    public function listenForImageTranscodingJobs()
    {
        $this->getStrategyByType('image')->listenForTranscodingJobs();
    }

    public function listenForVideoTranscodingJobs()
    {
        $this->getStrategyByType('video')->listenForTranscodingJobs();
    }

    public function listenForPdfTranscodingJobs()
    {
        $this->getStrategyByType('pdf')->listenForTranscodingJobs();
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

    public function transcodePdfUsingQueue($pdfUri, $entityId, $flySpec)
    {
        return $this->getStrategyByType('pdf')->transcodeUsingQueue($pdfUri, $entityId, $flySpec);
    }

    public function transcodeImageUsingQueue($imageUri, $entityId, $flySpec)
    {
        return $this->getStrategyByType('image')->transcodeUsingQueue($imageUri, $entityId, $flySpec);
    }

    public function transcodeVideoUsingQueue($videoFile, $entityId, $flySpec)
    {
        return $this->getStrategyByType('video')->transcodeUsingQueue($videoFile, $entityId, $flySpec);
    }

    public function transcodePdfSync($pdfUri, $entityId, $flySpec)
    {
        return $this->getStrategyByType('pdf')->transcodeSync($pdfUri, $entityId, $flySpec);
    }

    public function transcodeImageSync($imageUri, $entityId, $flySpec)
    {
        return $this->getStrategyByType('image')->transcodeSync($imageUri, $entityId, $flySpec);
    }

    public function transcodeVideoSync($videoFile, $entityId, $flySpec)
    {
        return $this->getStrategyByType('video')->transcodeSync($videoFile, $entityId, $flySpec);
    }

    public function getCachedMediaById($mediaId)
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
        $cachedDocument = $this->getCachedMedia($entityId, $flySpec);
        if ($cachedDocument->isDone()) {
            return true;
        } else {
            return false;
        }
    }

    public function getCachedMedia($externalId, $flySpec)
    {
        return $this->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($externalId, json_encode($flySpec));
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
        $cachedMedia = $this->getCachedMedia($entityId, $flySpec);
        $this->getCacheFileService()->storeFile(
            $transcodedFilePath,
            $cachedMedia->getId()
        );
    }

    public function advanceToDone($entityId, $flySpec)
    {
        $cachedMedia = $this->getCachedMedia($entityId, $flySpec);
        $this->advanceMediaToDone($cachedMedia);
    }

    public function advanceToScheduled($entityId, $flySpec)
    {
        $cachedMedia = $this->getCachedMedia($entityId, $flySpec);
        $this->advanceMediaToScheduled($cachedMedia);
    }

    public function advanceToCurrentlyTranscoding($entityId, $flySpec)
    {
        $cachedMedia = $this->getCachedMedia($entityId, $flySpec);
        $this->advanceMediaToCurrentlyTranscoding($cachedMedia);
    }

    protected function advanceMediaToScheduled($cachedMedia)
    {
        $cachedMedia->setStatus('scheduled');
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
    }

    protected function advanceMediaToCurrentlyTranscoding($cachedMedia)
    {
        $cachedMedia->setStatus('currently_transcoding');
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
    }

    protected function advanceMediaToDone($cachedMedia)
    {
        $cachedMedia->setStatus('complete');
        $this->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
    }
}
