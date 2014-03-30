<?php
error_reporting(-1);
ini_set('display_errors', 'off');
date_default_timezone_set('UTC');

require(__DIR__ . '/lib/Prerequisites.php');
require(__DIR__ . '/lib/API.php');
require(__DIR__ . '/lib/Http.php');
require(__DIR__ . '/lib/Cache.php');
require(__DIR__ . '/lib/Template.php');
require(__DIR__ . '/lib/Timeline.php');


//TODO: move those keys to ENV variables to avoid storing them in VCS
$apiParams = array(
    'base_url' => 'https://api.twitter.com/1.1',
    'consumer_key' => 'SmAz3MXeos9MwgauEVFcbayZm',
    'consumer_secret' => '4lwqttY7yTwjdKo8ec3xTusDjCaHcuRVwbX8CK1WBroPdD5dS4'
);


$timeout = 10; //seconds
$cacheTtl = 600; //10 min
$cacheDir = __DIR__ . '/cache';
$templateDir = __DIR__  . '/templates';

//This will cause 40 parallel curl requests !
//TODO: change mutl curl to throttling 
//I make it 40, otherwise you will get fast info 'Rate limit exceeded'
$followersLimit = 40;

//Helper objects
$cache = new Cache($cacheDir, $cacheTtl); //5min cache
$template = new Template($templateDir);
$http = new Http($timeout);
$api = new API($apiParams, $cache, $http);

//Main object
//TODO: Support real time updates using twitter stream API
$timeline = new Timeline($api, $cache, $template);
$screenName = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

try {
    $statuses = $timeline->get($screenName, $followersLimit);
} catch (Exception $e) {
    $statuses = $e->getMessage();
}

print $template->render(
    'index.tpl',
    array('::screenName', '::timeline'),
    array($screenName, $statuses)
);