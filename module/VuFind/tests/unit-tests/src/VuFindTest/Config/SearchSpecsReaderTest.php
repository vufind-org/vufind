<?php
/**
 * Config SearchSpecsReader Test Class
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
use VuFind\Config\SearchSpecsReader;

/**
 * Config SearchSpecsReader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchSpecsReaderTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

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
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        // Create test files:
        $parentPath = Locator::getLocalConfigPath('top.yaml', null, true);
        $parent = "top: foo";
        $childPath = Locator::getLocalConfigPath('middle.yaml', null, true);
        $child = "\"@parent_yaml\": $parentPath\nmiddle: bar";
        $grandchildPath = Locator::getLocalConfigPath('bottom.yaml', null, true);
        $grandchild = "\"@parent_yaml\": $childPath\nbottom: baz";

        // Fail if we are unable to write files:
        if (null === $parentPath || null === $childPath || null === $grandchildPath
            || !file_put_contents($parentPath, $parent)
            || !file_put_contents($childPath, $child)
            || !file_put_contents($grandchildPath, $grandchild)
        ) {
            self::$writeFailed = true;
            return;
        }

        // Mark for cleanup:
        self::$filesToDelete = [$parentPath, $childPath, $grandchildPath];
    }

    /**
     * Test loading of a YAML file.
     *
     * @return void
     */
    public function testSearchSpecsRead()
    {
        // The searchspecs.yaml file should define author dismax fields (among many
        // other things).
        $reader = $this->getServiceManager()->get(\VuFind\Config\SearchSpecsReader::class);
        $specs = $reader->get('searchspecs.yaml');
        $this->assertTrue(
            isset($specs['Author']['DismaxFields'])
            && !empty($specs['Author']['DismaxFields'])
        );
    }

    /**
     * Test loading of a non-existent YAML file.
     *
     * @return void
     */
    public function testMissingFileRead()
    {
        $reader = $this->getServiceManager()->get(\VuFind\Config\SearchSpecsReader::class);
        $specs = $reader->get('notreallyasearchspecs.yaml');
        $this->assertEquals([], $specs);
    }

    /**
     * Test direct loading of two single files.
     *
     * @return void
     */
    public function testYamlLoad()
    {
        $reader = new SearchSpecsReader();
        $core = $this->getFixtureDir() . 'configs/yaml/core.yaml';
        $local = $this->getFixtureDir() . 'configs/yaml/local.yaml';
        $this->assertEquals(
            [
                'top' => ['foo' => 'bar'],
                'bottom' => ['goo' => 'gar'],
            ],
            $this->callMethod($reader, 'getFromPaths', [$core])
        );
        $this->assertEquals(
            [
                'top' => ['foo' => 'xyzzy'],
                'middle' => ['moo' => 'cow'],
            ],
            $this->callMethod($reader, 'getFromPaths', [$local])
        );
    }

    /**
     * Test merging of two files.
     *
     * @return void
     */
    public function testYamlMerge()
    {
        $reader = new SearchSpecsReader();
        $core = $this->getFixtureDir() . 'configs/yaml/core.yaml';
        $local = $this->getFixtureDir() . 'configs/yaml/local.yaml';
        $this->assertEquals(
            [
                'top' => ['foo' => 'xyzzy'],
                'middle' => ['moo' => 'cow'],
                'bottom' => ['goo' => 'gar'],
            ],
            $this->callMethod($reader, 'getFromPaths', [$core, $local])
        );
    }

    /**
     * Test @parent_yaml directive.
     *
     * @return void
     */
    public function testParentYaml()
    {
        if (self::$writeFailed) {
            $this->markTestSkipped('Could not write test configurations.');
        }
        $reader = new SearchSpecsReader();
        $core = Locator::getLocalConfigPath('middle.yaml', null, true);
        $local = Locator::getLocalConfigPath('bottom.yaml', null, true);
        $this->assertEquals(
            [
                'top' => 'foo',
                'middle' => 'bar',
                'bottom' => 'baz',
            ],
            $this->callMethod($reader, 'getFromPaths', [$core, $local])
        );
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
