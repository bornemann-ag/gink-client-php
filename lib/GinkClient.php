<?php
/**
 * client to interact with the GINK web service
 *
 * Configuration: a few things regarding the GinkClient can be configured to
 * make it work with the application.  The client can be configured either in
 * the constructor or via a "gink.ini" file in the current working directory of
 * the application.
 *
 * gink.ini variables:
 *   url = base URL for GINK v2 webservice, i.e. "http://mygink.com/rest/v2/"
 *         this is configurable for non-production webservice feature-tests.
 *         default is the production web service.
 *   tmpdir = temporary directory that cURL needs write permissions for.  this
 *         is needed because fseek() does NOT reset "php://memory" streams in
 *         all versions of PHP.
 *         default is the current working directory.
 *         NOTE: PHP E_USER_ERROR will be triggered if directory NOT writeable!
 *
 * Constructor variables (same as above, but in PHP code):
 * <code>
 * $gink = new GinkClient($url, $tmpDir);
 * </code>
 *
 * The files created in tmpdir are:
 * gink-curl-heads (HTTP heads of last request, possibly multiple for redirects)
 * gink-curl-body  (HTTP body of last request)
 */
class GinkClient {

    // base URL for gateway, token calls
    private $base = 'http://mygink.com/rest/v2/';
    private $user = null;// HTTP DIGEST USERNAME
    private $pass = null;// HTTP DIGEST PASSWORD
    private $dnTmp = null;// directory to write temporary cURL files

    /**
     * initializes the gink webservice client.  reads "gink.ini", if available
     * in working directory, to set the base URL of the service (for local
     * testing, or to access service via proxy URL)
     *
     * @param string|null base URL for the client
     */
    public function __construct($urlBase = null, $dnTmp = null) {
        // look for a gink.ini file, parse it if readable.  FAILS SILENTLY
        $fnIni = './gink.ini';
        if ( is_readable($fnIni) && ($ini = @parse_ini_file($fnIni)) ) {
            // if there is a parseable URL, use that as the base
            // NOTE: this is mostly for local testing
            if ( ! empty($ini['url']) && @parse_url($ini['url']) ) {
                $this->base = $ini['url'];
            }
            // if there is a writeable 'tmpdir' directive, set that
            if ( ! empty($ini['tmpdir']) ) {
                $this->dnTmp = rtrim($ini['tmpdir'], '/');
            }
        }
        // URL given in constructor always overrides INI file
        if ( $urlBase ) { $this->base = $urlBase; }
        // passed-in temp directory always overrides INI file
        if ( $dnTmp ) { $this->dnTmp = rtrim($dnTmp, '/'); }
        // if no dnTmp set, try to use working directory
        if ( ! $this->dnTmp ) { $this->dnTmp = realpath("."); }
        // and make sure we somehow got a writeable dnTmp!
        if ( ! is_writeable($this->dnTmp) ) {
            $tmp = $this->dnTmp;
            trigger_error("gink.ini: {$tmp} not writeable", E_USER_ERROR);
        }
    }
    
    // property getter
    public function __get($p) {
        if ( isset($this->$p) ) { return $this->$p; }
        return null;
    }

    /**
     * sets the credentials to send via HTTP Digest Authentication.
     *
     * @param string username
     * @param string password
     */
    public function setCredentials($username, $password) {
        $this->user = $username;
        $this->pass = $password;
    }

    /**
     * gets the gateway object of the service
     *
     * @return object the 'gateway' object of gink service
     */
    public function gateway() {
        $url = self::resolve('gateway?_format=json', $this->base);
        return $this->get( $url );
    }

    /**
     * gets a token for programmatic access outside of HTTP Digest
     *
     * @param string username or 40-digit key
     * @param string password (or NULL for key usage)
     * @return object the 'gateway' object w/ token auth (if no '_error')
     */
    public function token($user, $pass = null, $duration = null) {
        $url = self::resolve('token?_format=json', $this->base);
        if ( $pass === null ) {
            $creds = (object) array('key' => $user);
        }
        else {
            $creds = (object) array('username' => $user, 'password' => $pass);
        }
        if ( $duration !== null ) { $creds->duration = (int) $duration; }
        return $this->post( $url, $creds, true );
    }

