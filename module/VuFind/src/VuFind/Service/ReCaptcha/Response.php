<?php
/**
 * Response object for the VuFind NoCAPTCHA ReCaptcha.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Service
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Service\ReCaptcha;
use Zend\Http\Response as HTTPResponse;

/**
 * Response object for the VuFind NoCAPTCHA ReCaptcha.
 *
 * @category VuFind2
 * @package  Service
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
     * The error code if the status is false. The different error codes
     * can be found in the recaptcha API docs.
     *
     * @var string
     */
    protected $errorCode = null;

    /**
     * Class constructor used to construct a response
     *
     * @param string              $status       Response status string
     * @param string              $errorCode    Error description
     * @param \Zend\Http\Response $httpResponse If this is set,
     * the content will override $status and $errorCode
     */
    public function __construct($status = null, $errorCode = null,
        HTTPResponse $httpResponse = null
    ) {
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
     * @param string $status Response status string
     *
     * @return \ZendService\ReCaptcha\Response
     */
    public function setStatus($status)
    {
        $this->status = $status;

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
     * @param string $errorCode Error description
     *
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
     * @param \Zend\Http\Response $response HTTP Response object, if set
     * the content will override $status and $errorCode
     *
     * @return \ZendService\ReCaptcha\Response
     */
    public function setFromHttpResponse(HTTPResponse $response)
    {
        $body = $response->getBody();

        $parse = \Zend\Json\Json::decode($response->getBody());

        $this->setStatus($parse->success);
        if (!$parse->success) {
            $this->setErrorCode($parse->{'error-codes'});
        }

        return $this;
    }
}
