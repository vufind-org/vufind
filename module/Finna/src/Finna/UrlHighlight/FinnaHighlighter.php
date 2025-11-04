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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  UrlHighlight
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\UrlHighlight;

use Finna\View\Helper\Root\TruncateUrl;
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
class FinnaHighlighter extends \VuFind\UrlHighlight\VuFindHighlighter
{
    /**
     * URL truncator
     *
     * @var TruncateUrl
     */
    protected $truncateUrl;

    /**
     * Constructor
     *
     * @param ProxyUrl    $proxyUrl    Proxy url helper
     * @param TruncateUrl $truncateUrl URL truncator
     */
    public function __construct(ProxyUrl $proxyUrl, TruncateUrl $truncateUrl)
    {
        $this->truncateUrl = $truncateUrl;
        parent::__construct($proxyUrl);
    }

    /**
     * Content used to display url: ...>{here}</a>
     *
     * @param UrlMatch $match URL match
     *
     * @return string
     */
    protected function getText(UrlMatch $match): string
    {
        return ($this->truncateUrl)($match->getFullMatch());
    }

    /**
     * Additional link attributes <a href="#"{here}>...
     * Consider to add leading space and escape quotes, tag brackets e.g. " < > etc.
     *
     * @param UrlMatch $match URL match
     *
     * @return string
     */
    protected function getAttributes(UrlMatch $match): string
    {
        return trim(parent::getAttributes($match) . ' target="_blank"');
    }

    /**
     * Content before highlight: {here}<a...
     *
     * @param UrlMatch $match URL match
     *
     * @return string
     */
    protected function getContentBefore(UrlMatch $match): string
    {
        return '<i class="fa fa-external-link"></i> ';
    }
}
