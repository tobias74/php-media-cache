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
        error_log('---------------------------------------------------------------------------this is our port '. $this->getConfig()['rabbitMqPort']);
        if ($this->getConfig()['rabbitMqPort'] === 5671) {
            error_log('using rabbit mq over ssl');
            $ssl = [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ];
    
            $connection = new \PhpAmqpLib\Connection\AMQPSSLConnection(
                $this->getConfig()['rabbitMqHost'],
                $this->getConfig()['rabbitMqPort'],
                $this->getConfig()['rabbitMqUser'],
                $this->getConfig()['rabbitMqPassword'],
                '/',
                $ssl
            );
    
            return $connection;
        } else {
            error_log('using rabbit my NON ssl');
            return new \PhpAmqpLib\Connection\AMQPStreamConnection($this->getConfig()['rabbitMqHost'], $this->getConfig()['rabbitMqPort'], $this->getConfig()['rabbitMqUser'], $this->getConfig()['rabbitMqPassword']);            
        }
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
                    $callback($msg, $connection);
                    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                });

                while ($channel->is_consuming()) {
                    echo 'in while loop before wait';
                    $channel->wait();
                    echo 'in while loop after wait';
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
        });
    }
}
