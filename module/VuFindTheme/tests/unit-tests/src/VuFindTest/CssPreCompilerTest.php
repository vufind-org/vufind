<?php

/**
 * CssPreCompilerTest Test Class
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

use VuFindTheme\ScssCompiler;

/**
 * CssPreCompilerTest Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CssPreCompilerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Our brave test subject
     *
     * @var string
     */
    protected $testDest;

    /**
     * Our brave test subject
     *
     * @var object
     */
    protected $compiler;

    /**
     * Data Provider for extensions and classes
     *
     * @return array
     */
    public static function extClassProvider()
    {
        return [
            ['scss', ScssCompiler::class],
        ];
    }

    /**
     * Create fixture files in temp folder
     *
     * @param string $ext Extension directory
     *
     * @return void
     */
    protected static function makeFakeThemeStructure($ext)
    {
        $temp = sys_get_temp_dir();
        $testDest = $temp . "/vufind_{$ext}_comp_test/";
        // Create directory structure, recursively
        mkdir($testDest . "themes/child/$ext", 0o777, true);
        mkdir($testDest . 'themes/empty', 0o777, true);
        mkdir($testDest . 'themes/parent/css', 0o777, true);
        mkdir($testDest . "themes/parent/$ext/relative", 0o777, true);
        file_put_contents(
            $testDest . 'themes/empty/theme.config.php',
            '<?php return array("extends"=>false);'
        );
        file_put_contents(
            $testDest . 'themes/parent/theme.config.php',
            "<?php return array(\"extends\"=>false, \"$ext\"=>array(\"compiled.$ext\", \"relative/relative.$ext\"));"
        );
        file_put_contents(
            $testDest . 'themes/child/theme.config.php',
            "<?php return array(\"extends\"=>\"parent\", \"$ext\"=>array(\"compiled.$ext\", \"missing.$ext\"));"
        );
        file_put_contents(
            $testDest . "themes/parent/$ext/compiled.$ext",
            '@import "parent";'
        );
        file_put_contents(
            $testDest . "themes/parent/$ext/parent.$ext",
            'body { background:url("../fake.png");color:#00D; a { color:#F00; } }'
        );
        file_put_contents(
            $testDest . "themes/parent/$ext/relative/relative.$ext",
            'div {background:#EEE}'
        );
        file_put_contents(
            $testDest . "themes/child/$ext/compiled.$ext",
            $ext == 'less'
                ? '@import "parent"; @black: #000; div {border:1px solid @black;}'
                : '@import "parent"; $black: #000; div {border:1px solid $black;}'
        );
    }

    /**
     * Initial class setup.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        foreach (self::extClassProvider() as [$ext]) {
            self::makeFakeThemeStructure($ext);
        }
    }

    /**
     * Individual test setup.
     *
     * @return void
     */
    public function setUp(): void
    {
        $temp = sys_get_temp_dir();
        $perms = fileperms($temp);
        if (!($perms & 0x0002)) {
            $this->markTestSkipped('No write permissions in system temporary file');
        }
    }

    /**
     * Assign appropriate values to $this->testDest and $this->compiler
     *
     * @param string $ext   Extension directory
     * @param string $class Name of compiler class
     *
     * @return void
     */
    protected function setupCompiler($ext, $class)
    {
        $temp = sys_get_temp_dir();
        $this->testDest = "$temp/vufind_{$ext}_comp_test/";
        $this->compiler = new $class();
        $this->compiler->setBasePath("$temp/vufind_{$ext}_comp_test");
        $this->compiler->setTempPath("$temp/vufind_{$ext}_comp_test/cache");
    }

    /**
     * Test compiling a single theme.
     *
     * @param string $ext   Extension directory
     * @param string $class Name of compiler class
     *
     * @dataProvider extClassProvider
     *
     * @return void
     */
    public function testThemeCompile($ext, $class)
    {
        $this->setupCompiler($ext, $class);
        $this->compiler->compile(['child']);
        $this->assertFileExists($this->testDest . 'themes/child/css/compiled.css');
        $this->assertFileDoesNotExist($this->testDest . 'themes/parent/css/compiled.css');
        unlink($this->testDest . 'themes/child/css/compiled.css');
    }

    /**
     * Test compiling all themes (default).
     *
     * @param string $ext   Extension directory
     * @param string $class Name of compiler class
     *
     * @dataProvider extClassProvider
     *
     * @return void
     */
    public function testAllCompile($ext, $class)
    {
        $this->setupCompiler($ext, $class);
        $this->compiler->compile([]);
        $this->assertFileExists($this->testDest . 'themes/child/css/compiled.css');
        $this->assertFileExists($this->testDest . 'themes/parent/css/compiled.css');
        $this->assertFileExists($this->testDest . 'themes/parent/css/relative/relative.css');
        unlink($this->testDest . 'themes/child/css/compiled.css');
        unlink($this->testDest . 'themes/parent/css/compiled.css');
        unlink($this->testDest . 'themes/parent/css/relative/relative.css');
    }

    /**
     * Delete a directory tree; adapted from
     * http://php.net/manual/en/function.rmdir.php
     *
     * @param string $dir Directory to delete.
     *
     * @return void
     */
    protected static function delTree($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file")
                ? self::delTree("$dir/$file")
                : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    /**
     * Final teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        $temp = sys_get_temp_dir();
        // Delete directory structure
        self::delTree("$temp/vufind_scss_comp_test/");
    }
}
