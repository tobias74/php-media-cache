<?php

namespace PhpMediaCache;

class CachedMedia
{
    protected $serializedSpecification = '';
    protected $status = '';
    protected $entityId = '';
    protected $id = false;

    public function isRunning()
    {
        return 'running' === $this->status;
    }

    public function isScheduled()
    {
        return 'scheduled' === $this->status;
    }

    public function isDone()
    {
        return 'complete' === $this->status;
    }

    public function setSerializedSpecification($val)
    {
        $this->serializedSpecification = $val;
    }

    public function getSerializedSpecification()
    {
        return $this->serializedSpecification;
    }

    public function setStatus($val)
    {
        $this->status = $val;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setEntityId($val)
    {
        $this->entityId = $val;
    }

    public function getEntityId()
    {
        return $this->entityId;
    }

    public function setId($val)
    {
        $this->id = $val;
    }

    public function getId()
    {
        return $this->id;
    }
}
