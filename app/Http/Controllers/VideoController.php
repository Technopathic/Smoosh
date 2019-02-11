<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\WebM;
use Google\Cloud\Storage\StorageClient;
use Image;


class VideoController extends Controller
{

  public function videoThumbnail(Request $request)
  {
    $video = $request->query('url');
    $newWidth = $request->query('w');
    $newHeight = $request->query('h');
    $aspect = $request->query('aspect');
    $key = $video.'_'.$newWidth.'_'.$newHeight.'_'.$aspect.'_thumbnail';

    if(empty($video)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    if (app('redis')->exists($key)) {
      return response()->json(['mediaThumbnail' => app('redis')->get($key)]);
    }

    if (filter_var($video, FILTER_VALIDATE_URL) === FALSE) {
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

    $ffmpeg = FFMpeg::create([
       'ffmpeg.binaries'  => env('FFMpeg', '/usr/bin/ffmpeg'),
       'ffprobe.binaries' => env('FFProbe', '/usr/bin/ffprobe'),
       'timeout'          => 3600,
       'ffmpeg.threads'   => 12,
   ]);
    $ffprobe = FFProbe::create([
      'ffmpeg.binaries'  => env('FFMpeg', '/usr/bin/ffmpeg'),
      'ffprobe.binaries' => env('FFProbe', '/usr/bin/ffprobe'),
      'timeout'          => 3600,
      'ffmpeg.threads'   => 12,
    ]);

    $file = $ffmpeg->open($video);
    $imageName = str_random(32);
    $length = $ffprobe->format($video)->get('duration');
    $length = round($length)/2;
    $file->frame(TimeCode::fromSeconds($length))->save(base_path().'/storage/temp/'.$imageName.'.png');

    $image = Image::make(base_path().'/storage/temp/'.$imageName.'.png');
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

  public function videoPreview(Request $request)
  {

    $video = $request->query('url');
    $newWidth = $request->query('w');
    $newHeight = $request->query('h');
    $aspect = $request->query('aspect');
    //$format = $request->query('format');
    //$key = $video.'_'.$newWidth.'_'.$newHeight.'_'.$format.'_preview';
    $key = $video.'_'.$newWidth.'_'.$newHeight.'_preview';

    if (app('redis')->exists($key)) {
      return response()->json(['mediaPreview' => app('redis')->get($key)]);
    }

    if(empty($video)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    if (filter_var($video, FILTER_VALIDATE_URL) === FALSE) {
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

    if(!empty($aspect)) {
      if($aspect != True && $aspect != False) {
        return response()->json(['error' => 'Aspect should be true or false.']);
      }
    } else {
      $aspect = False;
    }

    /*if(empty($format)) {
      return response()->json(['error' => 'Format missing.'], 400);
    }*/

    /*if($format != 'gif' && $format != 'webm') {
      return response()->json(['error' => 'Format invalid'], 400);
    }*/

    $ffmpeg = FFMpeg::create([
        'ffmpeg.binaries'  => env('FFMpeg', '/usr/bin/ffmpeg'),
        'ffprobe.binaries' => env('FFProbe', '/usr/bin/ffprobe'),
        'timeout'          => 3600,
        'ffmpeg.threads'   => 12,
    ]);
    $ffprobe = FFProbe::create([
      'ffmpeg.binaries'  => env('FFMpeg', '/usr/bin/ffmpeg'),
      'ffprobe.binaries' => env('FFProbe', '/usr/bin/ffprobe'),
      'timeout'          => 3600,
      'ffmpeg.threads'   => 12,
    ]);

    if(empty($newWidth)) { $newWidth = 640; }
    if(empty($newHeight)) { $newHeight = 360; }

    $file = $ffmpeg->open($video);
    $file->filters()->resize(new Dimension($newWidth, $newHeight), $aspect)->synchronize();;
    $imageName = str_random(32);
    $length = $ffprobe->format($video)->get('duration');
    $length = round($length)/2;

    $webm = new WebM();
    $webm->setKiloBitrate(1000)->setAudioChannels(2)->setAudioKiloBitrate(256);
    $file->filters()->clip(TimeCode::fromSeconds($length - 1), TimeCode::fromSeconds(3));
    $file->save($webm, base_path().'/storage/temp/'.$imageName.'.webm');

    $config = [
      'keyFilePath' => env('STORAGE_KEYFILE', '/var/www/cdn.devs.tv/storage/keyFile.json'),
      'projectId' => env('STORAGE_PROJECT', 'devstv-223819'),
    ];
    $storage = new StorageClient($config);
    $bucket = $storage->bucket('devstv-cdn');
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'.webm', 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'.webm' ]);
    $storageUrl = 'https://storage.googleapis.com/devstv-cdn/cache/'.$imageName.'.webm';

    //Cache::put($key, $storageUrl, 262800);
    app('redis')->set($key, $storageUrl);
    app('redis')->expire($key, 262800);
    unlink(base_path().'/storage/temp/'.$imageName.'.webm');

    return response()->json(['mediaPreview' => $storageUrl]);
  }

}
