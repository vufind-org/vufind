<?php
/**
 * HeadThemeResources view helper Test Class
 *
 * PHP version 7
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

use VuFindTheme\ResourceContainer;
use VuFindTheme\View\Helper\Slot;

/**
 * HeadThemeResources view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SlotTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test the helper.
     *
     * @return void
     */
    public function testInstance()
    {
        $helper = $this->getHelper();
        $ret = $helper->__invoke('test');
        $this->assertTrue($ret instanceof Slot);
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
        $this->assertEquals(null, $helper->__invoke('test')->get());
        $this->assertEquals('default', $helper->__invoke('test')->get('default'));

        // test populated over default
        $helper->__invoke('test')->set('ONE');
        $this->assertEquals('ONE', $helper->__invoke('test')->get());
        $this->assertEquals('ONE', $helper->__invoke('test')->get('default'));
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
        $ret = $helper->__invoke('test')->set('ONE');
        $this->assertEquals('ONE', $ret);

        // test get
        $this->assertEquals('ONE', $helper->__invoke('test')->get());

        // test no override
        $ret = $helper->__invoke('test')->set('TWO');
        $this->assertEquals('ONE', $ret);

        // test number
        $ret = $helper->__invoke('array')->set(100);
        $this->assertEquals(100, $ret);

        // test empty string (not null)
        $helper->__invoke('empty')->clear();
        $ret = $helper->__invoke('empty')->set('');
        $this->assertEquals('', $ret);
        $this->assertEquals('', $helper->__invoke('empty')->get('default'));

        // test array
        $helper->__invoke('array')->clear();
        $ret = $helper->__invoke('array')->set([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $ret);

        // test object
        $helper->__invoke('array')->clear();
        $ret = $helper->__invoke('array')->set(new \SplStack());
        $this->assertEquals('SplStack', get_class($ret));

        // test shortcuts
        $ret = $helper->__invoke('short', 'SUCCESS');
        $this->assertEquals('SUCCESS', $ret);
        $this->assertEquals('SUCCESS', $helper->__invoke('short'));
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
        $helper->__invoke('test')->start();
        echo 'BUFFER';
        $ret = $helper->__invoke('test')->end();
        $this->assertEquals('BUFFER', $ret);

        // test no override
        $helper->__invoke('test')->start();
        echo 'OVERRIDE';
        $ret = $helper->__invoke('test')->end();
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
        $set1 = $helper->__invoke('test')->set('ONE');
        $this->assertEquals('ONE', $set1);

        $ret = $helper->__invoke('test')->clear();

        // test returns old content
        $this->assertEquals('ONE', $ret);

        // test now null
        $this->assertEquals(null, $helper->__invoke('test')->get());

        // test set after clear
        $set1 = $helper->__invoke('test')->set('TWO');
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
        $ret = $helper->__invoke('test')->prepend('PRE1');
        $this->assertEquals('PRE1', $ret);
        // default only returns of all are unset
        $this->assertEquals('PRE1', $helper->__invoke('test')->get('default'));

        // test with block
        $ret = $helper->__invoke('test')->set('BLOCK');
        $this->assertEquals('PRE1 BLOCK', $ret);

        // test capture prepend
        $helper->__invoke('test')->start();
        echo 'PRE2';
        $ret = $helper->__invoke('test')->end('PREPEND'); // end mode
        $this->assertEquals('PRE2 PRE1 BLOCK', $ret);

        // test get
        $this->assertEquals('PRE2 PRE1 BLOCK', $helper->__invoke('test')->get());

        // test clear
        $helper->__invoke('test')->clear();
        $this->assertEquals(null, $helper->__invoke('test')->get());

        // test empty strings
        $ret = $helper->__invoke('test')->set('');
        $ret = $helper->__invoke('test')->prepend('PRE1');
        $this->assertEquals('PRE1', $ret);
        $helper->__invoke('test')->clear();
        $ret = $helper->__invoke('test')->set('BASE');
        $ret = $helper->__invoke('test')->prepend('');
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
        $ret = $helper->__invoke('test')->append('POST1');
        $this->assertEquals('POST1', $ret);
        // default only returns of all are unset
        $this->assertEquals('POST1', $helper->__invoke('test')->get('default'));

        // test with block
        $ret = $helper->__invoke('test')->set('BLOCK');
        $this->assertEquals('BLOCK POST1', $ret);

        // test capture append
        $helper->__invoke('test')->start();
        echo 'POST2';
        $ret = $helper->__invoke('test')->end('APPEND'); // end mode
        $this->assertEquals('BLOCK POST1 POST2', $ret);

        // test get
        $this->assertEquals('BLOCK POST1 POST2', $helper->__invoke('test')->get());

        // test clear
        $helper->__invoke('test')->clear();
        $this->assertEquals(null, $helper->__invoke('test')->get());

        // test empty strings
        $ret = $helper->__invoke('test')->set('');
        $ret = $helper->__invoke('test')->append('POST');
        $this->assertEquals('POST', $ret);
        $helper->__invoke('test')->clear();
        $ret = $helper->__invoke('test')->set('BASE');
        $ret = $helper->__invoke('test')->append('');
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

        $helper->__invoke('parent')->start();
        echo '<parent>';

        $helper->__invoke('child')->start();
        echo 'CHILD';
        echo $child = $helper->__invoke('child')->end();
        $this->assertEquals('CHILD', $child);

        echo '</parent>';
        $ret = $helper->__invoke('parent')->end();
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

        $helper->__invoke('parent')->start();
        echo '<parent>';

        $helper->__invoke('child')->start();
        echo 'CHILD';
        $child = $helper->__invoke('child')->end(); // no echo
        $this->assertEquals('CHILD', $child);

        echo '</parent>';
        $ret = $helper->__invoke('parent')->end();
        $this->assertEquals('<parent></parent>', $ret);
    }

    /**
     * Build Slot helper with mock view
     *
     * @return \VuFindTheme\View\Helper\Slot
     */
    protected function getHelper()
    {
        $helper = new Slot($this->getResourceContainer());
        $helper->setView($this->getMockView());
        return $helper;
    }

    /**
     * Get a populated resource container for testing.
     *
     * @return ResourceContainer
     */
    protected function getResourceContainer()
    {
        $rc = new ResourceContainer();
        $rc->setEncoding('utf-8');
        $rc->setGenerator('fake-generator');
        return $rc;
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
