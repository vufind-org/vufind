<?php
/**
 * UserIpReader Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
namespace VuFindTest\Net;

use Laminas\Stdlib\Parameters;
use VuFind\Net\UserIpReader;

/**
 * UserIpReader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserIpReaderTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test X-Real-IP; it should take priority over all other settings when
     * forwarding is allowed.
     *
     * @return void
     */
    public function testMultipleHeaders()
    {
        $params = new Parameters(
            [
                'HTTP_X_REAL_IP' => '1.2.3.4',
                'HTTP_X_FORWARDED_FOR' => '5.6.7.8',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        // Test appropriate behavior with forwarding configured to prefer Real-IP:
        $reader1 = new UserIpReader(
            $params, 'HTTP_X_REAL_IP,HTTP_X_FORWARDED_FOR:last'
        );
        $this->assertEquals('1.2.3.4', $reader1->getUserIp());
        // Test appropriate behavior with forwarding configured to ignore Real-IP:
        $reader2 = new UserIpReader($params, 'HTTP_X_FORWARDED_FOR:last');
        $this->assertEquals('5.6.7.8', $reader2->getUserIp());
        // Test appropriate behavior with forwarding disabled:
        $reader3 = new UserIpReader($params, false);
        $this->assertEquals('127.0.0.1', $reader3->getUserIp());
    }

    /**
     * Test X-Forwarded-For (multi-value); the leftmost IP should take priority over
     * REMOTE_ADDR when forwarding is allowed.
     *
     * @return void
     */
    public function testXForwardedForMultiValued()
    {
        $params = new Parameters(
            [
                'HTTP_X_FORWARDED_FOR' => '5.6.7.8, 9.10.11.12',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        // Test appropriate behavior with "first" selector:
        $reader1 = new UserIpReader($params, 'HTTP_X_FORWARDED_FOR:first');
        $this->assertEquals('5.6.7.8', $reader1->getUserIp());
        // Test appropriate behavior with "last" selector:
        $reader2 = new UserIpReader($params, 'HTTP_X_FORWARDED_FOR:last');
        $this->assertEquals('9.10.11.12', $reader2->getUserIp());
        // Test appropriate behavior with "single" selector:
        $reader3 = new UserIpReader($params, 'HTTP_X_FORWARDED_FOR:single');
        $this->assertEquals('127.0.0.1', $reader3->getUserIp());
        // Test that "single" selector is default behavior:
        $reader4 = new UserIpReader($params, 'HTTP_X_FORWARDED_FOR');
        $this->assertEquals('127.0.0.1', $reader4->getUserIp());
    }

    /**
     * Test what happens when only REMOTE_ADDR is provided.
     *
     * @return void
     */
    public function testXForwardedForWithoutHeaders()
    {
        $params = new Parameters(
            [
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        // Test appropriate behavior with forwarding enabled:
        $reader1 = new UserIpReader(
            $params, 'HTTP_X_REAL_IP,HTTP_X_FORWARDED_FOR:last'
        );
        $this->assertEquals('127.0.0.1', $reader1->getUserIp());
        // Test appropriate behavior with forwarding disabled:
        $reader2 = new UserIpReader($params, false);
        $this->assertEquals('127.0.0.1', $reader2->getUserIp());
    }
}
