<?php

/**
 * Abstract base class for actions that manage the search history.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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

namespace VuFind\Action\MyResearch;

use Exception;
use Laminas\Session\SessionManager;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Config\AccountCapabilities;
use VuFind\Crypt\SecretCalculator;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\Search\History;
use VuFind\Search\Results\PluginManager as SearchResultsPluginManager;
use VuFind\Search\SearchNormalizer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * Abstract base class for actions that manage the search history.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractSearchHistoryAction extends AbstractMyResearchAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager                Authentication manager
     * @param FollowupHelper                  $followupHelper             Followup helper
     * @param EmailAuthenticator              $emailAuthenticator         Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService         User session service
     * @param AuditEventServiceInterface      $auditEventService          Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper            Server URL helper
     * @param Mailer                          $mailer                     Mailer
     * @param SessionManager                  $sessionManager             Session manager
     * @param Connection                      $ilsConnection              ILS connection
     * @param array                           $config                     VuFind configuration
     * @param AccountCapabilities             $accountCapabilities        Account capabilities
     * @param History                         $searchHistory              Search history
     * @param SearchServiceInterface          $searchService              Search service
     * @param SearchNormalizer                $searchNormalizer           Search normalizer
     * @param SearchResultsPluginManager      $searchResultsPluginManager Search results plugin manager
     * @param SecretCalculator                $secretCalculator           Secret calculator
     */
    public function __construct(
        AuthManager $authManager,
        FollowupHelper $followupHelper,
        EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        UserSessionPersistenceInterface $userSessionService,
        #[Autowire(container: DbServicePluginManager::class)]
        AuditEventServiceInterface $auditEventService,
        ServerUrlHelper $serverUrlHelper,
        Mailer $mailer,
        SessionManager $sessionManager,
        Connection $ilsConnection,
        #[Autowire(config: 'config')]
        array $config,
        protected AccountCapabilities $accountCapabilities,
        protected History $searchHistory,
        #[Autowire(container: DbServicePluginManager::class)]
        protected SearchServiceInterface $searchService,
        protected SearchNormalizer $searchNormalizer,
        protected SearchResultsPluginManager $searchResultsPluginManager,
        protected SecretCalculator $secretCalculator,
    ) {
        parent::__construct(
            $authManager,
            $followupHelper,
            $emailAuthenticator,
            $userSessionService,
            $auditEventService,
            $serverUrlHelper,
            $mailer,
            $sessionManager,
            $ilsConnection,
            $config
        );
    }

    /**
     * Get a search row, but throw an exception if it is not owned by the specified
     * user or current active session.
     *
     * @param int                 $searchId ID of search row
     * @param UserEntityInterface $user     Current user ID
     *
     * @throws ForbiddenException
     * @return SearchEntityInterface
     */
    protected function getSearchRowSecurely(int $searchId, UserEntityInterface $user): SearchEntityInterface
    {
        $sessionId = $this->sessionManager->getId();
        if (!($search = $this->searchService->getSearchByIdAndOwner($searchId, $sessionId, $user))) {
            throw new ForbiddenException('Access denied.');
        }
        return $search;
    }

    /**
     * Is the provided search row a duplicate of a search that is already saved?
     *
     * @param SearchEntityInterface $rowToCheck Search row to check
     * @param UserEntityInterface   $user       Current user ID
     *
     * @return ?int
     */
    protected function isDuplicateOfSavedSearch(
        SearchEntityInterface $rowToCheck,
        UserEntityInterface $user
    ): ?int {
        $searchObject = $rowToCheck->getSearchObject();
        if (!$searchObject) {
            throw new Exception("Problem getting search object from search {$rowToCheck->getId()}.");
        }
        $normalized = $this->searchNormalizer->normalizeMinifiedSearch($searchObject);
        $sessionId = $this->sessionManager->getId();
        $matches = $this->searchNormalizer
            ->getSearchesMatchingNormalizedSearch($normalized, $sessionId, $user->getId());
        foreach ($matches as $current) {
            if ($current->getSaved() && $current->getId() !== $rowToCheck->getId()) {
                return $current->getId();
            }
        }
        return null;
    }
}
