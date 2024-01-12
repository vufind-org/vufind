<?php

/**
 * Config SearchSpecsReader Test Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

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
class SearchSpecsReaderTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\PathResolverTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test loading of a YAML file.
     *
     * @return void
     */
    public function testSearchSpecsRead()
    {
        // The searchspecs.yaml file should define author dismax fields (among many
        // other things).
        $reader = new SearchSpecsReader();
        $specs = $reader->get('searchspecs.yaml');
        $this->assertTrue(!empty($specs['Author']['DismaxFields']));
    }

    /**
     * Test loading of a non-existent YAML file.
     *
     * @return void
     */
    public function testMissingFileRead()
    {
        $reader = new SearchSpecsReader();
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
        $reader = new SearchSpecsReader(
            null,
            $this->getPathResolver($this->getFixtureDir() . 'configs/inheritance')
        );

        $this->assertEquals(
            [
                'top' => 'foo',
                'middle' => 'bar',
            ],
            $reader->get('middle.yaml')
        );

        $this->assertEquals(
            [
                'top' => 'foo',
                'middle' => 'bar',
                'bottom' => 'baz',
            ],
            $reader->get('bottom.yaml')
        );

        $this->assertEquals(
            [
                'top' => 'foo',
                'middle' => 'bar',
                'bottom' => 'baz',
            ],
            $this->callMethod(
                $reader,
                'getFromPaths',
                [
                    $this->getFixturePath(
                        'configs/inheritance/config/vufind/middle.yaml'
                    ),
                    $this->getFixturePath(
                        'configs/inheritance/config/vufind/bottom.yaml'
                    ),
                ]
            )
        );
    }
}
