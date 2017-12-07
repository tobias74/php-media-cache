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
    
    protected function instantiateCachedMedia($document)
    {
        $resultHash = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($document)),true);
        
        $cachedMedia = $this->produceCachedMedia();
        $cachedMedia->setSerializedSpecification( $resultHash['serialized_specification'] );
        $cachedMedia->setEntityId( $resultHash['entity_id'] );
        $cachedMedia->setStatus( $resultHash['status'] );
        $cachedMedia->setFileType( $resultHash['file_type'] );
        $cachedMedia->setPathToFile( $resultHash['path_to_file'] );

        return $cachedMedia;
    }
    
 
    public function updateCachedMedia($cachedMedia)
    {
        $document = array(
          'entity_id' => $cachedMedia->getEntityId(),
          'serialized_specification' => $cachedMedia->getSerializedSpecification(),
          'status' => $cachedMedia->getStatus(),
          'file_type' => $cachedMedia->getFileType(),
          'path_to_file' => $cachedMedia->getPathToFile()
        );
        $dbName = $this->getMongoDbName();
        $collection = $this->getConnection()->$dbName->cached_media;
        
        $collection->updateOne(array('entity_id'=>$cachedMedia->getEntityId(), 'serialized_specification'=>$cachedMedia->getSerializedSpecification()), array('$set'=>$document), array("upsert" => true));        
    }
 
 
    public function getCachedMediaByIdAndSpec($entityId, $serializedSpec)
    {
        $dbName = $this->getMongoDbName();
        $collection = $this->getConnection()->$dbName->cached_media;
        $document = $collection->findOne(array('entity_id' => $entityId, 'serialized_specification' => $serializedSpec));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }

    public function getCachedMediaByPathToFile($pathToFile)
    {
        $dbName = $this->getMongoDbName();
        $collection = $this->getConnection()->$dbName->cached_media;
        $document = $collection->findOne(array('path_to_file' => $pathToFile));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }

    public function getCachedMediaByEntityId($entityId)
    {
        $dbName = $this->getMongoDbName();
        $collection = $this->getConnection()->$dbName->cached_media;
        $document = $collection->findOne(array('entity_id' => $entityId));
        if (!$document)
        {
            throw new \Exception();
        }
        return $this->instantiateCachedMedia($document);
    }


    public function deleteCachedMedia($cachedMedia)
    {
        $dbName = $this->getMongoDbName();
        $collection = $this->getConnection()->$dbName->cached_media;
        $collection->deleteOne(array('entity_id'=>$cachedMedia->getEntityId(), 'serialized_specification'=>$cachedMedia->getSerializedSpecification()));        
    }
   
}