<?php

/**
 * Checkout history action.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;

use function is_array;

/**
 * Checkout history action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HistoryAction extends AbstractCheckoutsAction
{
    /**
     * Display checkout history.
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
        $this->resetValidRowIds();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        // Check function config
        $functionConfig = $this->ilsConnection->checkFunction(
            'getMyTransactionHistory',
            $patron
        );
        if (!$functionConfig) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('ils_action_unavailable');
            return $this->renderTemplate($request, $response);
        }
        $purgeSelectedAllowed = !empty($functionConfig['purge_selected']);
        $purgeAllAllowed = !empty($functionConfig['purge_all']);

        // Get paging setup:
        $pageOptions = $this->paginationHelper->getOptions(
            (int)$this->getQueryParam('page', 1),
            $this->getQueryParam('sort'),
            $this->config['Catalog']['historic_loan_page_size'] ?? 50,
            $functionConfig
        );

        // Get checked out item details:
        $result = $this->ilsConnection->getMyTransactionHistory($patron, $pageOptions['ilsParams']);

        if (isset($result['success']) && !$result['success']) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage($result['status']);
            return $this->renderTemplate($request, $response);
        }

        $paginator = $this->paginationHelper->getPaginator(
            $pageOptions,
            $result['count'],
            $result['transactions']
        );
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }

        $driversNeeded = $hiddenTransactions = [];
        foreach ($result['transactions'] as $i => $current) {
            // Build record drivers (only for the current visible page):
            if ($pageOptions['ilsPaging'] || ($i >= $pageStart && $i <= $pageEnd)) {
                $driversNeeded[] = $current;
            } else {
                $hiddenTransactions[] = $current;
            }
            if ($purgeSelectedAllowed && isset($current['row_id'])) {
                $this->rememberValidRowId($current['row_id']);
            }
        }

        $transactions = $this->ilsRecordsHelper->getDrivers($driversNeeded);
        $sortList = $pageOptions['sortList'];
        $params = $pageOptions['ilsParams'];
        return $this->renderTemplate(
            $request,
            $response,
            compact(
                'transactions',
                'paginator',
                'params',
                'hiddenTransactions',
                'sortList',
                'functionConfig',
                'purgeAllAllowed',
                'purgeSelectedAllowed'
            )
        );
    }
}
