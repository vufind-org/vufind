<?php

/**
 * VuFindHighlighter Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\UrlHighlight;

use PHPUnit\Framework\MockObject\MockObject;
use VStelmakh\UrlHighlight\Replacer\ReplacerFactory;
use VuFind\UrlHighlight\VuFindHighlighter;
use VuFind\View\Helper\Root\ProxyUrl;

/**
 * VuFindHighlighter Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VuFindHighlighterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock proxy object
     *
     * @var ProxyUrl&MockObject
     */
    protected $proxyUrl;

    /**
     * VuFind highlighter object
     *
     * @var VuFindHighlighter
     */
    protected $vuFindHighlighter;

    /**
     * Generic setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->proxyUrl = $this->createMock(ProxyUrl::class);
        $this->vuFindHighlighter = new VuFindHighlighter($this->proxyUrl);
    }

    /**
     * Generic teardown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->proxyUrl, $this->vuFindHighlighter);
    }

    /**
     * Test the highlight method
     *
     * @param string $url      URL
     * @param string $expected Expected result
     *
     * @return void
     *
     * @dataProvider getHighlightDataProvider
     */
    public function testGetHighlight(string $url, string $expected): void
    {
        $this->proxyUrl
            ->expects(self::atLeastOnce())
            ->method('__invoke')
            ->willReturnOnConsecutiveCalls('URL_WITH_PROXY');

        $replacer = ReplacerFactory::createReplacer();
        $actual = $this->vuFindHighlighter->highlight($url, $replacer);
        self::assertSame($expected, $actual);
    }

    /**
     * Data provider for testGetHighlight()
     *
     * @return array[]
     */
    public static function getHighlightDataProvider(): array
    {
        return [
            'http' => [
                'http://vufind.org',
                '<a href="URL_WITH_PROXY">http://vufind.org</a>',
            ],
            'complex link' => [
                'https://vufind.org?foo=1&bar=2#xyzzy',
                '<a href="URL_WITH_PROXY">https://vufind.org?foo=1&bar=2#xyzzy</a>',
            ],
            'quotes' => [
                'http://vufind.org/path/with"quotes"/?q=search',
                '<a href="URL_WITH_PROXY">http://vufind.org/path/with"quotes"/?q=search</a>',
            ],
            'no scheme' => [
                'vufind.org',
                '<a href="URL_WITH_PROXY">vufind.org</a>',
            ],
            'email' => [
                'user@vufind.org',
                '<a href="URL_WITH_PROXY">user@vufind.org</a>',
            ],
        ];
    }
}
