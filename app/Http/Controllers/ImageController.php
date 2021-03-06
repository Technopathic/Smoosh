<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Image;
use Google\Cloud\Storage\StorageClient;
//use Cache;

class ImageController extends Controller
{

  public function uploadImage(Request $request)
  {
    $media = $request->json('media');
    $uploadedImages = [];

    foreach($media as $mKey => $m) {

      $image = Image::make($m['data']);
      $imageName = str_random(32);

      if($image->filesize() > 8388608)
      {
        return response()->json(['error' => 'This image is too large.'], 400);
      }

      if($image->mime() != "image/png" && $image->mime() != "image/jpeg" && $image->mime() != "image/gif" && $image->mime() != "image/webp")
      {
        return response()->json(['error' => 'Not a valid PNG/JPG/GIF/WEBP image.']);
      }

      if($m['rotation'] != 0) {
        $image->rotate($m['rotation']);
        $image->flip('v');
      }

      $image->save(base_path().'/storage/temp/'.$imageName.'.webp');

      $config = [
        'keyFilePath' => env('STORAGE_KEYFILE', ''),
        'projectId' => env('STORAGE_PROJECT', ''),
      ];
      $storage = new StorageClient($config);
      $bucket = $storage->bucket(env('STORAGE_BUCKET'));
      $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'.webp', 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'.webp' ]);
      $storageUrl = 'https://storage.googleapis.com/'.env('STORAGE_BUCKET').'/cache/'.$imageName.'.webp';
      unlink(base_path().'/storage/temp/'.$imageName.'.webp');

      $uploadedImages[] = $storageUrl;

    }

    return response()->json(['uploadedImages' => $uploadedImages], 200);
  }

  public function handleImage(Request $request)
  {

    $newImage = $request->query('url');
    $newWidth = $request->query('w');
    $newHeight = $request->query('h');
    $exif = $request->query('exif');
    $aspect = $request->query('aspect');
    $fallback = $request->query('fallback');
    $type = 'webp';
    if($fallback == 'true') { $type = 'png'; }
    $key = $newImage.'_'.$newWidth.'_'.$exif.'_'.$aspect.'_'.$type;

    if(empty($newImage)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    if (app('redis')->exists($key)) {
      return response()->json(['mediaThumbnail' => app('redis')->get($key)]);
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
      if($newWidth > 1920) {
        return response()->json(['error' => 'Dimensions invalid.'], 400);
      }
    }

    if(!empty($newHeight)) {
      if($newHeight > 1920) {
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
    
    $image->save(base_path().'/storage/temp/'.$imageName.'.'.$type);

    $config = [
      'keyFilePath' => env('STORAGE_KEYFILE', ''),
      'projectId' => env('STORAGE_PROJECT', ''),
    ];
    $storage = new StorageClient($config);
    $bucket = $storage->bucket(env('STORAGE_BUCKET'));
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'.'.$type, 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'.'.$type ]);
    $storageUrl = 'https://storage.googleapis.com/'.env('STORAGE_BUCKET').'/cache/'.$imageName.'.'.$type;

    //Cache::put($key, $storageUrl, 262800);
    app('redis')->set($key, $storageUrl);
    app('redis')->expire($key, 262800);
    unlink(base_path().'/storage/temp/'.$imageName.'.'.$type);

    return response()->json(['mediaThumbnail' => $storageUrl]);
  }
}
