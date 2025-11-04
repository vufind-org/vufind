<?php

/**
 * YamlReader test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Config;

use Finna\Config\YamlReader;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\PathResolver;
use VuFindTest\Feature\FixtureTrait;

/**
 * YamlReader test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class YamlReaderTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Get testGetFinna data
     *
     * @return Generator
     */
    public static function getTestGetFinnaData(): Generator
    {
        yield 'Test merge yaml files' => [
            'merge.yaml',
            [
                'Section' => [
                    'SubSection' => [
                        'test' => [
                            'Title' => 'Yaml vufind title',
                            'Description' => 'Yaml vufind Description',
                            'Index' => 5,
                        ],
                        'second_test' => [
                            'Title' => 'Yaml finna title',
                            'Description' => 'Yaml finna Description',
                        ],
                    ],
                ],
                'Section_2' => [
                    'SubSection_2' => [
                        'some' => [
                            'nope' => 'nope',
                        ],
                        'where' => [
                            'array',
                            'here',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get YamlReader.
     *
     * @param array $pathResolverMap Path resolver map
     *
     * @return MockObject&YamlReader
     */
    public function getReader(array $pathResolverMap): MockObject&YamlReader
    {
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->expects($this->any())->method('getLocalConfigPath')->willReturnMap($pathResolverMap);
        $reader = $this->getMockBuilder(YamlReader::class)
            ->onlyMethods([])
            ->setConstructorArgs([
                $pathResolver,
                null,
            ])->getMock();
        return $reader;
    }

    /**
     * Test getFinna method
     *
     * @param string $fixture  Fixture name
     * @param array  $expected Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetFinnaData')]
    public function testGetFinna(string $fixture, array $expected): void
    {
        $resolverMap = [
            [$fixture, 'local/vufind', $this->getFixtureDir('Finna') . 'yaml_files/vufind/' . $fixture],
            [$fixture, 'local/finna', $this->getFixtureDir('Finna') . 'yaml_files/finna/' . $fixture],
        ];
        $yamlReader = $this->getReader($resolverMap);
        $resultVufind = $yamlReader->getFinna($fixture, 'local/vufind');
        $this->assertEquals($expected, $resultVufind);
    }
}
