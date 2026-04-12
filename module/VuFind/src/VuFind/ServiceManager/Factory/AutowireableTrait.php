<?php

/**
 * Trait to detect whether a class can be autowired.
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

use ReflectionClass;

/**
 * Trait to detect whether a class can be autowired.
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait AutowireableTrait
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
     * Is the provided class name compatible with autowiring?
     *
     * @param string $requestedName Name of service
     *
     * @return bool
     */
    public function isAutowireable(string $requestedName): bool
    {
        // Return cached status if we've already checked this service.
        if (null !== ($known = $this->autowireable[$requestedName] ?? null)) {
            return $known;
        }
        // Not a real class? Not autowireable!
        if (!class_exists($requestedName)) {
            $this->autowireable[$requestedName] = false;
            return false;
        }
        // No constructor? Autowireable!
        $reflectionClass = new ReflectionClass($requestedName);
        if (null === ($constructor = $reflectionClass->getConstructor())) {
            $this->autowireable[$requestedName] = true;
            return true;
        }
        // No constructor arguments? Autowireable!
        $reflectionParameters = $constructor->getParameters();
        if (empty($reflectionParameters)) {
            $this->autowireable[$requestedName] = true;
            return true;
        }
        // Check if the constructor has any parameters with the Autowire attribute:
        foreach ($reflectionParameters as $param) {
            if ($param->getAttributes(Autowire::class)) {
                $this->autowireable[$requestedName] = true;
                return true;
            }
        }
        // Final check: is there a top-level Autowire attribute on the constructor?
        return $this->autowireable[$requestedName] = !empty($constructor->getAttributes(Autowire::class));
    }
}
