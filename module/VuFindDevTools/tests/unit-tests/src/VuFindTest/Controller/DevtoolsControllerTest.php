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
        $c = $this->getMockController();

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
        $l1 = ['1' => 'one', '2' => 'two', '3' => 'three'];
        $l2 = ['2' => 'two', '4' => 'four'];
        $c = new Controller();
        $expected = [
            'notInL1' => [4],
            'notInL2' => [1, 3],
            'l1Percent' => '150.00',
            'l2Percent' => '66.67'
        ];
        $this->assertEquals($expected, $this->callMethod($c, 'compareLanguages', [$l1, $l2]));
    }

    /**
     * Test language action.
     *
     * @return void
     */
    public function testLanguageAction()
    {
        $c = $this->getMockController();
        $result = $c->languageAction();

        // Test default language selection -- English
        $this->assertEquals('en', $result['mainCode']);
        $this->assertEquals('English', $result['mainName']);

        // Make sure correct type of object was loaded:
        $this->assertEquals('Zend\I18n\Translator\TextDomain', get_class($result['main']));

        // Shortcut to help check some key details:
        $en = $result['details']['en'];

        // Did we load help files correctly?
        $this->assertTrue(count($en['helpFiles']) >= 4);
        $this->assertTrue(in_array('search.phtml', $en['helpFiles']));

        // Did we put the object in the right place?
        $this->assertEquals('Zend\I18n\Translator\TextDomain', get_class($en['object']));

        // Did the @parent_ini macro get stripped correctly?
        $this->assertFalse(isset($result['details']['en-gb']['object']['@parent_ini']));

        // Did the native.ini file get properly ignored?
        $this->assertFalse(isset($result['details']['native']));
    }

    /**
     * Get a mock controller.
     *
     * @return Controller
     */
    protected function getMockController()
    {
        $config = new Config(['Languages' => ['en' => 'English']]);
        $c = $this->getMock('VuFindDevTools\Controller\DevtoolsController', ['getConfig']);
        $c->expects($this->any())->method('getConfig')->will($this->returnValue($config));
        return $c;
    }
}
