<?php

/**
 * "Critical fix blowfish" upgrade action.
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
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Cookie\CookieManager;
use VuFind\Crypt\BlockCipher;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Critical fix blowfish" upgrade action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CriticalFixBlowfishAction extends AbstractUpgradeAction
{
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
     * @param BlockCipher              $blockCipher     Block cipher
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
        protected BlockCipher $blockCipher,
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
     * Lead user through the steps required to replace blowfish quickly and easily.
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
        // Test that blowfish is still working
        $blowfishIsWorking = true;
        try {
            $newcipher = $this->blockCipher->setAlgorithm('blowfish');
            $newcipher->setKey('akeyforatest');
            $newcipher->encrypt('youfoundtheeasteregg!');
        } catch (Exception $e) {
            $blowfishIsWorking = false;
        }

        // Get new settings
        [$newAlgorithm, $exampleKey] = $this->getSecureAlgorithmAndKey();
        return $this->renderTemplate($request, $response, compact('newAlgorithm', 'exampleKey', 'blowfishIsWorking'));
    }
}
