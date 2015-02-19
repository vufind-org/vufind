<?php
/**
 * LessCompiler Test Class
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
use VuFindTheme\LessCompiler;

/**
 * LessCompiler Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class LessCompilerTest extends Unit\TestCase
{
    /**
     * Our brave test subject
     *
     * @var VuFindTheme\LessCompiler
     */
    protected $testDest;

    /**
     * Our brave test subject
     *
     * @var VuFindTheme\LessCompiler
     */
    protected $compiler;

    public static function setUpBeforeClass()
    {
        $temp = sys_get_temp_dir();
        $testDest = $temp . '/vufind_less_comp_test/';
        // Create directory structure, recursively
        mkdir($testDest . 'themes/child/less',  0777, true);
        mkdir($testDest . 'themes/empty',  0777, true);
        mkdir($testDest . 'themes/parent/css',  0777, true);
        mkdir($testDest . 'themes/parent/less/relative', 0777, true);
        file_put_contents(
            $testDest . 'themes/empty/theme.config.php',
            '<?php return array("extends"=>false);'
        );
        file_put_contents(
            $testDest . 'themes/parent/theme.config.php',
            '<?php return array("extends"=>false, "less"=>array("compiled.less", "relative/relative.less"));'
        );
        file_put_contents(
            $testDest . 'themes/child/theme.config.php',
            '<?php return array("extends"=>"parent", "less"=>array("compiled.less", "missing.less"));'
        );
        file_put_contents(
            $testDest . 'themes/parent/less/compiled.less',
            '@import "parent";'
        );
        file_put_contents(
            $testDest . 'themes/parent/less/parent.less',
            'body { background:url("../fake.png");color:#00D; a { color:#F00; } }'
        );
        file_put_contents(
            $testDest . 'themes/parent/less/relative/relative.less',
            'div {background:#EEE}'
        );
        file_put_contents(
            $testDest . 'themes/child/less/compiled.less',
            '@import "parent"; @black: #000; div {border:1px solid @black;}'
        );
    }

    public function setUp()
    {
        $temp = sys_get_temp_dir();
        $perms = fileperms($temp);
        $this->testDest = $temp . '/vufind_less_comp_test/';
        if (!($perms & 0x0002)) {
            $this->markTestSkipped('No write permissions in system temporary file');
        }
        $this->compiler = new LessCompiler();
        $this->compiler->setBasePath($temp . '/vufind_less_comp_test');
        $this->compiler->setTempPath($temp . '/vufind_less_comp_test/cache');
    }

    public static function tearDownAfterClass()
    {
        $temp = sys_get_temp_dir();
        $testDest = $temp . '/vufind_less_comp_test/';
        // Delete directory structure
        self::delTree($testDest);
    }

    // adapted from http://php.net/manual/en/function.rmdir.php
    protected static function delTree($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file")
                ? self::delTree("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testThemeCompile()
    {
        $this->compiler->compile(['child']);
        $this->assertTrue(file_exists($this->testDest . 'themes/child/css/compiled.css'));
        $this->assertFalse(file_exists($this->testDest . 'themes/parent/css/compiled.css'));
        unlink($this->testDest . 'themes/child/css/compiled.css');
    }

    public function testAllCompile()
    {
        $this->compiler->compile([]);
        $this->assertTrue(file_exists($this->testDest . 'themes/child/css/compiled.css'));
        $this->assertTrue(file_exists($this->testDest . 'themes/parent/css/compiled.css'));
        $this->assertTrue(file_exists($this->testDest . 'themes/parent/css/relative/relative.css'));
        unlink($this->testDest . 'themes/child/css/compiled.css');
        unlink($this->testDest . 'themes/parent/css/compiled.css');
        unlink($this->testDest . 'themes/parent/css/relative/relative.css');
    }
}