<?php

/**
 * Logger Test Class
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

namespace VuFindTest\Log;

use VuFind\Log\Logger;

use function count;

/**
 * Logger Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LoggerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test logException()
     *
     * @return void
     */
    public function testLogException()
    {
        $callback = function ($a): bool {
            $expectedContext = <<<CONTEXT
                Server Context:
                Array
                (
                    [REMOTE_ADDR] => 5.6.7.8
                    [HTTP_USER_AGENT] => Fake browser
                    [HTTP_HOST] => localhost:80
                    [REQUEST_URI] => /foo/bar
                )
                CONTEXT;
            $expectedA2 = 'Exception : test'
                . '(Server: IP = 1.2.3.4, Referer = none, User Agent = Fake browser, '
                . 'Host = localhost:80, Request URI = /foo/bar)';
            return $a[1] === 'Exception : test'
                && $a[2] === $expectedA2
                && str_contains($a[3], $a[2])
                && str_contains($a[3], 'Backtrace:')
                && str_contains($a[3], 'line')
                && str_contains($a[3], 'class =')
                && str_contains($a[3], 'function =')
                && str_contains($a[4], $expectedContext)
                && str_contains($a[4], 'Backtrace:')
                && str_contains($a[4], 'line')
                && str_contains($a[4], 'class =')
                && str_contains($a[4], 'function =')
                && str_contains($a[5], $expectedContext)
                && str_contains($a[5], 'Backtrace:')
                && str_contains($a[5], 'line')
                && str_contains($a[5], 'args:')
                && str_contains($a[5], 'class =')
                && str_contains($a[5], 'function =')
                && count($a) == 5;
        };
        $mockIpReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserIp'])
            ->getMock();
        $mockIpReader->expects($this->once())->method('getUserIp')
            ->will($this->returnValue('1.2.3.4'));
        $logger = $this->getMockBuilder(\VuFind\Log\Logger::class)
            ->setConstructorArgs([$mockIpReader])
            ->onlyMethods(['log'])
            ->getMock();
        $logger->expects($this->once())->method('log')->with($this->equalTo(Logger::CRIT), $this->callback($callback));
        try {
            throw new \Exception('test');
        } catch (\Exception $e) {
            // Note that we use a different REMOTE_ADDR in the request than
            // in the mock IP reader above, to confirm that the IP reader is
            // being used instead of the request; this ensures that proxies
            // are handled correctly, etc.
            $fakeServer = new \Laminas\Stdlib\Parameters(
                [
                    'REMOTE_ADDR' => '5.6.7.8',
                    'HTTP_USER_AGENT' => 'Fake browser',
                    'HTTP_HOST' => 'localhost:80',
                    'REQUEST_URI' => '/foo/bar',
                ]
            );
            $logger->logException($e, $fakeServer);
        }
    }
}
