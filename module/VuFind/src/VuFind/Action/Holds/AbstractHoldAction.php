<?php

/**
 * Abstract base class for hold actions.
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
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\HandleIlsExceptionsTrait;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Cache\CacheTrait;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Db\Service\AuditEventService;
use VuFind\ILS\Connection;
use VuFind\ILS\PaginationHelper;
use VuFind\Validator\CsrfInterface;

/**
 * Abstract base class for hold actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractHoldAction extends AbstractTemplateRenderingAction
{
    use CacheTrait;
    use HandleIlsExceptionsTrait;

    /**
     * Session data.
     *
     * @var ?Container
     */
    protected ?Container $session = null;

    /**
     * ILS Pagination Helper.
     *
     * @var ?PaginationHelper
     */
    protected ?PaginationHelper $paginationHelper = null;

    /**
     * Constructor.
     *
     * @param Connection        $ilsConnection     ILS connection
     * @param SessionManager    $sessionManager    Session manager
     * @param AuthManager       $authManager       Authentication manager
     * @param CsrfInterface     $csrf              CSRF validator
     * @param AuditEventService $auditEventService Audit event service
     * @param CacheManager      $cacheManager      Cache manager
     */
    public function __construct(
        protected Connection $ilsConnection,
        protected SessionManager $sessionManager,
        protected AuthManager $authManager,
        protected CsrfInterface $csrf,
        protected AuditEventService $auditEventService,
        CacheManager $cacheManager,
    ) {
        parent::__construct();

        $this->setCacheStorage($cacheManager->getCache('object'));
        // Cache the data related to holds for up to 10 minutes:
        $this->cacheLifetime = 600;
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
     * Get the ILS pagination helper.
     *
     * @return PaginationHelper
     */
    protected function getPaginationHelper()
    {
        if (null === $this->paginationHelper) {
            $this->paginationHelper = new PaginationHelper();
        }
        return $this->paginationHelper;
    }

    /**
     * Get page options.
     *
     * @param array $patron Patron
     *
     * @return array
     */
    protected function getPageOptions(array $patron): array
    {
        // Get paging setup:
        $pageSize = $this->config['Catalog']['holds_page_size'] ?? 50;
        return $this->getPaginationHelper()->getOptions(
            (int)$this->getQueryParam('page', 1),
            null,
            $pageSize,
            $this->ilsConnection->checkFunction('getMyHolds', $patron)
        );
    }
}
