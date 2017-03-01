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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Controller;

use VuFindDevTools\Controller\DevtoolsController as Controller;
use Zend\Config\Config;

/**
 * Unit tests for DevTools controller.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class DevtoolsControllerTest extends \VuFindTest\Unit\TestCase
{
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
