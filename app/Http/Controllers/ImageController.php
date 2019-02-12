<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Image;
use finfo;
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

    if (app('redis')->exists($key)) {
      return response()->json(['mediaThumbnail' => app('redis')->get($key)]);
    }

    if(new finfo(FILEINFO_MIME, $newImage) == 'image/svg+xml') {
      app('redis')->set($key, $newImage);
      app('redis')->expire($key, 262800);
      return response()-json(['mediaThumbnail' => $newImage]);
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

    if(!empty($newWidth)) {
      if($newWidth > 720) {
        return response()->json(['error' => 'Dimensions invalid.'], 400);
      }
    }

    if(!empty($newHeight)) {
      if($newHeight > 720) {
        return response()->json(['error' => 'Dimensions invalid.'], 400);
      }
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
      'keyFilePath' => env('STORAGE_KEYFILE', '/var/www/cdn.devs.tv/storage/keyFile.json'),
      'projectId' => env('STORAGE_PROJECT', 'devstv-223819'),
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
