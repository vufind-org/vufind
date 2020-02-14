<?php
/**
 * AJAX handler for changing request status
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018-2020.
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

use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for changing request status.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ChangeRequestStatus extends \VuFind\AjaxHandler\AbstractIlsAndUserAction
    implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        // check if user is logged in
        if (!$this->user
            || !($patron = $this->ilsAuthenticator->storedCatalogLogin())
        ) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $requestId = $params->fromQuery('requestId');
        $recordId = $params->fromQuery('id');
        $itemId = $params->fromQuery('itemId');
        $frozen = $params->fromQuery('frozen');
        if (empty($requestId)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->ils
                ->checkFunction('changeRequestStatus', compact('patron'));
            if (!$result) {
                return $this->formatResponse(
                    $this->translate('unavailable'),
                    self::STATUS_HTTP_BAD_REQUEST
                );
            }

            $details = [
                'id' => $recordId,
                'item_id' => $itemId,
                'requestId' => $requestId,
                'frozen' => $frozen
            ];
            $results = $this->ils->changeRequestStatus($patron, $details);

            return $this->formatResponse($results);
        } catch (\Exception $e) {
            $this->logError('changePickupLocation failed: ' . $e->getMessage());
            // Fall through to the error message below.
        }

        return $this->formatResponse(
            $this->translate('An error has occurred'), self::STATUS_HTTP_ERROR
        );
    }
}
