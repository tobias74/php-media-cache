<?php 

namespace PhpMediaCache;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;


class VideoTranscoder
{
  
  public function __construct($cachedMediaService)
  {
    $this->cachedMediaService = $cachedMediaService;
  }
  
  protected function getCachedMediaService()
  {
    return $this->cachedMediaService;
  }

  protected function getConfig()
  {
    return $this->getCachedMediaService()->getConfig();  
  }
  
  public function getQueueName()
  {
    return 'task_queue_'.$this->getConfig()['rabbitQueueName'];
  }

  public function scheduleVideoTranscoding($absolutePath, $entityId, $flySpec)
  {
    error_log('inside the RabbitVideoScheduler scheduling '.$absolutePath.' to '.$this->getQueueName());

    $connection = new AMQPConnection($this->getConfig()['rabbitMqHost'], 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->queue_declare($this->getQueueName(), false, true, false, false);

    $dataArray = array(
      'entityId' => $entityId,
      'serializedSpec' => $flySpec->serialize(),
      'absolutePath' => $absolutePath
    );

    error_log('This is what we have as $dataArray: ');
    error_log(print_r($dataArray,true));

    $data = json_encode($dataArray);

    error_log('This is what we dispatch as $data: ');
    error_log(print_r($data,true));

    $msg = new AMQPMessage($data,
                            array('delivery_mode' => 2) # make message persistent
                          );

    $channel->basic_publish($msg, '', $this->getQueueName());

    error_log('sent data to rebbitmq: '.print_r($data,true));
    //echo " [x] Sent ", $data, "\n";

    $channel->close();
    $connection->close();

  }


  public function listenForTranscodingJobs()
  {
    $connection = new \PhpAmqpLib\Connection\AMQPConnection($this->getConfig()['rabbitMqHost'], 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->queue_declare($this->getQueueName(), false, true, false, false);

    echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

    $callback = function($msg){
      echo "now transcoding... [x] Received ", $msg->body, "\n";
      $data = json_decode($msg->body);
      
      try
      {
        $this->performTranscoding($data->entityId, $data->serializedSpec, $data->absolutePath);
      }
      catch (\ErrorException $e)
      {
        error_log($e->getMessage());
        error_log($e->getTraceAsString());
      }

      echo "after perform transcoding.";

      $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    };

    $channel->basic_qos(null, 1, null);
    $channel->basic_consume($this->getQueueName(), '', false, false, false, false, $callback);

    while(count($channel->callbacks)) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();

  }


  public function performTranscoding($entityId, $serializedSpec, $absolutePath)
  {
    $cachedMedia = $this->getCachedMediaService()->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $serializedSpec);

    if (!$cachedMedia->isScheduled())
    {
      error_log("we found a cache-item that was not scheduled but in the queue? ".$cachedMedia->getEntityId());
    }
    else
    {
      $cachedMedia->setStatus('currently_transcoding');
      $this->getCachedMediaService()->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);
    }

    $originalFile = $absolutePath;

    $flySpecHash = unserialize($cachedMedia->getSerializedSpecification());

    $targetVideoFileWithoutExtension = tempnam('/tmp','flyfiles');
    $targetVideoFile = $targetVideoFileWithoutExtension.'.'.$flySpecHash['format'];

    $command = dirname(__FILE__)."/scripts/convert_".$flySpecHash['format']." \"$originalFile\" $targetVideoFile";

    error_log("executing ".$command);
    exec($command);
    error_log("and done it");

    if ($flySpecHash['format'] === 'jpg')
    {
      $cachedMedia->setFileType('image/'.$flySpecHash['format']);
    }
    else
    {
      $cachedMedia->setFileType('video/'.$flySpecHash['format']);
    }

    $this->getCachedMediaService()->getCacheFileService()->storeFile($targetVideoFile, $cachedMedia->getId());

    $cachedMedia->setStatus('complete');
    $this->getCachedMediaService()->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

    unlink($targetVideoFile);
    unlink($targetVideoFileWithoutExtension);

  }





}


