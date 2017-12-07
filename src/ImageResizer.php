<?php 

namespace PhpMediaCache;


class ImageResizer
{
  public function createCachedImage($imageFileName, $targetFileName, $flySpec)
  {
    try
    {
      $origi = \imagecreatefromstring(\file_get_contents($imageFileName));

      try
      {
        $exif = \exif_read_data($imageFileName);
        if ($exif)
        {
          $origi = $this->correctRotation($origi, $exif);
        }
      }
      catch (\Exception $ee)
      {
        error_log('we did get an exception regarding the exif.');
      }

    }
    catch (Exception $e)
    {
      error_log($e->getMessage());
      error_log('copuld not find image '.$imageFileName);  
      $origi=ImageCreate(150,150);
      $bgc=ImageColorAllocate($origi,255,255,255);
      $tc=ImageColorAllocate($origi,0,0,0);
      ImageFilledRectangle($origi,0,0,150,150,$bgc);
      ImageString($origi,1,5,10,"Error loading Image ".$imageFileName,$tc);
    }
        
        
    if ($flySpec->isOriginalSize())
    {
      $newWidth = \imagesx($origi);
      $newHeight = \imagesy($origi);
      $im = $origi;
      
    } 
    else
    {
      switch ($flySpec->getMode())
      {
        case FlyImageSpecification::TOUCH_BOX_FROM_INSIDE:
          
          $originalWidth = \imagesx($origi);
          $originalHeight = \imagesy($origi);
          
          $newWidth = $flySpec->getMaximumWidth();
          $newHeight = (int) (($flySpec->getMaximumWidth() / $originalWidth) * $originalHeight);
          if ($newHeight > $flySpec->getMaximumHeight())
          {
              $newHeight = $flySpec->getMaximumHeight();
              $newWidth = (int) (($flySpec->getMaximumHeight() / $originalHeight) * $originalWidth);
          }
    
          $im = \imagecreatetruecolor($newWidth, $newHeight);
          \imagecopyresampled($im,$origi,0,0,0,0, $newWidth, $newHeight, $originalWidth ,$originalHeight);
          
          
          break;
  
  
  
        case FlyImageSpecification::SQUARE:
          
          $originalWidth = \imagesx($origi);
          $originalHeight = \imagesy($origi);
          
          
          // first make it square
          if ($originalHeight > $originalWidth)
          {
            $targetX = 0;
            $targetY = 0;
            $targetWidth = $originalWidth;
            $targetHeight = $originalWidth;
            
            $centerY = \round($originalHeight/2);
            $sourceY = $centerY - \round($targetHeight/2);
            $sourceX = 0; 
            $sourceWidth = $originalWidth;
            $sourceHeight = $originalWidth;
          }
          else
          {
            $targetX = 0;
            $targetY = 0;
            $targetHeight = $originalHeight;
            $targetWidth = $originalHeight;
            
            $centerX = \round($originalWidth/2);
            $sourceX = $centerX - \round($targetWidth/2);
            $sourceY = 0; 
            $sourceWidth = $originalHeight;
            $sourceHeight = $originalHeight;
            
          }
          
    
          $im = \imagecreatetruecolor($targetWidth, $targetHeight);
          \imagecopyresampled($im,$origi, $targetX, $targetY, $sourceX, $sourceY, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
                  
          
          
          // both should be equal
          $newWidth = $flySpec->getMaximumWidth();
          $newHeight = $flySpec->getMaximumHeight();
          
          
          
          break;
          
          
          
          
        case FlyImageSpecification::TOUCH_FROM_INSIDE_TO_4_3:
          $newAspectRatio = 8/3;
          
          $originalWidth = imagesx($origi);
          $originalHeight = imagesy($origi);
          $originalAspectRatio = $originalWidth/$originalHeight;
          
          if ($originalAspectRatio > $newAspectRatio)
          {
            // this means we have to cut a little from the left and the right.
            // the height will stay the same
            $targetHeight = $originalHeight;
            
            $faktor = $newAspectRatio/$originalAspectRatio;
            $targetWidth = round($originalWidth*$faktor);
            $cutX = $originalWidth - $targetWidth;
            $sourceX = round($cutX/2);
            $sourceY = 0;
            $targetX = 0;
            $targetY = 0;
          }
          else 
          {
            // this means we have to cut a little from the top and bottom.
            // the height will stay the same
            $targetWidth = $originalWidth;
            
            $faktor = $originalAspectRatio/$newAspectRatio;
            $targetHeight = round($originalHeight*$faktor);
            $cutY = $originalHeight - $targetHeight;
            $sourceX = 0;
            $sourceY = round($cutY/2);
            $targetX = 0;
            $targetY = 0;
          }
  
          $sourceHeight = $targetHeight;
          $sourceWidth = $targetWidth;
           
          $im = \imagecreatetruecolor($targetWidth, $targetHeight);
          \imagecopyresampled($im,$origi, $targetX, $targetY, $sourceX, $sourceY, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
  
  
          $newWidth = $flySpec->getMaximumWidth();
          $newHeight = $flySpec->getMaximumHeight();
  
          break;
          
  
        case FlyImageSpecification::TOUCH_BOX_FROM_OUTSIDE:
  
          $maxWidth = $flySpec->getMaximumWidth();
          $maxHeight = $flySpec->getMaximumHeight();
  
          $newAspectRatio = $maxWidth/$maxHeight;
          
          $originalWidth = \imagesx($origi);
          $originalHeight = \imagesy($origi);
          $originalAspectRatio = $originalWidth/$originalHeight;
          
          if ($originalAspectRatio > $newAspectRatio)
          {
            // this means we have to cut a little from the left and the right.
            // the height will stay the same
            $targetHeight = $originalHeight;
            
            $faktor = $newAspectRatio/$originalAspectRatio;
            $targetWidth = \round($originalWidth*$faktor);
            $cutX = $originalWidth - $targetWidth;
            $sourceX = \round($cutX/2);
            $sourceY = 0;
            $targetX = 0;
            $targetY = 0;
          }
          else 
          {
            // this means we have to cut a little from the top and bottom.
            // the height will stay the same
            $targetWidth = $originalWidth;
            
            $faktor = $originalAspectRatio/$newAspectRatio;
            $targetHeight = \round($originalHeight*$faktor);
            $cutY = $originalHeight - $targetHeight;
            $sourceX = 0;
            $sourceY = \round($cutY/2);
            $targetX = 0;
            $targetY = 0;
          }
  
          $sourceHeight = $targetHeight;
          $sourceWidth = $targetWidth;
           
          $im = \imagecreatetruecolor($maxWidth, $maxHeight);
          \imagecopyresampled($im,$origi, $targetX, $targetY, $sourceX, $sourceY, $maxWidth, $maxHeight, $sourceWidth, $sourceHeight);
  
        
  
          $newWidth = $flySpec->getMaximumWidth();
          $newHeight = $flySpec->getMaximumHeight();
  
          break;
          
  
          
        default:
          throw new \Exception("no fly image mode chosen?".$flySpec->getMode()  );
          
          
          
          
      }
      
    }   
            
      
    

    $targetFileName = tempnam('/tmp','flyfiles');  
    if (!imagepng($im, $targetFileName ))
    {
      throw new ErrorException("we could not save the image fly file.");
    }
    
    
    return $targetFileName;
  }
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  protected function correctRotation($im, $exif)
  {
    if (!empty($exif['Orientation'])) 
    {
      switch ($exif['Orientation']) 
      {
        case 3:
            $angle = 180 ;
            break;
    
        case 6:
            $angle = -90;
            break;
    
        case 8:
            $angle = 90; 
            break;
        default:
            $angle = 0;
            break;
      }   
      
      if ($angle !== 0)
      {
        $im = imagerotate($im, $angle, 0);
      }
    }    
    
    return $im;
  }
  
  
}




