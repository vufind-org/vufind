<?php

/**
 * FormatBased cover loader factory.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @package  Content
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:cover_loaders Wiki
 */

namespace VuFind\Content\Covers;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Record\Loader as RecordLoader;

use function defined;
use function in_array;
use function is_string;

/**
 * FormatBased cover loader factory.
 *
 * @category VuFind
 * @package  Content
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:cover_loaders Wiki
 */
class FormatBasedFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Create an object.
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
        $config = $container->get(ConfigManagerInterface::class)->getConfigArray('config');
        $section = $config['FormatBasedCovers'] ?? [];
        $imageDir = isset($section['image_dir']) ? trim((string)$section['image_dir']) : '';
        if ($imageDir === '' && defined('APPLICATION_PATH')) {
            // Use the default set of format images shipped with the bootstrap5 theme:
            $imageDir = APPLICATION_PATH . '/themes/bootstrap5/images/format-covers';
        }
        $default = isset($section['default']) ? trim((string)$section['default']) : '';
        $mapping = [];
        foreach ($section as $name => $value) {
            if (in_array($name, ['image_dir', 'default'], true) || !is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $mapping[$name] = $value;
            }
        }
        $recordLoader = $container->get(RecordLoader::class);
        return new $requestedName($recordLoader, $imageDir, $mapping, $default);
    }
}
