<?php

/**
 * "Fix database" upgrade action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016-2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
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
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action\Upgrade;

use Doctrine\ORM\EntityManager;
use Exception;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Cookie\CookieManager;
use VuFind\Crypt\Base62;
use VuFind\Db\Connection as DbConnection;
use VuFind\Db\ConnectionFactory;
use VuFind\Db\Migration\MigrationManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Db\Service\ShortlinksServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;

use function count;

/**
 * "Fix database" upgrade action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixDatabaseAction extends AbstractUpgradeAction
{
    /**
     * Constructor.
     *
     * @param PathResolver                 $pathResolver        Path resolver
     * @param ConfigManagerInterface       $configManager       Config manager
     * @param UserServiceInterface         $userService         User database service
     * @param UserCardServiceInterface     $userCardService     User card database service
     * @param array                        $config              VuFind configuration
     * @param CookieManager                $cookieManager       Cookie manager
     * @param SessionManager               $sessionManager      Session manager
     * @param EntityManager                $entityManager       Database entity manager
     * @param MigrationManager             $migrationManager    Database migration manager
     * @param ResourceServiceInterface     $resourceService     Resource database service
     * @param ResourceTagsServiceInterface $resourceTagsService Resource tags database service
     * @param TagsService                  $tagsService         Tags service
     * @param ShortlinksServiceInterface   $shortlinksService   Short links database service
     * @param ResultsManager               $resultsManager      Search results plugin manager
     * @param SearchServiceInterface       $searchDbService     Search database service
     * @param ConnectionFactory            $connectionFactory   Database connection factory
     */
    public function __construct(
        PathResolver $pathResolver,
        ConfigManagerInterface $configManager,
        #[Autowire(container: DbServicePluginManager::class)]
        UserServiceInterface $userService,
        #[Autowire(container: DbServicePluginManager::class)]
        UserCardServiceInterface $userCardService,
        #[Autowire(config: 'config')]
        array $config,
        CookieManager $cookieManager,
        SessionManager $sessionManager,
        #[Autowire(service: 'doctrine.entitymanager.orm_vufind')]
        EntityManager $entityManager,
        protected MigrationManager $migrationManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ResourceServiceInterface $resourceService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ResourceTagsServiceInterface $resourceTagsService,
        protected TagsService $tagsService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ShortlinksServiceInterface $shortlinksService,
        protected ResultsManager $resultsManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected SearchServiceInterface $searchDbService,
        protected ConnectionFactory $connectionFactory,
    ) {
        parent::__construct(
            $pathResolver,
            $configManager,
            $userService,
            $userCardService,
            $config,
            $cookieManager,
            $sessionManager,
            $entityManager
        );
    }

    /**
     * Upgrade the database.
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
        try {
            // If we haven't already tried it, attempt a structure update:
            if (!isset($this->session->sql)) {
                if ($result = $this->applyDatabaseMigrations()) {
                    return $result;
                }
            }

            // If we have SQL to show, stop at this point to allow the changes to be made before progressing any
            // further:
            if (!empty($this->session->sql)) {
                return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'upgrade/showsql');
            }

            // Now that database structure is addressed, we can fix database content -- the checks below should be
            // platform-independent.

            // Check for legacy tag bugs:
            $anonymousTags = $this->resourceTagsService->getAnonymousCount();
            if ($anonymousTags > 0 && !isset($this->cookie->skipAnonymousTags)) {
                return $this->getHelper(RedirectHelper::class)->redirectToRoute(
                    $response,
                    'upgrade-fixanonymoustags',
                    queryParams: ['anonymousCnt' => $anonymousTags]
                );
            }
            $dupeTags = $this->tagsService->getDuplicateTags();
            if (count($dupeTags) > 0 && !isset($this->cookie->skipDupeTags)) {
                return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'upgrade-fixduplicatetags');
            }

            // fix shortlinks
            $this->fixShortlinks();

            // Clean up the "VuFind" source, if necessary.
            $this->fixVuFindSourceInDatabase();
        } catch (Exception $e) {
            $this->getHelper(FlashMessagesHelper::class)
                ->addErrorMessage('Database upgrade failed: ' . (string)$e);
            return $this->renderTemplate($request, $response, template: 'upgrade/error');
        }

        // Add checksums to all saved searches but catch exceptions (e.g. in case column checksum does not exist yet
        // because of sqllog).
        try {
            $this->fixSearchChecksumsInDatabase();
        } catch (Exception $e) {
            $this->session->warnings->append(
                'Could not fix checksums in table search - maybe column ' .
                'checksum is missing? Exception thrown with ' .
                'message: ' . $e->getMessage()
            );
        }

        $this->cookie->databaseOkay = true;
        return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'upgrade-home');
    }

    /**
     * Apply migrations to the database.
     *
     * @return ?ResponseInterface Null if successful, or a response if user input is required
     */
    protected function applyDatabaseMigrations(): ?ResponseInterface
    {
        $this->clearDoctrineMetadataCache();
        $migrations = $this->migrationManager->getMigrations($this->cookie->oldVersion);
        $failedMigrations = $this->migrationManager->getFailedMigrations();
        if ($failedMigrations) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage(
                'Failed migration(s) detected: ' . implode(' ', $failedMigrations)
                . ' -- see migrations table in database for details; manual intervention may be needed.'
            );
        }
        if ($migrations && !$this->getQueryParam('logsql')) {
            if (!$this->hasDatabaseRootCredentials()) {
                return $this->getHelper(ForwardHelper::class)
                    ->forwardTo($this->request, $this->response, 'upgrade/getdbcredentials');
            }
            $this->migrationManager->applyMigrations($migrations, $this->getRootDbConnection());
            // Don't keep DB credentials in session longer than necessary:
            unset($this->session->dbRootUser);
            unset($this->session->dbRootPass);
            $this->session->sql = '';
        } else {
            $this->session->sql = $this->migrationManager->applyMigrations($migrations, null);
        }
        $this->clearDoctrineMetadataCache();
        return null;
    }

    /**
     * Generate base62 encoding to migrate old shortlinks.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function fixShortlinks(): void
    {
        $base62 = new Base62();

        try {
            $results = $this->shortlinksService->getShortLinksWithMissingHashes();

            foreach ($results as $result) {
                $result->setHash($base62->encode($result->getId()));
                $this->shortlinksService->persistEntity($result);
            }

            if (count($results) > 0) {
                $this->session->warnings->append('Added hash value(s) to ' . count($results) . ' short links.');
            }
        } catch (Exception $e) {
            $this->session->warnings->append(
                'Could not fix hashes in table shortlinks - maybe column hash is missing? Exception thrown with ' .
                'message: ' . (string)$e
            );
        }
    }

    /**
     * Clean up legacy 'VuFind' source values in the database.
     *
     * @return void
     */
    protected function fixVuFindSourceInDatabase(): void
    {
        if ($count = $this->resourceService->renameSource('VuFind', 'Solr')) {
            $this->session->warnings
                ->append('Converted ' . $count . ' legacy "VuFind" source value(s) in resource table');
        }
    }

    /**
     * Add checksums to search table rows.
     *
     * @return void
     */
    protected function fixSearchChecksumsInDatabase(): void
    {
        $searchRows = $this->searchDbService->getSavedSearchesWithMissingChecksums();
        if (count($searchRows) > 0) {
            foreach ($searchRows as $searchRow) {
                $searchObj = $searchRow->getSearchObject()?->deminify($this->resultsManager);
                if (!$searchObj) {
                    throw new Exception("Missing search data for row {$searchRow->getId()}.");
                }
                $url = $searchObj->getUrlQuery()->getParams();
                $checksum = crc32($url) & 0xFFFFFFF;
                $searchRow->setChecksum($checksum);
                $this->searchDbService->persistEntity($searchRow);
            }
            $this->session->warnings->append('Added checksum to ' . count($searchRows) . ' rows in search table');
        }
    }

    /**
     * Do we have root DB credentials stored?
     *
     * @return bool
     */
    protected function hasDatabaseRootCredentials()
    {
        return isset($this->session->dbRootUser) && isset($this->session->dbRootPass);
    }

    /**
     * Get a database connection for root access using credentials in session.
     *
     * @return DbConnection
     */
    protected function getRootDbConnection(): DbConnection
    {
        // Use static cache to avoid loading connection more than once on subsequent calls.
        static $connection = false;
        if (!$connection) {
            $connection
                = $this->connectionFactory->getConnection($this->session->dbRootUser, $this->session->dbRootPass);
        }
        return $connection;
    }
}
