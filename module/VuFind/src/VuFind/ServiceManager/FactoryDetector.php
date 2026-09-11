<?php

/**
 * Class to detect the best factory for another class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager;

use ReflectionClass;
use VuFind\ServiceManager\Factory\AutowiringFactory;
use VuFind\ServiceManager\Factory\DefaultFactory;

/**
 * Class to detect the best factory for another class.
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FactoryDetector
{
    use Factory\AutowireableTrait;

    /**
     * Given the name of a class, check if it has default factory behavior assigned; return an array
     * of values from the DefaultFactory attribute.
     *
     * @param string $class Class name
     *
     * @return array{name: ?string, autodetect: bool}
     */
    protected function getDefaultFactorySettings(string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $matches = $reflectionClass->getAttributes(DefaultFactory::class);
        if (!$matches) {
            $matches = $reflectionClass->getConstructor()?->getAttributes(DefaultFactory::class);
        }
        return isset($matches[0]) ? $matches[0]->getArguments() : ['name' => null, 'autodetect' => true];
    }

    /**
     * Given a class name, detect the best matching factory. Return null if none can be found.
     * This is a support method for getFactoryForClass and should not be called directly; use
     * getFactoryForClass to take advantage of internal caching.
     *
     * @param string $class Class name
     *
     * @return ?string
     */
    public function detectFactoryForClass(string $class): ?string
    {
        $defaultFactorySettings = $this->getDefaultFactorySettings($class);
        if ($defaultFactorySettings['name']) {
            return $defaultFactorySettings['name'];
        }
        // If the class is autowireable, take advantage of that:
        if ($this->isAutowireable($class)) {
            return AutowiringFactory::class;
        }
        // Autodetect a factory if desired:
        if ($defaultFactorySettings['autodetect']) {
            // If the class has an explicit factory, use that:
            if (class_exists($class . 'Factory')) {
                return $class . 'Factory';
            }
            // Check if parent classes have factories:
            $parentClass = get_parent_class($class);
            while ($parentClass) {
                if (class_exists($parentClass . 'Factory')) {
                    return $parentClass . 'Factory';
                }
                $parentClass = get_parent_class($parentClass);
            }
        }
        // If we got this far, we couldn't find a match.
        return null;
    }
}
