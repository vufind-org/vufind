<?php

/**
 * TemplatePath view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper;

use VuFindTheme\View\Helper\TemplatePath;

/**
 * TemplatePath view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TemplatePathTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Path to theme fixtures
     *
     * @var string
     */
    protected $fixturePath;

    /**
     * Constructor
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->fixturePath
            = realpath($this->getFixtureDir('VuFindTheme') . 'themes');
    }

    /**
     * Get a populated resource container for testing.
     *
     * @return TemplatePath
     */
    protected function getHelper()
    {
        // Get mock TemplateStack
        $stackMock = $this->getMockBuilder(\Laminas\View\Resolver\TemplatePathStack::class)
            ->disableOriginalConstructor()->getMock();

        $return = new \SplStack();
        $return->push("{$this->fixturePath}/asdf/templates/");
        $return->rewind();

        $stackMock->expects($this->any())
            ->method('getPaths')
            ->will($this->returnValue($return));

        // Make helper
        return new TemplatePath($stackMock);
    }

    /**
     * Test the basic parent function.
     *
     * @return void
     */
    public function testExists()
    {
        $helper = $this->getHelper();
        $this->assertEquals(
            "{$this->fixturePath}/parent/templates/everything.phtml",
            $helper('everything.phtml', 'parent')
        );
    }

    /**
     * Test thrown error
     *
     * @return void
     */
    public function testThemeDoesntExist()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Template not found in missing: file.phtml');

        $helper = $this->getHelper();
        $helper('file.phtml', 'missing');
    }

    /**
     * Test thrown error
     *
     * @return void
     */
    public function testFileDoesntExist()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Template not found in parent: missing.phtml');

        $helper = $this->getHelper();
        $helper('missing.phtml', 'parent');
    }
}
