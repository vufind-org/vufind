<?php

/**
 * Audit event database service factory
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\Session\SessionManager;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\Net\UserIpReader;

/**
 * Audit event database service factory
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AuditEventServiceFactory extends AbstractDbServiceFactory
{
    use ExplodeSettingTrait;

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
        $config = $container->get(\VuFind\Config\PluginManager::class)->get('config')->toArray();
        $enabledEventTypes = $this->explodeListSetting($config['Logging']['log_audit_events'] ?? 'payment');
        $sessionId = null;
        $clientIp = null;
        $serverIp = null;
        $serverName = null;
        $requestUri = null;
        if ('cli' !== PHP_SAPI) {
            $sessionId = $container->get(SessionManager::class)->getId();
            $clientIp = $container->get(UserIpReader::class)->getUserIp();
            $serverParams = $container->get('Request')->getServer();
            $serverIp = $serverParams->get('SERVER_ADDR');
            $serverName = $serverParams->get('SERVER_NAME');
            $requestUri = $serverParams->get('REQUEST_URI');
        }
        return parent::__invoke(
            $container,
            $requestedName,
            [
                $enabledEventTypes,
                $sessionId,
                $clientIp,
                $serverIp,
                $serverName,
                $requestUri,
            ]
        );
    }
}
