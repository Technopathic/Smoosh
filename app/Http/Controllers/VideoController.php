<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\WebM;
use Google\Cloud\Storage\StorageClient;

class VideoController extends Controller
{

  public function videoThumbnail(Request $request)
  {
    $video = $request->query('url');
    $width = $request->query('w');
    $height = $request->query('h');
    $key = $video.'_'.$width.'_'.$height.'_thumbnail';

    if(empty($video)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    if (app('redis')->exists($key)) {
      return response()->json(['mediaThumbnail' => app('redis')->get($key)]);
    }

    if (filter_var($video, FILTER_VALIDATE_URL) === FALSE) {
      return response()->json(['error' => 'URL invalid.'], 400);
    }

    if(empty($width) || empty($height)) {
      return reponse()->json(['error' => 'Missing dimension queries (h and w)']);
    }

    if($width > 720 || $height > 720) {
      return response()->json(['error' => 'Dimensions invalid.'], 400);
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
    $file->filters()->resize(Dimension($width, $height));
    $imageName = str_random(32);
    $length = $ffprobe->format($video)->get('duration');
    $length = round($length)/2;
    $file->frame(TimeCode::fromSeconds($length))->save(base_path().'/storage/temp/'.$imageName.'.png');

    $config = [
      'keyFilePath' => '/var/www/cdn.devs.tv/storage/keyFile.json',
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

  public function videoPreview(Request $request)
  {

    $video = $request->query('url');
    $width = $request->query('w');
    $height = $request->query('h');
    $format = $request->query('format');
    $key = $video.'_'.$width.'_'.$height.'_'.$format.'_preview';

    if (app('redis')->exists($key)) {
      return response()->json(['mediaPreview' => app('redis')->get($key)]);
    }

    if(empty($video)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    if (filter_var($video, FILTER_VALIDATE_URL) === FALSE) {
      return response()->json(['error' => 'URL invalid.'], 400);
    }

    if(empty($width) || empty($height)) {
      return reponse()->json(['error' => 'Missing dimension queries (h and w)']);
    }

    if($width > 720 || $height > 720) {
      return response()->json(['error' => 'Dimensions invalid.'], 400);
    }

    if(empty($format)) {
      return response()->json(['error' => 'Format missing.'], 400);
    }

    if($format != 'gif' && $format != 'webm') {
      return response()->json(['error' => 'Format invalid'], 400);
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
    $file->filters()->resize(Dimension($width, $height));
    $imageName = str_random(32);
    $length = $ffprobe->format($video)->get('duration');
    $length = round($length)/2;

    if($format == 'gif') {
      $thumbnail = $file->gif(TimeCode::fromSeconds($length), new Dimension(640, 480), 3)->save(base_path().'/storage/temp/'.$imageName.'.'.$format);
    }
    else if($format == 'webm') {
      $webm = new WebM();
      $webm->setKiloBitrate(1000)->setAudioChannels(0)->setAudioKiloBitrate(0);
      $file->filters()->clip(TimeCode::fromSeconds($length - 1), TimeCode::fromSeconds(3));
      $file->save($webm, base_path().'/storage/temp/'.$imageName.'.'.$format);
    }

    $config = [
      'keyFilePath' => '/var/www/cdn.devs.tv/storage/keyFile.json',
      'projectId' => 'devstv-223819',
    ];
    $storage = new StorageClient($config);
    $bucket = $storage->bucket('devstv-cdn');
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'.'.$format, 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'.'.$format ]);
    $storageUrl = 'https://storage.googleapis.com/devstv-cdn/cache/'.$imageName.'.'.$format;

    //Cache::put($key, $storageUrl, 262800);
    app('redis')->set($key, $storageUrl);
    app('redis')->expire($key, 262800);
    unlink(base_path().'/storage/temp/'.$imageName.'.'.$format);

    return response()->json(['mediaPreview' => $storageUrl]);
  }

}
