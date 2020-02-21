<?php

namespace PhpMediaCache\Stategies;

class VideoStrategy
{
    public function __construct()
    {
    }

    protected function getQueueName()
    {
        return 'transcoding_videos_queue_'.$this->getConfig()['rabbitQueueName'];
    }

    public function createTranscoder()
    {
        return new VideoTranscoder();
    }
    
    
}
