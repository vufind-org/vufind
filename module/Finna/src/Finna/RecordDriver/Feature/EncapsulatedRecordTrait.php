<?php

/**
 * Common functionality for encapsulated records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver\Feature;

use VuFind\RecordDriver\AbstractBase;

/**
 * Common functionality for encapsulated records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait EncapsulatedRecordTrait
{
    /**
     * Container record.
     *
     * @var ContainerFormatInterface
     */
    protected ContainerFormatInterface $containerRecord;

    /**
     * Sets the container record.
     *
     * @param ContainerFormatInterface $containerRecord Container record.
     *
     * @return void
     */
    public function setContainerRecord(ContainerFormatInterface $containerRecord): void
    {
        $this->containerRecord = $containerRecord;
    }

    /**
     * Returns the container record.
     *
     * @return ContainerFormatInterface
     */
    public function getContainerRecord(): ContainerFormatInterface
    {
        return $this->containerRecord;
    }

    /**
     * Does the encapsulated record need a record to be loaded?
     *
     * @return array|false Associative array specifying the record that needs loading
     * (contains 'id' and 'source' keys), or false
     */
    public function needsRecordLoaded(): array|false
    {
        return false;
    }

    /**
     * Set the loaded record specified by needsRecordLoaded().
     *
     * @param AbstractBase $record Loaded record
     *
     * @return void
     * @throws \LogicException If the record should not be set
     */
    public function setLoadedRecord(AbstractBase $record): void
    {
        $this->checkSetLoadedRecord($record);
    }

    /**
     * Checks that the provided record is valid for setting as a loaded record.
     *
     * @param AbstractBase $record Loaded record
     *
     * @return void
     * @throws \LogicException If the record should not be set
     */
    protected function checkSetLoadedRecord(AbstractBase $record): void
    {
        if (false === ($needed = $this->needsRecordLoaded())) {
            throw new \LogicException('Record loading not needed');
        } elseif ($record->getUniqueID() !== $needed['id']) {
            // Try previous ID.
            if ($record->tryMethod('getPreviousUniqueID') !== $needed['id']) {
                throw new \LogicException('Record ID does not match needed record ID');
            }
        } elseif ($record->getSourceIdentifier() !== $needed['source']) {
            throw new \LogicException('Record source does not match needed record source');
        }
    }
}
