<?php
/**
 * ThemeCompiler Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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
use VuFindTheme\ThemeCompiler;
use VuFindTheme\ThemeInfo;

/**
 * ThemeCompiler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeCompilerTest extends Unit\TestCase
{
    /**
     * Path to theme fixtures
     *
     * @var string
     */
    protected $fixturePath;

    /**
     * ThemeInfo object for tests
     *
     * @var ThemeInfo
     */
    protected $info;

    /**
     * Path where new theme will be created
     *
     * @var string
     */
    protected $targetPath;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->fixturePath = realpath(__DIR__ . '/../../fixtures/themes');
        $this->info = new ThemeInfo($this->fixturePath, 'parent');
        $this->targetPath = $this->info->getBaseDir() . '/compiled';
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if the target directory already exists:
        if (is_dir($this->targetPath)) {
            return $this->markTestSkipped('compiled theme already exists.');
        }
    }

    /**
     * Test the compiler.
     *
     * @return void
     */
    public function testStandardCompilation()
    {
        $baseDir = $this->info->getBaseDir();
        $parentDir = $baseDir . '/parent';
        $childDir = $baseDir . '/child';
        $compiler = $this->getThemeCompiler();
        $result = $compiler->compile('child', 'compiled');

        // Did the compiler report success?
        $this->assertEquals('', $compiler->getLastError());
        $this->assertTrue($result);

        // Was the target directory created with the expected files?
        $this->assertTrue(is_dir($this->targetPath));
        $this->assertTrue(file_exists("{$this->targetPath}/parent.txt"));
        $this->assertTrue(file_exists("{$this->targetPath}/child.txt"));

        // Did the right version of the  file that exists in both parent and child
        // get copied over?
        $this->assertEquals(
            file_get_contents("$childDir/js/hello.js"),
            file_get_contents("{$this->targetPath}/js/hello.js")
        );
        $this->assertNotEquals(
            file_get_contents("$parentDir/js/hello.js"),
            file_get_contents("{$this->targetPath}/js/hello.js")
        );

        // Did the configuration merge correctly?
        $expectedConfig = [
            'extends' => false,
            'css' => ['child.css'],
            'js' => ['hello.js', 'extra.js'],
            'helpers' => [
                'factories' => [
                    'foo' => 'fooOverrideFactory',
                    'bar' => 'barFactory',
                ],
                'invokables' => [
                    'xyzzy' => 'Xyzzy',
                ]
            ],
        ];
        $mergedConfig = include "{$this->targetPath}/theme.config.php";
        $this->assertEquals($expectedConfig, $mergedConfig);
    }

    /**
     * Test the compiler with a mixin.
     *
     * @return void
     */
    public function testStandardCompilationWithMixin()
    {
        $baseDir = $this->info->getBaseDir();
        $parentDir = $baseDir . '/parent';
        $childDir = $baseDir . '/child';
        $mixinDir = $baseDir . '/mixin';
        $compiler = $this->getThemeCompiler();
        $result = $compiler->compile('mixin_user', 'compiled');

        // Did the compiler report success?
        $this->assertEquals('', $compiler->getLastError());
        $this->assertTrue($result);

        // Was the target directory created with the expected files?
        $this->assertTrue(is_dir($this->targetPath));
        $this->assertTrue(file_exists("{$this->targetPath}/parent.txt"));
        $this->assertTrue(file_exists("{$this->targetPath}/child.txt"));
        $this->assertTrue(file_exists("{$this->targetPath}/js/mixin.js"));

        // Did the right version of the  file that exists in both parent and child
        // get copied over?
        $this->assertEquals(
            file_get_contents("$mixinDir/js/hello.js"),
            file_get_contents("{$this->targetPath}/js/hello.js")
        );
        $this->assertNotEquals(
            file_get_contents("$childDir/js/hello.js"),
            file_get_contents("{$this->targetPath}/js/hello.js")
        );
        $this->assertNotEquals(
            file_get_contents("$parentDir/js/hello.js"),
            file_get_contents("{$this->targetPath}/js/hello.js")
        );

        // Did the configuration merge correctly?
        $expectedConfig = [
            'extends' => false,
            'css' => ['child.css'],
            'js' => ['hello.js', 'extra.js', 'mixin.js'],
            'helpers' => [
                'factories' => [
                    'foo' => 'fooOverrideFactory',
                    'bar' => 'barFactory',
                ],
                'invokables' => [
                    'xyzzy' => 'Xyzzy',
                ]
            ],
        ];
        $mergedConfig = include "{$this->targetPath}/theme.config.php";
        $this->assertEquals($expectedConfig, $mergedConfig);
    }

    /**
     * Test overwrite protection.
     *
     * @return void
     */
    public function testOverwriteProtection()
    {
        // First, compile the theme:
        $compiler = $this->getThemeCompiler();
        $this->assertTrue($compiler->compile('child', 'compiled'));

        // Now confirm that by default, we're not allowed to recompile it on
        // top of itself...
        $this->assertFalse($compiler->compile('child', 'compiled'));
        $this->assertEquals(
            "Cannot overwrite {$this->targetPath} without --force switch!",
            $compiler->getLastError()
        );

        // Write a file into the compiled theme so we can check that it gets
        // removed when we force a recompile:
        $markerFile = $this->targetPath . '/fake-marker.txt';
        file_put_contents($markerFile, 'junk');
        $this->assertTrue(file_exists($markerFile));

        // Now recompile with "force" set to true, confirm that this succeeds,
        // and make sure the marker file is now gone:
        $this->assertTrue($compiler->compile('child', 'compiled', true));
        $this->assertFalse(file_exists($markerFile));
    }

    /**
     * Teardown method: clean up test directory.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->getThemeCompiler()->removeTheme('compiled');
    }

    /**
     * Get a test ThemeCompiler object
     *
     * @return ThemeInfo
     */
    protected function getThemeCompiler()
    {
        return new ThemeCompiler($this->info);
    }
}
