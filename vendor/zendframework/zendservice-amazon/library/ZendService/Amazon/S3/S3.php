<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon\S3;

use SimpleXMLElement;
use Zend\Crypt\Hmac;
use Zend\Http\Header;
use Zend\Http\Response\Stream as StreamResponse;
use ZendService\Amazon;
use Zend\Uri;

/**
 * Amazon S3 PHP connection class
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon_S3
 * @see        http://docs.amazonwebservices.com/AmazonS3/2006-03-01/
 */
class S3 extends \ZendService\Amazon\AbstractAmazon
{
    /**
     * Store for stream wrapper clients
     *
     * @var array
     */
    protected static $_wrapperClients = array();

    /**
     * Endpoint for the service
     *
     * @var Uri\Uri
     */
    protected $_endpoint;

    private static $lastThrottle = null;

    // Amazon terms of use compliance: no more than one query per second
    // Make it slightly more than one second to account for possible one second timing precision on the server
    const S3_REQUEST_THROTTLE_TIME = 1.001;

    const S3_ENDPOINT = 's3.amazonaws.com';

    const S3_ACL_PRIVATE = 'private';
    const S3_ACL_PUBLIC_READ = 'public-read';
    const S3_ACL_PUBLIC_WRITE = 'public-read-write';
    const S3_ACL_AUTH_READ = 'authenticated-read';

    const S3_REQUESTPAY_HEADER = 'x-amz-request-payer';
    const S3_ACL_HEADER = 'x-amz-acl';
    const S3_CONTENT_TYPE_HEADER = 'Content-Type';

    /**
     * Set S3 endpoint to use
     *
     * @param  string|Uri\Uri                     $endpoint
     * @return S3
     * @throws Exception\InvalidArgumentException
     */
    public function setEndpoint($endpoint)
    {
        if (!($endpoint instanceof Uri\Uri)) {
            $endpoint = Uri\UriFactory::factory($endpoint);
        }
        if (!$endpoint->isValid()) {
            throw new Exception\InvalidArgumentException('Invalid endpoint supplied');
        }
        $this->_endpoint = $endpoint;

        return $this;
    }

    /**
     * Get current S3 endpoint
     *
     * @return Uri\Uri
     */
    public function getEndpoint()
    {
        return $this->_endpoint;
    }

    /**
     * Constructor
     *
     * @param string $accessKey
     * @param string $secretKey
     * @param string $region
     */
    public function __construct($accessKey = null, $secretKey = null, HttpClient $httpClient = null)
    {
        parent::__construct($accessKey, $secretKey, $httpClient);

        $this->setEndpoint('http://'.self::S3_ENDPOINT);
    }

    /**
     * Verify if the bucket name is valid
     * For reference: http://docs.amazonwebservices.com/AmazonS3/latest/dev/BucketRestrictions.html
     *
     * @param  string                             $bucket
     * @return boolean
     * @throws Exception\InvalidArgumentException
     */
    public function _validBucketName($bucket)
    {

        //Labels must not be empty and should start and end with letter or number (no dashes)
        $labelEmpty = false;
        $labelDash  = false;
        $labels     = explode('.', $bucket);
        foreach ($labels as $label) {
            if (empty($label)) {
                $labelEmpty = true;
                break;
            }

            if ($label[0] == '-' || $label[strlen($label)-1] == '-') {
                $labelDash = true;
                break;
            }
        }

        //Label name length
        $len = strlen($bucket);
        if ($len < 3 || $len > 63) {
            throw new Exception\InvalidArgumentException("Bucket name \"$bucket\" must be between 3 and 63 characters long");
        }

        //No Capital letters or underscores
        if (preg_match('/[^a-z0-9\.-]/', $bucket)) {
            throw new Exception\InvalidArgumentException("Bucket name \"$bucket\" contains invalid characters");
        }

        //IP Address
        if (preg_match('/(\d){1,3}\.(\d){1,3}\.(\d){1,3}\.(\d){1,3}/', $bucket)) {
            throw new Exception\InvalidArgumentException("Bucket name \"$bucket\" cannot be an IP address");
        }

        if ($labelEmpty) {
            throw new Exception\InvalidArgumentException("Bucket labels in \"$bucket\" must not be empty");
        }

        if ($labelDash) {
            throw new Exception\InvalidArgumentException("Bucket labels in \"$bucket\" must start and end with a letter or a number");
        }

        return true;
    }

