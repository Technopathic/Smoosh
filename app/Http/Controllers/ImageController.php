<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Image;
use Google\Cloud\Storage\StorageClient;
//use Cache;

class ImageController extends Controller
{

  public function handleImage(Request $request)
  {
    //Be sure to instsall ext-phpiredis

    $newImage = $request->query('url');
    $newWidth = $request->query('w');
    $newHeight = $request->query('h');
    $exif = $request->query('exif');
    $aspect = $request->query('aspect');
    $key = $newImage.'_'.$newWidth.'_'.$exif.'_'.$aspect;

    if(empty($newImage)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    /*if (Cache::has($key)) {
      return response()->json(['mediaThumbnail' => Cache::get($key)]);
    }*/
    if (app('redis')->exists($key)) {
      return response()->json(['mediaThumbnail' => app('redis')->get($key)]);
    }

    if (filter_var($newImage, FILTER_VALIDATE_URL) === FALSE) {
      return response()->json(['error' => 'URL invalid.'], 400);
    }

    $image = Image::make($newImage);
    $imageName = str_random(32);

    if($image->filesize() > 8388608)
    {
      return response()->json(['error' => 'This image is too large.']);
    }

    if($image->mime() != "image/png" && $image->mime() != "image/jpeg" && $image->mime() != "image/gif" && $image->mime() != "image/webp")
    {
      return response()->json(['error' => 'Not a valid PNG/JPG/GIF/WEBP image.']);
    }

    $width = $image->width();
    $height = $image->height();

    if(!empty($newWidth)) { $width = $newWidth; }
    if(!empty($newHeight)) { $height = $newHeight; }

    if(!empty($aspect)) {
      if($aspect == 'x') {
        $image->resize($width, null, function ($constraint) {
            $constraint->aspectRatio();
        });
      }
      else if($aspect == 'y') {
        $image->resize(null, $height, function ($constraint) {
            $constraint->aspectRatio();
        });
      }
    }
    else {
      $image->resize($width, $height);
    }

    if($request->has('exif'))
    {
      if($exif == '1') {
        $image->orientate();
      }
    }
    
    $image->save(base_path().'/storage/temp/'.$imageName.'.png');

    $config = [
      'keyFilePath' => 'C:\Users\Renix\Documents\Projects\devstv-backend\storage\devstv-223819-035b29e72f73.json',
      'projectId' => 'devstv-223819',
    ];
    $storage = new StorageClient($config);
    $bucket = $storage->bucket('devstv-cdn');
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'.png', 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'.png' ]);
    $storageUrl = 'https://storage.googleapis.com/devstv-cdn/cache/'.$imageName.'.png';

    //Cache::put($key, $storageUrl, 262800);
    app('redis')->set($key, $storageUrl);
    app('redis')->expire($key, 262800);
    unlink(base_path().'/storage/temp/'.$imageName.'.png');

    return response()->json(['mediaThumbnail' => $storageUrl]);
  }
}
