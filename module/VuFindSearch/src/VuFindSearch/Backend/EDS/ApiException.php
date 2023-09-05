<?php

/**
 * EBSCO API Exception class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\EDS;

use function count;
use function is_array;

/**
 * EBSCO API Exception class
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ApiException extends \VuFindSearch\Backend\Exception\BackendException
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
            parent::__construct($this->apiErrorDetails['Description'] ?? '');
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
        if (isset($message['ErrorCode'])) {
            // AuthErrorMessages
            $this->apiErrorDetails['ErrorCode'] = $message['ErrorCode'];
            $this->apiErrorDetails['Description'] = $message['Reason'];
            $this->apiErrorDetails['DetailedDescription']
                = $message['AdditionalDetail'];
        } elseif (isset($message['ErrorNumber'])) {
            // EDSAPI error messages
            $this->apiErrorDetails['ErrorCode'] = $message['ErrorNumber'];
            $this->apiErrorDetails['Description'] = $message['ErrorDescription'];
            $this->apiErrorDetails['DetailedDescription']
                = $message['DetailedErrorDescription'];
        } elseif (
            is_array($message['errors'] ?? null)
            && count($message['errors']) > 0
        ) {
            // Array of errors
            $this->apiErrorDetails['ErrorCode'] = $message['errors'][0]['code'];
            $this->apiErrorDetails['Description'] = $message['errors'][0]['message'];
        } else {
            $this->apiErrorDetails['ErrorCode'] = null;
            $this->apiErrorDetails['Description'] = 'unrecognized error';
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
        return $this->apiErrorDetails['ErrorCode'] ?? '';
    }

    /**
     * Known api error description
     *
     * @return string
     */
    public function getApiErrorDescription()
    {
        return $this->apiErrorDetails['Description'] ?? '';
    }

    /**
     * Known api detailed error description
     *
     * @return string
     */
    public function getApiDetailedErrorDescription()
    {
        return $this->apiErrorDetails['DetailedDescription'] ?? '';
    }
}
