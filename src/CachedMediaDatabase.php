<?php
namespace PhpMediaCache;


class CachedMediaDatabase
{
    protected $connection = false;

    public function __construct()
    {
        
    }
    

    public function setMongoDbHost($val)
    {
        $this->mongoDbHost = $val;
    }

    public function setMongoDbName($val)
    {
        $this->mongoDbName = $val;
    }
    
    protected function getUniqueId()
    {
        $uid=uniqid();
        $uid.=rand(100000,999999);
        return $uid;
    }
    
    protected function getConnection()
    {
        if (!$this->connection)
        {
            $this->connection = new \MongoDB\Client("mongodb://".$this->mongoDbHost.":27017");        
        }
        return $this->connection;
    }
    
    protected function getMongoDbName()
    {
        return $this->mongoDbName;
    }
    

    protected function produceCachedMedia()
    {
        return new CachedMedia();
    }
    
    protected function getMediaCollection()
    {
        $dbName = $this->getMongoDbName();
        $collection = $this->getConnection()->$dbName->cached_media;
        return $collection;        
    }
    
    
    protected function instantiateCachedMedia($document)
    {
        $resultHash = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($document)),true);
        
        $cachedMedia = $this->produceCachedMedia();
        $cachedMedia->setSerializedSpecification( $resultHash['serialized_specification'] );
        $cachedMedia->setEntityId( $resultHash['entity_id'] );
        $cachedMedia->setStatus( $resultHash['status'] );
        $cachedMedia->setFileType( $resultHash['file_type'] );
        $cachedMedia->setId( $resultHash['id'] );

        return $cachedMedia;
    }
    
    public function getById($mediaId)
    {
        $document = $this->getMediaCollection()->findOne(array('id' => $mediaId));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }
 
    public function updateCachedMedia($cachedMedia)
    {
        if ($cachedMedia->getId() == false)
        {
          $cachedMedia->setId("fly".$this->getUniqueId());
        }
    
        $document = array(
          'entity_id' => $cachedMedia->getEntityId(),
          'serialized_specification' => $cachedMedia->getSerializedSpecification(),
          'status' => $cachedMedia->getStatus(),
          'file_type' => $cachedMedia->getFileType(),
          'id' => $cachedMedia->getId()
        );

        $this->getMediaCollection()->updateOne(array('id'=>$cachedMedia->getId()), array('$set'=>$document), array("upsert" => true));        
    }
 
 
    public function getCachedMediaByIdAndSpec($entityId, $serializedSpec)
    {
        $document = $this->getMediaCollection()->findOne(array('entity_id' => $entityId, 'serialized_specification' => $serializedSpec));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }

    public function getCachedMediaByPathToFile($pathToFile)
    {
        $document = $this->getMediaCollection()->findOne(array('path_to_file' => $pathToFile));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }

    public function getCachedMediaByEntityId($entityId)
    {
        $document = $this->getMediaCollection()->findOne(array('entity_id' => $entityId));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }


    public function deleteCachedMedia($cachedMedia)
    {
        $this->getMediaCollection()->deleteOne(array('entity_id'=>$cachedMedia->getEntityId(), 'serialized_specification'=>$cachedMedia->getSerializedSpecification()));        
    }
   
}