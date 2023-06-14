<?php

/**
 * "Get Library Pickup Locations" AJAX handler
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

/**
 * "Get Library Pickup Locations" AJAX handler
 *
 * Get pick up locations for a library
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetLibraryPickupLocations extends AbstractIlsAndUserAction
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
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $params->fromQuery('id');
        $pickupLib = $params->fromQuery('pickupLib');
        if (null === $id || null === $pickupLib) {
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
                $results = $this->ils
                    ->getILLPickupLocations($id, $pickupLib, $patron);
                foreach ($results as &$result) {
                    if (isset($result['name'])) {
                        $result['name'] = $this->translateWithPrefix(
                            'location_',
                            $result['name']
                        );
                    }
                }
                return $this->formatResponse(['locations' => $results]);
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
