<?php

/**
 * ThemeInfo Test Class
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

namespace VuFindTest;

use Laminas\Cache\Storage\StorageInterface;
use VuFindTheme\ThemeInfo;

/**
 * ThemeInfo Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeInfoTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Path to theme fixtures
     *
     * @var string
     */
    protected $fixturePath;

    /**
     * Generic setup function
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->fixturePath
            = realpath($this->getFixtureDir('VuFindTheme') . 'themes');
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
     */
    public function testInvalidTheme()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot load theme: invalid');

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
        $expectedChild = include "{$this->fixturePath}/child/theme.config.php";
        $expectedParent = include "{$this->fixturePath}/parent/theme.config.php";
        $this->assertEquals('parent', $expectedChild['extends']);
        $this->assertEquals(false, $expectedParent['extends']);
        $this->assertEquals(
            ['child' => $expectedChild, 'parent' => $expectedParent],
            $ti->getThemeInfo()
        );
    }

    /**
     * Test theme info with a mixin
     *
     * @return void
     */
    public function testGetThemeInfoWithMixin()
    {
        $ti = $this->getThemeInfo();
        $ti->setTheme('mixin_user');
        $expectedChild = include "{$this->fixturePath}/child/theme.config.php";
        $expectedParent = include "{$this->fixturePath}/parent/theme.config.php";
        $expectedMixin = include "{$this->fixturePath}/mixin/mixin.config.php";
        $expectedMixinUser
            = include "{$this->fixturePath}/mixin_user/theme.config.php";
        $this->assertEquals('parent', $expectedChild['extends']);
        $this->assertEquals(false, $expectedParent['extends']);
        $this->assertEquals(
            [
                'mixin' => $expectedMixin,
                'mixin_user' => $expectedMixinUser,
                'child' => $expectedChild,
                'parent' => $expectedParent,
            ],
            $ti->getThemeInfo()
        );
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
        $this->assertEquals(
            $this->fixturePath . '/parent/parent.txt',
            $ti->findContainingTheme('parent.txt', true)
        );
        $expected = [
            'theme' => 'parent',
            'path' => $this->fixturePath . '/parent/parent.txt',
            'relativePath' => 'parent.txt',
        ];
        $this->assertEquals($expected, $ti->findContainingTheme('parent.txt', ThemeInfo::RETURN_ALL_DETAILS));
    }

    /**
     * Test findContainingTheme() with a mixin
     *
     * @return void
     */
    public function testFindContainingThemeWithMixin()
    {
        $ti = $this->getThemeInfo();
        $ti->setTheme('mixin_user');
        $this->assertEquals('mixin', $ti->findContainingTheme('js/mixin.js'));
        $this->assertEquals('child', $ti->findContainingTheme('child.txt'));
    }

    /**
     * Test findInThemes()
     *
     * @return void
     */
    public function testFindInThemes()
    {
        $ti = $this->getThemeInfo();
        $ti->setTheme('child');
        $files = $ti->findInThemes(
            [
                'templates/content/*.phtml',
                'templates/content/*.md',
            ]
        );
        $this->assertIsArray($files);
        $this->assertCount(3, $files);
        $this->assertEquals('parent', $files[0]['theme']);
        $this->assertEquals(
            'templates/content/page1.phtml',
            $files[0]['relativeFile']
        );
        $this->assertEquals('child', $files[1]['theme']);
        $this->assertEquals(
            'templates/content/page2.phtml',
            $files[1]['relativeFile']
        );
        $this->assertEquals('parent', $files[2]['theme']);
        $this->assertEquals(
            'templates/content/page3.md',
            $files[2]['relativeFile']
        );
    }

    /**
     * Test getMergedConfig() with a basic theme
     *
     * @return void
     */
    public function testGetMergedConfigParentOnly()
    {
        // Parent
        $ti = $this->getThemeInfo();
        $parentJS = $ti->getMergedConfig('js');
        $this->assertEquals(['hello.js'], $parentJS);
        // recursive
        $parentHelpers = $ti->getMergedConfig('helpers');
        $this->assertEquals(
            'fooFactory',
            $parentHelpers['factories']['foo']
        );
    }

    /**
     * Test getMergedConfig() using a child theme
     *
     * @return void
     */
    public function testGetMergedConfigChild()
    {
        // Child with parents merged in
        $ti = $this->getThemeInfo();
        $ti->setTheme('child');
        $childJS = $ti->getMergedConfig('js');
        $this->assertEquals(['hello.js', 'extra.js'], $childJS);
        // recursive
        $childHelpers = $ti->getMergedConfig('helpers');
        $this->assertEquals(
            'fooOverrideFactory',
            $childHelpers['factories']['foo']
        );
    }

    /**
     * Test getMergedConfig() using a mixin
     *
     * @return void
     */
    public function testGetMergedConfigMixin()
    {
        // Theme using a mixin
        $ti = $this->getThemeInfo();
        $ti->setTheme('mixin_user');
        $mixinJS = $ti->getMergedConfig('js');
        $this->assertEquals(['hello.js', 'extra.js', 'mixin.js'], $mixinJS);
        $mixinHelpers = $ti->getMergedConfig('helpers');
        $this->assertEquals(
            'fooMixinFactory',
            $mixinHelpers['factories']['foo']
        );
    }

    /**
     * Test getMergedConfig() on string value in config
     *
     * @return void
     */
    public function testGetMergedConfigReturnString()
    {
        $ti = $this->getThemeInfo();
        $doctype = $ti->getMergedConfig('doctype');
        $this->assertEquals('HTML5', $doctype);
    }

    /**
     * Test getMergedConfig() with no key (return all)
     *
     * @return void
     */
    public function testGetMergedConfigNoKey()
    {
        $ti = $this->getThemeInfo();
        $config = $ti->getMergedConfig();
        $this->assertEquals('HTML5', $config['doctype']);
        $this->assertEqualsCanonicalizing(
            ['doctype', 'extends', 'js', 'helpers'],
            array_keys($config)
        );
    }

    /**
     * Stress-test our merging algorithm
     *
     * @param array $test     Test data
     * @param array $expected Expected response
     *
     * @dataProvider mergeEdgeCasesProvider
     *
     * @return void
     */
    public function testMergeWithoutOverrideEdgeCases($test, $expected)
    {
        $ti = $this->getThemeInfo();

        $merged = $this->callMethod($ti, 'mergeRecursive', $test);

        $this->assertEquals($expected, $merged);
    }

    /**
     * Test cases for mergeWithoutOverride
     *
     * @return array
     */
    public static function mergeEdgeCasesProvider(): array
    {
        return [
            // string
            [
                [
                    'override',
                    'original',
                ],
                'original',
            ],

            // array
            [
                [
                    ['override'],
                    ['original'],
                ],
                ['override', 'original'],
            ],

            // string-keyed arrays
            [
                [
                    ['array' => [2], 'string' => 'override', 'sub' => ['a' => 2]],
                    ['array' => [1], 'string' => 'original', 'sub' => ['a' => 1]],
                ],
                ['array' => [2, 1], 'string' => 'original', 'sub' => ['a' => 1]],
            ],

            // string-keyed arrays: missing
            [
                [
                    ['shared' => [1], 'child' => 'only'],
                    ['shared' => [1], 'parent' => 'only'],
                ],
                ['shared' => [1, 1], 'parent' => 'only', 'child' => 'only'],
            ],

            // string-keyed string -> array
            [
                [
                    ['mixed' => 'string'],
                    ['mixed' => ['array']],
                ],
                ['mixed' => ['string', 'array']],
            ],

            // string-keyed array -> string
            [
                [
                    ['mixed' => ['array']],
                    ['mixed' => 'string'],
                ],
                ['mixed' => ['array', 'string']],
            ],

            // arrays and strings
            [
                [
                    'not an array',
                    ['mixed' => ['array']],
                ],
                [
                    'mixed' => ['array'],
                ],
            ],
        ];
    }

    /**
     * Test that caching works correctly.
     *
     * @return void
     */
    public function testCaching(): void
    {
        $key = 'parent_doctype';
        $expected = 'HTML5';

        // Create a mock cache that simulates normal cache functionality;
        // the first call to getItem returns null, then it expects a call
        // to setItem, and then the second call to getItem will return an
        // expected value.
        $cache = $this->getMockBuilder(StorageInterface::class)->getMock();
        $cache->expects($this->exactly(2))->method('getItem')
            ->with($this->equalTo($key))
            ->willReturnOnConsecutiveCalls(null, $expected);
        $cache->expects($this->once())->method('setItem')
            ->with($this->equalTo($key), $this->equalTo($expected));

        // Set cache
        $ti = $this->getThemeInfo();
        $ti->setCache($cache);

        // Invoke the helper twice to meet the expectations of the cache mock:
        $ti->getMergedConfig('doctype');
        $ti->getMergedConfig('doctype');
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
