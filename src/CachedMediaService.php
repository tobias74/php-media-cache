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

    public function scheduleTranscoding($mediaFilePath, $cachedMedia, $type)
    {
        switch ($type) {
            case 'image':
                $strategy = new Strategies\ImageStrategy($this->getConfig());
                break;
            case 'video':
                $strategy = new Strategies\VideoStrategy($this->getConfig());
                break;
            case 'pdf':
                $strategy = new Strategies\PdfStrategy($this->getConfig());
                break;
            default:
                throw new \Exception('unknown media type in schedule Trnscoding');
        }

        $transcodingQueue = new MediaTranscodingQueue($this, $strategy);
        $transcodingQueue->scheduleTranscoding($mediaFilePath, $cachedMedia->getEntityId(), $cachedMedia->getSerializedSpecification());
    }

    public function listenForImageTranscodingJobs()
    {
        $transcodingQueue = new MediaTranscodingQueue($this, new Strategies\ImageStrategy($this->getConfig()));
        $transcodingQueue->listenForTranscodingJobs();
    }

    public function listenForVideoTranscodingJobs()
    {
        $transcodingQueue = new MediaTranscodingQueue($this, new Strategies\VideoStrategy($this->getConfig()));
        $transcodingQueue->listenForTranscodingJobs();
    }

    public function listenForPdfTranscodingJobs()
    {
        $transcodingQueue = new MediaTranscodingQueue($this, new Strategies\PdfStrategy($this->getConfig()));
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

    public function transcodePdfUsingCache($pdfUri, $entityId, $flySpec)
    {
        return $this->transcodeUsingCache(array(
            'originalUri' => $pdfUri,
            'type' => 'pdf',
            'entityId' => $entityId,
            'flySpec' => $flySpec,
        ));
    }

    public function transcodeImageUsingCache($imageUri, $entityId, $flySpec)
    {
        return $this->transcodeUsingCache(array(
            'originalUri' => $imageUri,
            'type' => 'image',
            'entityId' => $entityId,
            'flySpec' => $flySpec,
        ));
    }

    public function transcodeVideoUsingCache($videoFile, $entityId, $flySpec)
    {
        return $this->transcodeUsingCache(array(
            'originalUri' => $imageUri,
            'type' => 'video',
            'entityId' => $entityId,
            'flySpec' => $flySpec,
        ));
    }

    protected function transcodeUsingCache($data)
    {
        try {
            $cachedMedia = $this->getCachedMedia($data['entityId'], $data['flySpec']);
        } catch (\Exception $e) {
            $cachedMedia = $this->createCachedMedia($data['entityId'], $data['flySpec'], $data['type']);
            $this->scheduleTranscoding($data['originalUri'], $cachedMedia, $data['type']);
        }

        return $cachedMedia;
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

    public function createCachedMedia($entityId, $flySpec, $type)
    {
        $cachedMedia = new CachedMedia();
        $cachedMedia->setStatus('initialized');
        $cachedMedia->setSerializedSpecification(json_encode($flySpec));
        $cachedMedia->setEntityId($entityId);
        $cachedMedia->setType($type);
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
