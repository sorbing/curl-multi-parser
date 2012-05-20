<?php
/**
 * Example Script for CurlMulti.
 *
 * @author Julius Beckmann <php@juliusbeckmann.de>
 * @license GPL
 */

require_once __DIR__.'/CurlMulti.php';

// Config
$max_parallel_requests = 2;
$tries_max = 3;

// Urls
$urls = array(
  'https://github.com/',
  'https://github.com/404-',
  'http://soundcloud.com/',
  'http://juliusbeckmann.de/',
);


// Callback for finished requests
function callback_output(CurlMultiRequest $request, CurlMultiManager $manager) {
  echo 'Request finished: ', $request->getInfo('http_code'),
       ' - ', $request->getUrl(), PHP_EOL;

  if(200 != $request->getInfo('http_code')) {
    // Retry this Request
    $request->doRetry();
  }
}


// Create a manager
$manager = new CurlMultiManager($max_parallel_requests);

// Start Requests
foreach($urls as $url) {
  $request = $manager->newRequest($url);
  $request->setCallback('callback_output');
  $request->setTriesMax($tries_max);
  $manager->startRequest($request);
}

// Wait for all requests to finish
// Would be done when $manager gets destructed like when PHP ends.
$manager->finishAllRequests();


