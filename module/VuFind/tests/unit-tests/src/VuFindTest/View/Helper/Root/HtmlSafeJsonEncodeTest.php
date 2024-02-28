<?php

/**
 * HtmlSafeJsonEncode View Helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

use Laminas\View\Helper\EscapeHtmlAttr;
use VuFind\View\Helper\Root\HtmlSafeJsonEncode;

/**
 * HtmlSafeJsonEncode View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HtmlSafeJsonEncodeTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Get helper to test
     *
     * @param array $plugins Array of extra plugins for renderer
     *
     * @return HtmlSafeJsonEncode
     */
    protected function getHelper(array $plugins = []): HtmlSafeJsonEncode
    {
        $helper = new HtmlSafeJsonEncode();
        $helper->setView($this->getPhpRenderer($plugins));
        return $helper;
    }

    /**
     * Test that the helper uses escapeHtmlAttr by default.
     *
     * @return void
     */
    public function testDefaultEscaping(): void
    {
        $escapeHtmlAttr = $this->getMockBuilder(EscapeHtmlAttr::class)
            ->disableOriginalConstructor()->getMock();
        $escapeHtmlAttr->expects($this->once())->method('__invoke')
            ->with($this->equalTo('1'))
            ->will($this->returnValue('1'));
        $this->assertEquals('1', ($this->getHelper(compact('escapeHtmlAttr')))(1));
    }

    /**
     * Data provider for JSON encoding tests
     *
     * @return array
     */
    public static function getJsonTests(): array
    {
        return [
            'string with special characters'
                => ['<\'">', '"\u003C\u0027\u0022\u003E"'],
            'array of special characters' => [
                ['<', '"', "'", '>', '&'],
                '["\u003C","\u0022","\u0027","\u003E","\u0026"]',
            ],
        ];
    }

    /**
     * Test escaping values without an outer helper enabled.
     *
     * @param mixed  $input          Input
     * @param string $expectedOutput Expected output
     *
     * @return void
     *
     * @dataProvider getJsonTests
     */
    public function testCoreEncoding($input, string $expectedOutput): void
    {
        $this->assertEquals($expectedOutput, ($this->getHelper())($input, null));
    }
}
