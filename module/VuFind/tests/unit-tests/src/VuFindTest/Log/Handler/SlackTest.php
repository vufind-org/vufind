<?php

/**
 * Slack Log Handler Test Class
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Log\Handler;

use Monolog\Level;
use Monolog\LogRecord;
use VuFind\Log\Handler\SlackWebhookHandler;

/**
 * Slack Log Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SlackTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test writer functionality
     *
     * @return void
     */
    public function testHandler(): void
    {
        $fakeUri = 'http://fake/webhook';

        $logRecord = new LogRecord(
            datetime: new \DateTimeImmutable('2025-07-09T14:57:30+00:00'),
            channel: 'test',
            level: Level::Alert,
            message: 'test',
            context: [],
            extra: []
        );
        $handler = $this->getMockBuilder(SlackWebhookHandler::class)
            ->setConstructorArgs([
                $fakeUri,
                '#test',
                'TestName',
            ])
            ->onlyMethods(['write'])
            ->getMock();

        $handler->expects($this->once())
            ->method('write')
            ->with($this->callback(function (LogRecord $record) {
                $this->assertEquals('test', $record->message);
                return true;
            }));

        $handler->handle($logRecord);
    }
}
