<?php
/**
 * ILS driver test
 *
 * PHP version 7
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
use VuFind\ILS\Driver\GeniePlus;

/**
 * ILS driver test
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
     * @param string $body Body
     *
     * @return Response
     */
    protected function getMockResponse($body): Response
    {
        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->any())
            ->method('getBody')
            ->will($this->returnValue($body));
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
        $this->driver = $this->getMockBuilder(GeniePlus::class)
            ->onlyMethods(['makeRequest'])
            ->getMock();
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
        $this->driver->expects($this->exactly(2))
            ->method('makeRequest')
            ->withConsecutive(
                $this->expectedTokenRequest,
                [
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
                    ]
                ],
            )->willReturnOnConsecutiveCalls(
                $response1,
                $response2,
            );
        $this->driver->setConfig($this->config);
        $this->assertEquals(
            $this->defaultPatron,
            $this->driver->patronLogin('foo@foo.com', 'bar')
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
        $this->driver->expects($this->exactly(2))
            ->method('makeRequest')
            ->withConsecutive(
                $this->expectedTokenRequest,
                [
                    'GET',
                    '/_rest/databases/api_database_name/templates/Borrower/search-result',
                    [
                        'page-size' => 1,
                        'page' => 0,
                        'fields' => 'Address1,Address2,ZipCode,City,StateProv.CodeDesc,Country.CodeDesc,PhoneNumber,ExpiryDate',
                        'command' => "ID == 'fake.user.fake.com'",
                    ],
                    [
                        'Accept: application/json',
                        'Authorization: Bearer fake-token',
                    ]
                ],
            )->willReturnOnConsecutiveCalls(
                $response1,
                $response2,
            );
        $this->driver->setConfig($this->config);
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
        $this->driver->expects($this->exactly(2))
            ->method('makeRequest')
            ->withConsecutive(
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
                    ]
                ],
            )->willReturnOnConsecutiveCalls(
                $response1,
                $response2,
            );
        $this->driver->setConfig($this->config);
        $this->assertEquals(
            [
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
            $this->driver->getMyTransactions($this->defaultPatron)
        );
    }
}
