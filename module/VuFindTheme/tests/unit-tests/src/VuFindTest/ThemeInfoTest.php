<?php
/**
 * ThemeInfo Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest;
use VuFindTheme\ThemeInfo;

/**
 * ThemeInfo Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ThemeInfoTest extends Unit\TestCase
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
    public function __construct()
    {
        $this->fixturePath = realpath(__DIR__ . '/../../fixtures/themes');
    }

    /**
     * Test getBaseDir
     *
     * @return void
     */
    public function testGetBaseDir()
    {
        $this->assertEquals($this->fixturePath, $this->getThemeInfo()->getBaseDir());
    }

    /**
     * Test get/setTheme
     *
     * @return void
     */
    public function testThemeSetting()
    {
        $ti = $this->getThemeInfo();
        $this->assertEquals('parent', $ti->getTheme()); // default
        $ti->setTheme('child');
        $this->assertEquals('child', $ti->getTheme());
    }

    /**
     * Test setting invalid theme
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Cannot load theme: invalid
     */
    public function testInvalidTheme()
    {
        $this->getThemeInfo()->setTheme('invalid');
    }

    /**
     * Test theme info
     *
     * @return void
     */
    public function testGetThemeInfo()
    {
        $ti = $this->getThemeInfo();
        $ti->setTheme('child');
        $this->assertEquals(['child' => ['extends' => 'parent'], 'parent' => ['extends' => false]], $ti->getThemeInfo());
    }

    /**
     * Test unfindable item.
     *
     * @return void
     */
    public function testUnfindableItem()
    {
        $this->assertNull($this->getThemeInfo()->findContainingTheme('does-not-exist'));
    }

    /**
     * Test findContainingTheme()
     *
     * @return void
     */
    public function testFindContainingTheme()
    {
        $ti = $this->getThemeInfo();
        $ti->setTheme('child');
        $this->assertEquals('child', $ti->findContainingTheme('child.txt'));
        $this->assertEquals('parent', $ti->findContainingTheme('parent.txt'));
        $this->assertEquals($this->fixturePath . '/parent/parent.txt', $ti->findContainingTheme('parent.txt', true));
        $expected = ['theme' => 'parent', 'path' => $this->fixturePath . '/parent/parent.txt'];
        $this->assertEquals($expected, $ti->findContainingTheme('parent.txt', ThemeInfo::RETURN_ALL_DETAILS));
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