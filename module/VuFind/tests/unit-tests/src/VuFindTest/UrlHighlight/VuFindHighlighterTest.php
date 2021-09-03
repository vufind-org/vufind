<?php
/**
 * VuFindHighlighter Test Class
 *
 * PHP version 7
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
use VStelmakh\UrlHighlight\Highlighter\HtmlHighlighter;
use VStelmakh\UrlHighlight\Matcher\Match;
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
     * @var ProxyUrl&MockObject
     */
    private $proxyUrl;

    /**
     * @var HtmlHighlighter&MockObject
     */
    private $htmlHighlighter;

    /**
     * @var VuFindHighlighter
     */
    private $vuFindHighlighter;

    public function setUp(): void
    {
        $this->proxyUrl = $this->createMock(ProxyUrl::class);
        $this->htmlHighlighter = $this->createMock(HtmlHighlighter::class);
        $this->vuFindHighlighter = new VuFindHighlighter($this->proxyUrl, $this->htmlHighlighter);
    }

    public function tearDown(): void
    {
        unset($this->proxyUrl, $this->vuFindHighlighter);
    }

    /**
     * @dataProvider getHighlightDataProvider
     *
     * @param array $urlData
     * @param string $expected
     */
    public function testGetHighlight(array $urlData, string $expected): void
    {
        $this->proxyUrl
            ->expects(self::atLeastOnce())
            ->method('__invoke')
            ->willReturnOnConsecutiveCalls('URL_WITH_PROXY');

        $match = $this->createMatchMock(...$urlData);
        $actual = $this->vuFindHighlighter->getHighlight($match);
        self::assertSame($expected, $actual);
    }

    /**
     * @return array[]
     */
    public function getHighlightDataProvider(): array
    {
        return [
            'http' => [
                ['http://vufind.org', 'http', null],
                '<a href="URL_WITH_PROXY">http://vufind.org</a>',
            ],
            'complex link' => [
                ['https://vufind.org?foo=1&bar=2#xyzzy', 'https', null],
                '<a href="URL_WITH_PROXY">https://vufind.org?foo=1&bar=2#xyzzy</a>',
            ],
            'quotes' => [
                ['http://vufind.org/path/with"quotes"/?q=search', 'http', null],
                '<a href="URL_WITH_PROXY">http://vufind.org/path/with"quotes"/?q=search</a>',
            ],
            'no scheme' => [
                ['vufind.org', null, null],
                '<a href="URL_WITH_PROXY">vufind.org</a>',
            ],
            'email' => [
                ['user@vufind.org', null, 'user'],
                '<a href="URL_WITH_PROXY">user@vufind.org</a>',
            ],
        ];
    }

    /**
     * @param string $string
     * @param string $expected
     */
    public function testFilterOverhighlight(): void
    {
        $string = 'some input';

        $this->htmlHighlighter
            ->expects(self::once())
            ->method('filterOverhighlight')
            ->with($string);

        $this->vuFindHighlighter->filterOverhighlight($string);
    }

    /**
     * @param string $url
     * @param string|null $scheme
     * @param string|null $userinfo
     * @return Match&MockObject
     */
    private function createMatchMock(string $url, ?string $scheme, ?string $userinfo): MockObject
    {
        $match = $this->createMock(Match::class);
        $match->method('getFullMatch')->willReturn($url);
        $match->method('getScheme')->willReturn($scheme);
        $match->method('getUserinfo')->willReturn($userinfo);
        $match->method('getUrl')->willReturn($url);
        return $match;
    }
}
