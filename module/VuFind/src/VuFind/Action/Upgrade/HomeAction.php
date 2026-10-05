<?php

/**
 * Upgrade home action.
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
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Migration\MigrationManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function dirname;

/**
 * Upgrade home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HomeAction extends AbstractUpgradeAction
{
    /**
     * Constructor.
     *
     * @param PathResolver             $pathResolver     Path resolver
     * @param ConfigManagerInterface   $configManager    Config manager
     * @param UserServiceInterface     $userService      User database service
     * @param UserCardServiceInterface $userCardService  User card database service
     * @param array                    $config           VuFind configuration
     * @param CookieManager            $cookieManager    Cookie manager
     * @param SessionManager           $sessionManager   Session manager
     * @param EntityManager            $entityManager    Database entity manager
     * @param MigrationManager         $migrationManager Database migration manager
     * @param CacheManager             $cacheManager     Cache manager
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
        protected CacheManager $cacheManager,
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
     * Display summary of installation status.
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
        // If the cache is messed up, nothing is going to work right -- check that first:
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        if ($this->cacheManager->hasDirectoryCreationError()) {
            return $redirectHelper->redirectToRoute($response, 'install-fixcache');
        }

        // First find out which version we are upgrading:
        $forwardHelper = $this->getHelper(ForwardHelper::class);
        if (!isset($this->cookie->oldVersion) || !isset($this->cookie->newVersion)) {
            return $forwardHelper->forwardTo($request, $response, 'upgrade/getsourceversion');
        }

        // Check for critical upgrades:
        $criticalFixForward = $this->performCriticalChecks() ?? null;
        if ($criticalFixForward !== null) {
            return $forwardHelper->forwardTo($request, $response, $criticalFixForward);
        }

        // Now make sure we have a configuration file ready:
        if (empty($this->cookie->configOkay)) {
            return $redirectHelper->redirectToRoute($response, 'upgrade-fixconfig');
        }

        // Now make sure the database is up to date:
        if (empty($this->cookie->databaseOkay)) {
            return $redirectHelper->redirectToRoute($response, 'upgrade-fixdatabase');
        }

        // Check for missing metadata in the resource table; note that we do a redirect rather than a forward here so
        // that a submit button clicked in the database action doesn't cause the metadata action to also submit!
        if (empty($this->cookie->metadataOkay)) {
            return $redirectHelper->redirectToRoute($response, 'upgrade-fixmetadata');
        }

        // We're finally done -- display any warnings that we collected during the process:
        $allWarnings = array_merge($this->cookie->warnings ?? [], (array)$this->session->warnings);
        foreach ($allWarnings as $warning) {
            $this->getHelper(FlashMessagesHelper::class)->addInfoMessage($warning);
        }

        return $this->renderTemplate(
            $request,
            $response,
            ['configDir' => dirname($this->getForcedLocalConfigPath('config'))]
        );
    }

    /**
     * Organize and run critical, blocking checks.
     *
     * @return ?string Action to redirect to for a fix, or null if everything is okay
     */
    protected function performCriticalChecks(): ?string
    {
        // Run through a series of checks to be sure there are no critical issues:
        return $this->criticalCheckForInsecureDatabase()
            ?? $this->criticalCheckForBlowfishEncryption();
    }

    /**
     * Check for insecure database settings.
     *
     * @return ?string Action to redirect to for a fix, or null if everything is okay
     */
    protected function criticalCheckForInsecureDatabase(): ?string
    {
        if (!empty($this->cookie->ignoreInsecureDb)) {
            return null;
        }
        return $this->hasSecureDatabase() ? null : 'upgrade/criticalfixinsecuredatabase';
    }

    /**
     * Check for deprecated and insecure use of blowfish encryption.
     *
     * @return ?string Action to redirect to for a fix, or null if everything is okay
     */
    protected function criticalCheckForBlowfishEncryption(): ?string
    {
        $encryptionEnabled = $this->config['Authentication']['encrypt_ils_password'] ?? false;
        $algo = $this->config['Authentication']['ils_encryption_algo'] ?? 'blowfish';
        return ($encryptionEnabled && $algo === 'blowfish') ? 'upgrade/criticalfixblowfish' : null;
    }
}
