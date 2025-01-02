<?php

/**
 * Abstract base class for fetching information about user requests.
 *
 * PHP version 8
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

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Account\AccountStatusLevelType;

/**
 * Abstract base class for fetching information about user requests.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractUserRequestAction extends AbstractIlsUserAndRendererAction
{
    use \VuFind\ILS\Logic\SummaryTrait;

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
            return $this->formatResponse('', self::STATUS_HTTP_NEED_AUTH);
        }
        if (!$this->ils->checkCapability($this->lookupMethod, [$patron])) {
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }
        $requests = $this->ils->{$this->lookupMethod}($patron);
        $result = $this->getRequestSummary($requests);
        $result['level'] = $this->getAccountStatusLevel($result);
        $result['html'] = $this->renderer->render('ajax/account/requests.phtml', $result);
        return $this->formatResponse($result);
    }

    /**
     * Get account status level for notification icon
     *
     * @param array $status Status information
     *
     * @return AccountStatusLevelType
     */
    protected function getAccountStatusLevel(array $status): AccountStatusLevelType
    {
        if ($status['available']) {
            // This is equivalent to the GOOD level in account_ajax.js, though e.g. ActionRequired could also make sense
            return AccountStatusLevelType::Good;
        }
        return AccountStatusLevelType::Normal;
    }
}
