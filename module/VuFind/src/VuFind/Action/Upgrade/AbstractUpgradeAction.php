<?php

/**
 * Abstract base class for upgrade actions.
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

use ArrayObject;
use Doctrine\ORM\EntityManager;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\SessionManager;
use VuFind\Action\Install\AbstractInstallOrUpgradeAction;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Cookie\Container as CookieContainer;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for upgrade actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractUpgradeAction extends AbstractInstallOrUpgradeAction
{
    /**
     * Cookie container.
     *
     * @var CookieContainer
     */
    protected CookieContainer $cookie;

    /**
     * Session container.
     *
     * @var SessionContainer
     */
    protected SessionContainer $session;

    /**
     * Constructor.
     *
     * @param PathResolver             $pathResolver    Path resolver
     * @param ConfigManagerInterface   $configManager   Config manager
     * @param UserServiceInterface     $userService     User database service
     * @param UserCardServiceInterface $userCardService User card database service
     * @param array                    $config          VuFind configuration
     * @param CookieManager            $cookieManager   Cookie manager
     * @param SessionManager           $sessionManager  Session manager
     * @param EntityManager            $entityManager   Entity manager
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
        protected EntityManager $entityManager,
    ) {
        parent::__construct(
            $pathResolver,
            $configManager,
            $userService,
            $userCardService,
            $config
        );

        // We want to use cookies for tracking the state of the upgrade, since the
        // session is unreliable -- if the user upgrades a configuration that uses
        // a different session handler than the default one, we'll lose track of our
        // upgrade state in the middle of the process!
        $this->cookie = new CookieContainer('vfup', $cookieManager);

        // ...however, once the configuration piece of the upgrade is done, we can
        // safely use the session for storing some values. We'll use this for the
        // temporary storage of root database credentials, since it is unwise to
        // send such sensitive values around as cookies!
        $this->session = new SessionContainer('upgrade', $sessionManager);

        // We should also use the session for storing warnings once we know it will be stable; this will prevent the
        // cookies from getting too big.
        if (!isset($this->session->warnings)) {
            $this->session->warnings = new ArrayObject();
        }
    }

    /**
     * Clear Doctrine's metadata cache to ensure the schema information is up to date.
     *
     * @return void
     */
    protected function clearDoctrineMetadataCache(): void
    {
        $this->entityManager->getConfiguration()->getMetadataCache()?->clear();
    }
}
