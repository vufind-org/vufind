<?php

/**
 * "Check Request is Valid" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

use function is_array;

/**
 * "Check Request is Valid" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CheckRequestIsValid extends AbstractIlsAndUserAction
{
    /**
     * Status messages
     *
     * @var array
     */
    protected $statuses = [
        'ILLRequest' => [
            'success' =>  'ill_request_place_text',
            'failure' => 'ill_request_error_blocked',
        ],
        'StorageRetrievalRequest' => [
            'success' => 'storage_retrieval_request_place_text',
            'failure' => 'storage_retrieval_request_error_blocked',
        ],
    ];

    /**
     * Given a request type and a boolean success status, return an appropriate
     * message.
     *
     * @param string $requestType Type of request being made
     * @param bool   $results     Result status
     *
     * @return string
     */
    protected function getStatusMessage($requestType, $results)
    {
        // If successful, return success message:
        if ($results) {
            return $this->statuses[$requestType]['success'] ?? 'request_place_text';
        }
        // If unsuccessful, return failure message:
        return $this->statuses[$requestType]['failure'] ?? 'hold_error_blocked';
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $params->fromQuery('id');
        $data = $params->fromQuery('data');
        $requestType = $params->fromQuery('requestType');
        if (empty($id) || empty($data)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        // check if user is logged in
        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        try {
            $patron = $this->ilsAuthenticator->storedCatalogLogin();
            if ($patron) {
                switch ($requestType) {
                    case 'ILLRequest':
                        $results = $this->ils
                            ->checkILLRequestIsValid($id, $data, $patron);
                        break;
                    case 'StorageRetrievalRequest':
                        $results = $this->ils->checkStorageRetrievalRequestIsValid(
                            $id,
                            $data,
                            $patron
                        );
                        break;
                    default:
                        $results = $this->ils
                            ->checkRequestIsValid($id, $data, $patron);
                        break;
                }
                if (is_array($results)) {
                    $msg = $results['status'];
                    $results = $results['valid'];
                } else {
                    $msg = $this->getStatusMessage($requestType, $results);
                }
                return $this->formatResponse(
                    ['status' => $results, 'msg' => $this->translate($msg)]
                );
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->formatResponse(
            $this->translate('An error has occurred'),
            self::STATUS_HTTP_ERROR
        );
    }
}
