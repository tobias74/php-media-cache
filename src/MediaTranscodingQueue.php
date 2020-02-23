<?php

namespace PhpMediaCache;

use PhpAmqpLib\Message\AMQPMessage;

class MediaTranscodingQueue
{
    public function __construct($cachedMediaService, $strategy)
    {
        $this->cachedMediaService = $cachedMediaService;
        $this->strategy = $strategy;
    }

    protected function getQueueName()
    {
        return $this->strategy->getQueueName();
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

        error_log('Publishing Message to channel '.$channelName);

        // make message persistent
        $msg = new AMQPMessage(json_encode($messageData), array('delivery_mode' => 2));
        $channel->basic_publish($msg, '', $channelName);

        $channel->close();
        $connection->close();
    }

    public function scheduleTranscoding($absolutePath, $entityId, $serializedSpec)
    {
        $flySpec = json_decode($serializedSpec, true);

        $this->getCachedMediaService()->advanceToScheduled($entityId, $flySpec);

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
        try {
            $connection = $this->getConnection();
            $channel = $connection->channel();
            $channel->queue_declare($queueName, false, true, false, false);

            echo ' [*] Waiting for messages on '.$queueName.'. To exit press CTRL+C', "\n";

            $channel->basic_qos(null, 1, null);
            $channel->basic_consume($queueName, '', false, false, false, false, function ($msg) use ($callback) {
                $callback($msg);
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            });

            while (count($channel->callbacks)) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();
        } catch (\ErrorException $e) {
            echo $e->getMessage();
            echo $e->getTraceAsString();
            echo 'Exiting from listening on Queue....................................';
            die(255);
        }
    }

    public function listenForTranscodingJobs()
    {
        $this->listenOnQueue($this->getQueueName(), function ($msg) {
            echo 'now transcoding... [x] Received ', $msg->body, "\n";
            $data = json_decode($msg->body, true);
            $this->performTranscoding($data['entityId'], $data['serializedSpec'], $data['absolutePath']);
        });
    }

    protected function performTranscoding($entityId, $serializedSpec, $absolutePath)
    {
        echo 'Performing Transcoding';
        error_log('Again in Errorlog- Trying transcoding');
        try {
            $flySpec = json_decode($serializedSpec, true);
            $this->getCachedMediaService()->advanceToCurrentlyTranscoding($entityId, $flySpec);

            $transcoder = $this->strategy->createTranscoder();
            $this->getCachedMediaService()->storeTranscodedFile($entityId, $flySpec, $transcoder->transcode($absolutePath, $flySpec));
            $this->getCachedMediaService()->advanceToDone($entityId, $flySpec);
            $transcoder->cleanup();
        } catch (\Exception $e) {
            echo 'error position code: 23874698769876';
            echo $e->getMessage();
        }
    }
}
