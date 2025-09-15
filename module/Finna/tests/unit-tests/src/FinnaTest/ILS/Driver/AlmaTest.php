<?php

/**
 * Alma test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\ILS\Driver;

use Finna\ILS\Driver\Alma;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use SimpleXMLElement;

/**
 * Alma test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AlmaTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get data for update address test
     *
     * @return Generator
     */
    public static function getTestUpdateAddressData(): Generator
    {
        yield 'single entries' => [
        [
          'id' => '123456',
        ],
        [
          'address1' => 'Test address update',
          'email' => 'newemail@email.fi',
          'phone' => '0123456789',
        ],
        [
          'updateProfile' => [
              'fields' => [
                  'Address:address1',
                  'Email:email',
                  'Phone:phone',
              ],
          ],
        ],
        'alma/user.xml',
        'alma/user_address_updated.xml',
        ];

        yield 'empty entries' => [
          [
            'id' => '123456',
          ],
          [
            'address1' => '',
            'email' => '',
            'phone' => '',
          ],
          [
            'updateProfile' => [
                'fields' => [
                    'Address:address1',
                    'Email:email',
                    'Phone:phone',
                ],
            ],
          ],
          'alma/user.xml',
          'alma/user_empty_address_updated.xml',
        ];

        yield 'multiple addresses' => [
        [
          'id' => '123456',
        ],
        [
          'addresses' => [
            'address' => [
              'address1' => 'Test address 1',
              'address2' => 'Test address 2',
            ],
          ],
        ],
        [
          'updateProfile' => [
              'fields' => [
                  'Address:address1',
                  'Address2:address2',
                  'Email:email',
                  'Phone:phone',
              ],
          ],
        ],
        'alma/user_multiple_address.xml',
        'alma/user_multiple_address_updated.xml',
        ];
    }

    /**
     * Test updating address
     *
     * @param array  $patron          User patron
     * @param array  $details         Update details
     * @param array  $config          Driver field config
     * @param string $userFixture     Path to user fixture
     * @param string $expectedFixture Path to expected fixture
     *
     * @return       void
     * @dataProvider getTestUpdateAddressData
     */
    public function testUpdateAddress(
        array $patron,
        array $details,
        array $config,
        string $userFixture,
        string $expectedFixture
    ): void {
        $userXml = $this->getFixture($userFixture, 'Finna');
        $expectedXml = $this->getFixture($expectedFixture, 'Finna');
        $expectedXmlObject = new SimpleXMLElement($expectedXml);
        $makeRequestMap = [
            ['/users/' . $patron['id'], 'GET', new SimpleXMLElement($userXml)],
            ['/users/' . $patron['id'], 'PUT', ['', 200], 'expected' => $expectedXmlObject->asXML()],
        ];
        $driver = $this->getDriver($makeRequestMap);
        $driver->setConfig($config);
        $driver->updateAddress($patron, $details);
    }

    /**
     * Get mocked alma record driver
     *
     * @param array $makeRequestMap Map for requests in makeRequest function.
     *                              0 => url, 1 => method, 2 => response, expected => if assertEquals is required
     *
     * @return MockObject
     */
    public function getDriver(array $makeRequestMap = []): MockObject
    {
        $driver = $this->getMockBuilder(Alma::class)->onlyMethods(['makeRequest'])
          ->disableOriginalConstructor()->getMock();
        $driver->expects($this->any())->method('makeRequest')->willReturnCallback(
            function (
                $url,
                $getP = [],
                $postP = [],
                $method = 'GET',
                $rawBody = null,
                $headers = null,
                $allowedErrors = [],
                $returnStatus = false
            ) use ($makeRequestMap) {
                $foundRequest = [];
                foreach ($makeRequestMap as $request) {
                    if (
                        $request[0] === $url
                        && $request[1] === $method
                    ) {
                        if ($method === 'PUT') {
                            $this->assertEquals($request['expected'], $rawBody);
                        }
                        $foundRequest = $request[2];
                        break;
                    }
                }
                return $foundRequest;
            }
        );
        return $driver;
    }
}
