<?php

/**
 * Linkify Test Class
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VStelmakh\UrlHighlight\UrlHighlight;
use VuFind\View\Helper\Root\Linkify;

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
class LinkifyTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that Linkify proxies the UrlHighlight object as expected.
     *
     * @return void
     */
    public function testLinkify(): void
    {
        $urlHighlight = $this->createMock(UrlHighlight::class);
        $urlHighlightExceptEmail = $this->createMock(UrlHighlight::class);
        $linkify = new Linkify($urlHighlight, $urlHighlightExceptEmail);
        $urlHighlight
            ->expects($this->once())
            ->method('highlightUrls')
            ->with($this->equalTo('input text'))
            ->willReturn('Text with highlighted urls');

        $urlHighlightExceptEmail
            ->expects($this->once())
            ->method('highlightUrls')
            ->with($this->equalTo('input text'))
            ->willReturn('Text with highlighted urls except emails');

        $actual = ($linkify)('input text');
        $this->assertSame('Text with highlighted urls', $actual);

        $actual = ($linkify)('input text', false);
        $this->assertSame('Text with highlighted urls except emails', $actual);
    }
}
