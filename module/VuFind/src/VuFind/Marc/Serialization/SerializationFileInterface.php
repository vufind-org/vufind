<?php
/**
 * MARC serialization file interface.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * MARC serialization file interface.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
interface SerializationFileInterface
{
    /**
     * Check if the serialization class can parse the given MARC collection file
     *
     * @param string $file File name
     *
     * @return bool
     */
    public static function canParseCollectionFile(string $file): bool;

    /**
     * Set message callback
     *
     * @param callable $callback Message callback
     *
     * @return void
     */
    public function setMessageCallback(?callable $callback): void;

    /**
     * Open a collection file
     *
     * @param string $file File name
     *
     * @return void
     */
    public function openCollectionFile(string $file): void;

    /**
     * Rewind the collection file
     *
     * @return void;
     */
    public function rewind(): void;

    /**
     * Get next record from the file or an empty string on EOF
     *
     * @return string
     */
    public function getNextRecord(): string;
}
