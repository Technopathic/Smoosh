<p align="center">
  <img width="573" height="114" src="http://h4z.it/Image/f659ca_Smoosh.png">
</p>

*Smoosh is tool intended for developers to extend their web applications and reduce the use of resources required to process media files.*

When you're making apps and websites, it can be a pain creating thumbnails and previews for your media files. Smoosh is a web application which allows you to use an API to submit files or URLs to be processed into thumbnails or short media previews. For example, you can GET query an image file and specify new height and width dimensions. Or you can link a video file and Smoosh will create a thumbnail, GIF, or WebM preview. If you submit an audio link, Smoosh will create a short audio preview.

Smoosh uses Redis to cache each media file for quicker and optimized redistribution after initial generation.

## Requirements
* FFMPEG
* Redis
* PHP 7.0
* Composer
* Google Cloud Storage (Optional)

## Getting Started
Begin by installing the Smoosh Web Application:
```
git clone https://github.com/Technopathic/Smoosh.git
cd Smoosh
composer install
```

Rename the .env.example file to .env. Fill in your Redis information, FFMpeg paths, and Google Cloud Storage credentials. 

## Usage
```
image =>
  image => url (required)
  width => integer
  height => integer
  aspect = string (x or y)
  exif => boolean
  greyscale => boolean
```

```
videoPreview =>
  video => url (required)
  gif => boolean
  webm => boolean

videoThumbnail =>
  video => url (required)

videoAudio =>
  video => url (required)
```

```
audioPreview =>
  audio => url (required)
```

With Images, you can specify your width and height in pixel values and Smoosh will resize accordingly. If you do not specify a width and height, the original dimensions will be used. Aspect allows you to constrain the image and maintain aspect ratio depending on the x or y axis. Setting the exif option to True will correct the exif orientation from mobile cameras. Setting the greyscale option to True will apply a greyscale filter to the image.

## License
MIT