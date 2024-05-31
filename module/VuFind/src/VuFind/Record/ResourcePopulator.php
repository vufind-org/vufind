<?php

/**
 * Class for populating record rows in the resource table of the database
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record;

use VuFind\Date\Converter as DateConverter;
use VuFind\Date\DateException;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function intval;
use function strlen;

/**
 * Class for populating record rows in the resource table of the database
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResourcePopulator
{
    /**
     * Constructor
     *
     * @param ResourceServiceInterface $resourceService Resource database service
     * @param Loader                   $loader          Record loader
     * @param DateConverter            $dateConverter   Date converter
     *
     * @return void
     */
    public function __construct(
        protected ResourceServiceInterface $resourceService,
        protected Loader $loader,
        protected DateConverter $dateConverter
    ) {
    }

    /**
     * Retrieve an existing row matching the provided record driver if it exists; create, populate and persist it if
     * it does not.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return ResourceEntityInterface
     */
    public function getOrCreateResourceForDriver(RecordDriver $driver): ResourceEntityInterface
    {
        $resource = $this->resourceService
            ->getResourceByRecordId($driver->getUniqueID(), $driver->getSourceIdentifier());
        return $resource ? $resource : $this->createAndPersistResourceForDriver($driver);
    }

    /**
     * Retrieve an existing row matching the provided id/source if it exists; create, populate and persist it if
     * it does not.
     *
     * @param string $id     Record id
     * @param string $source Record source
     *
     * @return ResourceEntityInterface
     */
    public function getOrCreateResourceForRecordId(string $id, string $source): ResourceEntityInterface
    {
        $resource = $this->resourceService->getResourceByRecordId($id, $source);
        return $resource ? $resource : $this->createAndPersistResourceForRecordId($id, $source);
    }

    /**
     * Create (but do not persist) a ResourceEntityInterface object populated with data from
     * the provided record driver.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return ResourceEntityInterface
     */
    public function createResourceForDriver(RecordDriver $driver): ResourceEntityInterface
    {
        return $this->assignMetadata($this->resourceService->createEntity(), $driver)
            ->setRecordId($driver->getUniqueId())
            ->setSource($driver->getSourceIdentifier());
    }

    /**
     * Create (but do not persist) a ResourceEntityInterface object populated with data from
     * the record driver looked up using the provided record ID and source.
     *
     * @param string $id     Record id
     * @param string $source Record source
     *
     * @return ResourceEntityInterface
     */
    public function createResourceForRecordId(string $id, string $source): ResourceEntityInterface
    {
        return $this->createResourceForDriver($this->loader->load($id, $source));
    }

    /**
     * Create and a ResourceEntityInterface object populated with data from the provided record driver.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return ResourceEntityInterface
     */
    public function createAndPersistResourceForDriver(RecordDriver $driver): ResourceEntityInterface
    {
        $resource = $this->createResourceForDriver($driver);
        $this->resourceService->persistEntity($resource);
        return $resource;
    }

    /**
     * Create and persist a ResourceEntityInterface object populated with data from the record driver
     * looked up using the provided record ID and source.
     *
     * @param string $id     Record id
     * @param string $source Record source
     *
     * @return ResourceEntityInterface
     */
    public function createAndPersistResourceForRecordId(string $id, string $source): ResourceEntityInterface
    {
        $resource = $this->createResourceForRecordId($id, $source);
        $this->resourceService->persistEntity($resource);
        return $resource;
    }

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
        // Grab title -- we have to have something in this field!
        $title = mb_substr(
            $driver->tryMethod('getSortTitle'),
            0,
            255,
            'UTF-8'
        );
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }
        $resource->setTitle($title);

        // Try to find an author; if not available, just leave the default null:
        $author = mb_substr(
            $driver->tryMethod('getPrimaryAuthor'),
            0,
            255,
            'UTF-8'
        );
        if (!empty($author)) {
            $resource->setAuthor($author);
        }

        // Try to find a year; if not available, just leave the default null:
        $dates = $driver->tryMethod('getPublicationDates');
        if (isset($dates[0]) && strlen($dates[0]) > 4) {
            try {
                $year = $this->dateConverter->convertFromDisplayDate('Y', $dates[0]);
            } catch (DateException) {
                // If conversion fails, don't store a date:
                $year = '';
            }
        } else {
            $year = $dates[0] ?? '';
        }
        if (!empty($year)) {
            $resource->setYear(intval($year));
        }

        if ($extra = $driver->tryMethod('getExtraResourceMetadata')) {
            $resource->setExtraMetadata(json_encode($extra));
        }
        return $resource;
    }
}
