<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon\Ec2;

use DOMXPath;
use ZendService\Amazon;
use Zend\Crypt\Hmac;
use Zend\Http\Client as HttpClient;

/**
 * Provides the basic functionality to send a request to the Amazon Ec2 Query API
 *
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage Ec2
 */
abstract class AbstractEc2 extends Amazon\AbstractAmazon
{
    /**
     * The HTTP query server
     */
    protected $_ec2Endpoint = 'ec2.amazonaws.com';

    /**
     * The API version to use
     */
    protected $_ec2ApiVersion = '2009-04-04';

    /**
     * Signature Version
     */
    protected $_ec2SignatureVersion = '2';

    /**
     * Signature Encoding Method
     */
    protected $_ec2SignatureMethod = 'HmacSHA256';

    /**
     * Period after which HTTP request will timeout in seconds
     */
    protected $_httpTimeout = 10;

    /**
     * @var string Amazon Region
     */
    protected static $_defaultRegion = 'us-east-1';

    /**
     * @var string Amazon Region
     */
    protected $_region;

    /**
     * An array that contains all the valid Amazon Ec2 Regions.
     *
     * @var array
     */
    protected static $_validEc2Regions = array(
        'us-east-1', 'us-west-2', 'us-west-1', 'eu-west-1',
        'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1',
        'sa-east-1');

    /**
     * Constructor
     *
     * @param  null                               $accessKey  Override the default Access Key
     * @param  null                               $secretKey  Override the default Secret Key
     * @param  null                               $region     Sets the AWS Region
     * @param  HttpClient                         $httpClient Override the default HTTP Client
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($accessKey = null, $secretKey = null, $region = null, HttpClient $httpClient = null)
    {
        parent::__construct($accessKey, $secretKey, $httpClient);
        $this->setRegion($region ?: self::$_defaultRegion);
    }

    /**
     * Set which region you are working in.  It will append the
     * end point automatically
     *
     * @param  string                             $region
     * @throws Exception\InvalidArgumentException
     */
    public function setRegion($region)
    {
        if (in_array(strtolower($region), self::$_validEc2Regions, true)) {
            $this->_region = $region;
        } else {
            throw new Exception\InvalidArgumentException('Invalid Amazon Ec2 Region');
        }
    }

    /**
     * Method to fetch the AWS Region
     *
     * @return string
     */
    protected function _getRegion()
    {
        return (!empty($this->_region)) ? $this->_region . '.' : '';
    }

    /**
     * Sends a HTTP request to the queue service using Zend_Http_Client
     *
     * @param  array                      $params List of parameters to send with the request
     * @return Response
     * @throws Exception\RuntimeException
     */
    protected function sendRequest(array $params = array())
    {
        $url = 'https://' . $this->_getRegion() . $this->_ec2Endpoint . '/';

        $params = $this->addRequiredParameters($params);

        try {
            /* @var $request HttpClient */
            $request = $this->getHttpClient();
            $request->resetParameters();

            $request->setOptions(array(
                'timeout' => $this->_httpTimeout
            ));

            $request->setUri($url);
            $request->setMethod('POST');
            $request->setParameterPost($params);

            $httpResponse = $request->send();
        } catch (\Zend\Http\Client\Exception\ExceptionInterface $zhce) {
            $message = 'Error in request to AWS service: ' . $zhce->getMessage();
            throw new Exception\RuntimeException($message, $zhce->getCode(), $zhce);
        }
        $response = new Response($httpResponse);
        $this->checkForErrors($response);

        return $response;
    }

    /**
     * Adds required authentication and version parameters to an array of
     * parameters
     *
     * The required parameters are:
     * - AWSAccessKey
     * - SignatureVersion
     * - Timestamp
     * - Version and
     * - Signature
     *
     * If a required parameter is already set in the <tt>$parameters</tt> array,
     * it is overwritten.
     *
     * @param array $parameters the array to which to add the required
     *                          parameters.
     *
     * @return array
     */
    protected function addRequiredParameters(array $parameters)
    {
        $parameters['AWSAccessKeyId']   = $this->_getAccessKey();
        $parameters['SignatureVersion'] = $this->_ec2SignatureVersion;
        $parameters['Timestamp']        = gmdate('Y-m-d\TH:i:s\Z');
        $parameters['Version']          = $this->_ec2ApiVersion;
        $parameters['SignatureMethod']  = $this->_ec2SignatureMethod;
        $parameters['Signature']        = $this->signParameters($parameters);

        return $parameters;
    }

    /**
     * Computes the RFC 2104-compliant HMAC signature for request parameters
     *
     * This implements the Amazon Web Services signature, as per the following
     * specification:
     *
     * 1. Sort all request parameters (including <tt>SignatureVersion</tt> and
     *    excluding <tt>Signature</tt>, the value of which is being created),
     *    ignoring case.
     *
     * 2. Iterate over the sorted list and append the parameter name (in its
     *    original case) and then its value. Do not URL-encode the parameter
     *    values before constructing this string. Do not use any separator
     *    characters when appending strings.
     *
     * @param array $parameters the parameters for which to get the signature.
     *
     * @return string the signed data.
     */
    protected function signParameters(array $parameters)
    {
        $data = "POST\n";
        $data .= $this->_getRegion() . $this->_ec2Endpoint . "\n";
        $data .= "/\n";

        uksort($parameters, 'strcmp');
        unset($parameters['Signature']);

        $arrData = array();
        foreach ($parameters as $key => $value) {
            $arrData[] = $key . '=' . str_replace("%7E", "~", rawurlencode($value));
        }

        $data .= implode('&', $arrData);

        $hmac = Hmac::compute($this->_getSecretKey(), 'SHA256', $data, Hmac::OUTPUT_BINARY);

        return base64_encode($hmac);
    }

    /**
     * Checks for errors responses from Amazon
     *
     * @param  Response                   $response the response object to check.
     * @throws Exception\RuntimeException if one or more errors are
     *         returned from Amazon.
     */
    private function checkForErrors(Response $response)
    {
        $xpath = new DOMXPath($response->getDocument());
        $list  = $xpath->query('//Error');
        if ($list->length > 0) {
            $node    = $list->item(0);
            $code    = $xpath->evaluate('string(Code/text())', $node);
            $message = $xpath->evaluate('string(Message/text())', $node);
            //throw new Exception\RuntimeException($message, 0, $code);
            throw new Exception\RuntimeException($code.' '.$message);
        }
    }
}
