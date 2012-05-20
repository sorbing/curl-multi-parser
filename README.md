CurlMulti
=========

A simple PHP class for making parallel requests with curl using callbacks and retries.

The code consists of a CurlMultiRequest class that acts as a wrapper for the cURL options. The Requests handles the action when it is finished by the manager. It also decides when to retry.

The CurlMultiManager is capeable of starting multiple requests and limit the number of parallel requests. It calls the CurlMultiRequest::process() which calls the callback.

# Example Code

Minimal example code using 'var_dump' as callback.

    <?php
    require_once __DIR__.'/CurlMulti.php';

    // Config
    $max_parallel_requests = 2;
    $tries_max = 3;
    $urls = array(
      'https://github.com/',
      'http://soundcloud.com/',
      'http://juliusbeckmann.de/',
    );

    $manager = new CurlMultiManager($max_parallel_requests);
    foreach($urls as $url) {
      $request = $manager->newRequest($url);
      $request->setCallback('var_dump');
      $request->setTriesMax($tries_max);
      $manager->startRequest($request);
    }
    $manager->finishAllRequests();
    ?>

