<?php

/**
 * Slot view helper Test Class
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

namespace VuFindTest\View\Helper;

use VuFindTheme\View\Helper\Slot;

/**
 * Slot view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SlotTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the helper.
     *
     * @return void
     */
    public function testInstance()
    {
        $helper = $this->getHelper();
        $ret = $helper('test');
        $this->assertInstanceOf(Slot::class, $ret);
    }

    /**
     * Test get value of slot.
     *
     * @return void
     */
    public function testGet()
    {
        $helper = $this->getHelper();

        // test empty default
        $this->assertEquals(null, $helper('test')->get());
        $this->assertEquals('default', $helper('test')->get('default'));

        // test populated over default
        $helper('test')->set('ONE');
        $this->assertEquals('ONE', $helper('test')->get());
        $this->assertEquals('ONE', $helper('test')->get('default'));
    }

    /**
     * Test setting value of slot blocking later sets.
     *
     * @return void
     */
    public function testSet()
    {
        $helper = $this->getHelper();

        // test return
        $ret = $helper('test')->set('ONE');
        $this->assertEquals('ONE', $ret);

        // test get
        $this->assertEquals('ONE', $helper('test')->get());

        // test no override
        $ret = $helper('test')->set('TWO');
        $this->assertEquals('ONE', $ret);

        // test number
        $ret = $helper('array')->set(100);
        $this->assertEquals(100, $ret);

        // test empty string (not null)
        $helper('empty')->clear();
        $ret = $helper('empty')->set('');
        $this->assertEquals('', $ret);
        $this->assertEquals('', $helper('empty')->get('default'));

        // test array
        $helper('array')->clear();
        $ret = $helper('array')->set([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $ret);

        // test object
        $helper('array')->clear();
        $ret = $helper('array')->set(new \SplStack());
        $this->assertEquals(\SplStack::class, $ret::class);

        // test shortcuts
        $ret = $helper('short', 'SUCCESS');
        $this->assertEquals('SUCCESS', $ret);
        $this->assertEquals('SUCCESS', $helper('short'));
    }

    /**
     * Test capturing echo with start and end.
     *
     * @return void
     */
    public function testCapture()
    {
        $helper = $this->getHelper();

        // test capture
        $helper('test')->start();
        echo 'BUFFER';
        $ret = $helper('test')->end();
        $this->assertEquals('BUFFER', $ret);

        // test no override
        $helper('test')->start();
        echo 'OVERRIDE';
        $ret = $helper('test')->end();
        $this->assertEquals('BUFFER', $ret);
    }

    /**
     * Test clearing blocks and allowing for override.
     *
     * @return void
     */
    public function testClear()
    {
        $helper = $this->getHelper();
        $set1 = $helper('test')->set('ONE');
        $this->assertEquals('ONE', $set1);

        $ret = $helper('test')->clear();

        // test returns old content
        $this->assertEquals('ONE', $ret);

        // test now null
        $this->assertEquals(null, $helper('test')->get());

        // test set after clear
        $set1 = $helper('test')->set('TWO');
        $this->assertEquals('TWO', $set1);
    }

    /**
     * Test prepending more to blocks.
     *
     * @return void
     */
    public function testPrepend()
    {
        $helper = $this->getHelper();

        // test no block
        $ret = $helper('test')->prepend('PRE1');
        $this->assertEquals('PRE1', $ret);
        // default only returns of all are unset
        $this->assertEquals('PRE1', $helper('test')->get('default'));

        // test with block
        $ret = $helper('test')->set('BLOCK');
        $this->assertEquals('PRE1 BLOCK', $ret);

        // test capture prepend
        $helper('test')->start();
        echo 'PRE2';
        $ret = $helper('test')->end('PREPEND'); // end mode
        $this->assertEquals('PRE2 PRE1 BLOCK', $ret);

        // test get
        $this->assertEquals('PRE2 PRE1 BLOCK', $helper('test')->get());

        // test clear
        $helper('test')->clear();
        $this->assertEquals(null, $helper('test')->get());

        // test empty strings
        $helper('test')->set('');
        $ret = $helper('test')->prepend('PRE1');
        $this->assertEquals('PRE1', $ret);
        $helper('test')->clear();
        $helper('test')->set('BASE');
        $ret = $helper('test')->prepend('');
        $this->assertEquals('BASE', $ret);
    }

    /**
     * Test appending more to blocks.
     *
     * @return void
     */
    public function testAppend()
    {
        $helper = $this->getHelper();

        // test no block
        $ret = $helper('test')->append('POST1');
        $this->assertEquals('POST1', $ret);
        // default only returns of all are unset
        $this->assertEquals('POST1', $helper('test')->get('default'));

        // test with block
        $ret = $helper('test')->set('BLOCK');
        $this->assertEquals('BLOCK POST1', $ret);

        // test capture append
        $helper('test')->start();
        echo 'POST2';
        $ret = $helper('test')->end('APPEND'); // end mode
        $this->assertEquals('BLOCK POST1 POST2', $ret);

        // test get
        $this->assertEquals('BLOCK POST1 POST2', $helper('test')->get());

        // test clear
        $helper('test')->clear();
        $this->assertEquals(null, $helper('test')->get());

        // test empty strings
        $helper('test')->set('');
        $ret = $helper('test')->append('POST');
        $this->assertEquals('POST', $ret);
        $helper('test')->clear();
        $helper('test')->set('BASE');
        $ret = $helper('test')->append('');
        $this->assertEquals('BASE', $ret);
    }

    /**
     * Test nested slots.
     *
     * @return void
     */
    public function testNesting()
    {
        $helper = $this->getHelper();

        $helper('parent')->start();
        echo '<parent>';

        $helper('child')->start();
        echo 'CHILD';
        echo $child = $helper('child')->end();
        $this->assertEquals('CHILD', $child);

        echo '</parent>';
        $ret = $helper('parent')->end();
        $this->assertEquals('<parent>CHILD</parent>', $ret);
    }

    /**
     * Test nested slots showing that children don't appear in parent without echo.
     *
     * @return void
     */
    public function testNestingWithoutEcho()
    {
        $helper = $this->getHelper();

        $helper('parent')->start();
        echo '<parent>';

        $helper('child')->start();
        echo 'CHILD';
        $child = $helper('child')->end(); // no echo
        $this->assertEquals('CHILD', $child);

        echo '</parent>';
        $ret = $helper('parent')->end();
        $this->assertEquals('<parent></parent>', $ret);
    }

    /**
     * Build Slot helper with mock view
     *
     * @return \VuFindTheme\View\Helper\Slot
     */
    protected function getHelper()
    {
        $helper = new Slot();
        $helper->setView($this->getMockView());
        return $helper;
    }

    /**
     * Get a fake view object.
     *
     * @return \Laminas\View\Renderer\PhpRenderer
     */
    protected function getMockView()
    {
        $view = $this->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        return $view;
    }
}
