<?php

/**
 * Factory for OAI harvest command.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Harvest;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function strlen;

/**
 * Factory for OAI harvest command.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HarvestOaiCommandFactory implements FactoryInterface
{
    /**
     * Get the base directory for harvesting OAI-PMH data.
     *
     * @return string
     */
    protected function getHarvestRoot()
    {
        // Get the base VuFind path:
        $home = strlen(LOCAL_OVERRIDE_DIR) > 0
            ? LOCAL_OVERRIDE_DIR
            : realpath(APPLICATION_PATH . '/..');

        // Build the full harvest path:
        $dir = $home . '/harvest/';

        // Create the directory if it does not already exist:
        if (!is_dir($dir) && !mkdir($dir)) {
            throw new \Exception("Problem creating directory {$dir}.");
        }

        return $dir;
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
        return new $requestedName(
            $container->get(\VuFindHttp\HttpService::class)->createClient(),
            $this->getHarvestRoot(),
            null,
            false,
            null,
            $container->get(\VuFind\Config\PathResolver::class),
            ...($options ?? [])
        );
    }
}
