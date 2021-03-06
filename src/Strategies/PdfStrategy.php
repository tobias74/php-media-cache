<?php

namespace PhpMediaCache\Strategies;

class PdfStrategy extends AbstractStrategy
{
    public function getQueueName()
    {
        return 'transcoding_pdfs_task_queue_'.$this->getConfig()['rabbitQueueName'];
    }

    public function createTranscoder()
    {
        return new PdfTranscoder();
    }
}
