<?php

/**
 * Office 365 Log Handler Test Class
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

use Laminas\Http\Client;
use Monolog\Level;
use Monolog\LogRecord;
use VuFind\Log\Handler\Office365Handler;

/**
 * Office 365 Log Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class Office365Test extends \PHPUnit\Framework\TestCase
{
    /**
     * Test handler functionality
     *
     * @return void
     */
    public function testHandler(): void
    {
        $fakeUri = 'http://fake';
        $expectedBody = '{"@context":"https:\/\/schema.org\/extensions",'
            . '"@type":"MessageCard","themeColor":"0072C6",'
            . '"title":"Test Title","text":"[2025-07-09T14:55:20+00:00] test.INFO: test [] []\n"}';

        $options = ['title' => 'Test Title'];
        $logRecord = new LogRecord(
            datetime: new \DateTimeImmutable('2025-07-09T14:55:20+00:00'),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: []
        );

        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo($fakeUri));
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'));
        $client->expects($this->once())->method('setEncType')
            ->with($this->equalTo('application/json'));
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($expectedBody));
        $client->expects($this->once())->method('send');

        $handler = new Office365Handler($fakeUri, $client, $options);
        $handler->handle($logRecord);
    }
}
