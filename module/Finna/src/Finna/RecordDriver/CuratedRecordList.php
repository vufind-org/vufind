<?php

/**
 * Model for a list of curated VuFind records.
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
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use Finna\RecordDriver\Feature\ContainerFormatInterface;
use Finna\RecordDriver\Feature\ContainerFormatTrait;
use Finna\RecordDriver\Feature\EncapsulatedRecordInterface;
use Finna\RecordDriver\Feature\EncapsulatedRecordTrait;
use Finna\RecordDriver\Feature\FinnaXmlReaderTrait;

/**
 * Model for a list of curated VuFind records.
 *
 * This driver is designed to be used as a virtual record driver by container format
 * drivers encapsulating lists of curated VuFind records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class CuratedRecordList extends SolrDefault implements
    ContainerFormatInterface,
    EncapsulatedRecordInterface
{
    use ContainerFormatTrait;
    use EncapsulatedRecordTrait;
    use FinnaXmlReaderTrait;

    /**
     * Return description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->fields['description'] ?? '';
    }

    /**
     * Return additional type.
     *
     * @return string
     */
    public function getAdditionalType(): string
    {
        return $this->fields['additionalType'] ?? '';
    }

    /**
     * Returns the tag name of XML elements containing an encapsulated record.
     *
     * @return string
     */
    protected function getEncapsulatedRecordElementTagName(): string
    {
        return 'curatedRecord';
    }

    /**
     * Return format for an encapsulated record.
     *
     * @param mixed $item Encapsulated record item
     *
     * @return string
     * @throws \RuntimeException If the format can not be determined
     */
    protected function getEncapsulatedRecordFormat($item): string
    {
        return 'CuratedRecord';
    }
}
