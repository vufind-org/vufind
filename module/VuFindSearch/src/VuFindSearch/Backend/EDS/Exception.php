<?php
/**
 * EBSCO EdsApi Exception class
 *
 * PHP version 5
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

/**
 * EBSCO EdsApi Exception class
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class EbscoEdsApiException extends Exception
{
    /**
     * Error message details returned from the API
     *
     * @var array
     */
    protected $apiErrorDetails = [];

    /**
     * Constructor
     *
     * @param array $apiErrorMessage Error message
     */
    public function __construct($apiErrorMessage)
    {
        if (is_array($apiErrorMessage)) {
            $this->setApiError($apiErrorMessage);
            parent::__construct();
        } else {
            parent::__construct($apiErrorMessage);
        }

    }

    /**
     * Set the api error details into an array
     *
     * @param array $message Error message
     *
     * @return void
     */
    protected function setApiError($message)
    {
        //AuthErrorMessages
        if (isset($message['ErrorCode'])) {
            $this->apiErrorDetails['ErrorCode'] = $message['ErrorCode'];
            $this->apiErrorDetails['Description'] = $message['Reason'];
            $this->apiErrorDetails['DetailedDescription']
                = $message['AdditionalDetail'];
        }

        //EDSAPI error messages
        if (isset($message['ErrorNumber'])) {
            $this->apiErrorDetails['ErrorCode'] = $message['ErrorNumber'];
            $this->apiErrorDetails['Description'] = $message['ErrorDescription'];
            $this->apiErrorDetails['DetailedDescription']
                = $message['DetailedErrorDescription'];

        }
    }

    /**
     * Get the Api Error message details.
     *
     * @return array
     */
    public function getApiError()
    {
        return $this->apiErrorDetails;
    }

    /**
     * Is this a know api error
     *
     * @return bool
     */
    public function isApiError()
    {
        return isset($this->apiErrorDetails);
    }

    /**
     * Known api error code
     *
     * @return array
     */
    public function getApiErrorCode()
    {
        return isset($this->apiErrorDetails)
            ? $this->apiErrorDetails['ErrorCode'] : '';
    }

    /**
     * Known api error description
     *
     * @return string
     */
    public function getApiErrorDescription()
    {
        return isset($this->apiErrorDetails)
            ? $this->apiErrorDetails['Description'] : '';
    }

    /**
     * Known api detailed error description
     *
     * @return string
     */
    public function getApiDetailedErrorDescription()
    {
        return (isset($this->apiErrorDetails))
            ? $this->apiErrorDetails['DetailedDescription'] : '';
    }
}