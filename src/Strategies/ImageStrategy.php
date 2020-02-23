<?php

namespace PhpMediaCache\Strategies;

class ImageStrategy extends AbstractStrategy
{
    public function getQueueName()
    {
        return 'transcoding_images_task_queue_'.$this->getConfig()['rabbitQueueName'];
    }

    public function createTranscoder()
    {
        return new ImageTranscoder();
    }
}
