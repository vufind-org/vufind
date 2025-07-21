<?php

/**
 * "Get User Transactions" AJAX handler
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
use VuFind\Account\AccountStatusLevelType;

/**
 * "Get User Transactions" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserTransactions extends AbstractIlsUserAndRendererAction
{
    use \VuFind\ILS\Logic\SummaryTrait;

    /**
     * Paginator
     *
     * @var \VuFind\ILS\PaginationHelper
     */
    protected $paginationHelper = null;

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
        if (!$this->ils->checkCapability('getMyTransactions')) {
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        $result = [];
        $functionConfig = $this->ils->checkFunction('getMyTransactions', $patron);
        $page = 1;
        do {
            // Try to use large page size, but take ILS limits into account
            $pageOptions = $this->getPaginationHelper()
                ->getOptions($page, null, 1000, $functionConfig);
            $transactions = $this->ils->getMyTransactions($patron, $pageOptions['ilsParams']);

            $summary = $this->getTransactionSummary($transactions['records']);
            foreach ($summary as $key => $value) {
                $result[$key] = ($result[$key] ?? 0) + $value;
            }
            $pageEnd = $pageOptions['ilsPaging']
                ? ceil($transactions['count'] / $pageOptions['limit'])
                : 1;
            $page++;
        } while ($page <= $pageEnd);

        $result['level'] = $this->getAccountStatusLevel($result);
        $result['html'] = $this->renderer->render('ajax/account/checkouts.phtml', $result);
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
        if ($status['overdue']) {
            return AccountStatusLevelType::ActionRequired;
        }
        if ($status['warn']) {
            return AccountStatusLevelType::Attention;
        }
        return AccountStatusLevelType::Normal;
    }

    /**
     * Set the ILS pagination helper
     *
     * @param \VuFind\ILS\PaginationHelper $helper Pagination helper
     *
     * @return void
     */
    protected function setPaginationHelper($helper)
    {
        $this->paginationHelper = $helper;
    }

    /**
     * Get the ILS pagination helper
     *
     * @return \VuFind\ILS\PaginationHelper
     */
    protected function getPaginationHelper()
    {
        if (null === $this->paginationHelper) {
            $this->paginationHelper = new \VuFind\ILS\PaginationHelper();
        }
        return $this->paginationHelper;
    }
}
