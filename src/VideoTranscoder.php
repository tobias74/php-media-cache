<?php 

namespace Zeitfaden\CachedMediaService;

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
    error_log('inside the RabbitVideoScheduler scheduling to '.$this->getQueueName());

    $connection = new AMQPConnection($this->getConfig()['rabbitMqHost'], 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->queue_declare($this->getQueueName(), false, true, false, false);

    $data = json_encode(array(
      'entityId' => $entityId,
      'serializedSpec' => $flySpec->serialize(),
      'absolutePath' => $absolutePath
    ));
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
      $this->performTranscoding($data->entityId, $data->serializedSpec, $data->absolutePath);
      echo "done transcoding.";

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

    $targetVideoFile = tempnam('/tmp','flyfiles');
    $flySpecHash = unserialize($cachedMedia->getSerializedSpecification());

//  $uniqueFileNameMp4 = $uniqueFileName.'.mp4';
    $targetVideoFile = $targetVideoFile.'.'.$flySpecHash['format'];

    $command = dirname(__FILE__)."/scripts/convert_".$flySpecHash['format']." $originalFile $targetVideoFile";

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

    $cachedMedia->setStatus('complete');

    $relativeFilePath = $this->getCachedMediaService()->getCacheFileService()->storeFileByPath($targetVideoFile);
    $cachedMedia->setPathToFile($relativeFilePath);
    $this->getCachedMediaService()->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

    unlink($targetVideoFile);

  }





}


