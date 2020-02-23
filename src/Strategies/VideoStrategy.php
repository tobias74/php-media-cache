<?php

namespace PhpMediaCache\Strategies;

class VideoStrategy extends AbstractStrategy
{
    public function getQueueName()
    {
        return 'transcoding_videos_queue_'.$this->getConfig()['rabbitQueueName'];
    }

    public function createTranscoder()
    {
        return new VideoTranscoder();
    }
}
