<?php

/**
 * "Get User Fines" AJAX handler.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Account\AccountStatusLevelType;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Http\HttpStatus;
use VuFind\ILS\Connection;
use VuFind\Service\CurrencyFormatter;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * "Get User Fines" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserFines extends AbstractIlsUserAndRendererAction
{
    use \VuFind\ILS\Logic\SummaryTrait;

    /**
     * Constructor.
     *
     * @param SessionSettings           $ss                Session settings
     * @param Connection                $ils               ILS connection
     * @param ILSAuthenticator          $ilsAuthenticator  ILS authenticator
     * @param ?UserEntityInterface      $user              Logged in user (or false)
     * @param TemplateRendererInterface $renderer          Renderer
     * @param CurrencyFormatter         $currencyFormatter Currency formatter
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        ILSAuthenticator $ilsAuthenticator,
        ?UserEntityInterface $user,
        TemplateRendererInterface $renderer,
        protected CurrencyFormatter $currencyFormatter,
    ) {
        parent::__construct($ss, $ils, $ilsAuthenticator, $user, $renderer);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $patron = $this->ilsAuthenticator->storedCatalogLogin();
        if (!$patron) {
            return $this->formatResponse('', HttpStatus::NEED_AUTH);
        }
        if (!$this->ils->checkCapability('getMyFines')) {
            return $this->formatResponse('', HttpStatus::ERROR);
        }
        $fines = $this->ils->getMyFines($patron);
        $result = $this->getFineSummary($fines, $this->currencyFormatter);
        $result['level'] = $this->getAccountStatusLevel($result);
        $result['html'] = $this->renderer->renderTemplateAsString($request, 'ajax/account/fines.phtml', $result);
        return $this->formatResponse($result);
    }

    /**
     * Get account status level for notification icon.
     *
     * @param array $status Status information
     *
     * @return AccountStatusLevelType
     */
    protected function getAccountStatusLevel(array $status): AccountStatusLevelType
    {
        return $status['total'] ? AccountStatusLevelType::ActionRequired : AccountStatusLevelType::Normal;
    }
}
