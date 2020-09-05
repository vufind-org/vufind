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
 * @author   Volodymyr Stelmakh
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use VStelmakh\UrlHighlight\Highlighter\HighlighterInterface;
use VStelmakh\UrlHighlight\Highlighter\HtmlHighlighter;
use VStelmakh\UrlHighlight\Matcher\Match;
use VStelmakh\UrlHighlight\Util\LinkHelper;

/**
 * Provide url formatted as HTML and prefixed with proxy if applicable
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Volodymyr Stelmakh
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LinkifyHighlighter implements HighlighterInterface
{
    private const DEFAULT_SCHEME = 'http';

    /**
     * @var HtmlHighlighter
     */
    private $htmlHighlighter;

    /**
     * @var ProxyUrl
     */
    private $proxyUrl;

    /**
     * @param ProxyUrl|null $proxyUrl
     */
    public function __construct(?ProxyUrl $proxyUrl)
    {
        $this->htmlHighlighter = new HtmlHighlighter(self::DEFAULT_SCHEME, []);
        $this->proxyUrl = $proxyUrl;
    }

    /**
     * @inheritdoc
     */
    public function getHighlight(Match $match): string
    {
        $link = LinkHelper::getLink($match, self::DEFAULT_SCHEME);
        $linkProxy = $this->proxyUrl->__invoke($link);
        $linkSafeQuotes = str_replace('"', '%22', $linkProxy);
        return sprintf('<a href="%s">%s</a>', $linkSafeQuotes, $match->getFullMatch());
    }

    /**
     * @inheritdoc
     */
    public function filterOverhighlight(string $string): string
    {
        return $this->htmlHighlighter->filterOverhighlight($string);
    }
}
