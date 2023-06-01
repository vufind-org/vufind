<?php

/**
 * PermissionProvider Shibboleth Test Class
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

use VuFind\Role\PermissionProvider\Shibboleth;

/**
 * PermissionProvider Shibboleth Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ShibbolethTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test option alias idpentityid for Shib-Identity-Provider
     *
     * @return void
     */
    public function testAliasIdpentityidTrue()
    {
        $this->checkShibboleth(
            ['Shib-Identity-Provider' => 'https://example.org/shibboleth-idp'],
            ['idpentityid https://example.org/shibboleth-idp'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test multi-valued option with matching header
     *
     * @return void
     */
    public function testMultivaluedOptionTrue()
    {
        $this->checkShibboleth(
            ['Shib-Identity-Provider' => 'https://example.org/shibboleth-idp',
             'affiliation' => 'student@example.org;member@example.org'],
            ['affiliation member@example.org'],
            ['guest', 'loggedin']
        );
    }

    /**
     * Test multi-valued option with matching no header
     *
     * @return void
     */
    public function testMultivaluedOptionFalse()
    {
        $this->checkShibboleth(
            ['Shib-Identity-Provider' => 'https://example.org/shibboleth-idp',
             'affiliation' => 'student@example.org;member@example.org'],
            ['affiliation staff@example.org'],
            []
        );
    }

    /**
     * Setup request and shibboleth objects, run getPermissions and check the result
     *
     * @param array $headers        Request headers
     * @param mixed $options        options as from configuration
     * @param array $expectedResult expected result returned by getPermissions
     * @param array $config         VuFind configuration options
     *
     * @return void
     */
    protected function checkShibboleth(
        $headers,
        $options,
        $expectedResult,
        $config = []
    ) {
        $request = new \Laminas\Http\PhpEnvironment\Request();
        $request->setServer(new \Laminas\Stdlib\Parameters($headers));
        $shibboleth = new Shibboleth($request, new \Laminas\Config\Config($config));
        $result = $shibboleth->getPermissions($options);
        $this->assertEquals($result, $expectedResult);
    }
}
