<?php 

namespace PhpMediaCache;


class ImageResizer
{
  public function createCachedImage($imageFileName, $flySpec)
  {
    $imagick = new \Imagick(); 
    $imagick->readImage($imageFileName);


    if ($flySpec->isOriginalSize())
    {
      $imagick->resampleImage(100, 100, \Imagick::FILTER_LANCZOS, 1);
    } 
    else
    {
      $imagick->resizeImage($flySpec->width, $flySpec->height, \Imagick::FILTER_LANCZOS, 1, true);
    }   
         
    $targetFileName = tempnam('/tmp','flyfiles');  
    $imagick->writeImage($targetFileName);

    return $targetFileName;
  }
}