    /**
     * gets a data object, after resolving all the '*_url' properties
     *
     * @param string URL of data object
     */
    public function get($url) {
        return $this->curl( $url );
    }

    /**
     * performs HTTP POST at the given URL
     *
     * @param string URL to call HTTP POST on
     * @param object data object to POST to URL
     * @param boolean whether to follow redirects from response
     * @return object gink-ws data object
     */
    public function post($url, $obj, $follow = true) {
        return $this->curl( $url, "POST", $obj, $follow);
    }

    /**
     * performs HTTP PUT request
     *
     * @param string URL to call HTTP PUT on
     * @param object data object to PUT to URL
     * @param boolean whether to follow redirects from response
     * @return object gink-ws data object
     */
    public function put($url, $obj, $follow = false) {
        return $this->curl( $url, "PUT", $obj, $follow);
    }

    /**
     * performs HTTP DELETE request
     *
     * @param string URL to call HTTP DELETE on
     * @return object gink-ws data object (or request object)
     */
    public function del($url) {
        return $this->curl( $url, "DELETE" );
    }

    /**
     * initiates a cURL handle for data retrieval/push
     *
     * @param string URL
     * @param string HTTP method
     * @param object|null data to send (as JSON) in request body
     * @param boolean whether to follow server redirects
     * @return object gink-ws data object, or object representing HTTP response
     */
    private function curl( $url, $meth = null, $obj = null, $follow = true) {
        $ch = curl_init( $url );
        // we do the following ourselves, otherwise method is wrongly preserved
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        // setup cookie file
        $root = $this->dnTmp;
        curl_setopt($ch, CURLOPT_COOKIEJAR, "{$root}/gink-curl-cookies");
        curl_setopt($ch, CURLOPT_COOKIEFILE, "{$root}/gink-curl-cookies");

        // add digest authentication
        if ( $this->user && $this->pass ) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
        }

