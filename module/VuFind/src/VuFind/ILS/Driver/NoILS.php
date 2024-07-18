<?php

/**
 * Driver for offline/missing ILS.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function strlen;

/**
 * Driver for offline/missing ILS.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class NoILS extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Loader $loader Record loader
     */
    public function __construct(\VuFind\Record\Loader $loader)
    {
        $this->recordLoader = $loader;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        // No special processing needed here.
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = [])
    {
        return $this->config[$function] ?? false;
    }

    /**
     * Get the ID prefix from the configuration, if set.
     *
     * @return string
     */
    protected function getIdPrefix()
    {
        return $this->config['settings']['idPrefix'] ?? null;
    }

    /**
     * Get a Solr record.
     *
     * @param string $id ID of record to retrieve
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getSolrRecord($id)
    {
        // Add idPrefix condition
        $idPrefix = $this->getIdPrefix();
        return $this->recordLoader->load(
            strlen($idPrefix) ? $idPrefix . $id : $id,
            DEFAULT_SEARCH_BACKEND,
            true    // tolerate missing records
        );
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $useStatus = $this->config['settings']['useStatus'] ?? 'none';
        if ($useStatus == 'custom') {
            $status = $this->translate($this->config['Status']['status'] ?? '');
            return [
                [
                    'id' => $id,
                    'availability' => $this->config['Status']['availability'] ?? false,
                    'status' => $status,
                    'use_unknown_message' => (bool)($this->config['Status']['use_unknown_message'] ?? false),
                    'status_array' => [$status],
                    'location' => $this->translate($this->config['Status']['location'] ?? ''),
                    'reserve' => $this->config['Status']['reserve'] ?? 'N',
                    'callnumber' => $this->translate($this->config['Status']['callnumber'] ?? ''),
                ],
            ];
        } elseif ($useStatus == 'marc') {
            // Retrieve record from index:
            $recordDriver = $this->getSolrRecord($id);
            return $this->getFormattedMarcDetails($recordDriver, 'MarcStatus');
        }
        return [];
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        $useStatus = $this->config['settings']['useStatus'] ?? 'none';
        if ($useStatus == 'custom' || $useStatus == 'marc') {
            $status = [];
            foreach ($idList as $id) {
                $status[] = $this->getStatus($id);
            }
            return $status;
        }
        return [];
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options (not currently used)
     *
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        $useHoldings = $this->config['settings']['useHoldings'] ?? 'none';
        if ($useHoldings == 'custom') {
            return [
                [
                    'id' => $id,
                    'number' => $this->translate($this->config['Holdings']['number'] ?? ''),
                    'availability' => $this->config['Holdings']['availability'] ?? false,
                    'status' => $this->translate($this->config['Holdings']['status'] ?? ''),
                    'use_unknown_message' => (bool)($this->config['Holdings']['use_unknown_message'] ?? false),
                    'location' => $this->translate($this->config['Holdings']['location'] ?? ''),
                    'reserve' => $this->config['Holdings']['reserve'] ?? 'N',
                    'callnumber' => $this->translate($this->config['Holdings']['callnumber'] ?? ''),
                    'barcode' => $this->config['Holdings']['barcode'] ?? '',
                    'notes' => $this->config['Holdings']['notes'] ?? [],
                    'summary' => $this->config['Holdings']['summary'] ?? [],
                ],
            ];
        } elseif ($useHoldings == 'marc') {
            // Retrieve record from index:
            $recordDriver = $this->getSolrRecord($id);
            return $this->getFormattedMarcDetails($recordDriver, 'MarcHoldings');
        }

        return [];
    }

    /**
     * This is responsible for retrieving the status or holdings information of a
     * certain record from a Marc Record.
     *
     * @param object $recordDriver  A RecordDriver Object
     * @param string $configSection Section of driver config containing data
     * on how to extract details from MARC.
     *
     * @return array An Array of Holdings Information
     */
    protected function getFormattedMarcDetails($recordDriver, $configSection)
    {
        $marcStatus = $this->config[$configSection] ?? false;
        if ($marcStatus) {
            $field = $marcStatus['marcField'];
            unset($marcStatus['marcField']);
            $result = $recordDriver->tryMethod(
                'getFormattedMarcDetails',
                [$field, $marcStatus]
            );
            // If the details coming back from the record driver include the
            // ID prefix, strip it off!
            $idPrefix = $this->getIdPrefix();
            if (
                isset($result[0]['id'])
                && '' !== $idPrefix
                && str_starts_with($result[0]['id'], $idPrefix)
            ) {
                $result[0]['id'] = substr($result[0]['id'], strlen($idPrefix));
            }
            return empty($result) ? [] : $result;
        }
        return [];
    }

    /**
     * Has Holdings
     *
     * This is responsible for determining if holdings exist for a particular
     * bibliographic id
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return bool True if holdings exist, False if they do not
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasHoldings($id)
    {
        // If the ILS is offline, we should if we can look up details:
        $useHoldings = $this->config['settings']['useHoldings'] ?? '';

        // "none" will be processed differently in the config depending
        // on whether it's in or out of quotes; handle both cases.
        return $useHoldings != 'none' && !empty($useHoldings)
            && !empty($this->getHolding($id));
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return array
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        return [];
    }

    /**
     * Get Offline Mode
     *
     * This is responsible for returning the offline mode
     *
     * @return string "ils-offline" for systems where the main ILS is offline,
     * "ils-none" for systems which do not use an ILS
     */
    public function getOfflineMode()
    {
        return $this->config['settings']['mode'] ?? 'ils-offline';
    }

    /**
     * Get Hidden Login Mode
     *
     * This is responsible for indicating whether login should be hidden.
     *
     * @return bool true if the login should be hidden, false if not
     */
    public function loginIsHidden()
    {
        return $this->config['settings']['hideLogin'] ?? false;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username Patron username
     * @param string $password Patron password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        // Block authentication:
        return null;
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @throws ILSException
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        // Does not work while ILS offline:
        return [];
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        // Does not work while ILS offline:
        return [];
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        // Does not work while ILS offline:
        return [];
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        // Does not work while ILS offline:
        return [];
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * This version of findReserves was contributed by Matthew Hooper and includes
     * support for electronic reserves (though eReserve support is still a work in
     * progress).
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        // Does not work while ILS offline:
        return [];
    }
}
