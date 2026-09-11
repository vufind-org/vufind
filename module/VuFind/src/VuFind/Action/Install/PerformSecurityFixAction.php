<?php

/**
 * Install "perform security fix" action.
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
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Crypt\PasswordHasher;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindHttp\HttpService;
use VuFindSearch\Service as SearchService;

use function count;

/**
 * Install "perform security fix" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PerformSecurityFixAction extends AbstractInstallAction
{
    /**
     * Constructor.
     *
     * @param CacheManager             $cacheManager     Cache manager
     * @param Connection               $ilsConnection    ILS connection
     * @param SearchService            $searchService    Search service
     * @param PathResolver             $pathResolver     Path resolver
     * @param ConfigManagerInterface   $configManager    Config manager
     * @param ServerUrlHelper          $serverUrlHelper  Server URL helper
     * @param HttpService              $httpService      HTTP service
     * @param TagServiceInterface      $tagService       Tags database service
     * @param UserServiceInterface     $userService      User database service
     * @param UserCardServiceInterface $userCardService  User card database service
     * @param array                    $config           VuFind configuration
     * @param PasswordHasher           $passwordHasher   Password hasher
     * @param ILSAuthenticator         $ilsAuthenticator ILS authenticator
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
        protected PasswordHasher $passwordHasher,
        protected ILSAuthenticator $ilsAuthenticator,
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
     * Perform security fixes.
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
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);

        // This can take a while -- don't time out!
        set_time_limit(0);

        // First, set encryption/hashing to true, and set the key
        if ($fixedConfig = $this->getFixedSecurityConfiguration($this->config)) {
            try {
                $this->changeConfig('config', $fixedConfig);
            } catch (\Exception $e) {
                // Problem writing? Show the user an error:
                return $redirectHelper->redirectToRoute($response, 'install-fixbasicconfig');
            }

            // Success? Redirect to this action in order to reload the configuration:
            return $redirectHelper->redirectToRoute($response, 'install-performsecurityfix');
        }

        // Now we want to loop through the database and update passwords (if
        // necessary).
        $userRows = $this->userService->getInsecureRows();
        if (count($userRows) > 0) {
            foreach ($userRows as $row) {
                if ($row->getRawPassword() != '') {
                    $row->setPasswordHash($this->passwordHasher->create($row->getRawPassword()));
                    $row->setRawPassword('');
                }
                if ($rawPassword = $row->getRawCatPassword()) {
                    $this->ilsAuthenticator->saveUserCatalogCredentials($row, $row->getCatUsername(), $rawPassword);
                } else {
                    $this->userService->persistEntity($row);
                }
            }
            $msg = count($userRows) . ' user row(s) encrypted.';
            $flashMessagesHelper->addInfoMessage($msg);
        }
        $cardRows = $this->userCardService->getInsecureRows();
        if (count($cardRows) > 0) {
            foreach ($cardRows as $row) {
                $row->setCatPassEnc($this->ilsAuthenticator->encrypt($row->getRawCatPassword()));
                $row->setRawCatPassword(null);
                $this->userCardService->persistEntity($row);
            }
            $msg = count($cardRows) . ' user_card row(s) encrypted.';
            $flashMessagesHelper->addInfoMessage($msg);
        }
        return $redirectHelper->redirectToRoute($response, 'install-home');
    }
}
