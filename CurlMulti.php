<?php
/**
 * Handling multiple cURL Requests in Parallel and execute callbacks on
 * response or retry requests.
 *
 * @author Julius Beckmann <php@juliusbeckmann.de>
 * @license GPL
 */

/**
 * cURL Multi Request.
 * A Interface that can be used in CurlMultiManager.
 * Acts as a Wrapper for the cURL Options.
 * Defines the Interface for calbacks and retrys.
 */
class CurlMultiRequest {

  /**
   * Current number of tries for this request.
   * @var Integer
   */
  protected $tries = 0;

  /**
   * Maximum number of tries.
   * @var Integer
   */
  protected $tries_max = 1;

  /**
   * Retry flag.
   * @var Boolean
   */
  protected $do_retry = false;

  /**
   * Current cURL handle.
   * @var null | Resource
   */
  protected $curl_handle = null;

  /**
   * Current cURL Options.
   * @var Array
   */
  protected $curl_options = array(CURLOPT_RETURNTRANSFER => true);

  /**
   * Content fetched from cURL handle.
   * @var null | String
   */
  protected $content = null;

  /**
   * Callback that should be called after process().
   * @var null | String | Array
   */
  protected $callback = null;

  /**
   * Info from cURL handle.
   * @var Array
   */
  protected $info = array();

  /**
   * Error Message from cURL Handle.
   * @var null | String
   */
  protected $error = null;

  /**
   * Error Number from cURL Handle.
   * @var null | Integer
   */
  protected $errno = null;
  
  /**
   * New Request fur given url.
   * @param $url Url
   */
  public function __construct($url) {
    $this->setUrl($url);
  }

  /**
   * Returns cURL handle info.
   * @param $key Recieve only key if set
   * @return array
   */
  public function getInfo($key=null) {
    if($key) {
      if(isset($this->info[$key])) {
        return $this->info[$key];
      }
      return false;
    }
    return $this->info;
  }

  /**
   * Returns cURL handle content.
   * @return String
   */
  public function getContent() {
    return $this->content;
  }

  /**
   * Returns cURL handle error message.
   * @return String
   */
  public function getError() {
    return $this->error;
  }

  /**
   * Returns cURL handle error Number.
   * @return Integer
   */
  public function getErrno() {
    return $this->errno;
  }

  /**
   * Marks this Request so that the Manager excutes them again.
   * @return $this
   */
  public function doRetry() {
    $this->do_retry = true;
    return $this;
  }

  /**
   * Set maximum count of tries.
   * @param $number Number of Tries
   * @throws InvalidArgumentException
   * @return $this
   */
  public function setTriesMax($number) {
    if($number < 1) {
      throw new \InvalidArgumentException("Invalid tries number given.");
    }
    $this->tries_max = $number;
    return $this;
  }

  /**
   * Sets callback for handling at the end of process().
   * Callback will get this Request as first parameter and
   * CurlMultiManager as second.
   * @param $callback Callback
   * @throws InvalidArgumentException
   * @return $this
   */
  public function setCallback($callback) {
    if(!is_callable($callback)) {
      throw new \InvalidArgumentException("No callable Callback given.");
    }
    $this->callback = $callback;
    return $this;
  }

  /**
   * Shortcut for getting url.
   * @return String
   */
  public function getUrl() {
    return $this->curl_options[CURLOPT_URL];
  }
  
  /**
   * Shortcut for setting url.
   * @param $url String Url
   * @return $this
   */
  public function setUrl($url) {
    $this->setCurlOption(CURLOPT_URL, $url);
    return $this;
  }

  /**
   * Setting cURL option.
   * @param $key cURL Options Key
   * @param $value Value
   * @return $this
   */
  public function setCurlOption($key, $value=null) {
    $this->curl_options[$key] = $value;
    return $this;
  }
  
  /**
   * Returns cURL handle.
   * @param $new=false Flag if a new handle should be created
   * @return Resource of cURL Handle
   */
  public function getCurlHandle($new=false) {
    if($new) {
      @curl_close($this->curl_handle);
      $this->curl_handle = false;
    }
    if(!is_resource($this->curl_handle)) {
      $this->curl_handle = curl_init();
      curl_setopt_array($this->curl_handle, $this->curl_options);
    }
    return $this->curl_handle;
  }

  /**
   * Process completed request.
   * Sets data from cURL Handle and calls callback.
   * @param $cmm CurlMultiManager Instance of Manager
   * @return Boolean
   */
  public function process(CurlMultiManager $cmm = null) {
    // Init
    $this->tries++;
    $this->do_retry = false;
    
    // Fetch cURL data
    $this->content = curl_multi_getcontent($this->curl_handle);
    $this->info = curl_getinfo($this->curl_handle);
    $this->error = curl_error($this->curl_handle);
    $this->errno = curl_errno($this->curl_handle);

    // Callback
    if($this->callback) {
      call_user_func($this->callback, $this, $cmm);
    }
    
    return true;
  }

  /**
   * Returns if this request is confiured to be retried.
   * @return Boolean
   */
  public function shouldRetry()  {
    if($this->tries >= $this->tries_max) {
      // Reached max tries
      return false;
    }
    if($this->do_retry) {
      // Retry this request
      return true;
    }
    return false;
  }

}


/**
 * cURL Manager that can manage multi cURL Request in parallel.
 */
class CurlMultiManager {

  /**
   * Maximum number of all requests.
   * @var Integer
   */
  private $max_requests = 0;

