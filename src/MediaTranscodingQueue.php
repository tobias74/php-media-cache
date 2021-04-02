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
        return new \PhpAmqpLib\Connection\AMQPConnection(
            $this->getConfig()['rabbitMqHost'],
            $this->getConfig()['rabbitMqPort'],
            $this->getConfig()['rabbitMqUser'],
            $this->getConfig()['rabbitMqPassword'],
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            3.0,
            null,
            true,  //keepalive
            0, // heartbeat
            0.0,
            null
        );
    }

    protected function listenOnQueue($queueName, $callback)
    {
        while (true) {
            try {
                $connection = $this->getConnection();
                $channel = $connection->channel();
                $channel->queue_declare($queueName, false, true, false, false);

                error_log(' [*] Waiting for messages on '.$queueName.'. To exit press CTRL+C'."\n");

                $channel->basic_qos(null, 1, null);
                $channel->basic_consume($queueName, '', false, false, false, false, function ($msg) use ($callback, $connection) {
                    error_log("we are in the consume!! \n");
                    $callback($msg, $connection);
                    error_log("we cam back from the callback \n");
                    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                    error_log('we delivered the info');
                });

                while ($channel->is_consuming()) {
                    error_log('in while loop before wait');
                    $channel->wait();
                    error_log('in while loop after wait');
                }

                error_log('we did leave the while loop of the channel...');
                $channel->close();
                $connection->close();
                error_log('we closed all channels');
            } catch (\Exception $e) {
                echo 'Exception happend: ';
                echo $e->getMessage();
                echo $e->getTraceAsString();
            }
        }
    }

    public function listenForTranscodingJobs()
    {
        $this->listenOnQueue($this->getQueueName(), function ($msg, $connection) {
            error_log('now transcoding... [x] Received '.$msg->body."\n");
            $data = json_decode($msg->body, true);
            $this->strategy->performTranscoding($data['entityId'], $data['serializedSpec'], $data['absolutePath']);
            error_log('in the listen callbacj, here we should be finished?');
        });
    }
}
