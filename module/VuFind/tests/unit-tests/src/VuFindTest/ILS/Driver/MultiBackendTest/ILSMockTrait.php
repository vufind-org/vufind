<?php

/**
 * Support trait for MultiBackend ILS driver test
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014-2021.
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
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

 namespace VuFindTest\ILS\Driver;

/**
 * Support trait for MultiBackend ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ILSMockTrait
{
    public function cancelHolds($cancelDetails)
    {
        return [];
    }

    public function cancelILLRequests($cancelDetails)
    {
        return [];
    }

    public function cancelStorageRetrievalRequests($cancelDetails)
    {
        return [];
    }

    public function checkRequestIsValid($id, $data, $patron)
    {
    }

    public function checkILLRequestIsValid($id, $data, $patron)
    {
    }

    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
    }

    public function getCancelHoldDetails($holdDetails, $patron = [])
    {
        return '';
    }

    public function getCancelILLRequestDetails($holdDetails, $patron)
    {
        return '';
    }

    public function getCancelStorageRetrievalRequestDetails($holdDetails, $patron)
    {
        return '';
    }

    public function getConfig($function, $params = null)
    {
        return [];
    }

    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return '';
    }

    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        return false;
    }

    public function getMyILLRequests($patron)
    {
    }

    public function getILLPickUpLibraries($patron = false, $holdDetails = null)
    {
    }

    public function getILLPickUpLocations($id, $pickupLib, $patron)
    {
    }

    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        return [];
    }

    public function getRenewDetails($checkoutDetails)
    {
        return '';
    }

    public function getRequestGroups($bibId = null, $patron = null, $holdDetails = null)
    {
        return [];
    }

    public function loginIsHidden()
    {
        return false;
    }

    public function placeHold($holdDetails)
    {
    }

    public function placeILLRequest($holdDetails)
    {
    }

    public function placeStorageRetrievalRequest($details)
    {
    }

    public function renewMyItems($renewDetails)
    {
        return [];
    }

    public function getAccountBlocks($patron)
    {
        return false;
    }

    public function getRequestBlocks($patron)
    {
        return false;
    }
}
