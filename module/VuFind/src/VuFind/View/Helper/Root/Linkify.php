<?php

/**
 * Linkify a string so that the links become clickable HTML
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VStelmakh\UrlHighlight\UrlHighlight;

/**
 * Linkify a string so that the links become clickable HTML
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Linkify extends AbstractHelper
{
    /**
     * Constructor
     *
     * @param UrlHighlight $urlHighlight            Url highlighter
     * @param UrlHighlight $urlHighlightExceptEmail Url highlighter that ignores email addresses
     */
    public function __construct(protected UrlHighlight $urlHighlight, protected UrlHighlight $urlHighlightExceptEmail)
    {
    }

    /**
     * Replace urls and emails by html tags
     *
     * @param string $string       String to linkify (must be HTML-escaped)
     * @param bool   $includeEmail If email addresses should also be linkified
     *
     * @return string
     */
    public function __invoke(string $string, bool $includeEmail = true): string
    {
        return $includeEmail
            ? $this->urlHighlight->highlightUrls($string)
            : $this->urlHighlightExceptEmail->highlightUrls($string);
    }
}
