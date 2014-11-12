<?php
/**
 * Driver for offline/missing ILS.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException,
    VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Driver for offline/missing ILS.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class NoILS extends AbstractBase implements TranslatorAwareInterface
{
    /**
     * Translator (or null if unavailable)
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = null;

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
     * @param string $func   The name of the feature to be checked
     * @param array  $params Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function, $params = null)
    {
        return isset($this->config[$function]) ? $this->config[$function] : false;
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
        return $this->recordLoader->load($id);
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
        $useStatus = isset($this->config['settings']['useStatus'])
            ? $this->config['settings']['useStatus'] : 'none';
        if ($useStatus == "custom") {
            $status = $this->translate($this->config['Status']['status']);
            return array(
                array(
                    'id' => $id,
                    'availability' => $this->config['Status']['availability'],
                    'status' => $status,
                    'use_unknown_message' =>
                        $this->config['Status']['use_unknown_message'],
                    'status_array' => array($status),
                    'location' => $this->translate(
                        $this->config['Status']['location']
                    ),
                    'reserve' => $this->config['Status']['reserve'],
                    'callnumber' => $this->translate(
                        $this->config['Status']['callnumber']
                    )
                )
            );
        } else if ($useStatus == "marc") {
            // Retrieve record from index:
            $recordDriver = $this->getSolrRecord($id);
            return $this->getFormattedMarcDetails($recordDriver, 'MarcStatus');
        }
        return array();
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
        $useStatus = isset($this->config['settings']['useStatus'])
            ? $this->config['settings']['useStatus'] : 'none';
        if ($useStatus == "custom" || $useStatus == "marc") {
            $status = array();
            foreach ($idList as $id) {
                $status[] = $this->getStatus($id);
            }
            return $status;
        }
        return array();
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $useHoldings = isset($this->config['settings']['useHoldings'])
            ? $this->config['settings']['useHoldings'] : 'none';

        if ($useHoldings == "custom") {
            return array(
                array(
                    'id' => $id,
                    'number' => $this->translate(
                        $this->config['Holdings']['number']
                    ),
                    'availability' => $this->config['Holdings']['availability'],
                    'status' => $this->translate(
                        $this->config['Holdings']['status']
                    ),
                    'use_unknown_message' =>
                        $this->config['Holdings']['use_unknown_message'],
                    'location' => $this->translate(
                        $this->config['Holdings']['location']
                    ),
                    'reserve' => $this->config['Holdings']['reserve'],
                    'callnumber' => $this->translate(
                        $this->config['Holdings']['callnumber']
                    ),
                    'barcode' => $this->config['Holdings']['barcode'],
                    'notes' => isset($this->config['Holdings']['notes'])
                        ? $this->config['Holdings']['notes'] : array(),
                    'summary' => isset($this->config['Holdings']['summary'])
                        ? $this->config['Holdings']['summary'] : array()
                )
            );
        } elseif ($useHoldings == "marc") {
            // Retrieve record from index:
            $recordDriver = $this->getSolrRecord($id);
            return $this->getFormattedMarcDetails($recordDriver, 'MarcHoldings');
        }

        return array();
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
        $marcStatus = isset($this->config[$configSection])
            ? $this->config[$configSection] : false;
        if ($marcStatus) {
            $field = $marcStatus['marcField'];
            unset($marcStatus['marcField']);
            $result = $recordDriver->tryMethod(
                'getFormattedMarcDetails', array($field, $marcStatus)
            );
            return empty($result) ? array() : $result;
        }
        return array();
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasHoldings($id)
    {
        $useHoldings = isset($this->config['settings']['useHoldings'])
            ? $this->config['settings']['useHoldings'] : '';

        // "none" will be processed differently in the config depending
        // on whether it's in or out of quotes; handle both cases.
        return $useHoldings != 'none' && !empty($useHoldings);
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
        return array();
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        return array();
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
        return isset($this->config['settings']['mode'])
            ? $this->config['settings']['mode'] : "ils-offline";
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
        return isset($this->config['settings']['hideLogin'])
            ? $this->config['settings']['hideLogin'] : false;
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        // Block authentication:
        return null;
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return NoILS
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Translate a string if a translator is available.
     *
     * @param string $msg Message to translate
     *
     * @return string
     */
    protected function translate($msg)
    {
        return null !== $this->translator
            ? $this->translator->translate($msg) : $msg;
    }
}
