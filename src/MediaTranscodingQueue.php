<?php

namespace PhpMediaCache;

use PhpAmqpLib\Message\AMQPMessage;

class MediaTranscodingQueue
{
    public function __construct($cachedMediaService, $queueName, $strategy)
    {
        $this->cachedMediaService = $cachedMediaService;
        $this->queueName = $queueName;
        $this->strategy = $strategy;
    }

    protected function getQueueName()
    {
        return $this->queueName;
    }

    protected function getCachedMediaService()
    {
        return $this->cachedMediaService;
    }

    protected function getConfig()
    {
        return $this->getCachedMediaService()->getConfig();
    }

    protected function publishMessage($channelName, $messageData)
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();
        $channel->queue_declare($channelName, false, true, false, false);

        error_log('This is what we have as $dataArray: ');
        error_log(print_r($dataArray, true));

        // make message persistent
        $msg = new AMQPMessage(json_encode($messageData), array('delivery_mode' => 2));
        $channel->basic_publish($msg, '', $channelName);

        $channel->close();
        $connection->close();
    }

    protected function scheduleTranscoding($absolutePath, $entityId, $serializedSpec)
    {
        $this->publishMessage($this->getQueueName(), array(
          'entityId' => $entityId,
          'serializedSpec' => $serializedSpec,
          'absolutePath' => $absolutePath,
        ));
    }

    protected function getConnection()
    {
        return new \PhpAmqpLib\Connection\AMQPConnection($this->getConfig()['rabbitMqHost'], 5672, 'guest', 'guest');
    }

    protected function listenOnQueue($queueName, $callback)
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();
        $channel->queue_declare($queueName, false, true, false, false);

        echo ' [*] Waiting for messages on '.$queueName.'. To exit press CTRL+C', "\n";

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($queueName, '', false, false, false, false, function ($msg) {
            $callback($msg);
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        });

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    protected function listenForTranscodingJobs()
    {
        $this->listenOnQueue($this->getQueueName(), function ($msg) {
            echo 'now transcoding... [x] Received ', $msg->body, "\n";
            $data = json_decode($msg->body);

            try {
                $this->performTranscoding($data->entityId, $data->serializedSpec, $data->absolutePath);
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                error_log($e->getTraceAsString());
            }

            echo 'after perform transcoding.';
        });
    }

    protected function performTranscoding($entityId, $serializedSpec, $absolutePath)
    {
        $cachedMedia = $this->getCachedMediaService()->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $serializedSpec);
        $this->advanceMediaToCurrentlyTranscoding($cachedMedia);
        $flySpec = json_decode($cachedMedia->getSerializedSpecification());

        $transcoder = $this->strategy->createTranscoder();

        $cachedImageTempName = $transcoder->transcode($absolutePath, $flySpec);
        

        // is this used or read anywhere?
        /*
        if ('jpg' === $flySpec['format']) {
            $cachedMedia->setFileType('image/'.$flySpec['format']);
        } else {
            $cachedMedia->setFileType('video/'.$flySpec['format']);
        }
        */
        
        

        $this->getCachedMediaService()->getCacheFileService()->storeFile($cachedImageTempName, $cachedMedia->getId());
        $cachedMedia->setStatus('complete');
        $this->getCachedMediaService()->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

        $transcoder->cleanup();
    }

    protected function advanceMediaToCurrentlyTranscoding($cachedMedia)
    {
        if (!$cachedMedia->isScheduled()) {
            throw new \Exception('we found a cache-item that was not scheduled but in the queue? '.$cachedMedia->getEntityId());
        } else {
            $cachedMedia->setStatus('currently_transcoding');
            $this->getCachedMediaService()->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
        }
    }
}
