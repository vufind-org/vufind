<?php

/**
 * "Get source version" upgrade action.
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

use Composer\Semver\Comparator;
use Doctrine\ORM\EntityManager;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Config\Version;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Migration\MigrationManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function in_array;

/**
 * "Get source version" upgrade action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetSourceVersionAction extends AbstractUpgradeAction
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
        protected MigrationManager $migrationManager,
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
     * Prompt the user for database credentials.
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
        // Process form submission:
        $version = $this->getPostParam('sourceversion');
        if ($version) {
            $this->cookie->newVersion = $newVersion = Version::getBuildVersion();
            if (Comparator::lessThan($version, '10.0')) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage(
                    'Illegal version number; please upgrade to at least version 10.x before proceeding.'
                );
            } elseif (Comparator::greaterThan($version, $newVersion)) {
                $this->getHelper(FlashMessagesHelper::class)
                    ->addErrorMessage("Source version must be less than or equal to {$newVersion}.");
            } else {
                $this->cookie->oldVersion = $version;
                // Clear out request to avoid infinite loop:
                $request = $request->withParsedBody(['sourceversion' => ''] + $request->getParsedBody());
                $this->processSkipParam();
                return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'upgrade/home');
            }
        }

        $oldVersion = $this->migrationManager->determineOldVersion();
        return $this->renderTemplate($request, $response, compact('oldVersion'));
    }

    /**
     * Make sure we only skip the actions the user wants us to.
     *
     * @return void
     */
    protected function processSkipParam(): void
    {
        $skip = (array)($this->getPostParam('skip', []));
        foreach (['config', 'database', 'metadata'] as $action) {
            $this->cookie->{$action . 'Okay'} = in_array($action, $skip);
        }
    }
}
