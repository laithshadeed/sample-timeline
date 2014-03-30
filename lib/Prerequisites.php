<?php

if (!function_exists('json_decode')) {
    throw new Exception('JSON extension is not enabled');
}


if (!function_exists('curl_init')) {
    throw new Exception('Curl extension is not enabled');
}
