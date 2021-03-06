<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\FrameRate;
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
    $fallback = $request->query('fallback');
    $type = 'webp';
    if($fallback == 'true') { $type = 'png'; }
    $key = $video.'_'.$newWidth.'_'.$newHeight.'_'.$aspect.'_'.$type.'_thumbnail';

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
      if($newWidth > 1920) {
        return response()->json(['error' => 'Dimensions invalid.'], 400);
      }
    }

    if(!empty($newHeight)) {
      if($newHeight > 1920) {
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
    if($ffprobe->format($video)->get('size') > 1073741824)
    {
      return response()->json(['error' => 'This video is too large.']);
    }
    
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

    if($ffprobe->format($video)->get('size') > 1073741824)
    {
      return response()->json(['error' => 'This video is too large.']);
    }

    $imageName = str_random(32);
    $length = $ffprobe->format($video)->get('duration');
    $length = round($length)/2;

    $file->filters()->clip(TimeCode::fromSeconds($length - 1), TimeCode::fromSeconds(20));

    //$file->filters()->resize(new Dimension($newWidth, $newHeight), $aspect)->framerate(new FrameRate(90), 9)->synchronize();
    $file->filters()->resize(new Dimension($newWidth, $newHeight), $aspect)->synchronize();
    //$file->filters()->framerate(new FrameRate(60), 6)->synchronize();
    //$file->gif(TimeCode::fromSeconds($length - 1), new Dimension($newWidth, $newHeight), 15)->save(base_path().'/storage/temp/'.$imageName.'.gif');
    //$webm->setKiloBitrate(500)->setAudioChannels(1)->setAudioKiloBitrate(128);
    $file->save(new WebM(), base_path().'/storage/temp/'.$imageName.'.webm');
    //shell_exec('ffmpeg -i '.base_path().'/storage/temp/'.$imageName.'.webm -filter:v "setpts=0.5*PTS" '.base_path().'/storage/temp/'.$imageName.'.webm');
    //shell_exec('ffmpeg -i '.base_path().'/storage/temp/'.$imageName.'.webm -filter:v "setpts=0.5*PTS" -an -vf minterpolate=fps=60 '.base_path().'/storage/temp/'.$imageName.'.webm');

    $config = [
      'keyFilePath' => env('STORAGE_KEYFILE', ''),
      'projectId' => env('STORAGE_PROJECT', ''),
    ];
    $storage = new StorageClient($config);
    $bucket = $storage->bucket(env('STORAGE_BUCKET'));
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'.webm', 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'.webm' ]);
    $storageUrl = 'https://storage.googleapis.com/'.env('STORAGE_BUCKET').'/cache/'.$imageName.'.webm';

    //Cache::put($key, $storageUrl, 262800);
    app('redis')->set($key, $storageUrl);
    app('redis')->expire($key, 262800);
    unlink(base_path().'/storage/temp/'.$imageName.'.webm');

    return response()->json(['mediaPreview' => $storageUrl]);
  }

}
