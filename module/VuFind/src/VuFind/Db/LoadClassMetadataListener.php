<?php

/**
 * LoadClassMetadata event listener.
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

namespace VuFind\Db;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

use function is_array;

/**
 * LoadClassMetadata event listener.
 *
 * This event listener ensures that any VuFind entity can be extended by subclassing it.
 *
 * @category VuFind
 * @package  Db
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LoadClassMetadataListener
{
    /**
     * Reverse lookup array for aliased entities
     *
     * @var array
     */
    protected array $aliases;

    /**
     * Cache for loaded metadata
     *
     * @var array
     */
    protected array $loadedMetadata = [];

    /**
     * Constructor
     *
     * @param EntityManagerInterface $entityManager Entity manager
     * @param array                  $aliases       Entity aliases
     */
    public function __construct(protected EntityManagerInterface $entityManager, array $aliases)
    {
        $this->aliases = array_flip($aliases);
    }

    /**
     * Event listener for loadClassMetadata event
     *
     * @param LoadClassMetadataEventArgs $eventArgs Event arguments
     *
     * @return void
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $name = $classMetadata->getName();
        $this->loadedMetadata[$name] = $classMetadata;
        if (isset($this->aliases[$name])) {
            // Eliminate any field mapping pointers to the parent class to avoid mixup e.g. when searching by id using
            // the aliased class:
            foreach ($classMetadata->fieldMappings as &$mapping) {
                unset($mapping['inherited']);
                unset($mapping['declared']);
            }
            unset($mapping);
            // Copy other attributes from any parent class:
            if ($classMetadata->parentClasses) {
                $parentMetadata = $this->loadedMetadata[end($classMetadata->parentClasses)] ?? null;
                foreach ($parentMetadata->table ?? [] as $key => $val) {
                    // Merge arrays, always override table name and add missing fields:
                    if (is_array($val)) {
                        $classMetadata->table[$key] = [
                            ...$val,
                            ...$classMetadata->table[$key] ?? [],
                        ];
                    } elseif ('name' === $key || !isset($classMetadata->table[$key])) {
                        $classMetadata->table[$key] = $val;
                    }
                }
            }
        }
    }
}
