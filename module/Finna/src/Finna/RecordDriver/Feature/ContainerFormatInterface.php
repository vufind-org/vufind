<?php

/**
 * Container record format interface.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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

use VuFindSearch\Response\RecordInterface;

/**
 * Container record format interface.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
interface ContainerFormatInterface extends RecordInterface
{
    /**
     * Separator used in an ID combining the IDs of a container record and an
     * encapsulated record.
     */
    public const ENCAPSULATED_RECORD_ID_SEPARATOR = '|';

    /**
     * Get records encapsulated in this container record.
     *
     * @param int  $offset Offset for results
     * @param ?int $limit  Limit for results (null for none)
     *
     * @return RecordInterface[]
     * @throws \RuntimeException If the format of an encapsulated record is not
     * supported
     */
    public function getEncapsulatedRecords(
        int $offset = 0,
        ?int $limit = null
    ): array;

    /**
     * Returns the requested encapsulated record or null if not found.
     *
     * @param string $id Encapsulated record ID
     *
     * @return ?RecordInterface
     * @throws \RuntimeException If the format is not supported
     */
    public function getEncapsulatedRecord(string $id): ?RecordInterface;

    /**
     * Returns the total number of encapsulated records.
     *
     * @return int
     */
    public function getEncapsulatedRecordTotal(): int;
}
