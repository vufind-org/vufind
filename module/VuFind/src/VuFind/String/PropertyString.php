<?php

/**
 * Class for a string with additional properties.
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

use function array_key_exists;
use function in_array;

/**
 * Class for a string with additional properties.
 *
 * @category VuFind
 * @package  String
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PropertyString implements PropertyStringInterface
{
    /**
     * Constructor
     *
     * @param string $string     String value
     * @param array  $properties Associative array of any additional properties. Use a custom prefix for locally
     * defined properties. Double underscore is a reserved prefix, and currently the following keys are defined:
     * __ids  Identifiers (e.g. subject URIs)
     * __html HTML presentation
     */
    public function __construct(protected string $string, protected array $properties = [])
    {
    }

    /**
     * Create a PropertyString from an HTML string
     *
     * @param string $html       HTML
     * @param array  $properties Any additional properties (see __construct)
     *
     * @return PropertyString
     */
    public static function fromHtml(string $html, array $properties = []): PropertyString
    {
        $decodedHtml = html_entity_decode(strip_tags($html));
        return (new PropertyString($decodedHtml, $properties))->setHtml($html);
    }

    /**
     * Set string value
     *
     * @param string $str String value
     *
     * @return static
     */
    public function setString(string $str): static
    {
        $this->string = $str;
        return $this;
    }

    /**
     * Get string value
     *
     * @return string
     */
    public function getString(): string
    {
        return $this->string;
    }

    /**
     * Set HTML string
     *
     * @param string $html HTML
     *
     * @return static
     */
    public function setHtml(string $html): static
    {
        $this['__html'] = $html;
        return $this;
    }

    /**
     * Get HTML string
     *
     * Note: This could contain anything and must be sanitized for display unless marked trusted
     * (see setHtmlTrusted/isHtmlTrusted).
     *
     * @return ?string
     */
    public function getHtml(): ?string
    {
        return $this['__html'];
    }

    /**
     * Set flag for trusted HTML
     *
     * @param bool $trusted Is the HTML content trusted?
     *
     * @return static
     */
    public function setHtmlTrusted(bool $trusted): static
    {
        $this['__trustedHtml'] = $trusted;
        return $this;
    }

    /**
     * Get flag for trusted HTML
     *
     * @return ?bool
     */
    public function isHtmlTrusted(): ?bool
    {
        return $this['__trustedHtml'];
    }

    /**
     * Add an identifier
     *
     * @param string $id Identifier
     *
     * @return static
     */
    public function addId(string $id): static
    {
        $ids = $this->getIds() ?? [];
        if (!in_array($id, $ids)) {
            $ids[] = $id;
            $this->setIds($ids);
        }
        return $this;
    }

    /**
     * Set identifiers
     *
     * @param array $ids Identifiers
     *
     * @return static
     */
    public function setIds(array $ids): static
    {
        $this['__ids'] = $ids;
        return $this;
    }

    /**
     * Get identifiers
     *
     * @return ?array
     */
    public function getIds(): ?array
    {
        return $this['__ids'];
    }

    /**
     * Check if offset exists
     *
     * @param mixed $offset Offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->properties);
    }

    /**
     * Return value of offset
     *
     * @param mixed $offset Offset
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->properties[$offset] ?? null;
    }

    /**
     * Set value of offset
     *
     * @param mixed $offset Offset
     * @param mixed $value  Value
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->properties[$offset] = $value;
    }

    /**
     * Unset value of offset
     *
     * @param mixed $offset Offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->properties[$offset]);
    }

    /**
     * Return string value
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->string;
    }
}
