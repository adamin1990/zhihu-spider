<?php
/**
 * Http Request Lib
 * 
 * @author  Yang,junlong at 2016-02-06 12:07:09 build.
 * @version $Id$
 */

class Http {
    /**
     * The URL being requested.
     * 
     * @access private
     */
    private $request_url;
    
    
    private $method;
    
    private $request_headers;
    
    private $request_body;
    
    private $response;
    
    private $response_headers;
    
    private $response_body;
    
    private $curlopts;
    
    private $proxy;
    
    /**
     * The username to use for the request.
     */
    public $username = null;
    
    /**
     * The password to use for the request.
     */
    public $password = null;
    
	/**
     * Default useragent string to use.
     * 
     * @access private
     */
    private $useragent = 'xpage-http/1.0.0';
    
    private $debug_mode = false;
    
    /**
     * File to read from while streaming up.
     */
    public $read_file = null;
    
    /**
     * The resource to read from while streaming up.
     */
    public $read_stream = null;
    
    /**
     * The size of the stream to read from.
     */
    public $read_stream_size = null;
    
    /**
     * The length already read from the stream.
     */
    public $read_stream_read = 0;
    
    /**
     * File to write to while streaming down.
     */
    public $write_file = null;
    
    /**
     * The resource to write to while streaming down.
     */
    public $write_stream = null;
    
    /**
     * Stores the intended starting seek position.
     */
    public $seek_position = null;
    
    /**
     * The user-defined callback function to call when a stream is read from.
     */
    public $registered_streaming_read_callback = null;
    
    /**
     * The user-defined callback function to call when a stream is written to.
     */
    public $registered_streaming_write_callback = null;
    
    /**
     * GET HTTP Method
     */
    const HTTP_METHOD_GET = 'GET';
    
    /**
     * POST HTTP Method
     */
    const HTTP_METHOD_POST = 'POST';
    
    /**
     * PUT HTTP Method
     */
    const HTTP_METHOD_PUT = 'PUT';
    
    /**
     * DELETE HTTP Method
     */
    const HTTP_METHOD_DELETE = 'DELETE';
    
    /**
     * HEAD HTTP Method
     */
    const HTTP_METHOD_HEAD = 'HEAD';

	/**
	 * construct method
	 * 
	 * @access public
	 */
	public function __construct($url = null, $proxy = null, $conf = null) {
	    $_conf = array();
	    $_args_last = func_get_arg(func_num_args()-1);
	    
	    if(is_array($_args_last)) {
	        $_conf = $_args_last;
	    }
	    
	    if(is_string($url)) {
	        $_conf ['request_url'] = $url;
	    }
	    
	    if(is_string($proxy)) {
	        $_conf ['proxy'] = $proxy;
	    }
	    
	    foreach($_conf as $key => $value) {
	        $this->$key = $value;
	    }
	    
	    $this->setProxy();
		
		return $this;
	}

	public function get($url = null, $data = null, $callback = null) {
		if (is_object($url)) {
			$callback = $url;
			$url = null;
		}

		if (is_object($data)) {
			$callback = $data;
			$data = null;
		}
		  
		$this->request_body = $this->setHttpData($data);
		
	    $this->setMethod('get');
	    $this->request($url);
	    
	    if(is_object($callback)) {
			$callback($this->response_body, $this->response_headers, $this);
		}

		return $this;
	}

	public function post($url = null, $data = null, $callback = null) {
		if (is_object($url)) {
			$callback = $url;
			$url = null;
		}

		if (is_object($data)) {
			$callback = $data;
			$data = null;
		}
		  
		$this->request_body = $this->setHttpData($data);
		
	    $this->setMethod('post');
	    $this->request($url);
	    
	    if(is_object($callback)) {
			$callback($this->response_body, $this->response_headers, $this);
		}

		return $this;
	}

	public function head() {
		
	}

	public function put() {

	}

	public function delete() {

	}
	
	public function setMethod($method) {
	    $this->method = strtoupper($method);
	    return $this;
	}
	
