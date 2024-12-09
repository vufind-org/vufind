<?php

/**
 * Factory for local database-driven URL shortener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\UrlShortener;

use Exception;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\ShortlinksServiceInterface;

/**
 * Factory for local database-driven URL shortener.
 *
 * @category VuFind
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DatabaseFactory
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new Exception('Unexpected options passed to factory.');
        }
        $router = $container->get('HttpRouter');
        $serverUrl = $container->get('ViewRenderer')->plugin('serverurl');
        $baseUrl = $serverUrl($router->assemble([], ['name' => 'home']));
        $service = $container->get(\VuFind\Db\Service\PluginManager::class)
            ->get(ShortlinksServiceInterface::class);
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $salt = $config->Security->HMACkey ?? '';
        if (empty($salt)) {
            throw new Exception('HMACkey missing from configuration.');
        }
        $hashType = $config->Mail->url_shortener_key_type ?? 'md5';
        return new $requestedName(rtrim($baseUrl, '/'), $service, $salt, $hashType);
    }
}