    /**
     * Add a new bucket
     *
     * @param  string $bucket
     * @param  string $location
     * @return bool
     */
    public function createBucket($bucket, $location = null)
    {
        $this->_validBucketName($bucket);

        if ($location) {
            $data = '<CreateBucketConfiguration><LocationConstraint>'.$location.'</LocationConstraint></CreateBucketConfiguration>';
        } else {
            $data = null;
        }
        $response = $this->_makeRequest('PUT', $bucket, null, array(), $data);

        return ($response->getStatusCode() == 200);
    }

    /**
     * Checks if a given bucket name is available
     *
     * To determine whether a bucket name exists using REST, use HEAD, specify the name of the bucket, and set max-keys to 0.
     * http://docs.amazonwebservices.com/AmazonS3/2006-03-01/dev/UsingBucket.html
     *
     * Most common responses:
     * HTTP 200 OK: Success -- you own the bucket or have permission to access it
     * HTTP 403 Forbidden: AccessDenied -- someone else owns the bucket
     * HTTP 404 Not Found: NoSuchBucket -- bucket is not available
     * For full list, see: http://docs.amazonwebservices.com/AmazonS3/2006-03-01/API/ErrorResponses.html
     *
     * @param  string  $bucket
     * @return boolean
     */
    public function isBucketAvailable($bucket)
    {
        $this->_validBucketName($bucket);

        $response = $this->_makeRequest('HEAD', $bucket, array('max-keys'=>0));

        return ($response->getStatusCode() == 200);
    }

    /**
     * Checks if a given object exists
     *
     * @param  string  $object
     * @return boolean
     */
    public function isObjectAvailable($object)
    {
        $response = $this->_makeRequest('HEAD', $object);

        return ($response->getStatusCode() == 200);
    }

    /**
     * Remove a given bucket. All objects in the bucket must be removed prior
     * to removing the bucket.
     *
     * @param  string  $bucket
     * @return boolean
     */
    public function removeBucket($bucket)
    {
        $response = $this->_makeRequest('DELETE', $bucket);

        // Look for a 204 No Content response
        return ($response->getStatusCode() == 204);
    }

    /**
     * Get metadata information for a given object
     *
     * @param  string      $object
     * @return array|false
     */
    public function getInfo($object)
    {
        $info = array();

        $object = $this->_fixupObjectName($object);
        $response = $this->_makeRequest('HEAD', $object);

        if ($response->getStatusCode() == 200) {
            $headers = $response->getHeaders();

            //False if header not found
            $info['type']  = $headers->get('Content-type');
            $info['size']  = $headers->get('Content-length');
            $info['mtime'] = $headers->get('Last-modified');
            $info['etag']  = $headers->get('ETag');

            //Prevents from the fatal error method call on a non-object
            foreach ($info as $key => $value) {
                if ($value instanceof Header\HeaderInterface) {
                    $info[$key] = $value->getFieldValue();
                }
            }

            if ($info['mtime']) {
                $info['mtime'] = strtotime($headers->get('Last-modified')->getFieldValue());
            }
        } else {
            return false;
        }

        return $info;
    }

    /**
     * List the S3 buckets
     *
     * @return array|false
     */
    public function getBuckets()
    {
        $response = $this->_makeRequest('GET');

        if ($response->getStatusCode() != 200) {
            return false;
        }

        $xml = new SimpleXMLElement($response->getBody());

        $buckets = array();
        foreach ($xml->Buckets->Bucket as $bucket) {
            $buckets[] = (string) $bucket->Name;
        }

        return $buckets;
    }

    /**
     * Remove all objects in the bucket.
     *
     * @param  string  $bucket
     * @return boolean
     */
    public function cleanBucket($bucket)
    {
        $objects = $this->getObjectsByBucket($bucket);
        if (!$objects) {
            return false;
        }

        foreach ($objects as $object) {
            $this->removeObject("$bucket/$object");
        }

        return true;
    }

