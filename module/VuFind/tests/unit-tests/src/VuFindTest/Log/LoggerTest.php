<?php

/**
 * Logger Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use function is_array;

/**
 * Logger Test Class.
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
     * Test logException().
     *
     * @return void
     */
    public function testLogException()
    {
        $callback = function ($level, $message, $context = []): bool {
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
            if (is_array($message)) {
                $details = $message;
                $expectedMessage = 'Exception/Detailed log. See context for levels.';
                $contextCheck = isset($context['vufind_log_details']) && $context['vufind_log_details'] === $details;
            } else {
                $contextCheck = isset($context['vufind_log_details']) && is_array($context['vufind_log_details']);
                $details = $contextCheck ? $context['vufind_log_details'] : [];
                $expectedMessage = $message;
            }

            if (!$contextCheck && !is_array($message)) {
                return false;
            }
            $targetDetails = is_array($message) ? $message : ($context['vufind_log_details'] ?? []);

            if (count($targetDetails) !== 5) {
                return false;
            }

            $expectedA2 = 'Exception : test'
                . '(Server: IP = 1.2.3.4, Referer = none, User Agent = Fake browser, '
                . 'Host = localhost:80, Request URI = /foo/bar)';

            return $targetDetails[1] === 'Exception : test'
                && $targetDetails[2] === $expectedA2
                && str_contains($targetDetails[3], $targetDetails[2])
                && str_contains($targetDetails[3], 'Backtrace:')
                && str_contains($targetDetails[3], 'line')
                && str_contains($targetDetails[3], 'class =')
                && str_contains($targetDetails[3], 'function =')
                && str_contains($targetDetails[4], $expectedContext)
                && str_contains($targetDetails[4], 'Backtrace:')
                && str_contains($targetDetails[4], 'line')
                && str_contains($targetDetails[4], 'class =')
                && str_contains($targetDetails[4], 'function =')
                && str_contains($targetDetails[5], $expectedContext)
                && str_contains($targetDetails[5], 'Backtrace:')
                && str_contains($targetDetails[5], 'line')
                && str_contains($targetDetails[5], 'args:')
                && str_contains($targetDetails[5], 'class =')
                && str_contains($targetDetails[5], 'function =');
        };
        $mockIpReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserIp'])
            ->getMock();
        $mockIpReader->expects($this->once())->method('getUserIp')
            ->willReturn('1.2.3.4');
        $logger = $this->getMockBuilder(\VuFind\Log\Logger::class)
            ->setConstructorArgs([$mockIpReader, new \Monolog\Logger('test')])
            ->onlyMethods(['log'])
            ->getMock();
        $logger->expects($this->once())->method('log')
            ->willReturnCallback($callback);

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

    /**
     * Test fillInMissingDetails().
     *
     * @return void
     */
    public function testFillInMissingDetails()
    {
        $mockIpReader = $this->createMock(\VuFind\Net\UserIpReader::class);
        $logger = $this->getMockBuilder(\VuFind\Log\Logger::class)
            ->setConstructorArgs([$mockIpReader, new \Monolog\Logger('test')])
            ->onlyMethods(['log'])
            ->getMock();

        // Test 1: Gap in the middle (Index 3 missing)
        $context = [
            'details' => [
                1 => 'low',
                2 => 'med',
                // 3 is missing
                4 => 'high',
                5 => 'ultra',
            ],
        ];

        $method = new \ReflectionMethod($logger, 'fillInMissingDetails');
        $method->setAccessible(true);

        $result = $method->invoke($logger, $context);

        $this->assertEquals('med', $result['details'][3], 'Index 3 should backfill from Index 2');

        // Test 2: Missing start (Index 1 missing)
        $context2 = ['details' => [2 => 'value']];
        $result2 = $method->invoke($logger, $context2);
        $this->assertEquals('value', $result2['details'][1], 'Index 1 should frontfill from Index 2');

        // Test 3: Empty string in index
        $context3 = ['details' => [1 => 'data', 2 => '', 3 => 'more data']];
        $result3 = $method->invoke($logger, $context3);
        $this->assertEquals('data', $result3['details'][2], 'Empty string should be treated as missing');

        // Test 4: No index to backfill or frontfill from
        $context4 = ['details' => [1 => 'data']];
        $result4 = $method->invoke($logger, $context4);
        $this->assertEquals(
            '',
            $result4['details'][3],
            'Index 3-5 should be empty string since no near indexes to fill from'
        );
    }
}
