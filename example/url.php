<?php
/*
 * url.php
 *
 * Using SafeCurl\Url to only valid a URL
 */
require '../vendor/autoload.php';

use fin1te\SafeCurl\Options;
use fin1te\SafeCurl\Url;

try {
    $safeUrl = Url::validateUrl('http://google.com', new Options());
} catch (Exception $e) {
    //Handle exception
}