    /**
     * List the objects in a bucket.
     *
     * Provides the list of object keys that are contained in the bucket.  Valid params include the following.
     * prefix - Limits the response to keys which begin with the indicated prefix. You can use prefixes to separate a bucket into different sets of keys in a way similar to how a file system uses folders.
     * marker - Indicates where in the bucket to begin listing. The list will only include keys that occur lexicographically after marker. This is convenient for pagination: To get the next page of results use the last key of the current page as the marker.
     * max-keys - The maximum number of keys you'd like to see in the response body. The server might return fewer than this many keys, but will not return more.
     * delimiter - Causes keys that contain the same string between the prefix and the first occurrence of the delimiter to be rolled up into a single result element in the CommonPrefixes collection. These rolled-up keys are not returned elsewhere in the response.
     *
     * @param  string      $bucket
     * @param  array       $params S3 GET Bucket Parameter
     * @return array|false
     */
    public function getObjectsByBucket($bucket, $params = array())
    {
        $response = $this->_makeRequest('GET', $bucket, $params);

        if ($response->getStatusCode() != 200) {
            return false;
        }

        $xml = new SimpleXMLElement($response->getBody());

        $objects = array();
        if (isset($xml->Contents)) {
            foreach ($xml->Contents as $contents) {
                foreach ($contents->Key as $object) {
                    $objects[] = (string) $object;
                }
            }
        }
        if (count($objects) === 0) {
            return false;
        }

        return $objects;
    }

    /**
     * List the objects and common prefixes in a bucket.
     *
     * Provides the list of object keys and common prefixes that are contained in the bucket.  Valid params include the following.
     * prefix - Limits the response to keys which begin with the indicated prefix. You can use prefixes to separate a bucket into different sets of keys in a way similar to how a file system uses folders.
     * marker - Indicates where in the bucket to begin listing. The list will only include keys that occur lexicographically after marker. This is convenient for pagination: To get the next page of results use the last key of the current page as the marker.
     * max-keys - The maximum number of keys you'd like to see in the response body. The server might return fewer than this many keys, but will not return more.
     * delimiter - Causes keys that contain the same string between the prefix and the first occurrence of the delimiter to be rolled up into a single result element in the CommonPrefixes collection. These rolled-up keys are not returned elsewhere in the response.
     *
     * @param  string      $bucket
     * @param  array       $params S3 GET Bucket Parameter
     * @return array|false
     */
    public function getObjectsAndPrefixesByBucket($bucket, $params = array())
    {
        $response = $this->_makeRequest('GET', $bucket, $params);

        if ($response->getStatusCode() != 200) {
            return false;
        }

        $xml = new SimpleXMLElement($response->getBody());

        $objects = array();
        if (isset($xml->Contents)) {
            foreach ($xml->Contents as $contents) {
                foreach ($contents->Key as $object) {
                    $objects[] = (string) $object;
                }
            }
        }
        $prefixes = array();
        if (isset($xml->CommonPrefixes)) {
            foreach ($xml->CommonPrefixes as $prefix) {
                foreach ($prefix->Prefix as $object) {
                    $prefixes[] = (string) $object;
                }
            }
        }

        return array(
            'objects'  => $objects,
            'prefixes' => $prefixes
        );
    }

    /**
     * Make sure the object name is valid
     *
     * @param  string $object
     * @return string
     */
    protected function _fixupObjectName($object)
    {
        $nameparts = explode('/', $object);

        $this->_validBucketName($nameparts[0]);

        $firstpart = array_shift($nameparts);
        if (count($nameparts) == 0) {
            return $firstpart;
        }

        return $firstpart.'/'.implode('/', array_map('rawurlencode', $nameparts));
    }

    /**
     * Get an object
     *
     * @param  string       $object
     * @param  bool         $paidobject This is "requestor pays" object
     * @return string|false
     */
    public function getObject($object, $paidobject=false)
    {
        $object = $this->_fixupObjectName($object);
        if ($paidobject) {
            $response = $this->_makeRequest('GET', $object, null, array(self::S3_REQUESTPAY_HEADER => 'requester'));
        } else {
            $response = $this->_makeRequest('GET', $object);
        }

        if ($response->getStatusCode() != 200) {
            return false;
        }

        return $response->getBody();
    }

