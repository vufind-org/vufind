<?php

/**
 * List holds action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021-2026.
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

namespace VuFind\Action\Holds;

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\HoldsHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Db\Service\AuditEventService;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

use function count;
use function is_array;

/**
 * List holds action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ListAction extends AbstractHoldAction
{
    /**
     * Session data.
     *
     * @var ?Container
     */
    protected ?Container $session = null;

    /**
     * Constructor.
     *
     * @param Connection        $ilsConnection     ILS connection
     * @param SessionManager    $sessionManager    Session manager
     * @param CacheManager      $cacheManager      Cache manager
     * @param AuthManager       $authManager       Authentication manager
     * @param CsrfInterface     $csrf              CSRF validator
     * @param AuditEventService $auditEventService Audit event service
     * @param RecordsHelper     $recordsHelper     Records helper
     * @param array             $config            VuFind configuration
     */
    public function __construct(
        Connection $ilsConnection,
        SessionManager $sessionManager,
        CacheManager $cacheManager,
        AuthManager $authManager,
        CsrfInterface $csrf,
        AuditEventService $auditEventService,
        protected RecordsHelper $recordsHelper,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct($ilsConnection, $sessionManager, $authManager, $csrf, $auditEventService, $cacheManager);
    }

    /**
     * List holds and process cancellations and updates.
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

        // Process cancel requests if necessary:
        $holdsHelper = $this->getHelper(HoldsHelper::class);
        $cancelStatus = $this->ilsConnection->checkFunction('cancelHolds', compact('patron'));
        $cancelResults = $cancelStatus
            ? $holdsHelper->cancelHolds($request, $response, $this->ilsConnection, $patron)
            : [];
        // Check if we need to confirm cancellation:
        if ($cancelResults instanceof ResponseInterface) {
            return $cancelResults;
        }
        if ($cancelResults) {
            $this->auditEventService->addEvent(
                AuditEventType::ILS,
                AuditEventSubtype::CancelHolds,
                $this->authManager->getUserObject(),
                data: [
                    'username' => $patron['cat_username'],
                    'results' => $cancelResults,
                ]
            );
        }

        $templateParams = [
            'cancelResults' => $cancelResults,
            // By default, assume we will not need to display a cancel or update form:
            'cancelForm' => false,
            'updateForm' => false,
        ];

        // Process any update request results stored in the session:
        $updateResultsContainer = $this->getHoldUpdateResultsContainer();
        $holdUpdateResults = $updateResultsContainer->results ?? null;
        if ($holdUpdateResults) {
            $templateParams['updateResults'] = $holdUpdateResults;
            $updateResultsContainer->results = null;
        }
        // Process update requests if necessary:
        if ($this->getPostParam('updateSelected')) {
            $selectedIds = $this->getPostParam('selectedIDS');
            if (!$selectedIds) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('hold_empty_selection');
                if ($this->getHelper(ContextHelper::class)->inLightbox($request)) {
                    return $this->getHelper(ResponseHelper::class)->getRefreshResponse($response);
                }
            } else {
                return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'holds/edit');
            }
        }

        // Get paging setup:
        $pageOptions = $this->getPageOptions($patron);

        // Get held item details:
        $result = $this->ilsConnection->getMyHolds($patron, $pageOptions['ilsParams']);

        if (!$pageOptions['ilsPaging']) {
            // Cache the current list of requests for editing:
            $this->putCachedData($this->getCacheId($patron, 'holds'), $result);
        } else {
            $this->removeCachedData($this->getCacheId($patron, 'holds'));
        }

        // Build paginator if needed:
        $paginator = $this->getPaginationHelper()->getPaginator(
            $pageOptions,
            $result['count'],
            $result['records']
        );
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }
        $templateParams['paginator'] = $paginator;
        $templateParams['ilsPaging'] = $pageOptions['ilsPaging'];
        $templateParams['params'] = $pageOptions['ilsParams'];

        $driversNeeded = $hiddenHolds = [];
        $holdsHelper->resetValidation();
        $holdConfig = $this->ilsConnection->checkFunction('Holds', compact('patron'));
        foreach ($result['records'] as $i => $current) {
            // Add cancel details if appropriate:
            $current = $holdsHelper->addCancelDetails(
                $this->ilsConnection,
                $current,
                $cancelStatus,
                $patron
            );
            if (
                $cancelStatus && $cancelStatus['function'] !== 'getCancelHoldLink'
                && isset($current['cancel_details'])
            ) {
                // Enable cancel form if necessary:
                $templateParams['cancelForm'] = true;
            }

            // Add update details if appropriate
            if (isset($current['updateDetails'])) {
                if (
                    empty($holdConfig['updateFields'])
                    || '' === $current['updateDetails']
                ) {
                    unset($current['updateDetails']);
                } else {
                    $templateParams['updateForm'] = true;
                    $holdsHelper->rememberValidId($current['updateDetails']);
                }
            }

            // Build record drivers (only for the current visible page):
            if ($pageOptions['ilsPaging'] || ($i >= $pageStart && $i <= $pageEnd)) {
                $driversNeeded[] = $current;
            } else {
                $hiddenHolds[] = $current;
            }
        }
        $templateParams['hiddenHolds'] = $hiddenHolds;

        // Get list of pick up libraries based on patron's home library:
        try {
            $pickupCacheId = $this->getCacheId($patron, 'pickup');
            $templateParams['pickup'] = $this->getCachedData($pickupCacheId);
            if (null === $templateParams['pickup']) {
                $templateParams['pickup'] = $this->ilsConnection->getPickUpLocations($patron);
                $this->putCachedData($pickupCacheId, $templateParams['pickup']);
            }
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup locations, they are not supported and we
            // should ignore them.
        }

        $templateParams['recordList'] = $this->recordsHelper->getDrivers($driversNeeded);

        // If the results are not paged in the ILS, collect up-to-date stats for AJAX account notifications:
        if (!$pageOptions['ilsPaging'] || !$paginator || $result['count'] === count($result['records'])) {
            $templateParams['accountStatus'] = $this->recordsHelper->collectRequestStats($templateParams['recordList']);
        } else {
            $templateParams['accountStatus'] = null;
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Return a session container for hold update results.
     *
     * @return Container
     */
    protected function getHoldUpdateResultsContainer(): Container
    {
        return new \Laminas\Session\Container('hold_update', $this->sessionManager);
    }

    /**
     * Get a unique cache id for a patron.
     *
     * @param array  $patron Patron
     * @param string $type   Type of cached data
     *
     * @return string
     */
    protected function getCacheId(array $patron, string $type): string
    {
        return "$type::" . $patron['id'] . '::' . ($patron['cat_id'] ?? $patron['cat_username'] ?? '');
    }

    /**
     * Grab the Container object for storing helper-specific session data.
     *
     * @return Container
     */
    protected function getSession(): Container
    {
        if (!$this->session) {
            $this->session = new Container('Holds_Helper', $this->sessionManager);
        }
        return $this->session;
    }
}
