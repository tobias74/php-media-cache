<?php

namespace PhpMediaCache\Strategies;

class ImageResizer
{
    public function createCachedImage($imageFileName, $flySpec)
    {
        $imagick = new \Imagick();
        $imagick->setResourceLimit(\imagick::RESOURCETYPE_AREA, 67108864 * 100);
        $imagick->setResourceLimit(\imagick::RESOURCETYPE_MEMORY, 67108864 * 100);
        $imagick->setResourceLimit(\imagick::RESOURCETYPE_MAP, 67108864 * 100);
        $imagick->setResourceLimit(\imagick::RESOURCETYPE_HEIGHT, 32000 * 1000000);
        $imagick->setResourceLimit(\imagick::RESOURCETYPE_WIDTH, 32000 * 1000000);

        $imagick->readImage($imageFileName);

        $this->autoRotateImage($imagick);

        if ($flySpec['width'] && $flySpec['height']) {
            $imagick->resizeImage($flySpec['width'], $flySpec['height'], \Imagick::FILTER_LANCZOS, 1, true);
        } else {
            $imagick->resampleImage(100, 100, \Imagick::FILTER_LANCZOS, 1);
        }

        $targetFileName = tempnam('/tmp', 'flyfiles');
        $imagick->writeImage($targetFileName);

        return $targetFileName;
    }

    protected function autoRotateImage($image)
    {
        $orientation = $image->getImageOrientation();

        switch ($orientation) {
          case \imagick::ORIENTATION_BOTTOMRIGHT:
              $image->rotateimage('#000', 180); // rotate 180 degrees
          break;

          case \imagick::ORIENTATION_RIGHTTOP:
              $image->rotateimage('#000', 90); // rotate 90 degrees CW
          break;

          case \imagick::ORIENTATION_LEFTBOTTOM:
              $image->rotateimage('#000', -90); // rotate 90 degrees CCW
          break;
      }

        // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image!
        $image->setImageOrientation(\imagick::ORIENTATION_TOPLEFT);
    }
}
