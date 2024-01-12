<?php

/**
 * AlphaBrowse view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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

use Laminas\View\Helper\Url;
use VuFind\View\Helper\Root\AlphaBrowse;

/**
 * AlphaBrowse view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AlphaBrowseTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock URL helper.
     *
     * @param string $expectedQuery Expected query
     *
     * @return Url
     */
    protected function getMockUrlHelper($expectedQuery): Url
    {
        $mock = $this->createMock(Url::class);
        $mock->expects($this->once())->method('__invoke')
            ->with(
                $this->equalTo('search-results'),
                $this->equalTo([]),
                $this->equalTo(['query' => $expectedQuery])
            )->will($this->returnValue('foo'));
        return $mock;
    }

    /**
     * Get configured AlphaBrowse helper for testing.
     *
     * @param Url   $url     URL helper
     * @param array $options Extra options
     *
     * @return AlphaBrowse
     */
    protected function getHelper(Url $url, array $options = []): AlphaBrowse
    {
        return new AlphaBrowse($url, $options);
    }

    /**
     * Test that get URL displays an appropriate link for multiple results with
     * default settings.
     *
     * @return void
     */
    public function testGetUrlWithMultipleRecordsAndDefaultSettings(): void
    {
        $url = $this->getMockUrlHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"xyzzy"',
                'dfApplied' => 1,
            ]
        );
        $helper = $this->getHelper($url);
        $item = ['heading' => 'xyzzy', 'count' => 2];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }

    /**
     * Test that get URL displays an appropriate link for a single result with
     * default settings.
     *
     * @return void
     */
    public function testGetUrlWithSingleRecordAndDefaultSettings(): void
    {
        $url = $this->getMockUrlHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"xyzzy"',
                'dfApplied' => 1,
                'jumpto' => 1,
            ]
        );
        $helper = $this->getHelper($url);
        $item = ['heading' => 'xyzzy', 'count' => 1];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }

    /**
     * Test that get URL properly escapes quotes in headings.
     *
     * @return void
     */
    public function testGetUrlEscapesQuotes(): void
    {
        $url = $this->getMockUrlHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"\\"xyzzy\\""',
                'dfApplied' => 1,
            ]
        );
        $helper = $this->getHelper($url);
        $item = ['heading' => '"xyzzy"', 'count' => 100];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }

    /**
     * Test that get URL omits dfApplied when the bypass_default_filters option is
     * false.
     *
     * @return void
     */
    public function testGetUrlAppliesFilterBypassSetting(): void
    {
        $url = $this->getMockUrlHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"xyzzy"',
            ]
        );
        $helper = $this->getHelper($url, ['bypass_default_filters' => false]);
        $item = ['heading' => 'xyzzy', 'count' => 100];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }
}