  /**
   * Array of cURL options for all requests.
   * @var Array
   */
  private $curl_options = array(CURLOPT_RETURNTRANSFER => true);

  /**
   * Current running requests.
   * @var Array
   */
  private $requests = array();

  /**
   * Requests that failed.
   * @var Array
   */
  private $requests_retry = array();

  /**
   * cURL Multi handle.
   * @var null | Resource
   */
  private $multi_handle = null;

  /**
   * Create a new cURL Multi Manager.
   * @param $max_request Limit Number of Requests do do in Parallel.
   */
  public function __construct($max_requests=null) {
    if(!is_null($max_requests)) {
      $this->setMaxRequests($max_requests);
    }
    $this->initCurl();
  }

  /**
   * Factory method for a Request.
   * @param $url Url
   * @return CurlMultiRequest
   */
  public function newRequest($url) {
    return new CurlMultiRequest($url);
  }

  /**
   * Set cURL Option used on all started Requests.
   * @param $key cURL Option Key
   * @param $value Value
   * @return $this
   */
  public function setCurlOption($key, $value) {
    $this->curl_options[$key] = $value;
    return $this;
  }

  /**
   * Set maximum Number of requests.
   * Zero means 'UNLIMITED' (default).
   * @param $max_requests Number of maximal parallel Requests.
   * @throws InvalidArgumentException
   * @return $this
   */
  public function setMaxRequests($max_requests) {
    $max_requests = (int)$max_requests;
    if($max_requests < 0) {
      throw new \InvalidArgumentException("MaxRequests has to be >= 0");
    }
    $this->max_requests = $max_requests;
    return $this;
  }

  /**
   * Returns maximum number of parallel requests.
   * @return Integer
   */
  public function getMaxRequests() {
    return $this->max_requests;
  }

  /**
   * Initializes cURL Multi handle.
   * @throws Exception
   * @retrun $this
   */
  protected function initCurl() {
    $this->multi_handle = curl_multi_init();
    if(!$this->multi_handle) {
      throw new \Exception("Could not create Curl multi handle");
    }
    return $this;
  }

  /**
   * Start a new Requests or wait till a slot becomes free.
   * @param $request CurlMultiRequest Request to start
   * @param $new_handle Should a new cURL Handle be used.
   * @return $this
   */
  public function startRequest(CurlMultiRequest $request, $new_handle=false) {

    // Wait for free slots
    if ($this->getMaxRequests() > 0) {
      $this->waitForMaxActive($this->getMaxRequests()-1);
    }

    // Fetch request curl handle
    $ch = $request->getCurlHandle($new_handle);

    // Apply global cURL Options
    curl_setopt_array($ch, $this->curl_options);

    // Add Curl Handle
    curl_multi_add_handle($this->multi_handle, $ch);

    // Save Curl Handle
    $ch_id = (int)$ch;
    // Casting the cURL Resource to int returns the resource id.
    // Every Resource in PHP has a unique id.
    $this->requests[$ch_id] = $request;

    // Process
    $this->processRequests();

    return $this;
  }

  /**
   * Finishes all requests and start retrys.
   * @return $this
   */
  public function finishAllRequests() {

    // Wait for all requests to finish.
    $this->waitForMaxActive(0);

    // Retry failed Requests
    $requests_retry = $this->requests_retry;
    // Delete old array so there is no mixup when starting retrys
    $this->requests_retry = array();
    if($requests_retry) {
      // Perform retrys
      foreach($requests_retry as $request) {
        $this->startRequest($request, true);
      }
      // Wait for retrys to finish
      $this->finishAllRequests();
    }

    return $this;
  }

  /**
   * Process cURL multi handle and handle finished.
   * @throws Exception
   * @return $this
   */
  protected function processRequests() {

    // Call with empty timeout if there is anything to do
    // A timeout > would block.
    if (curl_multi_select($this->multi_handle, 0.0) === -1) {
      // Nothing to do
      return;
    }

    // Process what is active
    do {
      $mrc = curl_multi_exec($this->multi_handle, $active);
      // Sleep some milliseconds to avoid CPU usage.
      usleep(200);
    }
    while($mrc == CURLM_CALL_MULTI_PERFORM);

    // Process completed Requests
    do {
      // Fetch finished cURL requests
      $info = curl_multi_info_read($this->multi_handle);
      if($info) {
        
        // MAYBE: Use $info['result']
        
        // Fetch Curl Handle
        $ch = $info['handle'];

        // Check
        $ch_id = (int)$ch;
        if(!isset($this->requests[$ch_id])) {
          throw new \Exception("Unknown Curl Handle index: $ch_id");
        }

        // Process
        $request = $this->requests[$ch_id];
        $request->process($this);

        // Order Retrys
        if($request->shouldRetry()) {
          $this->requests_retry[] = $request;
        }

        // Clean up
        curl_multi_remove_handle($this->multi_handle, $ch);
        curl_close($ch);
        unset($this->requests[$ch_id]);
      }
    }
    while($info);

    return $this;
  }

  /**
   * Wait till specified amount of requests is active.
   * @param $max Maximum Number if active requests
   * @return $this
   */
  protected function waitForMaxActive($max) {
    while(count($this->requests) > $max) {
      $this->processRequests();
    }
    return $this;
  }

  /**
   * Wait for all requests to finish on destruction.
   */
  public function __destruct() {
    $this->finishAllRequests();
  }
}