        // set the method, if needed
        if ( $meth ) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $meth);
        }

        // put an object into the object body, if needed
        if ( $obj ) {
            // always use JSON encoding
            $json = json_encode($obj);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER,
                array(
                    "Content-Type: application/json",
                    "Content-Length: " . strlen($json)
                    )
                );
        }
        return $this->curlDo($ch, $url, $follow);
    }

    /**
     * runs the cURL handle, reading HTTP status header
     *
     * @param resource cURL handle
     * @param string URL that was set for this request (to resolve relative URLs)
     * @param boolean whether to follow server redirects
     * @return object gink-ws data object, or HTTP response
     */
    private function curlDo($ch, $urlFrom, $follow = true) {
        // setup headers to read
        $root = $this->dnTmp;
        $fpHead = fopen("{$root}/gink-curl-heads", "w+");
        curl_setopt($ch, CURLOPT_WRITEHEADER, $fpHead);

        // setup body to read
        $fpBody = fopen("{$root}/gink-curl-body", "w+");
        curl_setopt($ch, CURLOPT_FILE, $fpBody);

        // execute HTTP request, ensure OK
        $res = curl_exec( $ch );
        curl_close( $ch );
        if ( ! $res ) { return (object) array('_error' => "cURL error"); }
        fseek($fpHead, 0);
        fseek($fpBody, 0);

        // check HTTP status, skipping over 'continue' headers (from PUT)
        $heads = self::read_heads($fpHead);
        fclose($fpHead);
        $head = end($heads);
        $body = self::read_body($fpBody);
        fclose($fpBody);

        // SUCCESS
        if ( $head->status >= 200 && $head->status <= 200 ) {
            // empty content on success response
            if ( ! $body ) {
                $head->body = null;
                return $head;
            }

            // otherwise, expect JSON, resolve URLs
            if ( ! ($obj = json_decode($body)) ) {
                $head->body = $body;
                $head->_error = "Expected JSON, none returned";
                return $head;
            }
            $cb = array(__CLASS__, 'resolve');
            return self::modify($obj, '/_?url$/', $cb, $urlFrom);
        }

        // REDIRECT
        if ( $head->status >= 300 && $head->status <= 399 && $follow ) {
            if ( isset($head->headers['location']) ) {
                $location = $head->headers['location'];
                $urlNext = self::resolve($location, $urlFrom);
                return $this->get( $urlNext );
            }
            // ERROR: no redirection location (should NEVER happen)
            else {
                $head->_error = "Missing HTTP Header 'Location:'";
                return $head;
            }
        }
        // NON-FOLLOWING REDIRECTS (not an error)
        else if ( $head->status >= 300 && $head->status <= 399 ) {
            $head->body = $body;
            return $head;
        }

        // ERROR CODES
        $head->body = $body;
        $head->_error = "HTTP Error Status: {$head->status}";
        return $head;
    }

    /**
     * reads all the HTTP responses from a cURL request header stream.
     *
     * @param stream written from cURL
     * @return array header data objects
     */
    private static function read_heads($fpHead) {
        $statuses = array();
        while( ! feof($fpHead) ) {
            $statuses[] = ($res = new stdClass());
            $lnStatus = trim(fgets($fpHead, 1024));
            $status = explode(' ', $lnStatus, 3);
            $res->status = (int) $status[1];
            $res->statusLine = $lnStatus;
            $res->headers = array();
            while( $header = rtrim(fgets($fpHead)) ) {
                $tmp = explode(':', $header, 2);
                $res->headers[ strtolower($tmp[0]) ] = trim($tmp[1]);
            }
        }
        return $statuses;
    }

    /**
     * reads HTTP response body in its entirety
     *
     * @param stream written from cURL
     * @return string full response body
     */
    private static function read_body($fpBody) {
        $buff = array();
        while( ! feof($fpBody) ) { $buff[] = fread($fpBody, 1024); }
        return join('', $buff);
    }

    /**
     * resolves a relative URL to its absolute path based on where it came from.
     * NAIVE ALGORITHM: assumes not too many '..', which would dig past
     * path. this assumption is safe for our upstanding gink webservice.
     *
     * @param string|null relative URL to resolve (null returns right away)
     * @param string absolute URL (origin of relative URL)
     * @return string resolved URL
     */
    private static function resolve($urlTo, $urlFrom) {
        // NULL urls
        if ( ! $urlTo ) { return $urlTo; }
        // already absolute URLs
        if ( preg_match("/^https?:/", $urlTo) ) { return $urlTo; }

        // save query-part of URL for later
        if ( ($pos = strpos($urlTo, '?')) ) {
            $q = substr($urlTo, $pos);
            $urlTo = substr($urlTo, 0, $pos);
        }
        else { $q = ''; }// no query-part

        // always forget the query-part of from URL
        if ( ($pos = strpos($urlFrom, '?')) ) {
            $urlFrom = substr($urlFrom, 0, $pos);
        }

        // split the URL paths to compare them via simple iteration
        $from = explode('/', $urlFrom);
        $to = explode('/', $urlTo);
        array_pop($from);// path trail not used for resolution

        // chomp up all the parts, adding/removing 'from' URL
        while( $to ) {
            $toPart = array_shift($to);
            if ( $toPart === '..' ) { array_pop($from); }
            else if ( $toPart === '.' ) { /* nothing */ }
            else if ( $toPart === '' ) { /* nothing */ }
            else { array_push($from, $toPart); }
        }
        $urlTo = join('/', $from);
        return $urlTo . $q;// append query, if any
    }

    /**
     * modifies object properties matching regexp by calling a callback function
     *
     * @param object GINK webservice object
     * @param string Regular Expression to match property names
     * @param callback function to call when regexp matches
     * @param optional... additional arguments to append to callback
     * @return object modified object
     */
    private static function modify(stdClass $obj, $rxProp, $cb) {
        $args = func_get_args();
        $extra = array_slice($args, 3);
        $cbSelf = array(__CLASS__, 'modify');
        foreach($obj as $prop => $val) {
            // iterate for array values
            if ( is_array($val) ) {
                $argsSub = array_slice($args, 1);
                foreach($val as $sub) {
                    array_unshift($argsSub, $sub);
                    call_user_func_array($cbSelf, $argsSub);
                    array_shift($argsSub);
                }
            }
            // recurse for object values
            else if ( is_object($val) ) {
                $argsSub = array_slice($args, 1);
                array_unshift($argsSub, $val);
                call_user_func_array($cbSelf, $argsSub);
            }
            // otherwise, check the property names
            else if ( preg_match($rxProp, $prop) ) {
                array_unshift($extra, $val);
                $obj->$prop = call_user_func_array($cb, $extra);
                array_shift($extra);
            }
        }
        return $obj;
    }
}
