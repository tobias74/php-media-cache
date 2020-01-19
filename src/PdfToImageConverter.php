<?php

namespace PhpMediaCache;

class PdfToImageConverter
{
    public function createCachedImage($pdfFileName, $flySpec)
    {
        $im = new \Imagick();
        $im->setResolution(300, 300);
        $im->readImage($pdfFileName);
        $im->resetIterator();

        $im = $im->appendImages(true);

        $im->setImageFormat('jpg');

        $im->setImageBackgroundColor('white');
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

        if ($flySpec->isOriginalSize($im)) {
            $im->resampleImage(100, 100, \Imagick::FILTER_LANCZOS, 1);
        } else {
            $im->resizeImage($flySpec->width, $flySpec->height, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $targetFileName = tempnam('/tmp', 'flyfiles');
        $im->writeImages($targetFileName, true);

        return $targetFileName;
    }
}
