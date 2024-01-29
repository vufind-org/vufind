<?php

/**
 * GeniePlus ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Laminas\Http\Response;
use Laminas\Session\Container;
use VuFind\ILS\Driver\GeniePlus;

/**
 * GeniePlus ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GeniePlusTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Default driver configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Default expected patron login response
     *
     * @var array
     */
    protected $defaultPatron = [
        'id' => 'fake.user.fake.com',
        'firstname' => 'Fake',
        'lastname' => 'User',
        'cat_username' => 'foo@foo.com',
        'cat_password' => 'bar',
        'email' => 'fake.user@fake.com',
        'major' => null,
        'college' => null,
    ];

    /**
     * Expected parameters to patron login request
     *
     * @var array
     */
    protected $expectedLoginRequest = [
        'GET',
        '/_rest/databases/api_database_name/templates/Borrower/search-result',
        [
            'page-size' => 1,
            'page' => 0,
            'fields' => 'ID,Name,Email',
            'command' => "Email == 'foo@foo.com' AND InstitutionalIdNumber == 'bar'",
        ],
        [
            'Accept: application/json',
            'Authorization: Bearer fake-token',
        ],
    ];

    /**
     * Expected parameters to token generation request
     *
     * @var array
     */
    protected $expectedTokenRequest = [
        'POST',
        '/_oauth/token',
        [
            'client_id' => 'api_oauth_id',
            'grant_type' => 'password',
            'database' => 'api_database_name',
            'username' => 'api_username',
            'password' => 'api_password',
        ],
        ['Accept: application/json'],
    ];

    /**
     * Get a mock response with a predetermined body.
     *
     * @param string $body   Body
     * @param int    $status HTTP status code
     *
     * @return Response
     */
    protected function getMockResponse($body, $status = 200): Response
    {
        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->any())
            ->method('getBody')
            ->will($this->returnValue($body));
        $response->expects($this->any())
            ->method('getStatusCode')
            ->will($this->returnValue($status));
        return $response;
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->config = parse_ini_file(
            APPLICATION_PATH . '/config/vufind/GeniePlus.ini',
            true
        );
        $sessionFactory = function ($i) {
            return $this->getMockBuilder(Container::class)
                ->disableOriginalConstructor()
                ->getMock();
        };
        $this->driver = $this->getMockBuilder(GeniePlus::class)
            ->setConstructorArgs([$sessionFactory])
            ->onlyMethods(['makeRequest'])
            ->getMock();
    }

    /**
     * Test API failure
     *
     * @return void
     */
    public function testAPIFailure(): void
    {
        // Note: in a real-world scenario, the makeRequest method would throw
        // an exception instead of returning a value when encountering a 500
        // status, but this test is retained to confirm that invalid responses
        // are processed appropriately.
        $response = $this->getMockResponse('Internal server error', 500);
        $this->driver->expects($this->once())
            ->method('makeRequest')
            ->with(...$this->expectedTokenRequest)
            ->willReturn($response);
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->expectExceptionMessage('No access token in API response.');
        $this->driver->patronLogin('foo@foo.com', 'bar');
    }

    /**
     * Test token auto-renewal
     *
     * @return void
     */
    public function testTokenAutoRenewal(): void
    {
        $goodToken = $this->getMockResponse(
            $this->getFixture('genieplus/token.json')
        );
        $expiredToken = $this->getMockResponse('Forbidden', 403);
        $patronLogin = $this->getMockResponse(
            $this->getFixture('genieplus/patronLogin.json')
        );
        $this->expectConsecutiveCalls(
            $this->driver,
            'makeRequest',
            [
                // first attempt (new token):
                $this->expectedTokenRequest,
                $this->expectedLoginRequest,
                // second attempt (renew expired token):
                $this->expectedLoginRequest,
                $this->expectedTokenRequest,
                $this->expectedLoginRequest,
            ],
            [
                // first attempt (new token):
                $goodToken,
                $patronLogin,
                // second attempt (renew expired token):
                $expiredToken,
                $goodToken,
                $patronLogin,
            ]
        );
        $this->driver->setConfig($this->config);
        $this->driver->init();
        // We'll call patronLogin twice -- the first time will simulate a "normal"
        // first login, the second will simulate the token having expired after the
        // passage of time.
        for ($i = 0; $i < 2; $i++) {
            $this->assertEquals(
                $this->defaultPatron,
                $this->driver->patronLogin('foo@foo.com', 'bar')
            );
        }
    }

    /**
     * Test patron login
     *
     * @return void
     */
    public function testPatronLogin(): void
    {
        $response1 = $this->getMockResponse(
            $this->getFixture('genieplus/token.json')
        );
        $response2 = $this->getMockResponse(
            $this->getFixture('genieplus/patronLogin.json')
        );
        $this->expectConsecutiveCalls(
            $this->driver,
            'makeRequest',
            [
                $this->expectedTokenRequest,
                $this->expectedLoginRequest,
            ],
            [
                $response1,
                $response2,
            ]
        );
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->assertEquals(
            $this->defaultPatron,
            $this->driver->patronLogin('foo@foo.com', 'bar')
        );
    }

    /**
     * Configure the driver to respond to a getHolding() call.
     *
     * @return void
     */
    protected function setUpHoldingTest(): void
    {
        $response1 = $this->getMockResponse(
            $this->getFixture('genieplus/token.json')
        );
        $response2 = $this->getMockResponse(
            $this->getFixture('genieplus/holdings.json')
        );
        $this->expectConsecutiveCalls(
            $this->driver,
            'makeRequest',
            [
                $this->expectedTokenRequest,
                [
                    'GET',
                    '/_rest/databases/api_database_name/templates/Catalog/search-result',
                    [
                        'page-size' => 100,
                        'page' => 0,
                        'fields' => 'Inventory.Barcode,Inventory.CallNumLC,Inventory.ClaimDate,UniqRecNum,'
                        . 'Inventory.SubLoc.CodeDesc,Inventory.ActType.Status,Inventory.VolumeDesc',
                        'command' => "UniqRecNum == 'foo-id'",
                    ],
                    [
                        'Accept: application/json',
                        'Authorization: Bearer fake-token',
                    ],
                ],
            ],
            [
                $response1,
                $response2,
            ]
        );
    }

    /**
     * Test holdings lookup with default sort
     *
     * @return void
     */
    public function testGetHoldingWithDefaultSort(): void
    {
        $this->setUpHoldingTest();
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->assertEquals(
            [
                [
                    'id' => 'foo-id',
                    'availability' => 1,
                    'status' => 'Ready for Loans',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '',
                    'number' => '2017 no.3',
                    'barcode' => 'barcode3',
                ],
                [
                    'id' => 'foo-id',
                    'availability' => 0,
                    'status' => 'On Loan',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '3/4/2022 11:59:59 PM',
                    'number' => '2016 no.2',
                    'barcode' => 'barcode2',
                ],
                [
                    'id' => 'foo-id',
                    'availability' => 1,
                    'status' => 'Ready for Loans',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '',
                    'number' => '2015 no.1',
                    'barcode' => 'barcode1',
                ],
            ],
            $this->driver->getHolding('foo-id')
        );
    }

    /**
     * Test holdings lookup with custom ascending sort
     *
     * @return void
     */
    public function testGetHoldingWithNonDefaultAscendingSort(): void
    {
        $this->setUpHoldingTest();
        $this->config['Item']['sort'] = 'number asc';
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->assertEquals(
            [
                [
                    'id' => 'foo-id',
                    'availability' => 1,
                    'status' => 'Ready for Loans',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '',
                    'number' => '2015 no.1',
                    'barcode' => 'barcode1',
                ],
                [
                    'id' => 'foo-id',
                    'availability' => 0,
                    'status' => 'On Loan',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '3/4/2022 11:59:59 PM',
                    'number' => '2016 no.2',
                    'barcode' => 'barcode2',
                ],
                [
                    'id' => 'foo-id',
                    'availability' => 1,
                    'status' => 'Ready for Loans',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '',
                    'number' => '2017 no.3',
                    'barcode' => 'barcode3',
                ],
            ],
            $this->driver->getHolding('foo-id')
        );
    }

    /**
     * Test holdings lookup with custom descending sort
     *
     * @return void
     */
    public function testGetHoldingWithNonDefaultDescendingSort(): void
    {
        $this->setUpHoldingTest();
        $this->config['Item']['sort'] = 'status desc';
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->assertEquals(
            [
                [
                    'id' => 'foo-id',
                    'availability' => 1,
                    'status' => 'Ready for Loans',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '',
                    'number' => '2017 no.3',
                    'barcode' => 'barcode3',
                ],
                [
                    'id' => 'foo-id',
                    'availability' => 1,
                    'status' => 'Ready for Loans',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '',
                    'number' => '2015 no.1',
                    'barcode' => 'barcode1',
                ],
                [
                    'id' => 'foo-id',
                    'availability' => 0,
                    'status' => 'On Loan',
                    'location' => 'Second Floor',
                    'reserve' => 'N',
                    'callnumber' => 'KF4651 .A767',
                    'duedate' => '3/4/2022 11:59:59 PM',
                    'number' => '2016 no.2',
                    'barcode' => 'barcode2',
                ],
            ],
            $this->driver->getHolding('foo-id')
        );
    }

    /**
     * Test profile retrieval
     *
     * @return void
     */
    public function testGetMyProfile(): void
    {
        $response1 = $this->getMockResponse(
            $this->getFixture('genieplus/token.json')
        );
        $response2 = $this->getMockResponse(
            $this->getFixture('genieplus/profile.json')
        );
        $this->expectConsecutiveCalls(
            $this->driver,
            'makeRequest',
            [
                $this->expectedTokenRequest,
                [
                    'GET',
                    '/_rest/databases/api_database_name/templates/Borrower/search-result',
                    [
                        'page-size' => 1,
                        'page' => 0,
                        'fields' => 'Address1,Address2,ZipCode,City,StateProv.CodeDesc,Country.CodeDesc,'
                        . 'PhoneNumber,ExpiryDate',
                        'command' => "ID == 'fake.user.fake.com'",
                    ],
                    [
                        'Accept: application/json',
                        'Authorization: Bearer fake-token',
                    ],
                ],
            ],
            [
                $response1,
                $response2,
            ]
        );
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->assertEquals(
            [
                'firstname' => 'Fake',
                'lastname' => 'User',
                'address1' => 'Address 1',
                'address2' => 'Address 2',
                'zip' => '12345',
                'city' => 'FakeCity, FakeState',
                'country' => 'USA',
                'phone' => '1234567890',
                'expiration_date' => '12/31/2022 3:55:00 PM',
            ],
            $this->driver->getMyProfile($this->defaultPatron)
        );
    }

    /**
     * Test transaction retrieval
     *
     * @return void
     */
    public function testGetMyTransactions(): void
    {
        $response1 = $this->getMockResponse(
            $this->getFixture('genieplus/token.json')
        );
        $response2 = $this->getMockResponse(
            $this->getFixture('genieplus/checkedout.json')
        );
        $this->expectConsecutiveCalls(
            $this->driver,
            'makeRequest',
            [
                $this->expectedTokenRequest,
                [
                    'GET',
                    '/_rest/databases/api_database_name/templates/CirLoan/search-result',
                    [
                        'page-size' => 100,
                        'page' => 0,
                        'fields' => 'Inventory.Barcode,Inventory.Inventory@Catalog.UniqRecNum,ClaimDate',
                        'command' => "Borrower.ID == 'fake.user.fake.com' AND Archive == 'No'",
                    ],
                    [
                        'Accept: application/json',
                        'Authorization: Bearer fake-token',
                    ],
                ],
            ],
            [
                $response1,
                $response2,
            ]
        );
        $this->driver->setConfig($this->config);
        $this->driver->init();
        $this->assertEquals(
            [
                'count' => 2,
                'records' => [
                    [
                        'id' => 'id1',
                        'item_id' => 'barcode1',
                        'duedate' => '3/4/2022 11:59:59 PM',
                    ],
                    [
                        'id' => 'id2',
                        'item_id' => 'barcode2',
                        'duedate' => '3/4/2022 11:59:59 PM',
                    ],
                ],
            ],
            $this->driver->getMyTransactions($this->defaultPatron)
        );
    }
}
