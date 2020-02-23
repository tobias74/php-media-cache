<?php

namespace PhpMediaCache\Strategies;

class ImageTranscoder
{
    protected $transcodedFilePath;

    public function transcode($absolutePath, $flySpec)
    {
        $imageResizer = new ImageResizer();
        $this->transcodedFilePath = $imageResizer->createCachedImage($absolutePath, $flySpec);

        return $this->transcodedFilePath;
    }

    public function cleanup()
    {
        unlink($this->transcodedFilePath);
    }
}
