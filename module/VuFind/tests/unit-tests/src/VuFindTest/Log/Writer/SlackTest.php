<?php

/**
 * Slack Log Writer Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Log\Writer;

use Laminas\Http\Client;
use Laminas\Log\Formatter\Simple;
use VuFind\Log\Writer\Slack;

/**
 * Slack Log Writer Test Class
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
    public function testWriter(): void
    {
        // Set up data and expectations:
        $fakeUri = 'http://fake';
        $expectedBody = json_encode(
            [
                'channel' => '#test',
                'username' => 'TestName',
                'text' => ':rotating_light: Formatted message.' . PHP_EOL,
            ]
        );
        $message = ['message' => 'test', 'priority' => 1];
        $options = ['name' => 'TestName', 'channel' => '#test'];

        // Set up mock client:
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

        // Set up mock formatter:
        $formatter = $this->getMockBuilder(Simple::class)
            ->disableOriginalConstructor()->getMock();
        $formatter->expects($this->once())->method('format')
            ->with($this->equalTo($message))
            ->will($this->returnValue('Formatted message.'));

        // Run the test!
        $writer = new Slack($fakeUri, $client, $options);
        $writer->setContentType('application/json');
        $writer->setFormatter($formatter);
        $writer->write($message);
    }
}
