<?php
/**
 * Config Factory Test Class
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
namespace VuFindTest\Config;
use VuFind\Config\Locator;

/**
 * Config Factory Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class PluginFactoryTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Flag -- did writing config files fail?
     *
     * @var bool
     */
    protected static $writeFailed = false;

    /**
     * Array of files to clean up after test.
     *
     * @var array
     */
    protected static $filesToDelete = [];

    /**
     * Plugin factory instance.
     *
     * @var \VuFind\Config\PluginFactory
     */
    protected $factory;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        // Create test files:
        $parentPath = Locator::getLocalConfigPath('unit-test-parent.ini', null, true);
        $parent = "[Section1]\n"
            . "a=1\nb=2\nc=3\n"
            . "[Section2]\n"
            . "d=4\ne=5\nf=6\n"
            . "[Section3]\n"
            . "g=7\nh=8\ni=9\n";
        $childPath = Locator::getLocalConfigPath('unit-test-child.ini', null, true);
        $child = "[Section1]\n"
            . "j=10\nk=11\nl=12\n"
            . "[Section2]\n"
            . "m=13\nn=14\no=15\n"
            . "[Parent_Config]\n"
            . "path=\"{$parentPath}\"\n"
            . "override_full_sections=Section1\n";

        // Fail if we are unable to write files:
        if (null === $parentPath || null === $childPath
            || !file_put_contents($parentPath, $parent)
            || !file_put_contents($childPath, $child)
        ) {
            self::$writeFailed = true;
            return;
        }

        // Mark for cleanup:
        self::$filesToDelete = [$parentPath, $childPath];
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        $this->factory = new \VuFind\Config\PluginFactory();
    }

    /**
     * Wrapper around factory
     *
     * @param string $name Configuration to load
     *
     * @return \Zend\Config\Config
     */
    protected function getConfig($name)
    {
        return $this->factory->createServiceWithName(
            $this->getMock('Zend\ServiceManager\ServiceLocatorInterface'),
            $name, $name
        );
    }

    /**
     * Test basic config.ini loading.
     *
     * @return void
     */
    public function testBasicRead()
    {
        // This should retrieve config.ini, which should have "Library Catalog"
        // set as the default system title.
        $config = $this->getConfig('config');
        $this->assertEquals('Library Catalog', $config->Site->title);
    }

    /**
     * Test loading of a custom .ini file.
     *
     * @return void
     */
    public function testCustomRead()
    {
        // This should retrieve sms.ini, which should include a Carriers array.
        $config = $this->getConfig('sms');
        $this->assertTrue(isset($config->Carriers) && count($config->Carriers) > 0);
    }

    /**
     * Test inheritance features.
     *
     * @return void
     */
    public function testInheritance()
    {
        if (self::$writeFailed) {
            $this->markTestSkipped('Could not write test configurations.');
        }

        // Make sure load succeeds:
        $config = $this->getConfig('unit-test-child');
        $this->assertTrue(is_object($config));

        // Make sure Section 1 was overridden; values from parent should not be
        // present.
        $this->assertTrue(!isset($config->Section1->a));
        $this->assertEquals('10', $config->Section1->j);

        // Make sure Section 2 was merged; values from parent and child should
        // both be present.
        $this->assertEquals('4', $config->Section2->d);
        $this->assertEquals('13', $config->Section2->m);

        // Make sure Section 3 was inherited; values from parent should exist.
        $this->assertEquals('7', $config->Section3->g);
    }

    /**
     * Test configuration is read-only.
     *
     * @return void
     */
    public function testReadOnlyConfig()
    {
        if (self::$writeFailed) {
            $this->markTestSkipped('Could not write test configurations.');
        }
        $config = $this->getConfig('unit-test-parent');
        $this->setExpectedException('Zend\Config\Exception\RuntimeException');
        $config->Section1->z = 'bad';
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        // Clean up test files:
        array_map('unlink', self::$filesToDelete);
    }
}