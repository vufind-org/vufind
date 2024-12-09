<?php

/**
 * Unit tests for DevTools controller.
 *
 * PHP version 8
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

use Laminas\I18n\Translator\TextDomain;
use VuFindDevTools\Controller\DevtoolsController as Controller;

use function count;
use function get_class;
use function in_array;

/**
 * Unit tests for DevTools controller.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class DevtoolsControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test language action.
     *
     * @return void
     */
    public function testLanguageAction()
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->get(\VuFind\I18n\Locale\LocaleSettings::class)
            ->expects($this->once())->method('getEnabledLocales')
            ->will($this->returnValue(['en' => 'English']));
        $c = new Controller($container);
        $result = $c->languageAction();

        // Test default language selection -- English
        $this->assertEquals('en', $result['mainCode']);
        $this->assertEquals('English', $result['mainName']);

        // Make sure correct type of object was loaded:
        $this->assertEquals(TextDomain::class, get_class($result['main']));

        // Shortcut to help check some key details:
        $en = $result['details']['en'];

        // Did we load help files correctly?
        $this->assertGreaterThan(3, count($en['helpFiles']));
        $this->assertTrue(in_array('search.phtml', $en['helpFiles']));

        // Did we put the object in the right place?
        $this->assertEquals(TextDomain::class, get_class($en['object']));

        // Did the @parent_ini macro get stripped correctly?
        $this->assertArrayNotHasKey('@parent_ini', $result['details']['en-gb']['object']);

        // Did the native.ini file get properly ignored?
        $this->assertArrayNotHasKey('native', $result['details']);

        // Did the aliases.ini file get properly ignored?
        $this->assertArrayNotHasKey('aliases', $result['details']);
    }
}
