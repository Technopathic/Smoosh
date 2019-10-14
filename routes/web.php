<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$route->post('/uploadImage', 'ImageController@uploadImage');
$router->get('/image', 'ImageController@handleImage');
//$router->get('/videoThumbnail', 'VideoController@videoThumbnail');
//$router->get('/videoPreview', 'VideoController@videoPreview');
//$router->get('/audioPreview', 'AudioController@audioPreview');
