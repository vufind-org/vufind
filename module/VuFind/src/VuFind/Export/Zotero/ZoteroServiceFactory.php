<?php

/**
 * Zotero service factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Export\Zotero;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Laminas\Translator\TranslatorInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManager;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Http\GuzzleService;

/**
 * Zotero service factory.
 *
 * @category VuFind
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ZoteroServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $sessionContainer = new Container(
            'zotero_oauth',
            $container->get(SessionManager::class)
        );
        $config = $container->get(ConfigManager::class)->getConfigArray('config');
        $clientCredentials = (array)($config['Zotero'] ?? []);
        if (empty($clientCredentials['callback_uri'])) {
            $serverHelper = $container->get('ViewRenderer')->plugin('serverurl');
            $urlHelper = $container->get('ViewRenderer')->plugin('url');
            $clientCredentials['callback_uri'] = $serverHelper($urlHelper('zotero-authcallback'));
        }
        $translator = $container->get(TranslatorInterface::class);
        $serviceName = $translator->translate($config['Site']['title']);
        $zoteroOAuth = new ZoteroOAuth($container->get(GuzzleService::class), $serviceName, $clientCredentials);
        $dbPluginManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        $accessTokenService = $dbPluginManager->get(AccessTokenServiceInterface::class);
        $guzzleService = $container->get(GuzzleService::class);
        $cache = $container->get(\VuFind\Cache\Manager::class)->getCache('object');

        return new $requestedName(
            $sessionContainer,
            $zoteroOAuth,
            $accessTokenService,
            $guzzleService,
            $cache,
            ...$options ?? []
        );
    }
}
