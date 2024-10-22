<?php

/**
 * Permission provider plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */

namespace VuFind\Role\PermissionProvider;

/**
 * Permission provider plugin manager
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'insecureCookie' => InsecureCookie::class,
        'ipRange' => IpRange::class,
        'ipRegEx' => IpRegEx::class,
        'role' => Role::class,
        'serverParam' => ServerParam::class,
        'sessionKey' => SessionKey::class,
        'shibboleth' => Shibboleth::class,
        'user' => User::class,
        'username' => Username::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        InsecureCookie::class => InsecureCookieFactory::class,
        IpRange::class => IpRangeFactory::class,
        IpRegEx::class => IpRegExFactory::class,
        Role::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        ServerParam::class => InjectRequestFactory::class,
        SessionKey::class => SessionKeyFactory::class,
        Shibboleth::class => ShibbolethFactory::class,
        User::class => InjectAuthorizationServiceFactory::class,
        Username::class => InjectAuthorizationServiceFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return PermissionProviderInterface::class;
    }
}
