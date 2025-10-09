<?php

/**
 * Interface for a string with additional properties.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  String
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\String;

/**
 * Interface for a string with additional properties.
 *
 * @category VuFind
 * @package  String
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface PropertyStringInterface extends \ArrayAccess, \Stringable
{
    /**
     * Set string value
     *
     * @param string $str String value
     *
     * @return static
     */
    public function setString(string $str): static;

    /**
     * Get string value
     *
     * @return string
     */
    public function getString(): string;

    /**
     * Set HTML string
     *
     * @param string $html HTML
     *
     * @return static
     */
    public function setHtml(string $html): static;

    /**
     * Get HTML string
     *
     * Note: This could contain anything and must be sanitized for display unless marked trusted
     * (see setHtmlTrusted/isHtmlTrusted).
     *
     * @return ?string
     */
    public function getHtml(): ?string;

    /**
     * Set flag for trusted HTML
     *
     * @param bool $trusted Is the HTML content trusted?
     *
     * @return static
     */
    public function setHtmlTrusted(bool $trusted): static;

    /**
     * Get flag for trusted HTML
     *
     * @return ?bool
     */
    public function isHtmlTrusted(): ?bool;

    /**
     * Add an identifier
     *
     * @param string $id Identifier
     *
     * @return static
     */
    public function addId(string $id): static;

    /**
     * Set identifiers
     *
     * @param array $ids Identifiers
     *
     * @return static
     */
    public function setIds(array $ids): static;

    /**
     * Get identifiers
     *
     * @return ?array
     */
    public function getIds(): ?array;
}
