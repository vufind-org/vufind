<?php

/**
 * "Get User Fines" AJAX handler
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
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\ILS\Connection;
use VuFind\Service\CurrencyFormatter;
use VuFind\Session\Settings as SessionSettings;

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
    use \VuFind\ILS\Logic\SummaryTrait;

    /**
     * Currency formatter
     *
     * @var CurrencyFormatter
     */
    protected $currencyFormatter;

    /**
     * Constructor
     *
     * @param SessionSettings      $ss                Session settings
     * @param Connection           $ils               ILS connection
     * @param ILSAuthenticator     $ilsAuthenticator  ILS authenticator
     * @param ?UserEntityInterface $user              Logged in user (or false)
     * @param CurrencyFormatter    $currencyFormatter Currency formatter
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        ILSAuthenticator $ilsAuthenticator,
        ?UserEntityInterface $user,
        CurrencyFormatter $currencyFormatter
    ) {
        parent::__construct($ss, $ils, $ilsAuthenticator, $user);
        $this->currencyFormatter = $currencyFormatter;
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
            return $this->formatResponse('', self::STATUS_HTTP_NEED_AUTH);
        }
        if (!$this->ils->checkCapability('getMyFines')) {
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }
        $fines = $this->ils->getMyFines($patron);
        return $this->formatResponse(
            $this->getFineSummary($fines, $this->currencyFormatter)
        );
    }
}
