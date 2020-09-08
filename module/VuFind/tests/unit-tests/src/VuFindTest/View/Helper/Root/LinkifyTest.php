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
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;

use PHPUnit\Framework\MockObject\MockObject;
use VStelmakh\UrlHighlight\UrlHighlight;
use VuFind\View\Helper\Root\Linkify;
use VuFind\View\Helper\Root\ProxyUrl;
use VuFindTest\Unit\ViewHelperTestCase;

/**
 * Linkify Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LinkifyTest extends ViewHelperTestCase
{
    /**
     * @var ProxyUrl&MockObject
     */
    private $urlHighlight;

    /**
     * @var Linkify
     */
    private $linkify;

    public function setUp(): void
    {
        $this->urlHighlight = $this->createMock(UrlHighlight::class);
        $this->linkify = new Linkify($this->urlHighlight);
    }

    public function tearDown(): void
    {
        unset($this->proxyUrl, $this->urlHighlight);
    }

    public function testLinkify(): void
    {
        $this->urlHighlight
            ->expects(self::atLeastOnce())
            ->method('highlightUrls')
            ->willReturn('Text with highlighted urls');

        $actual = $this->linkify->__invoke('input text');
        self::assertSame('Text with highlighted urls', $actual);
    }
}
