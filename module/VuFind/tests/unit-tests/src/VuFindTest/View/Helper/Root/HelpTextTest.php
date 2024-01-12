<?php

/**
 * HelpText View Helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

use VuFind\View\Helper\Root\Content;
use VuFind\View\Helper\Root\HelpText;

/**
 * HelpText View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HelpTextTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a mock Content helper which will return a specific value and set a
     * specific set of page details when renderTranslated() is called.
     *
     * @param string $mockReturnValue The output of renderTranslated
     * @param array  $mockPageDetails The reference data set by renderTranslated
     * @param string $topic           The topic expected by renderTranslated
     * @param array  $context         The context expected by renderTranslated
     *
     * @return Content
     */
    protected function getMockContentHelper(
        string $mockReturnValue,
        array $mockPageDetails = [],
        string $topic = 'foo',
        array $context = []
    ): Content {
        $helper = $this->getMockBuilder(Content::class)
            ->disableOriginalConstructor()->getMock();
        $callback = function ($unused1, $unused2, $unused3, &$pageDetails) use ($mockPageDetails, $mockReturnValue) {
            $pageDetails = $mockPageDetails;
            return $mockReturnValue;
        };
        $helper->expects($this->once())->method('renderTranslated')
            ->with(
                $this->equalTo($topic),
                $this->equalTo('HelpTranslations'),
                $this->equalTo($context),
                $this->equalTo(null),
                $this->equalTo('%pathPrefix%/%language%/%pageName%')
            )->will($this->returnCallback($callback));
        return $helper;
    }

    /**
     * Test that an appropriate warning is set when help is missing.
     *
     * @return void
     */
    public function testMissingHelp(): void
    {
        $content = $this->getMockContentHelper('');
        $helpText = new HelpText($content);
        $this->assertEquals('', $helpText->render('foo'));
        $this->assertEquals(['help_page_missing'], $helpText->getWarnings());
    }

    /**
     * Test that an appropriate warning is set when help is not found in the
     * requested language.
     *
     * @return void
     */
    public function testMissingLanguageHelp(): void
    {
        $details = ['pageLocatorDetails' => ['matchType' => 'pageName']];
        $content = $this->getMockContentHelper('Foo', $details);
        $helpText = new HelpText($content);
        $this->assertEquals('Foo', $helpText->render('foo'));
        $msg = 'Sorry, but the help you requested is unavailable in your language.';
        $this->assertEquals([$msg], $helpText->getWarnings());
    }

    /**
     * Test an entirely successful render operation.
     *
     * @return void
     */
    public function testSuccess(): void
    {
        $details = ['pageLocatorDetails' => ['matchType' => 'language']];
        $content = $this->getMockContentHelper('Foo', $details);
        $helpText = new HelpText($content);
        $this->assertEquals('Foo', $helpText->render('foo'));
        $this->assertEquals([], $helpText->getWarnings());
    }
}
