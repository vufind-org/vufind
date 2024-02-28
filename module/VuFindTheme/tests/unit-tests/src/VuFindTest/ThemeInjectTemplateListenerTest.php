<?php

/**
 * InjectTemplateListener Test Class
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

use VuFindTheme\InjectTemplateListener;

/**
 * InjectTemplateListener Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeInjectTemplateListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test prefix stripping.
     *
     * @return void
     */
    public function testPrefixStripping()
    {
        $l = new InjectTemplateListener(['VuFind/']);
        // We should strip a registered prefix:
        $this->assertEquals(
            'search',
            $l->mapController(\VuFind\Controller\SearchController::class)
        );
        // We should NOT strip an unregistered prefix:
        $this->assertEquals(
            'vufindadmin/admin',
            $l->mapController(\VuFindAdmin\Controller\AdminController::class)
        );
    }

    /**
     * Test camelcase handling.
     *
     * @return void
     */
    public function testCamelCaseToLowerCase()
    {
        $l = new InjectTemplateListener(['VuFind/']);
        $this->assertEquals(
            'testcase',
            $this->callMethod($l, 'inflectName', ['VuFind/testCase'])
        );
    }
}
