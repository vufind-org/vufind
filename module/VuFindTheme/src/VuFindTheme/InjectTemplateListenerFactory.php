<?php

/**
 * Factory for InjectTemplateListener
 *
 * PHP version 8
 *
 * Copyright (C) 2019 Leipzig University Library
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @category VuFind
 * @package  Theme
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for InjectTemplateListener
 *
 * @category VuFind
 * @package  Theme
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org Main Site
 */
class InjectTemplateListenerFactory implements FactoryInterface
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
        $config = $container->get('config');
        $prefixes = $config['vufind']['extra_theme_prefixes'] ?? [];
        $exclude = $config['vufind']['excluded_theme_prefixes'] ?? [];

        // we assume that - by default - modules try loading templates from
        // their own namespace, thus all loaded modules are included for inflection
        $modules = array_map(
            function ($module) {
                return str_replace('\\', '/', $module) . '/';
            },
            $container->get('ModuleManager')->getModules()
        );
        $prefixes = array_filter(
            array_merge($prefixes, $modules),
            function ($prefix) use ($exclude) {
                foreach ($exclude as $current) {
                    if (str_starts_with($prefix, $current)) {
                        return false;
                    }
                }
                return true;
            }
        );

        return new $requestedName(array_unique($prefixes));
    }
}
