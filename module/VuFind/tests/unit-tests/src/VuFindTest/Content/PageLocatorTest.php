<?php

/**
 * Class PageLocatorTest
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2020.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Content;

use VuFind\Content\PageLocator;
use VuFindTheme\ThemeInfo;

/**
 * Content Page Locator Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PageLocatorTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Path to theme fixtures
     *
     * @var string
     */
    protected $fixturePath;

    /**
     * Constructor
     */
    public function setUp(): void
    {
        $this->fixturePath = realpath(__DIR__ . '/../../../../../../VuFindTheme/tests/unit-tests/fixtures/themes');
    }

    public function testDetermineTemplateAndRenderer()
    {
        $language  = 'aa';
        $defaultLanguage = 'bb';
        $pathPrefix = 'templates/page-locator-test/';
        $testCases = [
            [
                'pageName' => 'page1',
                'result' => [
                    'renderer' => 'phtml',
                    'path' => $this->fixturePath . '/parent/templates/page-locator-test/page1.phtml',
                    'page' => 'page1',
                ],
            ],
            [
                'pageName' => 'page2',
                'result' => [
                    'renderer' => 'phtml',
                    'path' => $this->fixturePath . '/parent/templates/page-locator-test/page2_aa.phtml',
                    'page' => 'page2_aa',
                ],
            ],
            [
                'pageName' => 'page3',
                'result' => [
                    'renderer' => 'phtml',
                    'path' => $this->fixturePath . '/parent/templates/page-locator-test/page3_bb.phtml',
                    'page' => 'page3_bb',
                ],
            ],
            [
                'pageName' => 'page4',
                'result' => [
                    'renderer' => 'md',
                    'path' => $this->fixturePath . '/parent/templates/page-locator-test/page4.md',
                    'page' => 'page4',
                ],
            ],
            [
                'pageName' => 'page5',
                'result' => [
                    'renderer' => 'md',
                    'path' => $this->fixturePath . '/parent/templates/page-locator-test/page5_aa.md',
                    'page' => 'page5_aa',
                ],
            ],
            [
                'pageName' => 'page6',
                'result' => [
                    'renderer' => 'md',
                    'path' => $this->fixturePath . '/parent/templates/page-locator-test/page6_bb.md',
                    'page' => 'page6_bb',
                ],
            ],
            [
                'pageName' => 'non-existant-page',
                'result' => null,
            ],
        ];
        $themeInfo = $this->getThemeInfo();
        $pageLocator = new PageLocator($themeInfo, $language, $defaultLanguage);
        foreach ($testCases as $case) {
            $this->assertEquals($case['result'], $pageLocator->determineTemplateAndRenderer($pathPrefix, $case['pageName']));
        }
    }

    /**
     * Get a test object
     *
     * @return ThemeInfo
     */
    protected function getThemeInfo()
    {
        return new ThemeInfo($this->fixturePath, 'parent');
    }
}
