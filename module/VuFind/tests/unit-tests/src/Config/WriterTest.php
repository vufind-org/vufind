<?php
/**
 * Config Writer Test Class
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
namespace VuFindTest\Config;
use VuFind\Config\Writer;

/**
 * Config Writer Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class WriterTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test reading from a file.
     *
     * @return void
     */
    public function testReadFile()
    {
        $file = realpath(__DIR__ . '/../../../fixtures/configs/1.1/sms.ini');
        $test = new Writer($file);
        $this->assertEquals(file_get_contents($file), $test->getContent());
    }

    /**
     * Test constructing text from an array.
     *
     * @return void
     */
    public function testReadArray()
    {
        $cfg = array('Test' => array('key1' => 'val1', 'key2' => 'val2'));
        $comments = array(
            'sections' => array(
                'Test' => array(
                    'before' => "; section head\n",
                    'inline' => '; inline',
                    'settings' => array(
                        'key1' => array(
                            'before' => "; key head\n",
                            'inline' => '; key inline'
                        )
                    )
                )
            ),
            'after' => "; the end\n"
        );
        $target = "; section head\n[Test]\t; inline\n; key head\n"
            . "key1             = \"val1\"\t; key inline\n"
            . "key2             = \"val2\"\n; the end\n";
        $test = new Writer('fake.ini', $cfg, $comments);
        $this->assertEquals($target, $test->getContent());
    }
}