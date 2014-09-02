<?php

/**
 * Unit tests for DevTools controller.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindTest\Controller;

use VuFindDevTools\Controller\DevtoolsController as Controller;
use Zend\Config\Config;

/**
 * Unit tests for DevTools controller.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class DevtoolsControllerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test language mappings.
     *
     * @return void
     */
    public function testGetLangName()
    {
        $config = new Config(array('Languages' => array('en' => 'English')));
        $c = $this->getMock('VuFindDevTools\Controller\DevtoolsController', array('getConfig'));
        $c->expects($this->any())->method('getConfig')->will($this->returnValue($config));

        // config-driven case:
        $this->assertEquals('English', $c->getLangName('en'));

        // special cases:
        $this->assertEquals('British English', $c->getLangName('en-gb'));
        $this->assertEquals('Brazilian Portuguese', $c->getLangName('pt-br'));

        // unknown case:
        $this->assertEquals('??', $c->getLangName('??'));
    }

    /**
     * Test language comparison.
     *
     * @return void
     */
    public function testComparison()
    {
        $l1 = array('1' => 'one', '2' => 'two', '3' => 'three');
        $l2 = array('2' => 'two', '4' => 'four');
        $c = new Controller();
        $expected = array(
            'notInL1' => array(4),
            'notInL2' => array(1, 3),
            'l1Percent' => '150.00',
            'l2Percent' => '66.67'
        );
        $this->assertEquals($expected, $this->callMethod($c, 'compareLanguages', array($l1, $l2)));
    }
}
