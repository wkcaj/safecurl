<?php
/*
 * redirects.php
 *
 * Using SafeCurl and following redirects with a limit
 */
require '../vendor/autoload.php';

use fin1te\SafeCurl\SafeCurl;
use fin1te\SafeCurl\Options;

try {
    $curlHandle = curl_init();

    $options = new Options();
    //Follow redirects, but limit to 10
    $options->enableFollowLocation()->setFollowLocationLimit(10);

    $result = SafeCurl::execute('http://fin1te.net', $curlHandle);
} catch (Exception $e) {
    //Handle exception
}
