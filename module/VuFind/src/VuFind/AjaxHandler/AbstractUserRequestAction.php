<?php
/**
 * Abstract base class for fetching information about user requests.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * Abstract base class for fetching information about user requests.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractUserRequestAction extends AbstractIlsAndUserAction
{
    /**
     * ILS driver method for data retrieval.
     *
     * @var string
     */
    protected $lookupMethod;    // must be set in subclass

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
            return $this->formatResponse('', self::STATUS_HTTP_NEED_AUTH, 401);
        }
        if (!$this->ils->checkCapability($this->lookupMethod)) {
            return $this->formatResponse('', self::STATUS_HTTP_ERROR, 405);
        }
        $requests = $this->ils->{$this->lookupMethod}($patron);
        $status = [
            'available' => 0,
            'in_transit' => 0
        ];
        foreach ($requests as $request) {
            if ($request['available'] ?? false) {
                $status['available'] += 1;
            }
            if ($request['in_transit'] ?? false) {
                $status['in_transit'] += 1;
            }
        }
        return $this->formatResponse($status);
    }
}
