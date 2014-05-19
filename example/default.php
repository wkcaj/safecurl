<?php
/*
 * default.php
 *
 * Using SafeCurl with it's default options
 */
require '../vendor/autoload.php';

use fin1te\SafeCurl\SafeCurl;

try {
    $curlHandle = curl_init();
    $result = SafeCurl::execute('https://fin1te.net', $curlHandle);
} catch (Exception $e) {
    //Handle exception
}
