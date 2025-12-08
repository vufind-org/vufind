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

use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata;
use ReflectionException;

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
     * Alias mappings.
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * Add an alias.
     *
     * @param string $alias  Name to be mapped.
     * @param string $target Target name.
     *
     * @return void
     */
    public function addAlias(string $alias, string $target): void
    {
        $this->aliases[$alias] = $target;
    }

    /**
     * Set all aliases.
     *
     * @param array $aliases Aliases with names to map as keys and targets as values.
     *
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
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
        return parent::getMetadataFor($this->resolveClassName($className));
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
        return parent::hasMetadataFor($this->resolveClassName($className));
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
        parent::setMetadataFor($this->resolveClassName($className), $class);
    }

    /**
     * Actually loads the metadata from the underlying metadata.
     *
     * @param ClassMetadata  $class                Class
     * @param ?ClassMetadata $parent               Parent class
     * @param bool           $rootEntityFound      True when there is another entity (non-mapped superclass) class above
     * the current class in the PHP class hierarchy.
     * @param array          $nonSuperclassParents All parent class names that are not marked as mapped superclasses,
     * with the direct parent class being the first and the root entity class the last element.
     *
     * @return void
     */
    protected function doLoadMetadata(
        $class,
        $parent,
        $rootEntityFound,
        array $nonSuperclassParents
    ) {
        parent::doLoadMetadata($class, $parent, $rootEntityFound, $nonSuperclassParents);

        foreach ($class->associationMappings as &$mapping) {
            $mapping['targetEntity'] = $this->resolveClassName($mapping['targetEntity']);
        }
        unset($mapping);
        // VuFind doesn't use single table inheritance that would require discriminator mapping, but we handle this here
        // anyway for the sake of completeness:
        foreach ($class->discriminatorMap as &$mapping) {
            $mapping = $this->resolveClassName($mapping);
        }
        unset($mapping);
    }

    /**
     * Maps any aliased class or interface name to the target entity.
     *
     * @param string $className Class name.
     *
     * @return string
     */
    protected function resolveClassName(string $className): string
    {
        return $this->aliases[$className] ?? $className;
    }
}
