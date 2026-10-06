<?php

/**
 * "Get database credentials" upgrade action.
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
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Cookie\CookieManager;
use VuFind\Db\ConnectionFactory;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Get database credentials" upgrade action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetDbCredentialsAction extends AbstractUpgradeAction
{
    /**
     * Constructor.
     *
     * @param PathResolver             $pathResolver      Path resolver
     * @param ConfigManagerInterface   $configManager     Config manager
     * @param UserServiceInterface     $userService       User database service
     * @param UserCardServiceInterface $userCardService   User card database service
     * @param array                    $config            VuFind configuration
     * @param CookieManager            $cookieManager     Cookie manager
     * @param SessionManager           $sessionManager    Session manager
     * @param EntityManager            $entityManager     Database entity manager
     * @param ConnectionFactory        $connectionFactory Database connection factory
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
        $print = $this->getPostParam('printsql');
        if ($print === 'Skip') {
            return $this->getHelper(ForwardHelper::class)->forwardTo(
                $request->withQueryParams(['logsql' => '1']),
                $response,
                'upgrade/fixdatabase'
            );
        }

        $dbrootuser = $this->getPostParam('dbrootuser', 'root');

        // Process form submission:
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $pass = $this->getPostParam('dbrootpass', '');

            // Test the connection:
            try {
                // Query a table known to exist
                $db = $this->connectionFactory->getConnection($dbrootuser, $pass);
                $db->executeQuery('SELECT * FROM user;');
                $this->session->dbRootUser = $dbrootuser;
                $this->session->dbRootPass = $pass;
                return $this->getHelper(ForwardHelper::class)
                    ->forwardTo($request, $response, 'upgrade/fixdatabase');
            } catch (Exception $e) {
                $this->getHelper(FlashMessagesHelper::class)
                    ->addErrorMessage('Could not connect; please try again. Error message: ' . $e->getMessage());
            }
        }

        return $this->renderTemplate($request, $response, compact('dbrootuser'));
    }
}
