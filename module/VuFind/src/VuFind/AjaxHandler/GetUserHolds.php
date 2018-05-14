<?php
/**
 * "Get User Holds" AJAX handler
 *
 * PHP version 7
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

use Zend\Mvc\Controller\Plugin\Params;

/**
 * "Get User Holds" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserHolds extends AbstractIlsAndUserAction
{
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
            return $this->formatResponse('', self::STATUS_NEED_AUTH, 401);
        }
        if (!$this->ils->checkCapability('getMyHolds')) {
            return $this->formatResponse('', self::STATUS_ERROR, 405);
        }
        $holds = $this->ils->getMyHolds($patron);
        $foundValid = false;
        foreach ($holds as $hold) {
            if ($hold['available'] ?? false) {
                return $this->formatResponse('PICKUP');
            }
            if ($hold['in_transit'] ?? false) {
                return $this->formatResponse('IN TRANSIT');
            }
        }
        return $this->formatResponse('CLEAR');
    }
}
