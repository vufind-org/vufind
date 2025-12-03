<?php

/**
 * Factory for instantiating Mailer objects
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Mailer;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\Feature\SecretTrait;

/**
 * Factory for instantiating Mailer objects
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory implements FactoryInterface
{
    use SecretTrait;

    /**
     * Return DSN from the configuration
     *
     * @param array $config Configuration
     *
     * @return string
     */
    protected function getDSN(array $config): string
    {
        // In test mode? Use null transport:
        if ($config['Mail']['testOnly'] ?? false) {
            return 'null://null';
        }

        if ($dsn = $config['Mail']['dsn'] ?? null) {
            return $dsn;
        }

        // Create DSN from settings:
        $protocol = ($config['Mail']['secure'] ?? false) ? 'smtps' : 'smtp';
        $dsn = "$protocol://";
        if (
            ('' !== ($username = rawurlencode($config['Mail']['username']) ?? ''))
            && ('' !== ($password = rawurlencode($this->getSecretFromConfig($config['Mail'], 'password') ?? '')))
        ) {
            $dsn .= "$username:$password@";
        }
        $dsn .= $config['Mail']['host'];
        if ($port = $config['Mail']['port'] ?? null) {
            $dsn .= ":$port";
        }

        $dsnParams = [];
        if ($name = $config['Mail']['name'] ?? null) {
            $dsnParams['local_domain'] = $name;
        }
        if (null !== ($limit = $config['Mail']['connection_time_limit'] ?? null)) {
            $dsnParams['ping_threshold'] = $limit;
        }
        if ($dsnParams) {
            $dsn .= '?' . http_build_query($dsnParams);
        }

        return $dsn;
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
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        // Load configurations:
        $config = $container->get(\VuFind\Config\ConfigManagerInterface::class)->getConfigArray('config');

        // Create service:
        $class = new $requestedName(
            new \Symfony\Component\Mailer\Mailer(
                \Symfony\Component\Mailer\Transport::fromDsn($this->getDSN($config))
            ),
            [
                'message_log' => $config['Mail']['message_log'] ?? null,
                'message_log_format' => $config['Mail']['message_log_format'] ?? null,
            ]
        );
        if ($fromOverride = $config['Mail']['override_from'] ?? null) {
            $class->setFromAddressOverride($fromOverride);
        }
        return $class;
    }
}
