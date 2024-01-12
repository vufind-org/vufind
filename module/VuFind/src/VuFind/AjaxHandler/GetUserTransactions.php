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

/**
 * "Get User Transactions" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserTransactions extends AbstractIlsAndUserAction
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

        $counts = [];
        $functionConfig = $this->ils->checkFunction('getMyTransactions', $patron);
        $page = 1;
        do {
            // Try to use large page size, but take ILS limits into account
            $pageOptions = $this->getPaginationHelper()
                ->getOptions($page, null, 1000, $functionConfig);
            $result = $this->ils
                ->getMyTransactions($patron, $pageOptions['ilsParams']);

            $summary = $this->getTransactionSummary($result['records']);
            foreach ($summary as $key => $value) {
                $counts[$key] = ($counts[$key] ?? 0) + $value;
            }
            $pageEnd = $pageOptions['ilsPaging']
                ? ceil($result['count'] / $pageOptions['limit'])
                : 1;
            $page++;
        } while ($page <= $pageEnd);

        return $this->formatResponse($counts);
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
