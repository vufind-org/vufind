<?php

/**
 * FakeOverdriveConnector
 *
 * Class responsible for simulating the Overdrive API for test purposes.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2023.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301
 * USA
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\DigitalContent;

use Laminas\Config\Config;

/**
 * FakeOverdriveConnector
 *
 * Class responsible for simulating the Overdrive API for test purposes.
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FakeOverdriveConnector extends OverdriveConnector
{
    /**
     * Constructor
     *
     * @param Config $mainConfig   VuFind main conf
     * @param Config $recordConfig Record-specific conf file
     */
    public function __construct(
        Config $mainConfig,
        Config $recordConfig
    ) {
        $this->mainConfig = $mainConfig;
        $this->recordConfig = $recordConfig;
    }

    /**
     * Get (Logged-in) User
     *
     * Returns the currently logged in user or false if the user is not
     *
     * @return array|boolean  an array of user info from the ILSAuthenticator
     *                        or false if user is not logged in.
     */
    public function getUser()
    {
        return [];
    }

    /**
     * Get Overdrive Access
     *
     * Whether the patron has access to overdrive actions (hold,
     * checkout etc.).
     * This is stored and retrieved from the session.
     *
     * @param bool $refresh Force a check instead of checking cache
     *
     * @return object
     */
    public function getAccess($refresh = false)
    {
        return (object)[
            'status' => true,
        ];
    }

    /**
     * Get Availability
     *
     * Retrieves the availability for a single resource from Overdrive API
     * with information like copiesOwned, copiesAvailable, numberOfHolds et.
     *
     * @param string $overDriveId The Overdrive ID (reserve ID) of the eResource
     *
     * @return object  Standard object with availability info
     *
     * @link https://developer.overdrive.com/apis/library-availability-new
     */
    public function getAvailability($overDriveId)
    {
        return new \stdClass();
    }

    /**
     * Get Availability (in) Bulk
     *
     * Gets availability for up to 25 titles at once.  This is used by the
     * the ajax availability system
     *
     * @param array $overDriveIds The Overdrive ID (reserve IDs) of the
     *                            eResources
     *
     * @return object|bool see getAvailability
     */
    public function getAvailabilityBulk($overDriveIds = [])
    {
        $data = [];
        $statuses = ['od_code_resource_not_found', 'od_code_login_for_avail'];
        foreach ($overDriveIds as $i => $id) {
            $code = $statuses[$i] ?? '';
            if (empty($code)) {
                $total = rand(1, 3);
                $avail = rand(0, $total);
                $holds = $avail === 0 ? rand(1, 100) : 0;
                $data[$id] = (object)[
                    'code' => '',
                    'copiesOwned' => $total,
                    'copiesAvailable' => $avail,
                    'numberOfHolds' => $holds,
                ];
            } else {
                $data[$id] = (object)compact('code');
            }
        }
        return (object)[
            'status' => true,
            'data' => $data,
        ];
    }

    /**
     * Get Collection Token
     *
     * Gets the collection token for the Overdrive collection. The collection
     * token doesn't change much but according to
     * the OD API docs it could change and should be retrieved each session.
     * Also, the collection token depends on the user if the user is in a
     * consortium.  If consortium support is turned on then the user collection
     * token will override the library collection token.
     * The token itself is returned but it's also saved in the session and
     * automatically returned.
     *
     * @return object|bool A collection token for the library's collection.
     */
    public function getCollectionToken()
    {
        return false;
    }

    /**
     * Overdrive Checkout
     * Processes a request to checkout a title from Overdrive
     *
     * @param string $overDriveId The overdrive id for the title
     *
     * @return object $result Results of the call.
     */
    public function doOverdriveCheckout($overDriveId)
    {
        return null;
    }

    /**
     * Places a hold on an item within OverDrive
     *
     * @param string $overDriveId The overdrive id for the title
     * @param string $email       The email overdrive should use for notif
     *
     * @return \stdClass Object with result
     */
    public function placeOverDriveHold($overDriveId, $email)
    {
        return new \stdClass();
    }

    /**
     * Cancel Hold
     * Cancel and existing Overdrive Hold
     *
     * @param string $overDriveId The overdrive id for the title
     *
     * @return \stdClass Object with result
     */
    public function cancelHold($overDriveId)
    {
        return new \stdClass();
    }

    /**
     * Return Resource
     * Return a title early.
     *
     * @param string $resourceID Overdrive ID of the resource
     *
     * @return object|bool Object with result
     */
    public function returnResource($resourceID)
    {
        return new \stdClass();
    }

    /**
     * Get Download Link for an Overdrive Resource
     *
     * @param string $overDriveId Overdrive ID
     * @param string $format      Overdrive string for this format
     * @param string $errorURL    A URL to show err if the download doesn't wk
     *
     * @return object Object with result. If successful, then data will
     * have the download URI ($result->downloadLink)
     */
    public function getDownloadLink($overDriveId, $format, $errorURL)
    {
        return new \stdClass();
    }

    /**
     * Lock In Overdrive Resource for a particular format
     *
     * @param string $overDriveId Overdrive Resource ID
     * @param string $format      Overdrive string for the format
     *
     * @return object|bool Result of the call.
     */
    public function lockinResource($overDriveId, $format)
    {
        return new \stdClass();
    }

    /**
     * Returns a hash of metadata keyed on overdrive reserveID
     *
     * @param array $overDriveIds Set of Overdrive IDs
     *
     * @return array results of metadata fetch
     */
    public function getMetadata($overDriveIds = [])
    {
        return [];
    }

    /**
     * Get Overdrive Checkout
     *
     * Get the overdrive checkout object for an overdrive title
     * for the current user
     *
     * @param string $overDriveId Overdrive resource id
     * @param bool   $refresh     Whether or not to ignore cache and get latest
     *
     * @return object|false PHP object that represents the checkout or false
     * the checkout is not in the current list of checkouts for the current
     * user.
     */
    public function getCheckout($overDriveId, $refresh = true)
    {
        return false;
    }

    /**
     * Get Overdrive Hold
     *
     * Get the overdrive hold object for an overdrive title
     * for the current user
     *
     * @param string $overDriveId Overdrive resource id
     * @param bool   $refresh     Whether or not to ignore cache and get latest
     *
     * @return object|false PHP object that represents the checkout or false
     * the checkout is not in the current list of checkouts for the current
     * user.
     */
    public function getHold($overDriveId, $refresh = true)
    {
        return false;
    }

    /**
     * Get Overdrive Checkouts (or a user)
     *
     * @param bool $refresh Whether or not to ignore cache and get latest
     *
     * @return object Results of the call
     */
    public function getCheckouts($refresh = true)
    {
        return (object)[
            'status' => true,
            'data' => [
                (object)[
                    'reserveId' => 'overdrive1',
                    'expires' => date('Y-m-d'),
                    'isReturnable' => true,
                ],
            ],
        ];
    }

    /**
     * Get Overdrive Holds (or a user)
     *
     * @param bool $refresh Whether or not to ignore cache and get latest
     *
     * @return \stdClass Results of the call
     */
    public function getHolds($refresh = true)
    {
        return (object)[
            'status' => true,
            'data' => [
                (object)[
                    'reserveId' => 'overdrive2',
                    'holdPlacedDate' => date('Y-m-d'),
                    'holdListPosition' => 6,
                    'numberOfHolds' => 23,
                    'emailAddress' => 'foo@example.com',
                ],
                (object)[
                    'reserveId' => 'overdrive3',
                    'holdPlacedDate' => date('Y-m-d'),
                    'holdListPosition' => 1,
                    'numberOfHolds' => 1,
                    'emailAddress' => 'foo@example.com',
                ],
            ],
        ];
    }
}
