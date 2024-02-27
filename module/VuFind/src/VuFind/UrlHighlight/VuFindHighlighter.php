<?php

/**
 * Provide URL formatted as HTML and prefixed with proxy if applicable
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  UrlHighlight
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\UrlHighlight;

use VStelmakh\UrlHighlight\Highlighter\HtmlHighlighter;
use VStelmakh\UrlHighlight\Matcher\UrlMatch;
use VuFind\View\Helper\Root\ProxyUrl;

/**
 * Provide URL formatted as HTML and prefixed with proxy if applicable
 *
 * @category VuFind
 * @package  UrlHighlight
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class VuFindHighlighter extends HtmlHighlighter
{
    public const DEFAULT_SCHEME = 'http';

    /**
     * Proxy url helper
     *
     * @var ProxyUrl
     */
    protected $proxyUrl;

    /**
     * Constructor
     *
     * @param ProxyUrl $proxyUrl Proxy url helper
     */
    public function __construct(ProxyUrl $proxyUrl)
    {
        $this->proxyUrl = $proxyUrl;
        parent::__construct(self::DEFAULT_SCHEME);
    }

    /**
     * Return url with proxy
     *
     * @param UrlMatch $match url highlight match
     *
     * @return string
     */
    protected function getLink(UrlMatch $match): string
    {
        $link = parent::getLink($match);
        return ($this->proxyUrl)($link);
    }
}
