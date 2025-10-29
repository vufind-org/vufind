<?php

/**
 * ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Connection\Oracle;
use VuFind\ILS\Driver\Virtua;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class VirtuaTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use ReflectionTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'Catalog' => [
            'apiBaseUrl' => 'http://localhost/v1',
            'apiKey' => 'key123',
        ],
    ];

    /**
     * Test that driver complains about missing configuration.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {
        $this->expectException(\VuFind\Exception\ILS::class);

        $this->createConnector([])->init();
    }

    /**
     * Data provider for testing getMyProfile
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): Generator
    {
        yield 'address 2 empty but city set' => [
            'profiles' => [
                [
                    'NAME' => 'Last,First',
                    'STREET_ADDRESS_1' => 'Address 1',
                    'STREET_ADDRESS_2' => '',
                    'POSTAL_CODE' => '01zip',
                    'TELEPHONE_PRIMARY' => '0-cat-1',
                    'PATRON_TYPE' => 'test',
                    'CITY' => 'new City',
                ],
            ],
            'expected' => [
                'firstname' => 'First',
                'lastname' => 'Last',
                'birthdate' => null,
                'address1' => 'Address 1',
                'address2' => 'new City',
                'city' => null,
                'country' => null,
                'zip' => '01zip',
                'phone' => '0-cat-1',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => 'test',
                'home_library' => null,
            ],
        ];

        yield 'first name and city empty' => [
            'profiles' => [
                [
                    'NAME' => 'Von Last',
                    'STREET_ADDRESS_1' => 'Address 1',
                    'STREET_ADDRESS_2' => 'Address 2',
                    'POSTAL_CODE' => '01zip',
                    'TELEPHONE_PRIMARY' => '0-cat-1',
                    'PATRON_TYPE' => 'test',
                    'CITY' => '',
                ],
            ],
            'expected' => [
                'firstname' => '',
                'lastname' => 'Von Last',
                'birthdate' => null,
                'address1' => 'Address 1',
                'address2' => 'Address 2',
                'city' => null,
                'country' => null,
                'zip' => '01zip',
                'phone' => '0-cat-1',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => 'test',
                'home_library' => null,
            ],
        ];

        yield 'address2 and city both exists, first name more complicated' => [
            'profiles' => [
                [
                    'NAME' => 'Von Last,S ome`special chars',
                    'STREET_ADDRESS_1' => 'Address 1',
                    'STREET_ADDRESS_2' => 'Address 2',
                    'PATRON_TYPE' => 'wizard',
                    'CITY' => 'Tammisaari',
                ],
            ],
            'expected' => [
                'firstname' => 'S',
                'lastname' => 'Von Last',
                'birthdate' => null,
                'address1' => 'Address 1',
                'address2' => 'Address 2, Tammisaari',
                'city' => null,
                'country' => null,
                'zip' => null,
                'phone' => null,
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => 'wizard',
                'home_library' => null,
            ],
        ];
    }

    /**
     * Test get my profile
     *
     * @param array $profiles Profiles mocking db select
     * @param array $expected Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(array $profiles, array $expected): void
    {
        $db = $this->getMockBuilder(Oracle::class)->onlyMethods(['simpleSelect'])
            ->disableOriginalConstructor()->getMock();
        $db->expects($this->any())->method('simpleSelect')->willReturn($profiles);
        $result = $this->createConnector(db: $db)->getMyProfile(['id' => '1111']);
        $this->assertEquals($expected, $result);
    }

    /**
     * Generate a new driver to return responses set in a json fixture
     *
     * Overwrites $this->driver
     *
     * @param ?array      $config Driver configuration (null to use default)
     * @param ?MockObject $db     Db mock object.
     *
     * @return MockObject&Virtua
     */
    protected function createConnector(?array $config = null, ?MockObject $db = null): MockObject&Virtua
    {
        // Create a stub for the class
        $driver = $this->getMockBuilder(Virtua::class)->onlyMethods([])->getMock();
        // Configure the stub
        $driver->setConfig($config ?? $this->defaultDriverConfig);

        $db ??= $this->getMockBuilder(\VuFind\Connection\Oracle::class)->onlyMethods(['simpleSelect'])
            ->disableOriginalConstructor()->getMock();
        // Mocks can not alter the destructor function and it will be called so set it to false to not
        // throw an error.
        $this->setProperty($db, 'dbHandle', false);
        // Reveal the protected db property and set the mock db as its value
        $this->setProperty($driver, 'db', $db);
        return $driver;
    }
}
