<?php

namespace PhpMediaCache\Strategies;

class PdfTranscoder
{
    protected $transcodedFilePath;

    public function transcode($absolutePath, $flySpec)
    {
        $im = new \Imagick();
        $im->setResolution(150, 150);
        $im->readImage($absolutePath);
        $im->resetIterator();

        $im = $im->appendImages(true);

        $im->setImageBackgroundColor('white');
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

        if ($flySpec['width'] && $flySpec['height']) {
            $im->resizeImage($flySpec['width'], $flySpec['height'], \Imagick::FILTER_LANCZOS, 1, true);
        }

        $im->setImageFormat('jpg');

        $this->transcodedFilePath = tempnam('/tmp', 'flyfiles');
        $im->writeImages($this->transcodedFilePath, true);

        return $this->transcodedFilePath;
    }

    public function cleanup()
    {
        unlink($this->transcodedFilePath);
    }
}
