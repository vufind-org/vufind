<?php

/**
 * Factory for Login token authentication
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Auth;

use BrowscapPHP\Browscap;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Log\PsrLoggerAdapter;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\LoginTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Factory for login token authentication
 *
 * @category VuFind
 * @package  Authentication
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LoginTokenManagerFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Service manager
     *
     * @var ContainerInterface
     */
    protected $container;

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
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $this->container = $container;

        $dbServiceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        return new $requestedName(
            $container->get(\VuFind\Config\PluginManager::class)->get('config'),
            $dbServiceManager->get(UserServiceInterface::class),
            $dbServiceManager->get(LoginTokenServiceInterface::class),
            $container->get(\VuFind\Cookie\CookieManager::class),
            $container->get(\Laminas\Session\SessionManager::class),
            $container->get(\VuFind\Mailer\Mailer::class),
            $container->get('ViewRenderer'),
            [$this, 'getBrowscap']
        );
    }

    /**
     * Create a Browscap instance
     *
     * @return Browscap
     */
    public function getBrowscap(): Browscap
    {
        $cache = new SimpleCacheDecorator($this->container->get(\VuFind\Cache\Manager::class)->getCache('browscap'));
        $logger = new PsrLoggerAdapter($this->container->get(\VuFind\Log\Logger::class));
        return new Browscap($cache, $logger);
    }
}