	public function curl($url = '') {
	    $url || $url = $this->request_url;
	    
	    $curl_handle = curl_init();
	    // Set default options.
	    curl_setopt($curl_handle, CURLOPT_URL, $url);
	    curl_setopt($curl_handle, CURLOPT_FILETIME, true);
	    curl_setopt($curl_handle, CURLOPT_FRESH_CONNECT, false);
	    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, true);
	    curl_setopt($curl_handle, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
	    curl_setopt($curl_handle, CURLOPT_MAXREDIRS, 5);
	    curl_setopt($curl_handle, CURLOPT_HEADER, true);
	    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($curl_handle, CURLOPT_TIMEOUT, 5184000);
	    curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 120);
	    curl_setopt($curl_handle, CURLOPT_NOSIGNAL, true);
	    curl_setopt($curl_handle, CURLOPT_REFERER, $url);
	    curl_setopt($curl_handle, CURLOPT_USERAGENT, $this->useragent);
	    curl_setopt($curl_handle, CURLOPT_READFUNCTION, array($this, 'streaming_read_callback'));
	    
	    if ($this->debug_mode) {
	        curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
	    }
	    
	    if ($this->proxy) {
	        curl_setopt($curl_handle, CURLOPT_HTTPPROXYTUNNEL, true);
	        $host = $this->proxy ['host'];
	        $host .= ($this->proxy ['port']) ? ':' . $this->proxy ['port'] : '';
	        curl_setopt($curl_handle, CURLOPT_PROXY, $host);
	        if (isset($this->proxy ['user']) && isset($this->proxy ['pass'])) {
	            curl_setopt($curl_handle, CURLOPT_PROXYUSERPWD, $this->proxy ['user'] . ':' . $this->proxy ['pass']);
	        }
	    }
	    
	    // Set credentials for HTTP Basic/Digest Authentication.
	    if ($this->username && $this->password) {
	        curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	        curl_setopt($curl_handle, CURLOPT_USERPWD, $this->username . ':' . $this->password);
	    }
	    
	    // Handle the encoding if we can.
	    if (extension_loaded('zlib')) {
	        curl_setopt($curl_handle, CURLOPT_ENCODING, '');
	    }
	    
