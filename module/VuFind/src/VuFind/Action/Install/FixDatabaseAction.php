<?php

/**
 * Install "fix database" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFind\Action\Install;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Db\DbBuilder;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindHttp\HttpService;
use VuFindSearch\Service as SearchService;

/**
 * Install "fix database" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixDatabaseAction extends AbstractInstallAction
{
    /**
     * Constructor.
     *
     * @param CacheManager             $cacheManager    Cache manager
     * @param Connection               $ilsConnection   ILS connection
     * @param SearchService            $searchService   Search service
     * @param PathResolver             $pathResolver    Path resolver
     * @param ConfigManagerInterface   $configManager   Config manager
     * @param ServerUrlHelper          $serverUrlHelper Server URL helper
     * @param HttpService              $httpService     HTTP service
     * @param TagServiceInterface      $tagService      Tags database service
     * @param UserServiceInterface     $userService     User database service
     * @param UserCardServiceInterface $userCardService User card database service
     * @param array                    $config          VuFind configuration
     * @param DbBuilder                $dbBuilder       Database builder
     */
    public function __construct(
        CacheManager $cacheManager,
        Connection $ilsConnection,
        SearchService $searchService,
        PathResolver $pathResolver,
        ConfigManagerInterface $configManager,
        ServerUrlHelper $serverUrlHelper,
        HttpService $httpService,
        #[Autowire(container: DbServicePluginManager::class)]
        TagServiceInterface $tagService,
        #[Autowire(container: DbServicePluginManager::class)]
        UserServiceInterface $userService,
        #[Autowire(container: DbServicePluginManager::class)]
        UserCardServiceInterface $userCardService,
        #[Autowire(config: 'config')]
        array $config,
        protected DbBuilder $dbBuilder,
    ) {
        parent::__construct(
            $cacheManager,
            $ilsConnection,
            $searchService,
            $pathResolver,
            $configManager,
            $serverUrlHelper,
            $httpService,
            $tagService,
            $userService,
            $userCardService,
            $config
        );
    }

    /**
     * Display repair instructions for database problems or fix them directly.
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
        $dbSettings = [
            'dbname' => $this->getPostParam('dbname', 'vufind'),
            'dbuser' => $this->getPostParam('dbuser', 'vufind'),
            'dbhost' => $this->getPostParam('dbhost', 'localhost'),
            'vufindhost' => $this->getPostParam('vufindhost', 'localhost'),
            'dbrootuser' => $this->getPostParam('dbrootuser', 'root'),
            'driver' => $this->getPostParam('driver', 'mysql'),
        ];

        $skip = $this->getPostParam('printsql') == 'Skip';

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!preg_match('/^\w*$/', $dbSettings['dbname'])) {
            $flashMessagesHelper->addErrorMessage('Database name must be alphanumeric.');
        } elseif (!preg_match('/^\w*$/', $dbSettings['dbuser'])) {
            $flashMessagesHelper->addErrorMessage('Database user must be alphanumeric.');
        } elseif ($skip || $this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $newpass = $this->getPostParam('dbpass');
            $newpassConf = $this->getPostParam('dbpassconfirm');
            if ((empty($newpass) || empty($newpassConf))) {
                $flashMessagesHelper->addErrorMessage('Password fields must not be blank.');
            } elseif ($newpass != $newpassConf) {
                $flashMessagesHelper->addErrorMessage('Password fields must match.');
            } else {
                // Connect to database:
                try {
                    $rootpass = $this->getPostParam('dbrootpass');
                    $omnisql = $this->dbBuilder->build(
                        $dbSettings['dbname'],
                        $dbSettings['dbuser'],
                        $newpass,
                        $dbSettings['driver'],
                        $dbSettings['dbhost'],
                        $dbSettings['vufindhost'],
                        $dbSettings['dbrootuser'],
                        $rootpass,
                        $skip
                    );
                    if ($skip) {
                        return $this->renderTemplate(
                            $request,
                            $response,
                            ['sql' => $omnisql],
                            'install/showsql'
                        );
                    } else {
                        // If we made it this far, we can update the config file and forward back to the home action!
                        $string = $dbSettings['driver'] . '://' . $dbSettings['dbuser'] . ':' . $newpass . '@'
                            . $dbSettings['dbhost'] . '/' . $dbSettings['dbname'];
                        try {
                            $this->changeConfig(
                                'config',
                                ['Database' => ['database' => $string]]
                            );
                        } catch (\Exception $e) {
                            return $this->getHelper(ForwardHelper::class)
                                ->forwardTo($request, $response, 'Install/FixBasicConfig');
                        }
                    }
                    return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'install-home');
                } catch (\Exception $e) {
                    $flashMessagesHelper->addErrorMessage($e->getMessage());
                }
            }
        }
        return $this->renderTemplate($request, $response, $dbSettings);
    }
}
