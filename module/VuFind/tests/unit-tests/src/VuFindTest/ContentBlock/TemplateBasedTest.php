<?php
/**
 * TemplateBased ContentBlack Test Class
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\ContentBlock;

/**
 * TemplateBased ContentBlack Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TemplateBasedTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Test basic functionality of .phtml content block.
     *
     * @return void
     */
    public function testBasicPhtmlFunctionality()
    {
        $locator = $this->getMockBuilder(\VuFind\Content\PageLocator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $locator->expects($this->once())->method('determineTemplateAndRenderer')
            ->with($this->equalTo('templates/ContentBlock/TemplateBased/'), $this->equalTo('foo'))
            ->will($this->returnValue(['renderer' => 'phtml', 'page' => 'foo', 'path' => '/path/to/foo.phtml']));
        $block = new \VuFind\ContentBlock\TemplateBased($locator);
        $block->setConfig('foo');
        $this->assertEquals(['templateName' => 'foo'], $block->getContext());
    }

    /**
     * Test functionality of .phtml content block w/ i18n.
     *
     * @return void
     */
    public function testI18nPhtmlFunctionality()
    {
        $locator = $this->getMockBuilder(\VuFind\Content\PageLocator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $locator->expects($this->once())->method('determineTemplateAndRenderer')
            ->with($this->equalTo('templates/ContentBlock/TemplateBased/'), $this->equalTo('foo'))
            ->will($this->returnValue(['renderer' => 'phtml', 'page' => 'foo_en', 'path' => '/path/to/foo_en.phtml']));
        $block = new \VuFind\ContentBlock\TemplateBased($locator);
        $block->setConfig('foo');
        $this->assertEquals(['templateName' => 'foo_en'], $block->getContext());
    }

    /**
     * Test basic functionality of Markdown content block.
     *
     * @return void
     */
    public function testBasicMarkdownFunctionality()
    {
        $fixturePath = realpath($this->getFixtureDir('VuFindTheme') . 'themes');
        $file = $fixturePath . '/parent/templates/page-locator-test/page4.md';
        $locator = $this->getMockBuilder(\VuFind\Content\PageLocator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $locator->expects($this->once())->method('determineTemplateAndRenderer')
            ->with($this->equalTo('templates/ContentBlock/TemplateBased/'), $this->equalTo($file))
            ->will($this->returnValue(['renderer' => 'md', 'page' => 'page4', 'path' => $file]));
        $block = new \VuFind\ContentBlock\TemplateBased($locator);
        $block->setConfig($file);
        $this->assertEquals(['templateName' => 'markdown', 'data' => file_get_contents($file)], $block->getContext());
    }
}
