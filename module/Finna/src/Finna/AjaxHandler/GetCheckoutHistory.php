<?php

/**
 * GetCheckoutHistory AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\ILS\Connection;
use VuFind\ILS\PaginationHelper;
use VuFind\Session\Settings as SessionSettings;

/**
 * GetCheckoutHistory AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetCheckoutHistory extends \VuFind\AjaxHandler\AbstractIlsAndUserAction
{
    /**
     * Cache for patron
     *
     * @var array
     */
    protected array $cachedPatron = [];

    /**
     * Cache for function config.
     *
     * @var array
     */
    protected array $cachedFunctionConfig = [];

    /**
     * Constructor
     *
     * @param SessionSettings       $ss               Session settings
     * @param Connection            $ils              ILS connection
     * @param ILSAuthenticator      $ilsAuthenticator ILS authenticator
     * @param ?UserEntityInterface  $user             Logged in user (or null)
     * @param \VuFind\Record\Loader $recordLoader     Record loader
     * @param int                   $batchLimit       Config specified default batch limit
     * @param int                   $defaultPageSize  Default page size set in config.ini
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        ILSAuthenticator $ilsAuthenticator,
        ?UserEntityInterface $user,
        protected \VuFind\Record\Loader $recordLoader,
        protected int $batchLimit = 1000,
        protected int $defaultPageSize = 50
    ) {
        if ($this->batchLimit < $defaultPageSize) {
            $this->batchLimit = $defaultPageSize;
        }
        parent::__construct($ss, $ils, $ilsAuthenticator, $user);
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $result = $this->getCheckoutHistoryResult();
        if ($result['success'] === false) {
            return $this->formatResponse($result['message'], $result['status']);
        }
        $calculatedResults = $this->calculateLimitsFromResult($result);
        return $this->formatResponse(['parts' => $calculatedResults['parts']]);
    }

    /**
     * Calculate limits used to fetch data from the results obtained from getCheckoutHistoryResult.
     *
     * @param array $result Checkout history result
     *
     * @return array
     */
    public function calculateLimitsFromResult(array $result): array
    {
        $resultCount = $result['function_result']['count'] ?? 1;
        $paginationHelper = new PaginationHelper();
        $paginator = $paginationHelper->getPaginator(
            $result['pageOptions'],
            $result['function_result']['count'],
            $result['function_result']['transactions']
        );
        $pageLimit = $paginator ? $paginator->getItemCountPerPage() : $this->defaultPageSize;
        $parts = $pageLimit > $this->batchLimit
            ? floor($resultCount / $this->batchLimit)
            : ceil($resultCount / $this->batchLimit);
        return [
            'pageLimit' => $paginator ? $paginator->getItemCountPerPage() : $this->defaultPageSize,
            'pageCount' => $paginator ? $paginator->count() : 1,
            'parts' => $parts,
        ];
    }

    /**
     * Get checkout history result for user if available
     *
     * @param int  $page  First page to get from ils
     * @param ?int $limit Current limit for the page size or null for default
     *
     * @return array
     */
    public function getCheckoutHistoryResult(int $page = 1, ?int $limit = null): array
    {
        $getErrorMessage = function ($message, $status) {
            $success = false;
            $message = $this->translate($message);
            return compact('success', 'message', 'status');
        };
        if (!$this->cachedPatron) {
            $patron = $this->ilsAuthenticator->storedCatalogLogin();
            if (!$this->user || !$patron) {
                return $getErrorMessage('You must be logged in first', self::STATUS_HTTP_NEED_AUTH);
            }
            $this->cachedPatron = $patron;
        }
        // Check function config
        if (!$this->cachedFunctionConfig) {
            $this->cachedFunctionConfig = $this->ils->checkFunction('getMyTransactionHistory', $this->cachedPatron);
            if (false === $this->cachedFunctionConfig) {
                return $getErrorMessage('ils_action_unavailable', self::STATUS_HTTP_UNAVAILABLE);
            }
        }
        $paginationHelper = new PaginationHelper();
        $pageOptions = $paginationHelper->getOptions(
            $page,
            null,
            $limit ?? $this->defaultPageSize,
            $this->cachedFunctionConfig
        );
        $result = $this->ils->getMyTransactionHistory($this->cachedPatron, $pageOptions['ilsParams']);
        if (isset($result['success']) && !$result['success']) {
            return $getErrorMessage('An error has occurred', self::STATUS_HTTP_ERROR);
        }
        return [
            'success' => true,
            'function_result' => $result,
            'pageOptions' => $pageOptions,
        ];
    }
}
