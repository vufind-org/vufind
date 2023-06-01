<?php

/**
 * PermissionProvider ServerParam Test Class
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
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Role\PermissionProvider;

use VuFind\Role\PermissionProvider\ServerParam;

/**
 * PermissionProvider ServerParam Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ServerParamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test single option with matching string
     *
     * @return void
     */
    public function testStringTrue()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            'testheader testvalue',
            ['guest', 'loggedin']
        );
    }

    /**
     * Test option array with matching string
     *
     * @return void
     */
    public function testArrayTrue()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader testvalue'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test multiple options with matching headers
     *
     * @return void
     */
    public function testOptionsAndTrue()
    {
        $this->checkServerParams(
            ['testheader1' => 'testvalue1', 'testheader2' => 'testvalue2'],
            ['testheader1 testvalue1', 'testheader2 testvalue2'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test multiple options with no matching header
     *
     * @return void
     */
    public function testOptionsAndFalse()
    {
        $this->checkServerParams(
            ['testheader1' => 'testvalue1'],
            ['testheader1 testvalue1', 'testheader2 testvalue2'],
            []
        );
    }

    /**
     * Test option with multiple values and matching header
     *
     * @return void
     */
    public function testOptionValuesOrTrue()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue1'],
            ['testheader testvalue1 testvalue2'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test option with multiple values and no matching header
     *
     * @return void
     */
    public function testOptionValuesOrFalse()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader testvalue1 testvalue2'],
            []
        );
    }

    /**
     * Test option with regex modifier and matching header
     *
     * @return void
     */
    public function testOptionRegexTrue()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader ~ ^testvalue$'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test option with regex modifier and no matching header
     *
     * @return void
     */
    public function testOptionRegexFalse()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader ~ ^estvalue'],
            []
        );
    }

    /**
     * Test option with not modifier and matching header
     *
     * @return void
     */
    public function testOptionNotTrue()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader ! testval'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test option with not modifier and no matching header
     *
     * @return void
     */
    public function testOptionNotFalse()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader ! testvalue'],
            []
        );
    }

    /**
     * Test option with not regex modifier and matching header
     *
     * @return void
     */
    public function testOptionNotRegexTrue()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader !~ testval$'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test option with not regex modifier and no matching header
     *
     * @return void
     */
    public function testOptionNotRegexFalse()
    {
        $this->checkServerParams(
            ['testheader' => 'testvalue'],
            ['testheader !~ ^testvalue'],
            []
        );
    }

    /**
     * Setup request and header objects, run getPermissions and check the result
     *
     * @param array $headers        Request headers
     * @param mixed $options        options as from configuration
     * @param array $expectedResult expected result returned by getPermissions
     *
     * @return void
     */
    protected function checkServerParams($headers, $options, $expectedResult)
    {
        $request = new \Laminas\Http\PhpEnvironment\Request();
        $request->setServer(new \Laminas\Stdlib\Parameters($headers));
        $header = new ServerParam($request);
        $result = $header->getPermissions($options);
        $this->assertEquals($result, $expectedResult);
    }
}
