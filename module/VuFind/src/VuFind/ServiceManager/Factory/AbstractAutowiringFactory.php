<?php

/**
 * VuFind Abstract Autowiring Factory
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * VuFind Abstract Autowiring Factory
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AbstractAutowiringFactory extends AutowiringFactory implements AbstractFactoryInterface
{
    /**
     * Autowireability of known services.
     *
     * @var array
     */
    protected array $autowireable = [
        'DoctrineModule\Cache\LaminasStorageCache' => false,
    ];

    /**
     * Can the factory create an instance for the service?
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     *
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        if (null !== ($known = $this->autowireable[$requestedName] ?? null)) {
            return $known;
        }
        if (!class_exists($requestedName)) {
            $this->autowireable[$requestedName] = false;
            return false;
        }
        $reflectionClass = new ReflectionClass($requestedName);
        if (null === ($constructor = $reflectionClass->getConstructor())) {
            $this->autowireable[$requestedName] = true;
            return true;
        }
        $reflectionParameters = $constructor->getParameters();
        if (empty($reflectionParameters)) {
            $this->autowireable[$requestedName] = true;
            return true;
        }
        return $this->autowireable[$requestedName] = !empty($constructor->getAttributes(Autowire::class));
    }
}
