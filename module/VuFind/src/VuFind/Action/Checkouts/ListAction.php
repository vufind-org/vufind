<?php

/**
 * Checkout list action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Checkouts;

use Laminas\Session\SessionManager;
use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\ILS\Logic\RenewalsHelper;
use VuFind\ILS\Logic\SummaryTrait;
use VuFind\ILS\PaginationHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

use function is_array;

/**
 * Checkout list action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ListAction extends AbstractCheckoutsAction
{
    use SummaryTrait;

    /**
     * Constructor.
     *
     * @param array                      $config            VuFind configuration
     * @param CsrfInterface              $csrf              CSRF validator
     * @param SessionManager             $sessionManager    Session manager
     * @param PaginationHelper           $paginationHelper  Pagination helper
     * @param Connection                 $ilsConnection     ILS connection
     * @param RecordsHelper              $ilsRecordsHelper  ILS records helper
     * @param RenewalsHelper             $ilsRenewalsHelper ILS Renewals helper
     * @param AuthManager                $authManager       Authentication manager
     * @param AuditEventServiceInterface $auditEventService Audit event service
     */
    public function __construct(
        #[Autowire(config: 'config')]
        array $config,
        CsrfInterface $csrf,
        SessionManager $sessionManager,
        PaginationHelper $paginationHelper,
        Connection $ilsConnection,
        RecordsHelper $ilsRecordsHelper,
        protected RenewalsHelper $ilsRenewalsHelper,
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventServiceInterface $auditEventService,
    ) {
        parent::__construct($config, $csrf, $sessionManager, $paginationHelper, $ilsConnection, $ilsRecordsHelper);
    }

    /**
     * Display a list of checkouts.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        // Display account blocks, if any:
        $this->getHelper(FlashMessagesHelper::class)->addAccountBlocks($this->ilsConnection, $patron);

        // Get the current renewal status and process renewal form, if necessary:
        $renewStatus = $this->ilsConnection->checkFunction('Renewals', compact('patron'));
        $renewResult = $renewStatus
            ? $this->ilsRenewalsHelper->processRenewals(
                new Parameters($request->getParsedBody()),
                $this->ilsConnection,
                $patron,
                $this->getHelper(FlashMessagesHelper::class)->getFlashMessenger(),
                $this->csrf
            )
            : [];

        if ($renewResult) {
            $this->auditEventService->addEvent(
                AuditEventType::ILS,
                AuditEventSubtype::RenewLoans,
                $this->authManager->getUserObject(),
                data: [
                    'username' => $patron['cat_username'],
                    'result' => $renewResult,
                ]
            );
        }

        // By default, assume we will not need to display a renewal form:
        $renewForm = false;

        // Get paging setup:
        $pageSize = $this->config['Catalog']['checked_out_page_size'] ?? 50;
        $pageOptions = $this->paginationHelper->getOptions(
            (int)($this->getQueryParam('page') ?? 1),
            $this->getQueryParam('sort'),
            $pageSize,
            $this->ilsConnection->checkFunction('getMyTransactions', $patron)
        );

        // Get checked out item details:
        $result = $this->ilsConnection->getMyTransactions($patron, $pageOptions['ilsParams']);

        // Build paginator if needed:
        $paginator = $this->paginationHelper->getPaginator($pageOptions, $result['count'], $result['records']);
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }

        // If the results are not paged in the ILS, collect up to date stats for ajax account notifications:
        if (
            ($this->config['Authentication']['enableAjax'] ?? false)
            && (!$pageOptions['ilsPaging'] || !$paginator
            || $result['count'] <= $pageSize)
        ) {
            $accountStatus = $this->getTransactionSummary($result['records']);
        } else {
            $accountStatus = null;
        }

        $driversNeeded = $hiddenTransactions = [];
        foreach ($result['records'] as $i => $current) {
            // Add renewal details if appropriate:
            $current = $this->ilsRenewalsHelper->addRenewDetails($this->ilsConnection, $current, $renewStatus);
            if ($renewStatus && !isset($current['renew_link']) && $current['renewable']) {
                // Enable renewal form if necessary:
                $renewForm = true;
            }

            // Build record drivers (only for the current visible page):
            if ($pageOptions['ilsPaging'] || ($i >= $pageStart && $i <= $pageEnd)) {
                $driversNeeded[] = $current;
            } else {
                $hiddenTransactions[] = $current;
            }
        }

        $transactions = $this->ilsRecordsHelper->getDrivers($driversNeeded);

        $displayItemBarcode = !empty($this->config['Catalog']['display_checked_out_item_barcode']);

        $ilsPaging = $pageOptions['ilsPaging'];
        $sortList = $pageOptions['sortList'];
        $params = $pageOptions['ilsParams'];

        return $this->renderTemplate(
            $request,
            $response,
            compact(
                'transactions',
                'renewForm',
                'renewResult',
                'paginator',
                'ilsPaging',
                'hiddenTransactions',
                'displayItemBarcode',
                'sortList',
                'params',
                'accountStatus'
            )
        );
    }
}
