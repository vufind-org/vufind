<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace LosReCaptcha\Service;

use Zend\Http\Response as HTTPResponse;

/**
 * Zend_Service_ReCaptcha_Response
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage ReCaptcha
 */
class Response
{
    /**
     * Status
     *
     * true if the response is valid or false otherwise
     *
     * @var boolean
     */
    protected $status = null;

    /**
     * Error code
     *
     * The error code if the status is false. The different error codes can be found in the
     * recaptcha API docs.
     *
     * @var string
     */
    protected $errorCode = null;

    /**
     * Class constructor used to construct a response
     *
     * @param string $status
     * @param string $errorCode
     * @param \Zend\Http\Response $httpResponse If this is set the content will override $status and $errorCode
     */
    public function __construct($status = null, $errorCode = null, HTTPResponse $httpResponse = null)
    {
        if ($status !== null) {
            $this->setStatus($status);
        }

        if ($errorCode !== null) {
            $this->setErrorCode($errorCode);
        }

        if ($httpResponse !== null) {
            $this->setFromHttpResponse($httpResponse);
        }
    }

    /**
     * Set the status
     *
     * @param boolean $status
     * @return \ZendService\ReCaptcha\Response
     */
    public function setStatus($status)
    {
        $this->status = (bool) $status;

        return $this;
    }

    /**
     * Get the status
     *
     * @return boolean
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Alias for getStatus()
     *
     * @return boolean
     */
    public function isValid()
    {
        return $this->getStatus();
    }

    /**
     * Set the error code
     *
     * @param string $errorCode
     * @return \ZendService\ReCaptcha\Response
     */
    public function setErrorCode($errorCode)
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    /**
     * Get the error code
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Populate this instance based on a Zend_Http_Response object
     *
     * @param \Zend\Http\Response $response
     * @return \ZendService\ReCaptcha\Response
     */
    public function setFromHttpResponse(HTTPResponse $response)
    {
        $body = $response->getBody();

        $parts = json_decode($body, true);

        $status = false;
        $errorCode = '';

        if (is_array($parts) && array_key_exists('success', $parts)) {
            $status = $parts['success'];
            if (array_key_exists('error-codes', $parts)) {
                $errorCode = implode(', ',$parts['error-codes']);
            }
        }

        $this->setStatus($status);
        $this->setErrorCode($errorCode);

        return $this;
    }
}