    /**
     * Get an object using streaming
     *
     * Can use either provided filename for storage or create a temp file if none provided.
     *
     * @param  string               $object     Object path
     * @param  string               $streamfile File to write the stream to
     * @param  bool                 $paidobject This is "requestor pays" object
     * @return StreamResponse|false
     */
    public function getObjectStream($object, $streamfile = null, $paidobject=false)
    {
        $object = $this->_fixupObjectName($object);
        $this->getHttpClient()->setStream($streamfile?$streamfile:true);
        if ($paidobject) {
            $response = $this->_makeRequest('GET', $object, null, array(self::S3_REQUESTPAY_HEADER => 'requester'));
        } else {
            $response = $this->_makeRequest('GET', $object);
        }
        $this->getHttpClient()->setStream(null);

        if ($response->getStatusCode() != 200 || !($response instanceof StreamResponse)) {
            return false;
        }

        return $response;
    }

    /**
     * Upload an object by a PHP string
     *
     * @param  string          $object Object name
     * @param  string|resource $data   Object data (can be string or stream)
     * @param  array           $meta   Metadata
     * @return boolean
     */
    public function putObject($object, $data, $meta=null)
    {
        $object = $this->_fixupObjectName($object);
        $headers = (is_array($meta)) ? $meta : array();

        if (!is_resource($data)) {
            $headers['Content-MD5'] = base64_encode(md5($data, true));
        }
        $headers['Expect'] = '100-continue';

        if (!isset($headers[self::S3_CONTENT_TYPE_HEADER])) {
            $headers[self::S3_CONTENT_TYPE_HEADER] = self::getMimeType($object);
        }

        $response = $this->_makeRequest('PUT', $object, null, $headers, $data);

        // Check the MD5 Etag returned by S3 against and MD5 of the buffer
        if ($response->getStatusCode() == 200) {
            $etag       = '';
            $etagHeader = $response->getHeaders()->get('Etag');
            if ($etagHeader instanceof Header\Etag) {
                $etag = $etagHeader->getFieldValue();
            }

            // It is escaped by double quotes for some reason
            $etag = str_replace('"', '', $etag);

            if (is_resource($data) || $etag == md5($data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Put file to S3 as object
     *
     * @param  string                     $path   File name
     * @param  string                     $object Object name
     * @param  array                      $meta   Metadata
     * @return boolean
     * @throws Exception\RuntimeException
     */
    public function putFile($path, $object, $meta=null)
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new Exception\RuntimeException("Cannot read file $path");
        }

        if (!is_array($meta)) {
            $meta = array();
        }

        if (!isset($meta[self::S3_CONTENT_TYPE_HEADER])) {
            $meta[self::S3_CONTENT_TYPE_HEADER] = self::getMimeType($path);
        }

        return $this->putObject($object, $data, $meta);
    }

    /**
     * Put file to S3 as object, using streaming
     *
     * @param  string                     $path   File name
     * @param  string                     $object Object name
     * @param  array                      $meta   Metadata
     * @return boolean
     * @throws Exception\RuntimeException
     */
    public function putFileStream($path, $object, $meta=null)
    {
        $data = @fopen($path, "rb");
        if ($data === false) {
            throw new Exception\RuntimeException("Cannot open file $path");
        }

        if (!is_array($meta)) {
            $meta = array();
        }

        if (!isset($meta[self::S3_CONTENT_TYPE_HEADER])) {
            $meta[self::S3_CONTENT_TYPE_HEADER] = self::getMimeType($path);
        }

        if (!isset($meta['Content-MD5'])) {
            $meta['Content-MD5'] = base64_encode(md5_file($path, true));
        }

        return $this->putObject($object, $data, $meta);
    }

    /**
     * Remove a given object
     *
     * @param  string  $object
     * @return boolean
     */
    public function removeObject($object)
    {
        $object = $this->_fixupObjectName($object);
        $response = $this->_makeRequest('DELETE', $object);

        // Look for a 204 No Content response
        return ($response->getStatusCode() == 204);
    }

    /**
     * Copy an object
     *
     * @param string $sourceObject Source object name
     * @param string $destObject   Destination object name
     * @param array  $meta         (OPTIONAL) Metadata to apply to desination object.
     *                               Set to null to copy metadata from source object.
     * @return boolean
     */
    public function copyObject($sourceObject, $destObject, $meta = null)
    {
        $sourceObject = $this->_fixupObjectName($sourceObject);
        $destObject   = $this->_fixupObjectName($destObject);

        $headers = (is_array($meta)) ? $meta : array();
        $headers['x-amz-copy-source'] = $sourceObject;
        $headers['x-amz-metadata-directive'] = $meta === null ? 'COPY' : 'REPLACE';

        $response = $this->_makeRequest('PUT', $destObject, null, $headers);

        if ($response->getStatusCode() == 200 && !stristr($response->getBody(), '<Error>')) {
            return true;
        }

        return false;
    }

    /**
     * Move an object
     *
     * Performs a copy to dest + verify + remove source
     *
     * @param string $sourceObject Source object name
     * @param string $destObject   Destination object name
     * @param array  $meta         (OPTIONAL) Metadata to apply to destination object.
     *                              Set to null to retain existing metadata.
     * @return bool
     */
    public function moveObject($sourceObject, $destObject, $meta = null)
    {
        $sourceInfo = $this->getInfo($sourceObject);

        $this->copyObject($sourceObject, $destObject, $meta);
        $destInfo = $this->getInfo($destObject);

        if ($sourceInfo['etag'] === $destInfo['etag']) {
            return $this->removeObject($sourceObject);
        } else {
            return false;
        }
    }

    /**
     * Make a request to Amazon S3
     *
     * @param  string                             $method  Request method
     * @param  string                             $path    Path to requested object
     * @param  array                              $params  Request parameters
     * @param  array                              $headers HTTP headers
     * @param  string|resource                    $data    Request data
     * @return \Zend\Http\Response
     * @throws Exception\InvalidArgumentException
     */
    public function _makeRequest($method, $path='', $params=null, $headers=array(), $data=null)
    {
        $retryCount         = 0;
        $this->lastResponse = null;

        if (!is_array($headers)) {
            $headers = array($headers);
        }

        $headers['Date'] = $this->getRequestDate();  //Helps with testing the signature

        if (is_resource($data) && $method != 'PUT') {
            throw new Exception\InvalidArgumentException("Only PUT request supports stream data");
        }

        // build the end point out
        $parts = explode('/', $path, 2);
        $endpoint = clone($this->_endpoint);
        if ($parts[0]) {
            // prepend bucket name to the hostname
            $endpoint->setHost($parts[0].'.'.$endpoint->getHost());
        }
        if (!empty($parts[1])) {
            $endpoint->setPath('/'.$parts[1]);
        } else {
            $endpoint->setPath('/');
            if ($parts[0]) {
                $path = $parts[0].'/';
            }
        }

        $client = $this->getHttpClient();
        $client->resetParameters();

        $hasContentType = false;
        foreach ($headers as $key => $value) {
            if (strtolower($key) == 'content-type') {
                $hasContentType = true;
            }
        }

        if (($method == 'PUT') && ($data !== null)) {
            if (!$hasContentType) {
                $headers['Content-Type'] = self::getMimeType($path);
            }
            $hasContentType = true;
            $client->setRawBody($data);
        }

        $client->setUri($endpoint);
        $client->setMethod($method);

        //Prevents from Http\Client adding up a content-type header, which would break signature
        if (!$hasContentType) {
            $headers['Content-Type'] = 'application/xml';
        }

        $headers = $this->addSignature($method, $path, $headers);
        $client->setHeaders($headers);

        if (is_array($params)) {
            $client->setParameterGet($params);
        }

        do {
            $retry                = false;
            $this->lastResponse   = $this->throttle(array($client, 'send'));
            $responseCode         = $this->lastResponse->getStatusCode();

            // Some 5xx errors are expected, so retry automatically
            if ($responseCode >= 500 && $responseCode < 600 && $retryCount <= 5) {
                $retry = true;
                $retryCount++;
                sleep($retryCount / 4 * $retryCount);
            } elseif ($responseCode == 307) {
                // Need to redirect, new S3 endpoint given
                // This should never happen as Zend_Http_Client will redirect automatically
            } elseif ($responseCode == 100) {
                // echo 'OK to Continue';
            }
        } while ($retry);

        return $this->lastResponse;
    }

    /**
     * Make sure calls are throttled with a minimum time interval $throttleTime
     *
     * @param callback $callback     The function to be throttled
     * @param array    $params       Parameters for the callback
     * @param int      $throttleTime Maximum seconds to throttle between calls
     */
    public function throttle($callback, $params = array(), $throttleTime = 1.0)
    {
        if (self::$lastThrottle) {
            $last = self::$lastThrottle;
        } else {
            $last = microtime(true);
            self::$lastThrottle = $last - $throttleTime; // Do not throttle for the first time
        }

        $sleepySecs = (self::$lastThrottle + $throttleTime) - $last;

        if ($sleepySecs > 0) {
            // @TODO warn with monolog?
            // printf("Throttling for %3.4f milliseconds...\n", 1e3 * $sleepySecs);
            usleep(1e6 * $sleepySecs);
        }

        self::$lastThrottle = microtime(true);

        return call_user_func_array($callback, $params);
    }

    /**
     * Add the S3 Authorization signature to the request headers
     *
     * @param  string $method
     * @param  string $path
     * @param  array  $headers
     * @return array
     */
    protected function addSignature($method, $path, $headers)
    {
        if (!is_array($headers)) {
            $headers = array($headers);
        }

        $type = $md5 = $date = '';

        // Search for the Content-type, Content-MD5 and Date headers
        foreach ($headers as $key=>$val) {
            if (strcasecmp($key, 'content-type') == 0) {
                $type = $val;
            } elseif (strcasecmp($key, 'content-md5') == 0) {
                $md5 = $val;
            } elseif (strcasecmp($key, 'date') == 0) {
                $date = $val;
            }
        }

        // If we have an x-amz-date header, use that instead of the normal Date
        if (isset($headers['x-amz-date']) && isset($date)) {
            $date = '';
        }

        $sig_str = "$method\n$md5\n$type\n$date\n";
        // For x-amz- headers, combine like keys, lowercase them, sort them
        // alphabetically and remove excess spaces around values
        $amz_headers = array();
        foreach ($headers as $key=>$val) {
            $key = strtolower($key);
            if (substr($key, 0, 6) == 'x-amz-') {
                if (is_array($val)) {
                    $amz_headers[$key] = $val;
                } else {
                    $amz_headers[$key][] = preg_replace('/\s+/', ' ', $val);
                }
            }
        }
        if (!empty($amz_headers)) {
            ksort($amz_headers);
            foreach ($amz_headers as $key=>$val) {
                $sig_str .= $key.':'.implode(',', $val)."\n";
            }
        }

        //US General bucket names must always be lowercased for the signature
        $urlPath = parse_url($path, PHP_URL_PATH);
        $urlPathParts = explode('/', $urlPath);
        if (!empty($urlPathParts[0])) {
            $urlPathParts[0] = strtolower($urlPathParts[0]);
        }

        $sig_str .= '/'.implode('/', $urlPathParts);
        if (strpos($path, '?location') !== false) {
            $sig_str .= '?location';
        } elseif (strpos($path, '?acl') !== false) {
            $sig_str .= '?acl';
        } elseif (strpos($path, '?torrent') !== false) {
            $sig_str .= '?torrent';
        }

        $signature = base64_encode(Hmac::compute($this->_getSecretKey(), 'sha1', utf8_encode($sig_str), Hmac::OUTPUT_BINARY));
        $headers['Authorization'] = 'AWS ' . $this->_getAccessKey() . ':' . $signature;

        return $headers;
    }

    /**
     * Attempt to get the content-type of a file based on the extension
     *
     * @param  string $path
     * @return string
     */
    public static function getMimeType($path)
    {
        $ext = substr(strrchr($path, '.'), 1);

        if (!$ext) {
            // shortcut
            return 'binary/octet-stream';
        }

        switch (strtolower($ext)) {
            case 'xls':
                $content_type = 'application/excel';
                break;
            case 'hqx':
                $content_type = 'application/macbinhex40';
                break;
            case 'doc':
            case 'dot':
            case 'wrd':
                $content_type = 'application/msword';
                break;
            case 'pdf':
                $content_type = 'application/pdf';
                break;
            case 'pgp':
                $content_type = 'application/pgp';
                break;
            case 'ps':
            case 'eps':
            case 'ai':
                $content_type = 'application/postscript';
                break;
            case 'ppt':
                $content_type = 'application/powerpoint';
                break;
            case 'rtf':
                $content_type = 'application/rtf';
                break;
            case 'tgz':
            case 'gtar':
                $content_type = 'application/x-gtar';
                break;
            case 'gz':
                $content_type = 'application/x-gzip';
                break;
            case 'php':
            case 'php3':
            case 'php4':
                $content_type = 'application/x-httpd-php';
                break;
            case 'js':
                $content_type = 'application/x-javascript';
                break;
            case 'ppd':
            case 'psd':
                $content_type = 'application/x-photoshop';
                break;
            case 'swf':
            case 'swc':
            case 'rf':
                $content_type = 'application/x-shockwave-flash';
                break;
            case 'tar':
                $content_type = 'application/x-tar';
                break;
            case 'zip':
                $content_type = 'application/zip';
                break;
            case 'mid':
            case 'midi':
            case 'kar':
                $content_type = 'audio/midi';
                break;
            case 'mp2':
            case 'mp3':
            case 'mpga':
                $content_type = 'audio/mpeg';
                break;
            case 'ra':
                $content_type = 'audio/x-realaudio';
                break;
            case 'wav':
                $content_type = 'audio/wav';
                break;
            case 'bmp':
                $content_type = 'image/bitmap';
                break;
            case 'gif':
                $content_type = 'image/gif';
                break;
            case 'iff':
                $content_type = 'image/iff';
                break;
            case 'jb2':
                $content_type = 'image/jb2';
                break;
            case 'jpg':
            case 'jpe':
            case 'jpeg':
                $content_type = 'image/jpeg';
                break;
            case 'jpx':
                $content_type = 'image/jpx';
                break;
            case 'png':
                $content_type = 'image/png';
                break;
            case 'tif':
            case 'tiff':
                $content_type = 'image/tiff';
                break;
            case 'wbmp':
                $content_type = 'image/vnd.wap.wbmp';
                break;
            case 'xbm':
                $content_type = 'image/xbm';
                break;
            case 'css':
                $content_type = 'text/css';
                break;
            case 'txt':
                $content_type = 'text/plain';
                break;
            case 'htm':
            case 'html':
                $content_type = 'text/html';
                break;
            case 'xml':
                $content_type = 'text/xml';
                break;
            case 'xsl':
                $content_type = 'text/xsl';
                break;
            case 'mpg':
            case 'mpe':
            case 'mpeg':
                $content_type = 'video/mpeg';
                break;
            case 'qt':
            case 'mov':
                $content_type = 'video/quicktime';
                break;
            case 'avi':
                $content_type = 'video/x-ms-video';
                break;
            case 'eml':
                $content_type = 'message/rfc822';
                break;
            default:
                $content_type = 'binary/octet-stream';
                break;
        }

        return $content_type;
    }

    /**
     * Register this object as stream wrapper client
     *
     * @param  string $name
     * @return S3
     */
    public function registerAsClient($name)
    {
        self::$_wrapperClients[$name] = $this;

        return $this;
    }

    /**
     * Unregister this object as stream wrapper client
     *
     * @param  string $name
     * @return S3
     */
    public function unregisterAsClient($name)
    {
        unset(self::$_wrapperClients[$name]);

        return $this;
    }

    /**
     * Get wrapper client for stream type
     *
     * @param  string $name
     * @return S3
     */
    public static function getWrapperClient($name)
    {
        return self::$_wrapperClients[$name];
    }

    /**
     * Register this object as stream wrapper
     *
     * @param  string $name
     * @return S3
     */
    public function registerStreamWrapper($name='s3')
    {
        stream_register_wrapper($name, 'ZendService\Amazon\S3\Stream');
        $this->registerAsClient($name);
    }

    /**
     * Unregister this object as stream wrapper
     *
     * @param  string $name
     * @return S3
     */
    public function unregisterStreamWrapper($name='s3')
    {
        stream_wrapper_unregister($name);
        $this->unregisterAsClient($name);
    }
}
