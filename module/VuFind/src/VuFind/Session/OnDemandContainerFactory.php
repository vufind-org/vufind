<?php
/**
 * Factory for on-demand session containers. This allows us to create a session
 * container but not start up the session unless it is actually accessed.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Session
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:session_handlers Wiki
 */
namespace VuFind\Session;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for on-demand session containers. This allows us to create a session
 * container but not start up the session unless it is actually accessed.
 *
 * @category VuFind2
 * @package  Session
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:session_handlers Wiki
 */
class OnDemandContainerFactory
{
    /**
     * Service manager
     *
     * @var ServiceManager
     */
    protected $sm;

    /**
     * Constructor
     *
     * @param ServiceManager $sm Service manager
     */
    public function __construct(ServiceManager $sm)
    {
        $this->sm = $sm;
    }

    /**
     * Get a specific named container.
     *
     * @param string $namespace Namespace for container.
     *
     * @return \Zend\Session\Container
     */
    public function get($namespace)
    {
        $sm = $this->sm;
        $callback = function (& $wrapped, $proxy) use ($namespace, $sm) {
            // Generate wrapped object:
            $manager = $sm->get('VuFind\SessionManager');
            $wrapped = new \Zend\Session\Container($namespace, $manager);

            // Indicate that initialization is complete to avoid reinitialization:
            $proxy->setProxyInitializer(null);
        };
        $cfg = $sm->get('VuFind\ProxyConfig');
        $factory = new \ProxyManager\Factory\LazyLoadingValueHolderFactory($cfg);
        return $factory->createProxy('Zend\Session\Container', $callback);
    }
}
