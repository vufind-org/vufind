<?php

/**
 * Content View Helper Test Class
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

use VuFind\ContentBlock\TemplateBased;
use VuFind\View\Helper\Root\Content;
use VuFind\View\Helper\Root\Context;

/**
 * Content View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ContentTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Perform a test of the helper.
     *
     * @param string $pageName           Name of the page
     * @param string $pathPrefix         Path where the template should be located
     * @param string $expectedPathPrefix Formatted version of $pathPrefix
     * @param array  $context            Optional array of context variables
     * @param string $pattern            Optional file system pattern to search page
     *
     * @return void
     */
    protected function performTest(
        string $pageName = 'foo',
        string $pathPrefix = 'content',
        string $expectedPathPrefix = 'templates/content/',
        array $context = ['bar' => 'baz'],
        ?string $pattern = null
    ): void {
        $mockTemplateBased = $this->getMockBuilder(TemplateBased::class)
            ->disableOriginalConstructor()->getMock();
        $contentBlockContext = ['context' => 'fakeContext'];
        $mockTemplateBased->expects($this->once())->method('getContext')
            ->with(
                $this->equalTo($expectedPathPrefix),
                $this->equalTo($pageName),
                $this->equalTo($pattern)
            )->will($this->returnValue($contentBlockContext));
        $mockContext = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()->getMock();
        $mockContext->expects($this->once())->method('renderInContext')
            ->with(
                $this->equalTo('ContentBlock/TemplateBased.phtml'),
                $this->equalTo($context + $contentBlockContext)
            )->will($this->returnValue('rendered-content'));
        $content = new Content($mockTemplateBased, $mockContext);
        // Confirm that expected content was rendered:
        $pageDetails = [];
        $this->assertEquals(
            'rendered-content',
            $content->renderTranslated(
                $pageName,
                $pathPrefix,
                $context,
                $pageDetails,
                $pattern
            )
        );
        // Confirm pass-by-reference array was updated:
        $this->assertEquals($contentBlockContext, $pageDetails);
    }

    /**
     * Test that the helper works when the $pathPrefix parameter has a trailing
     * slash.
     *
     * @return void
     */
    public function testBehaviorWithTrailingSlash(): void
    {
        $this->performTest('foo', 'content/');
    }

    /**
     * Test that the helper works when the $pathPrefix parameter has no trailing
     * slash.
     *
     * @return void
     */
    public function testBehaviorWithoutTrailingSlash(): void
    {
        $this->performTest('foo', 'content');
    }
}
