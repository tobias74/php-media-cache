<?php

namespace PhpMediaCache;

class ImageMediaTranscoder extends MediaTranscoder
{
    protected function performImageTranscoding($entityId, $serializedSpec, $absolutePath)
    {
        $cachedMedia = $this->getCachedMediaService()->getCachedMediaDatabase()->getCachedMediaByIdAndSpec($entityId, $serializedSpec);
        $this->advanceMediaToCurrentlyTranscoding($cachedMedia);
        $flySpec = json_decode($cachedMedia->getSerializedSpecification());

        $imageResizer = new ImageResizer();
        $cachedImageTempName = $imageResizer->createCachedImage($absolutePath, $flySpec);

        $this->getCachedMediaService()->getCacheFileService()->storeFile($cachedImageTempName, $cachedMedia->getId());
        $cachedMedia->setStatus('complete');
        $this->getCachedMediaService()->getCachedMediaDatabase()->updateCachedMedia($cachedMedia);

        unlink($cachedImageTempName);
    }
}
