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

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\ILS\Driver\XCNCIP2;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class XCNCIP2Test extends \VuFindTest\Unit\ILSDriverTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new XCNCIP2(new \VuFind\Date\Converter());
    }

    /**
     * Test definition for testGetMyTransactions
     *
     * @var array[]
     */
    protected $transactionsTests = [
        [
            'file' => [
                'lookupUserResponse.xml',
                'LookupItem.xml',
            ],
            'result' => [
                [
                    'id' => 'MZK01000847602-MZK50000847602000090',
                    'item_agency_id' => 'My Agency',
                    'patronAgencyId' => 'Test agency',
                    'duedate' => '11-19-2014',
                    'title' => 'Jahrbücher der Deutschen Malakozoologischen Gesellschaft ...',
                    'item_id' => '104',
                    'renewable' => false,
                ],
                [
                    'id' => 'KN3183000000046386',
                    'item_agency_id' => 'Agency from lookup item',
                    'patronAgencyId' => 'Test agency',
                    'duedate' => '11-26-2014',
                    'title' => 'Anna Nahowská a císař František Josef : zápisky / Friedrich Saathen ; z něm. přel. Ivana Víz',
                    'item_id' => '105',
                    'renewable' => true,
                ],
            ],
        ],
        [
            'file' => [
                'LookupUserResponseWithoutNamespacePrefix.xml',
            ],
            'result' => [
                [
                    'id' => 'MZK01000847602-MZK50000847602000090',
                    'item_agency_id' => 'My Agency',
                    'patronAgencyId' => 'Test agency',
                    'duedate' => '11-19-2014',
                    'title' => 'Jahrbücher der Deutschen Malakozoologischen Gesellschaft ...',
                    'item_id' => '104',
                    'renewable' => true,
                ],
                [
                    'id' => 'MZK01000000456-MZK50000000456000440',
                    'item_agency_id' => 'My Agency',
                    'patronAgencyId' => 'Test agency',
                    'duedate' => '11-26-2014',
                    'title' => 'Anna Nahowská a císař František Josef : zápisky / Friedrich Saathen ; z něm. přel. Ivana Víz',
                    'item_id' => '105',
                    'renewable' => true,
                ],
            ],
        ],
        [
            'file' => [
                'LookupUserResponseWithoutNamespaceDefinition.xml',
            ],
            'result' => [
                [
                    'id' => 'MZK01000847602-MZK50000847602000090',
                    'item_agency_id' => 'My Agency',
                    'patronAgencyId' => 'Test agency',
                    'duedate' => '11-19-2014',
                    'title' => 'Jahrbücher der Deutschen Malakozoologischen Gesellschaft ...',
                    'item_id' => '104',
                    'renewable' => true,
                ],
                [
                    'id' => 'MZK01000000456-MZK50000000456000440',
                    'item_agency_id' => 'My Agency',
                    'patronAgencyId' => 'Test agency',
                    'duedate' => '11-26-2014',
                    'title' => 'Anna Nahowská a císař František Josef : zápisky / Friedrich Saathen ; z něm. přel. Ivana Víz',
                    'item_id' => '105',
                    'renewable' => true,
                ],
            ],
        ],
    ];

    /**
     * Test definition for testGetMyFines
     *
     * @var array[]
     */
    protected $finesTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                [
                    'id' => '8071750247',
                    'duedate' => '',
                    'amount' => 25,
                    'balance' => 25,
                    'checkout' => '',
                    'fine' => 'Service Charge',
                    'createdate' => '11-14-2014',
                ],
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                [
                    'id' => '',
                    'duedate' => '',
                    'amount' => 25,
                    'balance' => 25,
                    'checkout' => '',
                    'fine' => 'Service Charge',
                    'createdate' => '11-14-2014',
                ],
            ],
        ],
    ];

    /**
     * Test definition for testPatronLogin
     *
     * @var array[]
     */
    protected $loginTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                'id' => '700',
                'patronAgencyId' => 'MZK',
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'email' => 'test@mzk.cz',
                'major' => null,
                'college' => null,
                'firstname' => 'John',
                'lastname' => 'Smith',
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                'id' => '700',
                'patronAgencyId' => 'MZK',
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'email' => 'test@mzk.cz',
                'major' => null,
                'college' => null,
                'firstname' => 'John',
                'lastname' => 'Smith',
            ],
        ],
    ];

    /**
     * Test definition for testGetMyHolds
     *
     * @var array[]
     */
    protected $holdsTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                [
                    'id' => '111',
                    'title' => 'Ahoj, Blanko! : dívčí román / Eva Bernardinová',
                    'item_id' => 'MZK01000353880-MZK50000353880000040',
                    'create' => '10-10-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'available' => false,

                ],
                [
                    'id' => '112',
                    'title' => 'Aktiv revizních techniků elektrických zařízení',
                    'item_id' => 'MZK01000065021-MZK50000065021000010',
                    'create' => '10-23-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'available' => false,
                ],
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                [
                    'id' => '111',
                    'title' => 'Ahoj, Blanko! : dívčí román / Eva Bernardinová',
                    'item_id' => 'MZK01000353880-MZK50000353880000040',
                    'create' => '10-10-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'available' => false,

                ],
                [
                    'id' => '112',
                    'title' => 'Aktiv revizních techniků elektrických zařízení',
                    'item_id' => 'MZK01000065021-MZK50000065021000010',
                    'create' => '10-23-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'available' => false,
                ],
            ],
        ],
    ];

    /**
     * Test definition for testGetMyProfile
     *
     * @var array[]
     */
    protected $profileTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                'firstname' => 'John',
                'lastname' => 'Smith',
                'address1' => 'Trvalá ulice 123, Big City, 12345',
                'address2' => '',
                'zip' => '',
                'phone' => '',
                'group' => '',
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                'firstname' => 'John',
                'lastname' => 'Smith',
                'address1' => 'Trvalá ulice 123, Big City, 12345',
                'address2' => '',
                'zip' => '',
                'phone' => '',
                'group' => '',
            ],
        ],
    ];

    /**
     * Test definition for testGetMyStorageRetrievalRequests
     *
     * @var array[]
     */
    protected $storageRetrievalTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                [
                    'id' => '155',
                    'title' => 'Listen and play : with magicians! : 3. ročník / Věra Štiková ; [ilustrace Andrea Schindlerová]',
                    'create' => '11-09-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'item_id' => 'MZK01001333770-MZK50001370317000020',
                    'available' => false,
                ],
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                [
                    'id' => '155',
                    'title' => 'Listen and play : with magicians! : 3. ročník / Věra Štiková ; [ilustrace Andrea Schindlerová]',
                    'create' => '11-09-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'item_id' => 'MZK01001333770-MZK50001370317000020',
                    'available' => false,
                ],
            ],
        ],
    ];

    /**
     * Test definition for testGetStatuses
     *
     * @var array[]
     */
    protected $statusesTests = [
        [
            'file' => 'lookupItemSet.xml',
            'result' => [
                'MZK01000000421' => [
                    [
                        'status' => 'Available on shelf',
                        'location' => null,
                        'callnumber' => '621.3 ANG',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000421',
                    ],
                ],
                'MZK01000062021' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => null,
                        'callnumber' => 'PK-0083.568',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000062021',
                    ],
                ],
                'MZK01000000425' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => 'Some holding location',
                        'callnumber' => '2-0997.767,2',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                    ],
                    [
                        'status' => 'Circulation Status Undefined',
                        'location' => 'Some holding location',
                        'callnumber' => null,
                        'availability' => false,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                        'use_unknown_message' => true,
                    ],
                ],
            ],
        ],
        [
            'file' => 'lookupItemSetWithoutNamespacePrefix.xml',
            'result' => [
                'MZK01000000421' => [
                    [
                        'status' => 'Available on shelf',
                        'location' => null,
                        'callnumber' => '621.3 ANG',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000421',
                    ],
                ],
                'MZK01000062021' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => null,
                        'callnumber' => 'PK-0083.568',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000062021',
                    ],
                ],
                'MZK01000000425' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => 'Some holding location',
                        'callnumber' => '2-0997.767,2',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                    ],
                    [
                        'status' => 'Available On Shelf',
                        'location' => 'Some holding location',
                        'callnumber' => null,
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                    ],
                ],
            ],
        ],
    ];

    /**
     * Test definition for testGetHolding
     *
     * @var array[]
     */
    protected $holdingTests = [
        [
            'file' => 'lookupItemSet.xml',
            'result' => [
                [
                    'status' => 'Available on shelf',
                    'location' => null,
                    'callnumber' => '621.3 ANG',
                    'availability' => true,
                    'reserve' => 'N',
                    'id' => '123456',
                    'item_id' => 'MZK01000000421-MZK50000000421000010',
                    'bib_id' => 'MZK01000000421',
                    'duedate' => '',
                    'volume' => '',
                    'number' => '',
                    'is_holdable' => true,
                    'addLink' => true,
                    'storageRetrievalRequest' => 'auto',
                    'addStorageRetrievalRequestLink' => 'true',
                    'eresource' => '',
                    'item_agency_id' => 'My university',
                    'holdtype' => 'Hold',
                    'barcode' => 'MZK01000000421-MZK50000000421000010',
                ],
                [
                    'status' => 'Available On Shelf',
                    'location' => null,
                    'callnumber' => 'PK-0083.568',
                    'availability' => true,
                    'reserve' => 'N',
                    'id' => '123456',
                    'bib_id' => 'MZK01000062021',
                    'item_id' => 'MZK01000062021-MZK50000062021000010',
                    'item_agency_id' => '',
                    'duedate' => '',
                    'volume' => '',
                    'number' => '',
                    'barcode' => 'Unknown barcode',
                    'is_holdable' => true,
                    'addLink' => true,
                    'holdtype' => 'Hold',
                    'storageRetrievalRequest' => 'auto',
                    'addStorageRetrievalRequestLink' => 'true',
                    'eresource' => '',
                ],
                [
                    'status' => 'Available On Shelf',
                    'location' => 'Some holding location',
                    'callnumber' => '2-0997.767,2',
                    'availability' => true,
                    'reserve' => 'N',
                    'id' => '123456',
                    'item_id' => 'MZK01000000425-MZK50000000425000020',
                    'bib_id' => 'MZK01000000425',
                    'item_agency_id' => '',
                    'duedate' => '',
                    'volume' => '',
                    'number' => '',
                    'barcode' => 'Unknown barcode',
                    'is_holdable' => true,
                    'addLink' => true,
                    'holdtype' => 'Hold',
                    'storageRetrievalRequest' => 'auto',
                    'addStorageRetrievalRequestLink' => 'true',
                    'eresource' => '',
                ],
                [
                    'status' => 'Circulation Status Undefined',
                    'location' => 'Some holding location',
                    'callnumber' => '',
                    'availability' => false,
                    'reserve' => 'N',
                    'id' => '123456',
                    'use_unknown_message' => true,
                    'item_id' => 'MZK01000000425-MZK50000000425000030',
                    'bib_id' => 'MZK01000000425',
                    'item_agency_id' => '',
                    'duedate' => '',
                    'volume' => '',
                    'number' => '',
                    'barcode' => 'Unknown barcode',
                    'is_holdable' => true,
                    'addLink' => true,
                    'holdtype' => 'Recall',
                    'storageRetrievalRequest' => 'auto',
                    'addStorageRetrievalRequestLink' => 'true',
                    'eresource' => '',
                ],
            ],
        ],
    ];

    /**
     * Test definition for testPlaceHold
     *
     * @var array[]
     */
    protected $placeHoldTests = [
        [
            'file' => 'RequestItemResponseAcceptedWithItemId.xml',
            'result' => [
                'success' => true,
                'sysMessage' => 'Request Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseAcceptedWithRequestId.xml',
            'result' => [
                'success' => true,
                'sysMessage' => 'Request Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDenied.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedWithIdentifiers.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedNotFullProblemElement.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedEmpty.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
    ];

    /**
     * Test definition for testPlaceStorageRetrievalRequest
     *
     * @var array[]
     */
    protected $placeStorageRetrievalRequestTests = [
        [
            'file' => 'RequestItemResponseAcceptedWithItemId.xml',
            'result' => [
                'success' => true,
                'sysMessage' => 'Storage Retrieval Request Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseAcceptedWithRequestId.xml',
            'result' => [
                'success' => true,
                'sysMessage' => 'Storage Retrieval Request Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDenied.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Storage Retrieval Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedWithIdentifiers.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Storage Retrieval Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedNotFullProblemElement.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Storage Retrieval Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedEmpty.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Storage Retrieval Request Not Successful.'
            ],
        ],
    ];

    /**
     * Test definition for testCancelHolds
     *
     * @var array[]
     */
    protected $cancelHoldsTests = [
        [
            'file' => 'CancelRequestItemResponseAccepted.xml',
            'result' => [
                'count' => 1,
                'items' => [
                    'Item1' => [
                        'success' => true,
                        'status' => 'hold_cancel_success',
                    ],
                ],
            ],
        ],
        [
            'file' => 'CancelRequestItemResponseDenied.xml',
            'result' => [
                'count' => 0,
                'items' => [
                    'Item1' => [
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                    ],
                ],
            ],
        ],
        [
            'file' => 'CancelRequestItemResponseDeniedWithUserId.xml',
            'result' => [
                'count' => 0,
                'items' => [
                    'Item1' => [
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                    ],
                ],
            ],
        ],
    ];

    /**
     * Test definition for testCancelStorageRetrievalRequests
     *
     * @var array[]
     */
    protected $cancelStorageRetrievalTests = [
        [
            'file' => 'CancelRequestItemResponseAccepted.xml',
            'result' => [
                'count' => 1,
                'items' => [
                    'Item1' => [
                        'success' => true,
                        'status' => 'storage_retrieval_request_cancel_success',
                    ],
                ],
            ],
        ],
        [
            'file' => 'CancelRequestItemResponseDenied.xml',
            'result' => [
                'count' => 0,
                'items' => [
                    'Item1' => [
                        'success' => false,
                        'status' => 'storage_retrieval_request_cancel_fail',
                    ],
                ],
            ],
        ],
        [
            'file' => 'CancelRequestItemResponseDeniedWithUserId.xml',
            'result' => [
                'count' => 0,
                'items' => [
                    'Item1' => [
                        'success' => false,
                        'status' => 'storage_retrieval_request_cancel_fail',
                    ],
                ],
            ],
        ],
    ];

    /**
     * Test definition for testRenewMyItems
     *
     * @var array[]
     */
    protected $renewMyItemsTests = [
        [
            'file' => 'RenewItemResponseAccepted.xml',
            'result' => [
                'blocks' => false,
                'details' => [
                    'Item1' => [
                        'success' => true,
                        'new_date' => '09-08-2020',
                        'new_time' => '20:00',
                        'item_id' => 'Item1'
                    ],
                ],
            ],
        ],
        [
            'file' => 'RenewItemResponseAcceptedAlternativeDateFormat.xml',
            'result' => [
                'blocks' => false,
                'details' => [
                    'Item1' => [
                        'success' => true,
                        'new_date' => '08-31-2020',
                        'new_time' => '17:59',
                        'item_id' => 'Item1'
                    ],
                ],
            ],
        ],
        [
            'file' => 'RenewItemResponseDenied.xml',
            'result' => [
                'blocks' => false,
                'details' => [
                    'Item1' => [
                        'success' => false,
                        'item_id' => 'Item1'
                    ],
                ],
            ],
        ],
        [
            'file' => 'RenewItemResponseDeniedInvalidMessage.xml',
            'result' => [
                'blocks' => false,
                'details' => [
                    'Item1' => [
                        'success' => false,
                        'item_id' => 'Item1'
                    ],
                ],
            ],
        ],
    ];

    /**
     * Test getMyTransactions
     *
     * @return void
     */
    public function testGetMyTransactions()
    {
        $this->configureDriver();
        foreach ($this->transactionsTests as $test) {
            $this->mockResponse($test['file']);
            $transactions = $this->driver->getMyTransactions([
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'patronAgencyId' => 'Test agency',
            ]);
            $this->assertEquals(
                $test['result'], $transactions, 'Fixture file: ' . implode(', ', (array)$test['file'])
            );
        }
    }

    /**
     * Test getMyFines
     *
     * @return void
     */
    public function testGetMyFines()
    {
        $this->configureDriver();
        foreach ($this->finesTests as $test) {
            $this->mockResponse($test['file']);
            $fines = $this->driver->getMyFines(
                [
                    'cat_username' => 'my_login', 'cat_password' => 'my_password',
                    'patronAgencyId' => 'Test agency',
                ]
            );
            $this->assertEquals($test['result'], $fines, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test patronLogin
     *
     * @return void
     */
    public function testPatronLogin()
    {
        $this->configureDriver();
        foreach ($this->loginTests as $test) {
            $this->mockResponse($test['file']);
            $patron = $this->driver->patronLogin('my_login', 'my_password');
            $this->assertEquals($test['result'], $patron, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test getMyHolds
     *
     * @return void
     */
    public function testGetMyHolds()
    {
        $this->configureDriver();
        foreach ($this->holdsTests as $test) {
            $this->mockResponse($test['file']);
            $holds = $this->driver->getMyHolds([
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'patronAgencyId' => 'Test agency',
             ]);
            $this->assertEquals($test['result'], $holds, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test getMyProfile
     *
     * @return void
     */
    public function testGetMyProfile()
    {
        $this->configureDriver();
        foreach ($this->profileTests as $test) {
            $this->mockResponse($test['file']);
            $profile = $this->driver->getMyProfile(
                [
                    'cat_username' => 'my_login',
                    'cat_password' => 'my_password',
                    'patronAgencyId' => 'Test agency',
                ]
            );
            $this->assertEquals($test['result'], $profile, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test getMyStorageRetrievalRequests
     *
     * @return void
     */
    public function testGetMyStorageRetrievalRequests()
    {
        $this->configureDriver();
        foreach ($this->storageRetrievalTests as $test) {
            $this->mockResponse($test['file']);
            $storageRetrievals = $this->driver->getMyStorageRetrievalRequests([
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'patronAgencyId' => 'Test agency',
            ]);
            $this->assertEquals($test['result'], $storageRetrievals, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test getStatuses
     *
     * @return void
     */
    public function testGetStatuses()
    {
        $this->configureDriver();
        foreach ($this->statusesTests as $test) {
            $this->mockResponse($test['file']);
            $status = $this->driver->getStatuses(['Some Id']);
            $this->assertEquals($test['result'], $status, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test getHolding
     *
     * @return void
     */
    public function testGetHolding()
    {
        $this->configureDriver();
        foreach ($this->holdingTests as $test) {
            $this->mockResponse($test['file']);
            $holdings = $this->driver->getHolding('123456');
            $this->assertEquals($test['result'], $holdings, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test getPickUpLocations
     *
     * @return void
     */
    public function testGetPickupLocations()
    {
        // Test reading pickup locations from file
        $this->configureDriver();
        $locations = $this->driver->getPickUpLocations([]);
        $this->assertEquals([
            [
                'locationId' => 'My University|1',
                'locationDisplay' => 'Main Circulation Desk',
            ],
            [
                'locationId' => 'My University|2',
                'locationDisplay' => 'Stacks',
            ]
        ], $locations);

        // Test reading pickup locations from NCIP responder
        $this->configureDriver([
                'Catalog' => [
                    'url' => 'https://test.ncip.example',
                    'consortium' => false,
                    'agency' => ['Test agency'],
                    'pickupLocationsFromNCIP' => true,
                ],
                'NCIP' => [],
            ]);
        $this->mockResponse('LookupAgencyResponse.xml');
        $locations = $this->driver->getPickUpLocations([]);
        $this->assertEquals([
            [
                'locationId' => 'My library|1',
                'locationDisplay' => 'Main library',
            ],
            [
                'locationId' => 'My library|2',
                'locationDisplay' => 'Stacks',
            ]
        ], $locations);

        // Test reading pickup locations from NCIP, but response is without locations
        $this->configureDriver([
            'Catalog' => [
                'url' => 'https://test.ncip.example',
                'consortium' => false,
                'agency' => ['Test agency'],
                'pickupLocationsFromNCIP' => true,
            ],
            'NCIP' => [],
        ]);
        $this->mockResponse('LookupAgencyResponseWithoutLocations.xml');
        $locations = $this->driver->getPickUpLocations([]);
        $this->assertEquals([], $locations);
    }

    /**
     * Test placeHold
     *
     * @return void
     */
    public function testPlaceHold()
    {
        $this->configureDriver();
        foreach ($this->placeHoldTests as $test) {
            $this->mockResponse($test['file']);
            $hold = $this->driver->placeHold(
                [
                    'patron' => [
                        'cat_username' => 'my_login',
                        'cat_password' => 'my_password',
                        'patronAgencyId' => 'Test agency',
                    ],
                    'bib_id' => '1',
                    'item_id' => '1',
                    'pickUpLocation' => 'My University|1',
                    'holdtype' => 'title',
                    'requiredBy' => '2020-12-30',
                    'item_agency_id' => 'My University',
                ]
            );
            $this->assertEquals($test['result'], $hold, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test placeStorageRetrievalRequest
     *
     * @return void
     */
    public function testPlaceStorageRetrievalRequest()
    {
        $this->configureDriver();
        foreach ($this->placeStorageRetrievalRequestTests as $test) {
            $this->mockResponse($test['file']);
            $result = $this->driver->placeStorageRetrievalRequest(
                [
                    'patron' => [
                        'cat_username' => 'my_login',
                        'cat_password' => 'my_password',
                        'patronAgencyId' => 'Test agency',
                    ],
                    'bib_id' => '1',
                    'item_id' => '1',
                    'pickUpLocation' => 'My University|1',
                    'holdtype' => 'title',
                    'requiredBy' => '2020-12-30',
                    'item_agency_id' => 'My University',
                ]
            );
            $this->assertEquals($test['result'], $result, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test cancelHolds
     *
     * @return void
     */
    public function testCancelHolds()
    {
        $this->configureDriver();
        foreach ($this->cancelHoldsTests as $test) {
            $this->mockResponse($test['file']);
            $result = $this->driver->cancelHolds(
                [
                    'patron' => [
                        'cat_username' => 'my_login',
                        'cat_password' => 'my_password',
                        'patronAgencyId' => 'Test agency',
                    ],
                    'details' => [
                        'My University|Request1|Item1',
                    ],
                ]
            );
            $this->assertEquals($test['result'], $result, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test cancelHolds
     *
     * @return void
     */
    public function testCancelStorageRetrievalRequests()
    {
        $this->configureDriver();
        foreach ($this->cancelStorageRetrievalTests as $test) {
            $this->mockResponse($test['file']);
            $result = $this->driver->cancelStorageRetrievalRequests(
                [
                    'patron' => [
                        'cat_username' => 'my_login',
                        'cat_password' => 'my_password',
                        'patronAgencyId' => 'Test agency',
                    ],
                    'details' => [
                        'My University|Request1|Item1',
                    ],
                ]
            );
            $this->assertEquals($test['result'], $result, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test renewMyItems
     *
     * @return void
     */
    public function testRenewMyItems()
    {
        $this->configureDriver();
        foreach ($this->renewMyItemsTests as $test) {
            $this->mockResponse($test['file']);
            $result = $this->driver->renewMyItems(
                [
                    'patron' => [
                        'cat_username' => 'my_login',
                        'cat_password' => 'my_password',
                        'patronAgencyId' => 'Test agency',
                    ],
                    'details' => [
                        'My University|Item1',
                    ],
                ]
            );
            $this->assertEquals($test['result'], $result, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test definition for testGetRequestMethods
     *
     * @var array[]
     */
    protected $requestTests = [
        '1' => [
            'method' => 'getStatusRequest',
            'config' => [
                'Catalog' => [
                    'url' => 'https://test.ncip.example',
                    'consortium' => false,
                    'agency' => ['Test agency'],
                    'pickupLocationsFile' => 'XCNCIP2_locations.txt',
                    'fromAgency' => 'My portal',
                ],
                'NCIP' => [],
            ],
            'params' => [['1'], null, 'Test agency'],
            'result' => 'LookupItemSetRequest.xml',
        ],
        '2' => [
            'method' => 'getStatusRequest',
            'params' => [['1'], null, 'Test agency'],
            'result' => 'LookupItemSetRequestWithoutHeader.xml',
        ],
        '3' => [
            'method' => 'getCancelRequest',
            'params' => ['', '', 'patron agency', 'item agency', 'rq1', 'Hold', 'item1', '12345'],
            'result' => 'CancelRequestItemRequest.xml'
        ],
        '4' => [
            'method' => 'getCancelRequest',
            'params' => ['username', 'password', 'patron agency', 'item agency', 'rq1', 'Hold', 'item1', '12345'],
            'result' => 'CancelRequestItemRequestAuthInput.xml'
        ],
        '5' => [
            'method' => 'getRenewRequest',
            'params' => ['username', 'password', 'item1', 'item agency', 'patron agency'],
            'result' => 'RenewItemRequest.xml'
        ],
        '6' => [
            'method' => 'getRequest',
            'config' => [
                'Catalog' => [
                    'url' => 'https://test.ncip.example',
                    'consortium' => false,
                    'agency' => ['Test agency'],
                    'pickupLocationsFile' => 'XCNCIP2_locations.txt',
                    'fromAgency' => 'My portal',
                ],
                'NCIP' => [],
            ],
            'params' => ['username', '', 'bib1', 'item1', 'patron agency', 'item agency', 'Hold', 'Item', '2020-12-20T00:00:00.000Z', null, 'patron1'],
            'result' => 'RequestItemRequest.xml'
        ],
        '7' => [
            'method' => 'getLookupUserRequest',
            'params' => [null, 'password', 'patron agency', ['<ns1:LoanedItemsDesired />'], 'patron1'],
            'result' => 'LookupUserRequest.xml'
        ],
        '8' => [
            'method' => 'getLookupAgencyRequest',
            'params' => [null],
            'result' => 'LookupAgencyRequest.xml'
        ],
        '9' => [
            'method' => 'getLookupItemRequest',
            'config' => [
                'Catalog' => [
                    'url' => 'https://test.ncip.example',
                    'consortium' => false,
                    'agency' => ['Test agency'],
                    'pickupLocationsFile' => 'XCNCIP2_locations.txt',
                    'fromAgency' => 'My portal',
                ],
                'NCIP' => [],
            ],
            'params' => ['item1', 'Accession Number'],
            'result' => 'LookupItemRequest.xml'
        ],
    ];

    /**
     * Test methods for creating NCIP requests
     *
     * @return void
     */
    public function testGetRequestMethods()
    {
        foreach ($this->requestTests as $id => $test) {
            $this->configureDriver($test['config'] ?? null);
            $method = new \ReflectionMethod('\VuFind\ILS\Driver\XCNCIP2', $test['method']);
            $method->setAccessible(true);
            $request = $method->invokeArgs($this->driver, $test['params'] ?? []);
            $file = realpath(
                __DIR__ .
                '/../../../../../../tests/fixtures/xcncip2/request/' .
                $test['result']
            );
            $expected = file_get_contents($file);
            $this->assertEquals($expected, $request, 'Test identifier: ' . $id);
        }
    }

    /**
     * Mock fixture as HTTP client response
     *
     * @param string|array|null $fixture Fixture file
     **
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function mockResponse($fixture = null)
    {
        $adapter = new TestAdapter();
        if (!empty($fixture)) {
            $fixture = (array)$fixture;
            $responseObj = $this->loadResponse($fixture[0]);
            $adapter->setResponse($responseObj);
            array_shift($fixture);
            foreach ($fixture as $f) {
                $responseObj = $this->loadResponse($f);
                $adapter->addResponse($responseObj);
            }
        }

        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $this->driver->setHttpService($service);
    }

    /**
     * Load response from file
     *
     * @param string $filename File name of raw HTTP response
     *
     * @return HttpResponse Response object
     */
    protected function loadResponse($filename)
    {
        $file = realpath(
            __DIR__ .
            '/../../../../../../tests/fixtures/xcncip2/response/' . $filename
        );
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(
                sprintf('Unable to load fixture file: %s ', $file)
            );
        }
        $response = file_get_contents($file);
        return HttpResponse::fromString($response);
    }

    /**
     * Configure driver for test case
     *
     * @param array|null $config ILS driver configuration
     *
     * @return void
     */
    protected function configureDriver($config = null)
    {
        $this->driver = new XCNCIP2(new \VuFind\Date\Converter());
        $this->driver->setConfig($config ?? [
            'Catalog' => [
                'url' => 'https://test.ncip.example',
                'consortium' => false,
                'agency' => 'Test agency',
                'pickupLocationsFile' => 'XCNCIP2_locations.txt',
            ],
            'NCIP' => [],
        ]);
        $this->driver->init();
    }
}
