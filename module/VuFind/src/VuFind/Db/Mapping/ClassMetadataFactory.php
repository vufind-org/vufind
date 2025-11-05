<?php

/**
 * Extended Doctrine class metadata factory.
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
 * @package  Db
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Mapping;

use Doctrine\Persistence\Mapping\ClassMetadata;

/**
 * Extended Doctrine class metadata factory.
 *
 * @category VuFind
 * @package  Db
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ClassMetadataFactory extends \Doctrine\ORM\Mapping\ClassMetadataFactory implements ClassMetadataMappingsInterface
{
    /**
     * Mappings for entity classes or interfaces
     *
     * @var array
     */
    protected array $mappings = [];

    /**
     * Add a mapping.
     *
     * @param string $alias  Name to be mapped.
     * @param string $target Target name.
     *
     * @return void
     */
    public function addMapping(string $alias, string $target): void
    {
        $this->mappings[$alias] = $target;
    }

    /**
     * Set all mappings.
     *
     * @param array $mappings Mappings with names to map as keys and targets as values.
     *
     * @return void
     */
    public function setMappings(array $mappings): void
    {
        $this->mappings = $mappings;
    }

    /**
     * Gets the class metadata descriptor for a class.
     *
     * @param string $className The name of the class.
     *
     * @return ClassMetadata
     *
     * @throws ReflectionException
     * @throws MappingException
     */
    public function getMetadataFor(string $className)
    {
        return parent::getMetadataFor($this->mapClassName($className));
    }

    /**
     * Checks whether the factory has the metadata for a class loaded already.
     *
     * @param string $className Class name.
     *
     * @return bool
     */
    public function hasMetadataFor(string $className)
    {
        return parent::hasMetadataFor($this->mapClassName($className));
    }

    /**
     * Sets the metadata descriptor for a specific class.
     *
     * NOTE: This is only useful in very special cases, like when generating proxy classes.
     *
     * @param string        $className Class name.
     * @param ClassMetadata $class     Metadata.
     *
     * @return void
     */
    public function setMetadataFor(string $className, ClassMetadata $class)
    {
        parent::setMetadataFor($this->mapClassName($className), $class);
    }

    /**
     * Maps any aliased class or interface name to the target entity.
     *
     * @param string $className Class name.
     *
     * @return string
     */
    protected function mapClassName(string $className): string
    {
        return $this->mappings[$className] ?? $className;
    }
}
