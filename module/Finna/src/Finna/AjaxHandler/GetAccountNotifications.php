<?php

/**
 * "Get Account Notifications" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
 * Copyright (C) The National Library of Finland 2020.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * "Get Account Notifications" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetAccountNotifications extends \VuFind\AjaxHandler\AbstractIlsAndUserAction
{
    public const STATUS_HTTP_NOT_ALLOWED = 405;   // method not allowed

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, internal status code, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $patron = $this->ilsAuthenticator->storedCatalogLogin();
        if (!$patron) {
            return $this->formatResponse('', self::STATUS_HTTP_NEED_AUTH);
        }
        if (!$this->ils->checkCapability('getMyProfile', compact('patron'))) {
            return $this->formatResponse('', self::STATUS_HTTP_NOT_ALLOWED);
        }
        $profile = $this->ils->getMyProfile($patron);
        $status = [
            'notifications' => false,
        ];
        if (
            !empty($profile['expired']) || !empty($profile['expiration_soon'])
            || !empty($profile['messages'])
        ) {
            $status['notifications'] = true;
        } else {
            if (
                $this->ils->checkCapability('getAccountBlocks', compact('patron'))
                && $this->ils->getAccountBlocks($patron)
            ) {
                $status['notifications'] = true;
            }
        }
        return $this->formatResponse($status);
    }
}
