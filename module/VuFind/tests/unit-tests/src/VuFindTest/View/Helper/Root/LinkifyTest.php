<?php
/**
 * Linkify Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;

use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\EscapeHtmlAttr;
use VuFind\View\Helper\Root\Linkify;
use VuFind\View\Helper\Root\ProxyUrl;

/**
 * Linkify Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LinkifyTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Get view helper to test.
     *
     * @return Linkify
     */
    protected function getHelper()
    {
        $view = $this->getPhpRenderer(
            [
                'proxyUrl' => new ProxyUrl(),
                'escapeHtmlAttr' => new EscapeHtmlAttr(),
            ]
        );
        $linkify = new Linkify();
        $linkify->setView($view);
        return $linkify;
    }

    /**
     * Run a simple test.
     *
     * @param string $text     Raw input text
     * @param string $expected Expected output HTML
     *
     * @return void
     */
    protected function linkify($text, $expected)
    {
        $escaper = new EscapeHtml();
        // The linkify helper expects HTML-escaped input, because after linkify
        // has been applied, we can no longer escape unlinked portions of the text
        // without messing up the link HTML:
        $html = $escaper->__invoke($text);
        $this->assertEquals($expected, $this->getHelper()->__invoke($html));
    }

    /**
     * Test linkification of HTTP URL.
     *
     * @return void
     */
    public function testHttpLink()
    {
        $text = 'This has http://vufind.org in the middle of it';
        $expected = 'This has '
            . '<a href="http&#x3A;&#x2F;&#x2F;vufind.org">http://vufind.org</a>'
            . ' in the middle of it';
        $this->linkify($text, $expected);
    }

    /**
     * Test linkification of HTTPS URL.
     *
     * @return void
     */
    public function testHttpsLink()
    {
        $text = "This has https://vufind.org in the middle of it";
        $expected = 'This has '
            . '<a href="https&#x3A;&#x2F;&#x2F;vufind.org">https://vufind.org</a>'
            . ' in the middle of it';
        $this->linkify($text, $expected);
    }

    /**
     * Test linkification of complex URL with parameters and hash.
     *
     * @return void
     */
    public function testComplexLink()
    {
        $text = "This has https://vufind.org?foo=1&bar=2#xyzzy in the middle of it";
        $expected = 'This has '
            . '<a href="https&#x3A;&#x2F;&#x2F;vufind.org'
            . '&#x3F;foo&#x3D;1&amp;bar&#x3D;2&#x23;xyzzy">'
            . 'https://vufind.org?foo=1&amp;bar=2#xyzzy</a> in the middle of it';
        $this->linkify($text, $expected);
    }

    /**
     * Test linkification of multiple URLs.
     *
     * @return void
     */
    public function testMultipleLinks()
    {
        $text = "This has https://vufind.org and http://vufind.org in it";
        $expected = 'This has '
            . '<a href="https&#x3A;&#x2F;&#x2F;vufind.org">https://vufind.org</a>'
            . ' and '
            . '<a href="http&#x3A;&#x2F;&#x2F;vufind.org">http://vufind.org</a>'
            . ' in it';
        $this->linkify($text, $expected);
    }
}
