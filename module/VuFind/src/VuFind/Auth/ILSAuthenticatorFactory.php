<?php
/**
 * ILS Authenticator factory.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Auth;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * ILS Authenticator factory.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ILSAuthenticatorFactory implements FactoryInterface
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
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        // Construct the ILS authenticator as a lazy loading value holder so that
        // the object is not instantiated until it is called. This helps break a
        // potential circular dependency with the MultiBackend driver as well as
        // saving on initialization costs in cases where the authenticator is not
        // actually utilized.
        $callback = function (& $wrapped, $proxy) use ($container, $requestedName) {
            // Generate wrapped object:
            $auth = $container->get(\VuFind\Auth\Manager::class);
            $catalog = $container->get(\VuFind\ILS\Connection::class);
            $emailAuth = $container->get(\VuFind\Auth\EmailAuthenticator::class);
            $wrapped = new $requestedName($auth, $catalog, $emailAuth);

            // Indicate that initialization is complete to avoid reinitialization:
            $proxy->setProxyInitializer(null);
        };
        $cfg = $container->get(\ProxyManager\Configuration::class);
        $factory = new \ProxyManager\Factory\LazyLoadingValueHolderFactory($cfg);
        return $factory->createProxy($requestedName, $callback);
    }
}
