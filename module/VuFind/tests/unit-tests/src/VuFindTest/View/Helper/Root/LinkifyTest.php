<?php
/**
 * Linkify Test Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Volodymyr Stelmakh
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\View\Helper\Root\Linkify;
use VuFind\View\Helper\Root\ProxyUrl;
use VuFindTest\Unit\ViewHelperTestCase;

/**
 * Linkify Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Volodymyr Stelmakh
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LinkifyTest extends ViewHelperTestCase
{
    /**
     * @var ProxyUrl&MockObject
     */
    private $proxyUrl;

    /**
     * @var Linkify
     */
    private $linkify;

    public function setUp(): void
    {
        $this->proxyUrl = $this->createMock(ProxyUrl::class);

        $view = $this->getPhpRenderer([
            'proxyUrl' => $this->proxyUrl,
        ]);

        $this->linkify = new Linkify();
        $this->linkify->setView($view);
    }

    public function tearDown(): void
    {
        unset($this->proxyUrl, $this->linkify);
    }

    /**
     * @dataProvider linkifyDataProvider
     *
     * @param string $input
     * @param array $urls
     * @param string $expected
     */
    public function testLinkify(string $input, array $urls, string $expected): void
    {
        $this->proxyUrl
            ->expects(self::atLeastOnce())
            ->method('__invoke')
            ->willReturnOnConsecutiveCalls(...$urls);

        $actual = $this->linkify->__invoke($input);
        self::assertSame($expected, $actual);
    }

    /**
     * @return array[]
     */
    public function linkifyDataProvider(): array
    {
        return [
            'http' => [
                'This has http://vufind.org in the middle of it',
                ['http://vufind.org'],
                'This has <a href="http://vufind.org">http://vufind.org</a> in the middle of it',
            ],
            'https' => [
                'This has https://vufind.org in the middle of it',
                ['https://vufind.org'],
                'This has <a href="https://vufind.org">https://vufind.org</a> in the middle of it',
            ],
            'complex link' => [
                'This has https://vufind.org?foo=1&bar=2#xyzzy in the middle of it',
                ['https://vufind.org?foo=1&bar=2#xyzzy'],
                'This has <a href="https://vufind.org?foo=1&bar=2#xyzzy">https://vufind.org?foo=1&bar=2#xyzzy</a>'
                . ' in the middle of it',
            ],
            'two urls' => [
                'This has https://vufind.org and http://vufind.org in it',
                ['https://vufind.org', 'http://vufind.org'],
                'This has <a href="https://vufind.org">https://vufind.org</a> and '
                . '<a href="http://vufind.org">http://vufind.org</a> in it',
            ],
            'html specialchars encoded' => [
                'This has &lt;b&gt;http://vufind.org&lt;/b&gt; in the middle of it',
                ['http://vufind.org'],
                'This has &lt;b&gt;<a href="http://vufind.org">http://vufind.org</a>&lt;/b&gt; in the middle of it',
            ],
            'quotes' => [
                'This has http://vufind.org/path/with"quotes"/?q=search in the middle of it',
                ['http://vufind.org/path/with"quotes"/?q=search'],
                'This has <a href="http://vufind.org/path/with%22quotes%22/?q=search">'
                . 'http://vufind.org/path/with"quotes"/?q=search</a> in the middle of it',
            ],
            'no scheme' => [
                'This has vufind.org in the middle of it',
                ['http://vufind.org'],
                'This has <a href="http://vufind.org">vufind.org</a> in the middle of it',
            ],
            'already highlighted' => [
                'This has <a href="http://vufind.org">http://vufind.org</a> in the middle of it',
                ['http://vufind.org'],
                'This has <a href="http://vufind.org">http://vufind.org</a> in the middle of it',
            ],
            'email' => [
                'This has user@vufind.org in the middle of it',
                ['mailto:user@vufind.org'],
                'This has <a href="mailto:user@vufind.org">user@vufind.org</a> in the middle of it',
            ],
        ];
    }
}
