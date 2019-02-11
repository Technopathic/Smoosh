<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Image;
use Response;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Audio\Mp3;

class AudioController extends Controller
{

  public function audioPreview(Request $request)
  {
    $audio = $request->query('url');
    $keyPreview = $audio.'_preview';
    $keyWave = $audio.'_wave';

    if(empty($audio)) {
      return response()->json(['error' => 'URL missing.'], 400);
    }

    if (app('redis')->exists($keyPreview) && app('redis')->exists($keyWave)) {
      return response()->json(['mediaThumbnail' => app('redis')->get($keyWave), 'mediaPreview' => app('redis')->get($keyPreview)]);
    }

    if (filter_var($audio, FILTER_VALIDATE_URL) === FALSE) {
      return response()->json(['error' => 'URL invalid.'], 400);
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

    $file = $ffmpeg->open($audio);
    if($ffprobe->format($video)->get('size') > 104857600)
    {
      return response()->json(['error' => 'This audio is too large.']);
    }

    $imageName = str_random(32);
    $length = $ffprobe->format($audio)->get('duration');
    $length = round($length)/2;
    $file->filters()->clip(TimeCode::fromSeconds($length - 7.5), TimeCode::fromSeconds(15));
    $file->save(new Mp3(), base_path().'/storage/temp/'.$imageName.'_preview.mp3');
    $waveform = $file->waveform(640, 240, array('#00FF00'));
    $waveform->save(base_path().'/storage/temp/'.$imageName.'_waveform.png');

    $config = [
      'keyFilePath' => env('STORAGE_KEYFILE', '/var/www/cdn.devs.tv/storage/keyFile.json'),
      'projectId' => env('STORAGE_PROJECT', 'devstv-223819'),
    ];
    $storage = new StorageClient($config);
    $bucket = $storage->bucket('devstv-cdn');
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'_preview.mp3', 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'_preview.mp3' ]);
    $bucket->upload(fopen(base_path().'/storage/temp/'.$imageName.'_waveform.png', 'r'), [ 'predefinedAcl' => 'publicRead', 'name' => 'cache/'.$imageName.'_waveform.png' ]);
    $mediaPreview = 'https://storage.googleapis.com/devstv-cdn/cache/'.$imageName.'_preview.mp3';
    $storageUrl = 'https://storage.googleapis.com/devstv-cdn/cache/'.$imageName.'_waveform.png';
    

    app('redis')->set($keyPreview, $mediaPreview);
    app('redis')->set($keyWave, $storageUrl);
    app('redis')->expire($keyPreview, 262800);
    app('redis')->expire($keyWave, 262800);
    unlink(base_path().'/storage/temp/'.$imageName.'_preview.mp3');
    unlink(base_path().'/storage/temp/'.$imageName.'_waveform.png');

    return response()->json(['mediaThumbnail' => $storageUrl, 'mediaPreview' => $mediaPreview]);

  }
}
