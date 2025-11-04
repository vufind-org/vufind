<?php

/**
 * Class for populating record rows in the resource table of the database
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
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Record;

use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Class for populating record rows in the resource table of the database
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResourcePopulator extends \VuFind\Record\ResourcePopulator
{
    /**
     * Use a record driver to assign metadata to the current row. Return the
     * current object to allow fluent interface.
     *
     * @param ResourceEntityInterface $resource The resource to populate
     * @param RecordDriver            $driver   The record driver to populate from
     *
     * @return ResourceEntityInterface
     */
    public function assignMetadata(ResourceEntityInterface $resource, RecordDriver $driver): ResourceEntityInterface
    {
        parent::assignMetadata($resource, $driver);

        if (null === $resource->getYear()) {
            if ($year = $driver->tryMethod('getYear')) {
                $resource->setYear($year);
            }
        }

        return $resource;
    }
}
