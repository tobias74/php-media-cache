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
        $uid = uniqid();
        $uid .= rand(100000, 999999);

        return $uid;
    }

    protected function getConnection()
    {
        if (!$this->connection) {
            $this->connection = new \MongoDB\Client($this->mongoDbHost);
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
        $resultHash = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($document)), true);

        $cachedMedia = $this->produceCachedMedia();
        $cachedMedia->setSerializedSpecification($resultHash['serialized_specification']);
        $cachedMedia->setEntityId($resultHash['entity_id']);
        $cachedMedia->setStatus($resultHash['status']);
        $cachedMedia->setId($resultHash['id']);
        $cachedMedia->lastUpdated = $resultHash['last_updated'] ?? 0;

        return $cachedMedia;
    }

    public function getById($mediaId)
    {
        $document = $this->getMediaCollection()->findOne(array('id' => $mediaId));
        if (!$document) {
            throw new \Exception();
        }

        return $this->instantiateCachedMedia($document);
    }

    public function updateCachedMedia($cachedMedia)
    {
        if (false == $cachedMedia->getId()) {
            $cachedMedia->setId('fly'.$this->getUniqueId());
        }

        $document = array(
          'entity_id' => $cachedMedia->getEntityId(),
          'serialized_specification' => $cachedMedia->getSerializedSpecification(),
          'status' => $cachedMedia->getStatus(),
          'id' => $cachedMedia->getId(),
          'last_updated' => time(),
        );

        $this->getMediaCollection()->updateOne(array('id' => $cachedMedia->getId()), array('$set' => $document), array('upsert' => true));
    }

    public function getCachedMediaByIdAndSpec($entityId, $serializedSpec)
    {
        $document = $this->getMediaCollection()->findOne(array('entity_id' => $entityId, 'serialized_specification' => $serializedSpec));
        if (!$document) {
            throw new \Exception('cached media not found for entity_id' + $entityId);
        }

        return $this->instantiateCachedMedia($document);
    }

    public function getCachedMediasByIdsAndSpec($entityIds, $serializedSpec)
    {
        $documents = $this->getMediaCollection()->find(array('entity_id' => array('$in' => $entityIds), 'serialized_specification' => $serializedSpec));

        $results = [];
        foreach ($documents as $document) {
            $results[] = $this->instantiateCachedMedia($document);
        }

        return $results;
    }

    public function getCachedMediasByEntityId($entityId)
    {
        $medias = array();
        $cursor = $this->getMediaCollection()->find(array('entity_id' => $entityId));
        foreach ($cursor as $document) {
            $medias[] = $this->instantiateCachedMedia($document);
        }

        return $medias;
    }

    public function deleteCachedMedia($cachedMedia)
    {
        $this->getMediaCollection()->deleteOne(array('entity_id' => $cachedMedia->getEntityId(), 'serialized_specification' => $cachedMedia->getSerializedSpecification()));
    }
}
