<?php

namespace PhpMediaCache;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class VideoTranscoder
{
    public function __construct($cachedMediaService)
    {
        $this->cachedMediaService = $cachedMediaService;
    }

    protected function getCachedMediaService()
    {
        return $this->cachedMediaService;
    }

    protected function getConfig()
    {
        return $this->getCachedMediaService()->getConfig();
    }

    public function getQueueName()
    {
        return 'task_queue_'.$this->getConfig()['rabbitQueueName'];
    }


    public function transcode($absolutePath, $flySpec)
    {
        $targetVideoFileWithoutExtension = tempnam('/tmp', 'flyfiles');
        $targetVideoFile = $targetVideoFileWithoutExtension.'.'.$flySpecHash['format'];

        $command = dirname(__FILE__).'/../scripts/convert_'.$flySpecHash['format']." \"$originalFile\" $targetVideoFile";

        error_log('executing '.$command);
        exec($command);
        error_log('and done it');
        
        return $targetVideoFile;
    }
    
    public function cleanup() {
        unlink($targetVideoFile);
        unlink($targetVideoFileWithoutExtension);
    }
}
