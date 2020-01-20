<?php

namespace PhpMediaCache;

class PdfToImageConverter
{
    public function createCachedImage($pdfFileName, $flySpec)
    {
        $im = new \Imagick();
        $im->setResolution(150, 150);
        $im->readImage($pdfFileName);
        $im->resetIterator();

        $im = $im->appendImages(true);

        $im->setImageBackgroundColor('white');
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

        if (!$flySpec->isOriginalSize($im)) {
            $im->resizeImage($flySpec->width, $flySpec->height, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $im->setImageFormat('jpg');

        $targetFileName = tempnam('/tmp', 'flyfiles');
        $im->writeImages($targetFileName, true);

        return $targetFileName;
    }
}
