<?php

/**
 * Config Writer Test Class
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

use VuFind\Config\Writer;

/**
 * Config Writer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WriterTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test reading from a file.
     *
     * @return void
     */
    public function testReadFile()
    {
        $test = new Writer($this->getFixtureDir() . 'configs/1.1/sms.ini');
        $this->assertEquals(
            $this->getFixture('configs/1.1/sms.ini'),
            $test->getContent()
        );
    }

    /**
     * Test constructing text from an array.
     *
     * @return void
     */
    public function testReadArray()
    {
        $cfg = ['Test' => ['key1' => 'val1', 'key2' => 'val2']];
        $comments = [
            'sections' => [
                'Test' => [
                    'before' => "; section head\n",
                    'inline' => '; inline',
                    'settings' => [
                        'key1' => [
                            'before' => "; key head\n",
                            'inline' => '; key inline',
                        ],
                    ],
                ],
            ],
            'after' => "; the end\n",
        ];
        $target = "; section head\n[Test]\t; inline\n; key head\n"
            . "key1             = \"val1\"\t; key inline\n"
            . "key2             = \"val2\"\n; the end\n";
        $test = new Writer('fake.ini', $cfg, $comments);
        $this->assertEquals($target, $test->getContent());
    }

    /**
     * Test reading from a string.
     *
     * @return void
     */
    public function testReadString()
    {
        $cfg = "[test]\nkey1=val1\n";
        $test = new Writer('fake.ini', $cfg);
        $this->assertEquals($cfg, $test->getContent());
    }

    /**
     * Test constructing text from a non-associative array.
     *
     * @return void
     */
    public function testStandardArray()
    {
        $cfg = ['Test' => ['test' => ['val1', 'val2']]];
        $test = new Writer('fake.ini', $cfg);
        $expected = "[Test]\ntest[]           = \"val1\"\n"
            . "test[]           = \"val2\"\n\n";
        $this->assertEquals($expected, $test->getContent());
    }

    /**
     * Test constructing text from a non-associative array with
     * non-consecutive keys.
     *
     * @return void
     */
    public function testOutOfOrderArray()
    {
        $cfg = ['Test' => ['test' => [6 => 'val1', 8 => 'val2']]];
        $test = new Writer('fake.ini', $cfg);
        $expected = "[Test]\ntest[6]          = \"val1\"\n"
            . "test[8]          = \"val2\"\n\n";
        $this->assertEquals($expected, $test->getContent());
    }

    /**
     * Test constructing text from an associative array.
     *
     * @return void
     */
    public function testAssocArray()
    {
        $cfg = [
            'Test' => ['test' => ['key1' => 'val1', 'key2' => 'val2']],
        ];
        $test = new Writer('fake.ini', $cfg);
        $expected = "[Test]\ntest['key1']     = \"val1\"\n"
            . "test['key2']     = \"val2\"\n\n";
        $this->assertEquals($expected, $test->getContent());
    }

    /**
     * Test setting a value.
     *
     * @return void
     */
    public function testBasicSet()
    {
        $cfg = "[test]\nkey1=val1\nkey3=val3\n";
        $test = new Writer('fake.ini', $cfg);
        $test->set('test', 'key2', 'val2');
        $test->set('test', 'key1', 'val1b');
        $test->set('test', 'key4', [1, 2, 3]);
        $test->set('test', 'key5', ['a' => 'b']);
        $test->set('test', 'keyQuote', 'I "quoted" it');
        $ini = parse_ini_string($test->getContent(), true);
        $this->assertEquals('val1b', $ini['test']['key1']);
        $this->assertEquals('val2', $ini['test']['key2']);
        $this->assertEquals('val3', $ini['test']['key3']);
        $this->assertEquals([1, 2, 3], $ini['test']['key4']);
        $this->assertEquals(['a' => 'b'], $ini['test']['key5']);
        $this->assertEquals('I "quoted" it', $ini['test']['keyQuote']);
    }

    /**
     * Test setting a duplicate value.
     *
     * @return void
     */
    public function testSetDuplicateValue()
    {
        $cfg = "[test]\nkey1=val1\nkey1=val2\n";
        $test = new Writer('fake.ini', $cfg);
        $test->set('test', 'key1', 'val1b');
        $ini = parse_ini_string($test->getContent(), true);
        $this->assertEquals('val1b', $ini['test']['key1']);
    }

    /**
     * Test that we add a missing section at the end if necessary.
     *
     * @return void
     */
    public function testAddMissingSection()
    {
        $cfg = "[test]\nkey1=val1\n";
        $test = new Writer('fake.ini', $cfg);
        $test->set('test2', 'key1', 'val1b');
        $ini = parse_ini_string($test->getContent(), true);
        $this->assertEquals('val1b', $ini['test2']['key1']);
    }

    /**
     * Test that comments are maintained.
     *
     * @return void
     */
    public function testCommentMaintenance()
    {
        $cfg = "[test]\nkey1=val1 ; comment\n";
        $test = new Writer('fake.ini', $cfg);
        $test->set('test', 'key1', 'val2');
        $this->assertEquals(
            "[test]\nkey1 = \"val2\" ; comment",
            trim($test->getContent())
        );
    }

    /**
     * Test inserting an empty setting.
     *
     * @return void
     */
    public function testInsertEmpty()
    {
        $cfg = "[a]\none=1\n[b]\n";
        $test = new Writer('fake.ini', $cfg);
        $test->set('a', 'two', '');
        $ini = parse_ini_string($test->getContent(), true);
        $this->assertEquals('', $ini['a']['two']);
    }

    /**
     * Test alignment of values.
     *
     * @return void
     */
    public function testTabAlignment()
    {
        $test = new Writer('fake.ini', ['general' => ['foo' => 'bar', 'foofoofoofoofoofo' => 'baz']]);
        $expected = "[general]\nfoo              = \"bar\"\nfoofoofoofoofoofo = \"baz\"\n";
        $this->assertEquals($expected, $test->getContent());
    }

    /**
     * Test clearing values.
     *
     * @return void
     */
    public function testClear()
    {
        $cfg = "[a]\nb[]=1\nb[]=2\n[b]\nc=3\n";
        $test = new Writer('fake.ini', $cfg);
        $test->clear('a', 'b[]');   // clear array
        $test->clear('b', 'c');     // clear single value
        $test->clear('z', 'z');     // clear value that does not exist
        $this->assertEquals("[a]\n[b]", trim($test->getContent()));
    }
}
