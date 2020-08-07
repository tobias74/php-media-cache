<?php

namespace PhpMediaCache\Strategies;

class AbstractStrategy
{
    public function __construct($cachedMediaService, $config)
    {
        $this->config = $config;
        $this->cachedMediaService = $cachedMediaService;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getCachedMediaService()
    {
        return $this->cachedMediaService;
    }

    public function getTranscodingQueue()
    {
        return new \PhpMediaCache\MediaTranscodingQueue($this->getCachedMediaService(), $this);
    }

    public function transcodeUsingQueue($originalUri, $entityId, $flySpec)
    {
        try {
            $cachedMedia = $this->getCachedMediaService()->getCachedMedia($entityId, $flySpec);
        } catch (\Exception $e) {
            $cachedMedia = $this->getCachedMediaService()->createCachedMedia($entityId, $flySpec);
        }

        if ($this->needsScheduling($cachedMedia)) {
            if ($cachedMedia->isIndividual()) {
                error_log('this is an individual (sync), this should not need scheduling');
            } else {
                $this->scheduleTranscoding($originalUri, $cachedMedia);
            }
        }

        return $cachedMedia;
    }

    public function transcodeSync($originalUri, $entityId, $flySpec)
    {
        try {
            $cachedMedia = $this->getCachedMediaService()->getCachedMedia($entityId, $flySpec);
        } catch (\Exception $e) {
            $cachedMedia = $this->getCachedMediaService()->createCachedMedia($entityId, $flySpec);
        }

        if ($cachedMedia->isDone()) {
            return $cachedMedia;
        } else {
            $cachedMedia->setStatus('individual');
            $this->performTranscoding($entityId, json_encode($flySpec), $originalUri);

            return $cachedMedia;
        }
    }

    protected function needsScheduling($cachedMedia)
    {
        return $cachedMedia->hasStalled() || $cachedMedia->isInitialized();
    }

    public function listenForTranscodingJobs()
    {
        $this->getTranscodingQueue()->listenForTranscodingJobs();
    }

    protected function scheduleTranscoding($mediaFilePath, $cachedMedia)
    {
        $this->getTranscodingQueue()->scheduleTranscoding($mediaFilePath, $cachedMedia->getEntityId(), $cachedMedia->getSerializedSpecification());
    }

    public function performTranscoding($entityId, $serializedSpec, $absolutePath)
    {
        echo 'Performing Transcoding';
        echo 'Again in Errorlog- Trying transcoding';
        try {
            $flySpec = json_decode($serializedSpec, true);
            $this->getCachedMediaService()->advanceToCurrentlyTranscoding($entityId, $flySpec);

            $transcoder = $this->createTranscoder();
            $this->getCachedMediaService()->storeTranscodedFile($entityId, $flySpec, $transcoder->transcode($absolutePath, $flySpec));
            $this->getCachedMediaService()->advanceToDone($entityId, $flySpec);
            $transcoder->cleanup();
        } catch (\Exception $e) {
            echo 'error position code: 23874698769876';
            echo $e->getMessage();
        }
    }
}
