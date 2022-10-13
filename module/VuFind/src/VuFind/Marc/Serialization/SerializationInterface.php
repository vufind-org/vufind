<?php
/**
 * MARC serialization interface.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\Marc\Serialization;

/**
 * MARC serialization interface.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
interface SerializationInterface
{
    /**
     * Check if the serialization class can parse the given MARC string
     *
     * @param string $marc MARC
     *
     * @return bool
     */
    public static function canParse(string $marc): bool;

    /**
     * Check if the serialization class can parse the given MARC collection string
     *
     * @param string $marc MARC
     *
     * @return bool
     */
    public static function canParseCollection(string $marc): bool;

    /**
     * Parse MARC collection from a string into an array of MarcReader classes
     *
     * @param string $collection MARC record collection in the format supported by
     * the serialization class
     *
     * @throws Exception
     * @return array
     */
    public static function collectionFromString(string $collection): array;

    /**
     * Parse MARC from a string
     *
     * @param string $marc MARC record in the format supported by the serialization
     * class
     *
     * @throws Exception
     * @return array
     */
    public static function fromString(string $marc): array;

    /**
     * Convert record to a string representing the format supported by the
     * serialization class
     *
     * @param array $data Record data
     *
     * @return string
     */
    public static function toString(array $data): string;
}
