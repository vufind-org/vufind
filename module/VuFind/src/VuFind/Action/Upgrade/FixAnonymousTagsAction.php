<?php

/**
 * "Fix anonymous tags" upgrade action.
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
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Fix anonymous tags" upgrade action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixAnonymousTagsAction extends AbstractUpgradeAction
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
     * @param EntityManager                $entityManager       Entity manager
     * @param ResourceTagsServiceInterface $resourceTagsService Resource tags database service
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
        #[Autowire(container: DbServicePluginManager::class)]
        protected ResourceTagsServiceInterface $resourceTagsService,
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
     * Fix anonymous tags.
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
        // Handle skip action:
        if ($this->getPostParam('skip')) {
            $this->cookie->skipAnonymousTags = true;
            return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'upgrade/fixdatabase');
        }

        // Handle submit action:
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $username = $this->getPostParam('username');
            if (!$username) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('Username must not be empty.');
            } else {
                $user = $this->userService->getUserByUsername($username);
                if (!$user) {
                    $this->getHelper(FlashMessagesHelper::class)->addErrorMessage("User $username not found.");
                } else {
                    $this->resourceTagsService->assignAnonymousTags($user);
                    $this->session->warnings->append("Assigned all anonymous tags to {$user->getUsername()}.");
                    return $this->getHelper(ForwardHelper::class)
                        ->forwardTo($request, $response, 'upgrade/fixdatabase');
                }
            }
        }

        return $this->renderTemplate($request, $response, ['anonymousTags' => $this->getQueryParam('anonymousCnt')]);
    }
}
