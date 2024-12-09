<?php

/**
 * Factory for instantiating Mailer objects
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Mailer;

use Laminas\Mail\Transport\InMemory;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for instantiating Mailer objects
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory implements FactoryInterface
{
    /**
     * Build the mail transport object.
     *
     * @param \Laminas\Config\Config $config Configuration
     *
     * @return InMemory|Smtp
     */
    protected function getTransport($config)
    {
        // In test mode? Return fake object:
        if (isset($config->Mail->testOnly) && $config->Mail->testOnly) {
            return new InMemory();
        }

        // Create mail transport:
        $settings = [
            'host' => $config->Mail->host, 'port' => $config->Mail->port,
        ];
        if (isset($config->Mail->name)) {
            $settings['name'] = $config->Mail->name;
        }
        if (isset($config->Mail->username) && isset($config->Mail->password)) {
            $settings['connection_class'] = 'login';
            $settings['connection_config'] = [
                'username' => $config->Mail->username,
                'password' => $config->Mail->password,
            ];
            // Set user defined secure connection if provided; otherwise set default
            // secure connection based on configured port number.
            if (isset($config->Mail->secure)) {
                $settings['connection_config']['ssl'] = $config->Mail->secure;
            } elseif ($settings['port'] == '587') {
                $settings['connection_config']['ssl'] = 'tls';
            } elseif ($settings['port'] == '487') {
                $settings['connection_config']['ssl'] = 'ssl';
            }
        }
        if (isset($config->Mail->connection_time_limit)) {
            $settings['connection_time_limit']
                = $config->Mail->connection_time_limit;
        }
        return new Smtp(new SmtpOptions($settings));
    }

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
            throw new \Exception('Unexpected options passed to factory.');
        }

        // Load configurations:
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        // Create service:
        $class = new $requestedName(
            $this->getTransport($config),
            $config->Mail->message_log
        );
        if (!empty($config->Mail->override_from)) {
            $class->setFromAddressOverride($config->Mail->override_from);
        }
        return $class;
    }
}
