<?php

namespace PhpMediaCache\Strategies;

class VideoStrategy
{
    public function __construct()
    {
    }

    public function getQueueName()
    {
        return 'transcoding_videos_queue_'.$this->getConfig()['rabbitQueueName'];
    }

    public function createTranscoder()
    {
        return new VideoTranscoder();
    }
}
