<?php

namespace VuFindTest\Config;

use VuFind\Config\Factory;
use VuFind\Config\Provider;
use VuFindTest\Unit\TestCase;
use Zend\EventManager\FilterChain;

class ProviderTest extends TestCase
{
    const FILE_COUNT = 12;
    const FILE_PATTERN = "**/*.{ini,json,yaml}";
    const BASE_PATH = __DIR__ . '/../../../../fixtures/configs/example';

    protected $base;

    protected $patterns;

    public function setUp()
    {
        Factory::init();
        $this->base = realpath(self::BASE_PATH);
        $this->patterns = [
            "$this->base/core/" . self::FILE_PATTERN,
            "$this->base/local/" . self::FILE_PATTERN
        ];
    }

    public function testGlobFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\Glob);
        $items = $chain->run(true, $this->patterns);

        $this->assertCount(self::FILE_COUNT, $items);

        $this->assertArraySubset([
            'base'    => "$this->base/core",
            'ext'     => 'ini',
            'path'    => "$this->base/core/ini.ini",
            'pattern' => "$this->base/core/" . self::FILE_PATTERN
        ], $items[0]);

        $this->assertArraySubset([
            'base'    => "$this->base/local",
            'ext'     => 'json',
            'path'    => "$this->base/local/nested/json.json",
            'pattern' => "$this->base/local/" . self::FILE_PATTERN
        ], $items[9]);
    }

    public function testLoadFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\Load);
        $items = $chain->run(true, [
            ['path' => "$this->base/core/ini.ini"],
            ['path' => "$this->base/core/nested/json.json"],
            ['path' => "$this->base/local/yaml.yaml"]
        ]);

        $this->assertArraySubset([
            'c' => true
        ], $items[0]['data']['S']['b']);

        $this->assertArraySubset([
            'u' => 'v'
        ], $items[1]['data']);

        $this->assertArraySubset([
            'key' => 43
        ], $items[2]['data']);
    }

    public function testFlatIniLoadFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\FlatIni);
        $chain->attach(new Provider\Filter\Load);
        $result = $chain->run(true, [
            ['path' => "$this->base/core/ini.ini"]
        ]);
        $this->assertArraySubset([
            'b.c' => '1'
        ], $result[0]['data']['S']);
    }

    public function testNestFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\Nest);
        $result = $chain->run(true, [
            [
                'base' => '/base',
                'path' => '/base/a/b.ini',
                'data' => ['key' => 'value']
            ]
        ]);
        $this->assertArraySubset([
            'key' => 'value'
        ], $result[0]['data']['a']['b']);
    }

    public function testUniqueSuffixFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\UniqueSuffix);
        $result = $chain->run(true, [
            [
                'base' => '/a',
                'path' => '/a/u/v.ini',
                'data' => ['x' => '0']
            ],
            [
                'base' => '/b',
                'path' => '/b/u/v.ini',
                'data' => ['x' => '1']
            ]
        ]);

        $this->assertArraySubset([
            'base' => '/b',
            'path' => '/b/u/v.ini',
            'data' => ['x' => '1']
        ], $result[0]);
    }

    public function testMergeFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\Merge);
        $result = $chain->run(true, [
            [
                'data' => [
                    'u' => true,
                    'v' => ['w' => 5, 'x' => 'y']
                ]
            ],
            [
                'data' => [
                    'u' => false,
                    'v' => ['x' => 'z']
                ]
            ]
        ]);

        $this->assertEquals([
            'u' => false,
            'v' => ['w' => 5, 'x' => 'z']
        ], $result);
    }

    public function testParentYamlFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\ParentYaml);
        $result = $chain->run(true, [
            [
                'ext'  => 'yaml',
                'data' => [
                    '@parent_yaml' => "$this->base/core/yaml.yaml",
                    'child_key'    => 'value',
                    'key'          => 'value'
                ]
            ]
        ]);

        $this->assertArraySubset([
            '%weired;Key$\\' => true,
            'child_key'      => 'value',
            'key'            => 'value'
        ], $result[0]['data']);
    }

    public function testParentIniFilter()
    {
        $chain = new FilterChain;
        $chain->attach(new Provider\Filter\ParentIni);
        $result = $chain->run(true, [
            [
                'ext'  => 'ini',
                'path' => "$this->base/local/ini.ini",
                'data' => [
                    'Parent_Config' => [
                        'relative_path' => '../core/ini.ini'
                    ],
                    'S'             => ['u' => 'v']
                ]
            ],
            [
                'ext'  => 'ini',
                'path' => "$this->base/local/ini.ini",
                'data' => [
                    'Parent_Config' => [
                        'path' => "$this->base/core/ini.ini"
                    ],
                    'S'             => ['u' => 'v']
                ]
            ],
        ]);

        $this->assertArraySubset([
            'a' => 1, 'u' => 'v'
        ], $result[0]['data']['S']);

        $this->assertArraySubset([
            'a' => 1, 'u' => 'v'
        ], $result[1]['data']['S']);
    }

    public function testBasicProvider()
    {
        $data = (new Provider\Basic($this->patterns))();

        $this->assertArraySubset([
            'a' => '2', 'b' => ['c' => '1', 'd' => '0']
        ], $data['ini']['S']);

        $this->assertArraySubset([
            'x' => 'z', 'a' => 'b'
        ], $data['nested']['ini']['T']);
    }

    public function testClassicProvider()
    {
        $data = (new Provider\Classic($this->patterns))();

        $this->assertArraySubset([
            'a' => '2', 'b.c' => '1', 'b.d' => '0'
        ], $data['ini']['S']);

        $this->assertArraySubset([
            'x' => 'z', 'a' => 'b'
        ], $data['nested']['ini']['T']);

        $this->assertArraySubset([
            '%weired;Key$\\' => true,
            'key' => 43
        ], $data['yaml']);

        $this->assertArraySubset([
            'a' => ['b' => false]
        ], $data['json']);
    }
}