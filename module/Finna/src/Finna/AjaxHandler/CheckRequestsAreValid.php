<?php
/**
 * AJAX handler for checking that requests are valid
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for checking that requests are valid.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CheckRequestsAreValid extends \VuFind\AjaxHandler\AbstractIlsAndUserAction
{
    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites(); // avoid session write timing bug

        // check if user is logged in
        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $id = $params->fromPost('id', $params->fromQuery('id'));
        $data = $params->fromPost('data', $params->fromQuery('data'));
        $requestType = $params->fromPost(
            'requestType', $params->fromQuery('requestType')
        );
        if (!empty($id) && !empty($data)) {
            try {
                $patron = $this->ilsAuthenticator->storedCatalogLogin();
                if ($patron) {
                    $results = [];
                    foreach ($data as $item) {
                        switch ($requestType) {
                        case 'ILLRequest':
                            $result = $this->ils->checkILLRequestIsValid(
                                $id, $item, $patron
                            );

                            if (is_array($result)) {
                                $msg = $result['status'];
                                $result = $result['valid'];
                            } else {
                                $msg = $result
                                    ? 'ill_request_place_text'
                                    : 'ill_request_error_blocked';
                            }
                            break;
                        case 'StorageRetrievalRequest':
                            $result = $this->ils
                                ->checkStorageRetrievalRequestIsValid(
                                    $id, $item, $patron
                                );

                            if (is_array($result)) {
                                $msg = $result['status'];
                                $result = $result['valid'];
                            } else {
                                $msg = $result
                                    ? 'storage_retrieval_request_place_text'
                                    : 'storage_retrieval_request_error_blocked';
                            }
                            break;
                        default:
                            $result = $this->ils->checkRequestIsValid(
                                $id, $item, $patron
                            );

                            if (is_array($result)) {
                                $msg = $result['status'];
                                $result = $result['valid'];
                            } else {
                                $msg = $result
                                    ? 'request_place_text'
                                    : 'hold_error_blocked';
                            }
                            break;
                        }
                        $results[] = [
                            'status' => $result,
                            'msg' => $this->translate($msg)
                        ];
                    }
                    return $this->formatResponse($results);
                }
            } catch (\Exception $e) {
                // Do nothing -- just fail through to the error message below.
            }
        }

        return $this->formatResponse(
            $this->translate('An error has occurred'), self::STATUS_HTTP_ERROR
        );
    }
}
