<?php

/**
 * Secure session delegator factory
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
 *
 * PHP version 8
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Container\ContainerInterface;

use function call_user_func;

/**
 * Secure session delegator factory
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class SecureDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Invokes this factory.
     *
     * @param ContainerInterface $container Service container
     * @param string             $name      Service name
     * @param callable           $callback  Service callback
     * @param array|null         $options   Service options
     *
     * @return SecureDelegator
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ): HandlerInterface {
        /**
         * The wrapped session handler.
         *
         * @var HandlerInterface $handler
         */
        $handler = call_user_func($callback);
        $config = $container->get(\VuFind\Config\PluginManager::class);
        $secure = $config->get('config')->Session->secure ?? false;
        return $secure ? $this->delegate($container, $handler) : $handler;
    }

    /**
     * Creates the delegating session handler
     *
     * @param ContainerInterface $container Service Container
     * @param HandlerInterface   $handler   Wrapped session handler
     *
     * @return HandlerInterface
     */
    protected function delegate(
        ContainerInterface $container,
        HandlerInterface $handler
    ): HandlerInterface {
        $cookieManager = $container->get(\VuFind\Cookie\CookieManager::class);
        return new SecureDelegator($cookieManager, $handler);
    }
}
