<?php

/**
 * VuFind Helper - Renewals Support Methods
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
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Logic;

use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\TestCase;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RenewalsHelper;
use VuFind\Validator\CsrfInterface;

use function in_array;
use function is_array;

/**
 * Unit tests for the RenewalsHelper
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RenewalsHelperTest extends TestCase
{
    /**
     * Test adding renew details when using an external link.
     *
     * @return void
     */
    public function testAddRenewDetailsWithLink(): void
    {
        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();

        $ilsDetails = ['id' => 'item123', 'title' => 'Test Item'];
        $renewStatus = ['function' => 'renewMyItemsLink'];
        $expectedLink = '/MyResearch/renew/item123';

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('renewMyItemsLink'),
                $this->equalTo([$ilsDetails])
            )
            ->willReturn($expectedLink);

        $helper = new RenewalsHelper();
        $result = $helper->addRenewDetails($catalog, $ilsDetails, $renewStatus);

        $this->assertArrayHasKey('renew_link', $result);
        $this->assertEquals($expectedLink, $result['renew_link']);
        $this->assertArrayNotHasKey('renew_details', $result);
    }

    /**
     * Test adding renew details when using a form.
     *
     * @return void
     */
    public function testAddRenewDetailsWithForm(): void
    {
        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();

        $ilsDetails = ['id' => 'item456', 'title' => 'Another Item'];
        $renewStatus = ['function' => 'renewMyItems'];
        $expectedDetails = ['form_data' => 'details_here'];

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('getRenewDetails'),
                $this->equalTo([$ilsDetails])
            )
            ->willReturn($expectedDetails);

        $helper = new RenewalsHelper();
        $result = $helper->addRenewDetails($catalog, $ilsDetails, $renewStatus);

        $this->assertArrayHasKey('renew_details', $result);
        $this->assertEquals($expectedDetails, $result['renew_details']);
        $this->assertArrayNotHasKey('renew_link', $result);
    }

    /**
     * Test adding renew details when renewals are disabled.
     *
     * @return void
     */
    public function testAddRenewDetailsRenewalsDisabled(): void
    {
        $catalog = $this->createMock(Connection::class);
        $ilsDetails = ['id' => 'item789', 'title' => 'Disabled Item'];
        $renewStatus = false;

        $helper = new RenewalsHelper();
        $result = $helper->addRenewDetails($catalog, $ilsDetails, $renewStatus);

        $this->assertEquals($ilsDetails, $result);
        $this->assertArrayNotHasKey('renew_link', $result);
        $this->assertArrayNotHasKey('renew_details', $result);
    }

    /**
     * Test processing renewals with 'renewAll' button selected.
     *
     * @return void
     */
    public function testProcessRenewalsAll(): void
    {
        $request = new Parameters([
            'renewAll' => '1',
            'renewAllIDS' => ['id1', 'id2', 'id3'],
        ]);
        $idsToRenew = ['id1', 'id2', 'id3'];

        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);

        $renewalResult = [
            'details' => [
                'id1' => ['success' => true],
                'id2' => ['success' => false],
                'id3' => ['success' => true],
            ],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('renewMyItems'),
                $this->equalTo([['details' => $idsToRenew, 'patron' => $patron]])
            )
            ->willReturn($renewalResult);

        $flashMessenger->expects($this->exactly(2))
            ->method('addMessage')
            ->with(
                $this->callback(
                    fn ($arg) => is_array($arg) && (
                        ($arg['msg'] === 'renew_success_summary' && $arg['tokens']['count'] === 2) ||
                        ($arg['msg'] === 'renew_error_summary' && $arg['tokens']['count'] === 1)
                    )
                ),
                $this->callback(
                    fn ($arg) => in_array($arg, ['success', 'error'])
                )
            );

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertEquals($renewalResult['details'], $result);
    }

    /**
     * Test processing renewals with 'renewSelected' and specific IDs.
     *
     * @return void
     */
    public function testProcessRenewalsSelected(): void
    {
        $request = new Parameters([
            'renewSelected' => '1',
            'renewSelectedIDS' => ['id_a', 'id_b'],
        ]);
        $idsToRenew = ['id_a', 'id_b'];

        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);

        $renewalResult = [
            'details' => [
                'id_a' => ['success' => true],
                'id_b' => ['success' => true],
            ],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('renewMyItems'),
                $this->equalTo([['details' => $idsToRenew, 'patron' => $patron]])
            )
            ->willReturn($renewalResult);

        $flashMessenger->expects($this->once())
            ->method('addMessage')
            ->with(
                $this->callback(
                    fn ($arg) => is_array($arg)
                        && ($arg['msg'] === 'renew_success_summary' && $arg['tokens']['count'] === 2)
                ),
                'success'
            );

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertEquals($renewalResult['details'], $result);
    }

    /**
     * Test processing renewals with 'renewSelected' and 'selectAll'.
     *
     * @return void
     */
    public function testProcessRenewalsSelectAll(): void
    {
        $request = new Parameters([
            'renewSelected' => '1',
            'selectAll' => '1',
            'selectAllIDS' => ['id_x', 'id_y', 'id_z'],
        ]);
        $idsToRenew = ['id_x', 'id_y', 'id_z'];

        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);

        $renewalResult = [
            'details' => [
                'id_x' => ['success' => false],
                'id_y' => ['success' => false],
                'id_z' => ['success' => false],
            ],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('renewMyItems'),
                $this->equalTo([['details' => $idsToRenew, 'patron' => $patron]])
            )
            ->willReturn($renewalResult);

        $flashMessenger->expects($this->once())
            ->method('addMessage')
            ->with(
                $this->callback(
                    fn ($arg) => is_array($arg)
                        && ($arg['msg'] === 'renew_error_summary' && $arg['tokens']['count'] === 3)
                ),
                'error'
            );

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertEquals($renewalResult['details'], $result);
    }

    /**
     * Test processing renewals with no renewal buttons pressed.
     *
     * @return void
     */
    public function testProcessRenewalsNoAction(): void
    {
        $request = new Parameters([]);
        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);

        $catalog->expects($this->never())
            ->method('__call');
        $flashMessenger->expects($this->never())
            ->method('addMessage');

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test processing renewals when a button is pressed but no items are selected.
     *
     * @return void
     */
    public function testProcessRenewalsEmptySelection(): void
    {
        $request = new Parameters([
            'renewSelected' => '1',
            'renewSelectedIDS' => null,
        ]);
        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);

        $catalog->expects($this->never())
            ->method('__call');
        $flashMessenger->expects($this->once())
            ->method('addMessage')
            ->with('renew_empty_selection', 'error');

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test process renews with CSRF validation failure.
     *
     * @return void
     */
    public function testProcessRenewalsCsrfFailure(): void
    {
        $request = new Parameters([
            'renewAll' => '1',
            'renewAllIDS' => ['id1', 'id2', 'id3'],
            'csrf' => 'bad_token',
        ]);
        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);
        $csrfValidator = $this->createMock(CsrfInterface::class);

        $csrfValidator->expects($this->once())
            ->method('isValid')
            ->with('bad_token')
            ->willReturn(false);

        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with('csrf_validation_failed');

        $catalog->expects($this->never())
            ->method('__call');
        $csrfValidator->expects($this->never())
            ->method('trimTokenList');

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger, $csrfValidator);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test processing renewals when ILS returns false
     * 
     * @return void
     */
    public function testProcessRenewalsSystemFailure(): void
    {
        $request = new Parameters([
            'renewAll' => '1',
            'renewAllIDS' => ['id1', 'id2'],
        ]);
        $idsToRenew = ['id1', 'id2'];

        $catalog = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessenger::class);

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('renewMyItems'),
                $this->equalTo([['details' => $idsToRenew, 'patron' => $patron]])
            )
            ->willReturn(false);

        $flashMessenger->expects($this->once())
            ->method('addMessage')
            ->with('renew_error', 'error');

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
