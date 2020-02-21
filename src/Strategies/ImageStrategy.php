<?php

namespace PhpMediaCache\Strategies;

class ImageStrategy
{
    public function __construct()
    {
    }
    
    protected function getQueueName()
    {
        return 'transcoding_images_task_queue_'.$this->getConfig()['rabbitQueueName'];
    }
    
    public function createTranscoder()
    {
        return new class {
            public function transcode($absolutePath, $flySpec) {
                $imageResizer = new ImageResizer();
                return $imageResizer->createCachedImage($absolutePath, $flySpec);
            }
            public function cleanup() {
                //unlink($cachedImageTempName);
            }
        };
    }
    
}
