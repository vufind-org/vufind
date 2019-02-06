<?php
/**
 * "Get User Fines" AJAX handler
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

use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Row\User;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\SafeMoneyFormat;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * "Get User Fines" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserFines extends AbstractIlsAndUserAction
{
    /**
     * Constructor
     *
     * @param SessionSettings  $ss               Session settings
     * @param Connection       $ils              ILS connection
     * @param ILSAuthenticator $ilsAuthenticator ILS authenticator
     * @param User|bool        $user             Logged in user (or false)
     * @param SafeMoneyFormat  $safeMoneyFormat  Money formatting view helper
     */
    public function __construct(SessionSettings $ss, Connection $ils,
        ILSAuthenticator $ilsAuthenticator, $user, SafeMoneyFormat $safeMoneyFormat
    ) {
        parent::__construct($ss, $ils, $ilsAuthenticator, $user);
        $this->safeMoneyFormat = $safeMoneyFormat;
    }

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
        if (!$this->ils->checkCapability('getMyFines')) {
            return $this->formatResponse('', self::STATUS_ERROR, 405);
        }
        $sum = 0;
        foreach ($this->ils->getMyFines($patron) as $fine) {
            $sum += $fine['balance'];
        }
        $value = $sum / 100;
        $display = $this->safeMoneyFormat->__invoke($sum / 100);
        return $this->formatResponse(compact('value', 'display'));
    }
}
