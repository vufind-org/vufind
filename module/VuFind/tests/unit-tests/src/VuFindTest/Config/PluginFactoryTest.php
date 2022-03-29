<?php
/**
 * Config Factory Test Class
 *
 * PHP version 7
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Config;

use VuFind\Config\Locator;

/**
 * Config Factory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PluginFactoryTest extends \PHPUnit\Framework\TestCase
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
    public static function setUpBeforeClass(): void
    {
        // Create test files:
        $parentPath = Locator::getLocalConfigPath('unit-test-parent.ini', null, true);
        $parent = "[Section1]\n"
            . "a=1\nb=2\nc=3\n"
            . "[Section2]\n"
            . "d=4\ne=5\nf=6\n"
            . "[Section3]\n"
            . "g=7\nh=8\ni=9\n"
            . "[Section4]\n"
            . "j[] = 1\nj[] = 2\nk[a] = 1\nk[b] = 2\n";
        $childPath = Locator::getLocalConfigPath('unit-test-child.ini', null, true);
        $child = "[Section1]\n"
            . "j=10\nk=11\nl=12\n"
            . "[Section2]\n"
            . "m=13\nn=14\no=15\n"
            . "[Section4]\n"
            . "j[] = 3\nk[c] = 3\n"
            . "[Parent_Config]\n"
            . "path=\"{$parentPath}\"\n"
            . "override_full_sections=Section1\n";
        $child2Path = Locator::getLocalConfigPath('unit-test-child2.ini', null, true);
        $child2 = "[Section1]\n"
            . "j=10\nk=11\nl=12\n"
            . "[Section2]\n"
            . "m=13\nn=14\no=15\n"
            . "[Section4]\n"
            . "j[] = 3\nk[c] = 3\n"
            . "[Parent_Config]\n"
            . "path=\"{$parentPath}\"\n"
            . "override_full_sections=Section1\n"
            . "merge_array_settings=true\n";

        // Fail if we are unable to write files:
        if (null === $parentPath || null === $childPath || null === $child2Path
            || !file_put_contents($parentPath, $parent)
            || !file_put_contents($childPath, $child)
            || !file_put_contents($child2Path, $child2)
        ) {
            self::$writeFailed = true;
            return;
        }

        // Mark for cleanup:
        self::$filesToDelete = [$parentPath, $childPath, $child2Path];
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->factory = new \VuFind\Config\PluginFactory();
    }

    /**
     * Wrapper around factory
     *
     * @param string $name Configuration to load
     *
     * @return \Laminas\Config\Config
     */
    protected function getConfig($name)
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        return ($this->factory)($container, $name);
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

        // Make sure Section 4 arrays were overwritten.
        $this->assertEquals([3], $config->Section4->j->toArray());
        $this->assertEquals(['c' => 3], $config->Section4->k->toArray());
    }

    /**
     * Test inheritance features with array merging turned on.
     *
     * @return void
     */
    public function testInheritanceWithArrayMerging()
    {
        if (self::$writeFailed) {
            $this->markTestSkipped('Could not write test configurations.');
        }

        // Make sure load succeeds:
        $config = $this->getConfig('unit-test-child2');
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

        // Make sure Section 4 arrays were overwritten.
        $this->assertEquals([1, 2, 3], $config->Section4->j->toArray());
        $this->assertEquals(
            ['a' => 1, 'b' => 2, 'c' => 3],
            $config->Section4->k->toArray()
        );
    }

    /**
     * Test that the plugin factory omits the Parent_Config section from the
     * merged configuration.
     *
     * @return void
     */
    public function testParentConfigOmission()
    {
        if (self::$writeFailed) {
            $this->markTestSkipped('Could not write test configurations.');
        }
        $config = $this->getConfig('unit-test-child');
        $this->assertFalse(isset($config->Parent_Config));
    }

    /**
     * Test configuration is read-only.
     *
     * @return void
     */
    public function testReadOnlyConfig()
    {
        $this->expectException(\Laminas\Config\Exception\RuntimeException::class);

        if (self::$writeFailed) {
            $this->markTestSkipped('Could not write test configurations.');
        }
        $config = $this->getConfig('unit-test-parent');
        $config->Section1->z = 'bad';
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        // Clean up test files:
        array_map('unlink', self::$filesToDelete);
    }
}
