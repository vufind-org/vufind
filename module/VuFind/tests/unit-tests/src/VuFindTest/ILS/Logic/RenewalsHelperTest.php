<?php

/**
 * Unit tests for the RenewalsHelper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\TestCase;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RenewalsHelper;
use VuFind\Validator\CsrfInterface;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

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
        $catalog = $this->createMock(Connection::class);

        $ilsDetails = ['id' => 'item123', 'title' => 'Test Item'];
        $renewStatus = ['function' => 'renewMyItemsLink'];
        $expectedLink = '/MyResearch/renew/item123';

        $catalog->expects($this->once())
            ->method('__call')
            ->with('renewMyItemsLink', [$ilsDetails])
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
        $catalog = $this->createMock(Connection::class);

        $ilsDetails = ['id' => 'item456', 'title' => 'Another Item'];
        $renewStatus = ['function' => 'renewMyItems'];
        $expectedDetails = ['form_data' => 'details_here'];

        $catalog->expects($this->once())
            ->method('__call')
            ->with('getRenewDetails', [$ilsDetails])
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

        $this->assertSame($ilsDetails, $result);
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

        $catalog = $this->createMock(Connection::class);

        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);

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
                'renewMyItems',
                [['details' => $idsToRenew, 'patron' => $patron]]
            )
            ->willReturn($renewalResult);

        $flashMessenger->expects($this->once())
            ->method('addSuccessMessage')
            ->with(['msg' => 'renew_success_summary', 'tokens' => ['count' => 2], 'icu' => true]);
        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with(['msg' => 'renew_error_summary', 'tokens' => ['count' => 1], 'icu' => true]);

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

        $catalog = $this->createMock(Connection::class);
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);

        $renewalResult = [
            'details' => [
                'id_a' => ['success' => true],
                'id_b' => ['success' => true],
            ],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with(
                'renewMyItems',
                [['details' => $idsToRenew, 'patron' => $patron]]
            )
            ->willReturn($renewalResult);

        $flashMessenger->expects($this->once())
            ->method('addSuccessMessage')
            ->with(
                $this->callback(
                    fn ($arg) => is_array($arg)
                        && ($arg['msg'] === 'renew_success_summary' && $arg['tokens']['count'] === 2)
                )
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

        $catalog = $this->createMock(Connection::class);
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);

        $renewalResult = [
            'details' => [
                'id_x' => ['success' => false],
                'id_y' => ['success' => false],
                'id_z' => ['success' => false],
            ],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with('renewMyItems', [['details' => $idsToRenew, 'patron' => $patron]])
            ->willReturn($renewalResult);

        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with(
                $this->callback(
                    fn ($arg) => is_array($arg)
                        && ($arg['msg'] === 'renew_error_summary' && $arg['tokens']['count'] === 3)
                )
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
        $catalog = $this->createMock(Connection::class);
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);

        $catalog->expects($this->never())
            ->method('__call');
        $flashMessenger->expects($this->never())
            ->method('addSuccessMessage');
        $flashMessenger->expects($this->never())
            ->method('addErrorMessage');

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
        $catalog = $this->createMock(Connection::class);
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);

        $catalog->expects($this->never())
            ->method('__call');
        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with('renew_empty_selection');

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
        $catalog = $this->createMock(Connection::class);
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);
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

        $catalog = $this->createMock(Connection::class);
        $patron = ['id' => 'patron1'];
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);

        $catalog->expects($this->once())
            ->method('__call')
            ->with('renewMyItems', [['details' => $idsToRenew, 'patron' => $patron]])
            ->willReturn(false);

        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with('renew_error');

        $helper = new RenewalsHelper();
        $result = $helper->processRenewals($request, $catalog, $patron, $flashMessenger);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
