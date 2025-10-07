<?php

/**
 * Escaper with configurable HTML attribute handling.
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
 * @package  Escaper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Escaper;

/**
 * Escaper with configurable HTML attribute handling.
 *
 * @category VuFind
 * @package  Escaper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Escaper extends \Laminas\Escaper\Escaper
{
    /**
     * Constructor
     *
     * @param bool $extendedHtmlAttrEscaping Use Laminas' extended HTML attribute escaping?
     */
    public function __construct(protected bool $extendedHtmlAttrEscaping = false)
    {
        parent::__construct();
    }

    /**
     * Escape a string for the HTML Attribute context.
     *
     * @param string $string String to escape
     *
     * @return string
     */
    public function escapeHtmlAttr(string $string)
    {
        return $this->extendedHtmlAttrEscaping
            ? parent::escapeHtmlAttr($string)
            : parent::escapeHtml($string);
    }
}
