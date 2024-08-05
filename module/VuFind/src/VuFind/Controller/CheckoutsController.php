<?php

/**
 * Checkouts Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\SessionManager;
use VuFind\ILS\PaginationHelper;
use VuFind\Validator\CsrfInterface;

use function is_array;

/**
 * Controller for the user checkouts area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CheckoutsController extends AbstractBase
{
    use Feature\CatchIlsExceptionsTrait;

    /**
     * CSRF validator
     *
     * @var CsrfInterface
     */
    protected $csrf;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Session container
     *
     * @var \Laminas\Session\Container
     */
    protected $sessionContainer = null;

    /**
     * Pagination helper
     *
     * @var PaginationHelper
     */
    protected $paginationHelper;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm      Service locator
     * @param CsrfInterface           $csrf    CSRF validator
     * @param SessionManager          $sessMgr Session manager
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        CsrfInterface $csrf,
        SessionManager $sessMgr
    ) {
        parent::__construct($sm);
        $this->csrf = $csrf;
        $this->sessionManager = $sessMgr;
        $this->paginationHelper = new PaginationHelper();
    }

    /**
     * Send loan history to view
     *
     * @return mixed
     */
    public function historyAction()
    {
        $this->resetValidRowIds();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Check function config
        $functionConfig = $catalog->checkFunction(
            'getMyTransactionHistory',
            $patron
        );
        if (false === $functionConfig) {
            $this->flashMessenger()->addErrorMessage('ils_action_unavailable');
            return $this->createViewModel();
        }
        $purgeSelectedAllowed = !empty($functionConfig['purge_selected']);
        $purgeAllAllowed = !empty($functionConfig['purge_all']);

        // Get paging setup:
        $config = $this->getConfig();
        $pageOptions = $this->paginationHelper->getOptions(
            (int)$this->params()->fromQuery('page', 1),
            $this->params()->fromQuery('sort'),
            $config->Catalog->historic_loan_page_size ?? 50,
            $functionConfig
        );

        // Get checked out item details:
        $result
            = $catalog->getMyTransactionHistory($patron, $pageOptions['ilsParams']);

        if (isset($result['success']) && !$result['success']) {
            $this->flashMessenger()->addErrorMessage($result['status']);
            return $this->createViewModel();
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

        $transactions = $this->ilsRecords()->getDrivers($driversNeeded);
        $sortList = $pageOptions['sortList'];
        $params = $pageOptions['ilsParams'];
        return $this->createViewModel(
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

    /**
     * Purge loans from loan history
     *
     * @return mixed
     */
    public function purgeHistoryAction()
    {
        $this->ilsExceptionResponse = $redirectResponse
            = $this->redirect()->toRoute('checkouts-history');

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $purgeSelected = $this->formWasSubmitted('purgeSelected', false);
        $purgeAll = $this->formWasSubmitted('purgeAll', false);
        if ($purgeSelected || $purgeAll) {
            $csrfToken = $this->getRequest()->getPost()->get('csrf');
            if (!$this->csrf->isValid($csrfToken)) {
                $this->flashMessenger()
                    ->addErrorMessage('error_inconsistent_parameters');
                return $redirectResponse;
            }
            // After successful token verification, clear list to shrink session:
            $this->csrf->trimTokenList(0);
            $catalog = $this->getILS();
            if ($purgeAll) {
                $result = $catalog->purgeTransactionHistory($patron, null);
            } else {
                $ids = $this->getRequest()->getPost()->get('purgeSelectedIDs', []);
                if (!$ids) {
                    $this->flashMessenger()
                        ->addErrorMessage('no_items_selected');
                    return $redirectResponse;
                }
                if (!$this->validateRowIds($ids)) {
                    $this->flashMessenger()
                        ->addErrorMessage('error_inconsistent_parameters');
                    return $redirectResponse;
                }
                $result = $catalog->purgeTransactionHistory($patron, $ids);
            }
            $this->flashMessenger()->addMessage(
                $result['status'],
                $result['success'] ? 'success' : 'error'
            );
        }
        return $redirectResponse;
    }

    /**
     * Return a session container for validating selected row ids.
     *
     * @return \Laminas\Session\Container
     */
    protected function getRowIdContainer()
    {
        if (null === $this->sessionContainer) {
            $this->sessionContainer
                = new \Laminas\Session\Container('row_ids', $this->sessionManager);
        }
        return $this->sessionContainer;
    }

    /**
     * Reset the array of valid IDs in the session (used for form submission
     * validation)
     *
     * @return void
     */
    protected function resetValidRowIds(): void
    {
        $this->getRowIdContainer()->validIds = [];
    }

    /**
     * Add an ID to the validation array.
     *
     * @param string $id ID to remember
     *
     * @return void
     */
    protected function rememberValidRowId($id): void
    {
        $this->getRowIdContainer()->validIds[] = $id;
    }

    /**
     * Validate supplied IDs against remembered IDs. Returns true if all supplied
     * IDs are remembered, otherwise returns false.
     *
     * @param array $ids IDs to validate
     *
     * @return bool
     */
    public function validateRowIds(array $ids): bool
    {
        return !(bool)array_diff($ids, $this->getRowIdContainer()->validIds ?? []);
    }
}
