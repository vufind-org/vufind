<?php
/**
 * Provide url formatted as HTML and prefixed with proxy if applicable
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
 * @package  View_Helpers
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\UrlHighlight;

use VStelmakh\UrlHighlight\Highlighter\HighlighterInterface;
use VStelmakh\UrlHighlight\Highlighter\HtmlHighlighter;
use VStelmakh\UrlHighlight\Matcher\Match;
use VStelmakh\UrlHighlight\Util\LinkHelper;
use VuFind\View\Helper\Root\ProxyUrl;

/**
 * Provide url formatted as HTML and prefixed with proxy if applicable
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class VuFindHighlighter implements HighlighterInterface
{
    private const DEFAULT_SCHEME = 'http';

    /**
     * Url highlight html highlighter
     *
     * @var HtmlHighlighter
     */
    private $_htmlHighlighter;

    /**
     * Proxy url helper
     *
     * @var ProxyUrl
     */
    private $_proxyUrl;

    /**
     * Constructor
     *
     * @param ProxyUrl $proxyUrl proxy url helper
     */
    public function __construct(ProxyUrl $proxyUrl)
    {
        $this->_htmlHighlighter = new HtmlHighlighter(self::DEFAULT_SCHEME, []);
        $this->_proxyUrl = $proxyUrl;
    }

    /**
     * Return html highlighted url with proxy
     *
     * @param Match $match url highlight match
     *
     * @return string
     */
    public function getHighlight(Match $match): string
    {
        $link = LinkHelper::getLink($match, self::DEFAULT_SCHEME);
        $linkProxy = $this->_proxyUrl->__invoke($link);
        $linkSafeQuot = str_replace('"', '%22', $linkProxy);
        return sprintf('<a href="%s">%s</a>', $linkSafeQuot, $match->getFullMatch());
    }

    /**
     * Filter already highlighted urls
     *
     * @param string $string string after highlighter applied
     *
     * @return string
     */
    public function filterOverhighlight(string $string): string
    {
        return $this->_htmlHighlighter->filterOverhighlight($string);
    }
}