	    // Process custom headers
	    if (isset($this->request_headers) && count($this->request_headers)) {
	        $temp_headers = array();
	        foreach ($this->request_headers as $k => $v) {
	            $temp_headers [] = $k . ': ' . $v;
	        }
	        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $temp_headers);
	    }
	    
	    switch ($this->method) {
	        case self::HTTP_METHOD_PUT :
	            curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, 'PUT');
	            if (isset($this->read_stream)) {
	                if (!isset($this->read_stream_size) || $this->read_stream_size < 0) {
	                    throw new RequestCore_Exception('The stream size for the streaming upload cannot be determined.');
	                }
	                curl_setopt($curl_handle, CURLOPT_INFILESIZE, $this->read_stream_size);
	                curl_setopt($curl_handle, CURLOPT_UPLOAD, true);
	            } else {
	                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $this->request_body);
	            }
	            break;
	        case self::HTTP_METHOD_POST :
	            curl_setopt($curl_handle, CURLOPT_POST, true);
	            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $this->request_body);
	            break;
	        case self::HTTP_METHOD_HEAD :
	            curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, self::HTTP_HEAD);
	            curl_setopt($curl_handle, CURLOPT_NOBODY, 1);
	            break;
	        default : // Assumed GET
	            curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, self::HTTP_METHOD_GET);
	            if (isset($this->write_stream)) {
	                curl_setopt($curl_handle, CURLOPT_WRITEFUNCTION, array(
	                    $this, 'streaming_write_callback'));
	                curl_setopt($curl_handle, CURLOPT_HEADER, false);
	            } else {
	                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $this->request_body);
	            }
	            break;
	    }
	    // Merge in the CURLOPTs
	    if (isset($this->curlopts) && sizeof($this->curlopts) > 0) {
	        foreach ($this->curlopts as $k => $v) {
	            curl_setopt($curl_handle, $k, $v);
	        }
	    }
	    return $curl_handle;
	}
	
	public function request($url = null) {
	    $curl_handle = $this->curl($url);
	    $this->response = curl_exec($curl_handle);
	    if ($this->response === false) {
	        throw new Exception('cURL resource: ' . (string) $curl_handle . '; cURL error: ' . curl_error($curl_handle) . ' (' . curl_errno($curl_handle) . ')');
	    }
	    $parsed_response = $this->parseResponse($curl_handle, $this->response);
	    curl_close($curl_handle);
	    
	    print_r($parsed_response);
	}
	
	public function parseResponse($curl_handle = null, $response = null) {
	    // Accept a custom one if it's passed.
	    if ($curl_handle && $response) {
	        $this->curl_handle = $curl_handle;
	        $this->response = $response;
	    }
	    // As long as this came back as a valid resource...
	    if (is_resource($this->curl_handle)) {
	        // Determine what's what.
	        $header_size = curl_getinfo($this->curl_handle, CURLINFO_HEADER_SIZE);
	        $this->response_headers = substr($this->response, 0, $header_size);
	        $this->response_body = substr($this->response, $header_size);
	        $this->response_code = curl_getinfo($this->curl_handle, CURLINFO_HTTP_CODE);
	        $this->response_info = curl_getinfo($this->curl_handle);
	        // Parse out the headers
	        $this->response_headers = explode("\r\n\r\n", trim($this->response_headers));
	        $this->response_headers = array_pop($this->response_headers);
	        $this->response_headers = explode("\r\n", $this->response_headers);
	        array_shift($this->response_headers);
	        // Loop through and split up the headers.
	        $header_assoc = array();
	        foreach ($this->response_headers as $header) {
	            $kv = explode(': ', $header);
	            $k = explode('-', strtolower ( $kv [0] ));
	            $k = implode('_', $k);
	            $v = trim($kv [1]);
	            
	            if(isset($header_assoc [$k])) {
	            	if(is_array($header_assoc [$k])) {
	            		array_push($header_assoc [$k], $v);
	            	} else {
	            		$header_assoc [$k] = array($header_assoc [$k]);
	            	}
	            } else {
	            	$header_assoc [$k] = $v;
	            }
	        }
	        // Reset the headers to the appropriate property.
	        $this->response_headers = $header_assoc;
	        $this->response_headers ['_info'] = $this->response_info;
	        $this->response_headers ['_info'] ['method'] = $this->method;
	    }
	    // Return false
	    return false;
	}
	
	public function setProxy($proxy = null) {
	    $proxy || $proxy = $this->proxy;
	    $proxy = parse_url($proxy);
	    $proxy ['host'] = isset($proxy ['host']) ? $proxy ['host'] : null;
	    $proxy ['user'] = isset($proxy ['user']) ? $proxy ['user'] : null;
	    $proxy ['pass'] = isset($proxy ['pass']) ? $proxy ['pass'] : null;
	    $proxy ['port'] = isset($proxy ['port']) ? $proxy ['port'] : null;
	    $this->proxy = $proxy;
	    return $this;
	}
	
	/**
	 * Sets a custom useragent string for the class.
	 *
	 * @param string $ua (Required) The useragent string to use.
	 * @return $this A reference to the current instance.
	 */
	public function setUseragent($ua) {
	    $this->useragent = $ua;
	    return $this;
	}
	
	/**
	 * Adds a custom HTTP header to the cURL request.
	 *
	 * @param string $key (Required) The custom HTTP header to set.
	 * @param mixed $value (Required) The value to assign to the custom HTTP header.
	 * @return $this A reference to the current instance.
	 */
	public function addHeader($key, $value) {
	    $this->request_headers [$key] = $value;
	    return $this;
	}
	
	/**
	 * Removes an HTTP header from the cURL request.
	 *
	 * @param string $key (Required) The custom HTTP header to set.
	 * @return $this A reference to the current instance.
	 */
	public function removeHeader($key) {
	    if (isset($this->request_headers [$key])) {
	        unset($this->request_headers [$key]);
	    }
	    return $this;
	}
	
	/**
	 * 析构方法
	 * 
	 * @access public
	 */
	public function __destruct() {
	    
	}
	
	function setHttpData($query){
	    if(empty($query)) {
	        return false;
	    }
	    if(!is_array($query)) {
	        $_query = explode('&', $query);
	        $query = array();
	        foreach ($_query as $val){
	            $keyval = explode('=', $val);
	            $key = empty($keyval[0]) ? '' : $keyval[0];
	            $val = empty($keyval[1]) ? '' : $keyval[1];
	            $query[$key] = $val;
	        }
	    }
	
	    ksort($query);
	
	    if(function_exists('http_build_query')) {
	        return http_build_query($query,'','&');
	    } else {
	        $_query = array();
	        foreach ($query as $key => &$val) {
	            if (is_array($val)) $val = implode(',', $val);
	            $_query[] = $key.'='.urlencode($val);
	        }
	        return implode('&', $_query);
	    }
	}
}

class HttpException extends Exception {

}