<?php
/**
 * VuFind Record Driver for SolrOverdrive Records
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use Zend\Log\LoggerAwareInterface;


/** TODO:==
 *
 * - method to determine whether logged in patron can access OD
 * -
 */

/**
 * VuFind Record Driver for SolrOverdrive Records
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrOverdrive extends SolrMarc implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }


    /**
     * @var \VuFind\DigitalContent\OverdriveConnector
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $recordConfig VuFind main configuration (omit for
     *                                            built-in defaults)
     * @param \Zend\Config\Config $recordConfig Record-specific configuration file
     *                                            (omit to use $recordConfig as $recordConfig)
     * @param \VuFind\DigitalContent\OverdriveConnector Overdrive Connector
     */
    public function __construct(
        $mainConfig = null, $recordConfig = null,
        $connector = null
    )
    {
        $this->debug("SolrOverdrive Rec Driver constructed");
        $this->connector = $connector;

        parent::__construct($mainConfig, $recordConfig, null);

        // Init session cache for session-specific data
        if ($conf = $this->_getConfig()) {
            $namespace = md5($conf->clientKey);
        }
        $this->debug("SolrOverdrive Rec Driver constructed");
    }


    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getDigitalFormats()
    {
        $results = array();
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, false);
        //$this->debug("formats: ".print_r($data->formats,true));
        if (isset($data->formats)) {
            foreach ($data->formats as $format) {
                $results[$format->name] = array(
                    "File Size" => $format->fileSize,
                    "Parts" => $format->partCount
                );
            }
        }
        $this->debug("returning formats array:" . print_r($results, true));
        return $results;
    }


    /**
     * summary
     *
     * Description.
     *
     * @since x.x.x
     *
     * @see   Function/method/class relied on
     * @link  URL
     * @global type $varname Description.
     * @global type $varname Description.
     *
     * @param type  $var     Description.
     * @param type  $var     Optional. Description. Default.
     *
     * @return type Description.
     */

    public function supportsAjaxStatus()
    {
        return true;
    }

    public function testDriver()
    {
        if ($this->hasILS()) {
            $rid = $this->fields['odrid_str'];
            return $rid;
        } else {
            return "NOILS";
        }
    }


    public function getOverdriveID()
    {
        $result = 0;
        if ($conf = $this->_getConfig()) {
            if ($conf->isMarc) {
                //TODO GET CONFIG
                $result = $this->getFieldArray('037', 'a')[0];
            } else {
                //TODO SET prefix in CONFIG
                //$prefix = 'overdrive.';
                //$result = substr($this->getUniqueID(), strlen($prefix));
                //$result = $this->getUniqueID();
                $result = $this->getUniqueID();
            }
        }
        return $result;
    }

    public function getUniqueID()
    {
        if (!isset($this->fields['id'])) {
            throw new \Exception('ID not set!');
        }
        //$this->debug("id: " . $this->fields['id']);

        return $this->fields['id'];
    }

    /**
     * Returns the availability of an overdrive product (digital resource)
     *
     * @link https://developer.overdrive.com/apis/library-availability-new
     *
     * @param type $overDriveId The product ID for the resource Ex: 622708F6-78D7-453A-A7C5-3FE6853F3167
     * @param type $productsKey Optional. If not passed it, it used configured product key
     *
     * @return type returns an object with the info in it (see URL above) or false if there was a problem.
     */

    public function getOverdriveAvailability()
    {
        $overDriveId = $this->getOverdriveID();
        $this->debug("idb4: $overDriveId");
        return $this->connector->getAvailability($overDriveId);

        //$this->debug("idb4: $overDriveId");
        //$overDriveId = $this->_translateODID($overDriveId);
        //$this->debug("idafter: $overDriveId");
        $res = false;
        if (!$overDriveId) {
            $this->logWarning(
                "no overdrive content ID was passed in.",
                ["getOverdriveAvailability"]
            );
            return false;
        }
        if ($conf = $this->_getConfig()) {

            //if ($productsKey == null){
            $productsKey = $conf->prodKey;
            //}
            $baseUrl = $conf->discURL;
            $availabilityUrl
                = "$baseUrl/v2/collections/$productsKey/products/$overDriveId/availability";
            $res = $this->_callUrl($availabilityUrl);
        }
        return $res;
    }


    /**
     * isCheckedOut   Is this resource already checked out to the user?
     *
     * @param $user
     */
    public function isCheckedOut($user)
    {
        $this->debug(" ischeckout", array(), true);
        $overDriveId = $this->getOverdriveID();
        $result = $this->connector->getCheckouts($user, false);
        if ($result->status) {
            $checkouts = $result->data;
            foreach ($checkouts as $checkout) {
                $this->debug(
                    strtolower($checkout->reserveId) . " == " . $overDriveId
                );
                if (strtolower($checkout->reserveId) == $overDriveId) {
                    $this->debug("overdrive checkout found");
                    return true;
                }
            }

        }
        //if it didn't work, an error should be logged from the connector
        return false;
    }

    /**
     * @param $user
     *
     * @return bool
     */
    public function isHeld($user)
    {
        $overDriveId = $this->getOverdriveID();
        $result = $this->connector->getHolds($user, true);
        if ($result->status) {
            $holds = $result->data;
            foreach ($holds as $hold) {
                $this->debug(
                    strtolower($hold->reserveId) . " == " . $overDriveId
                );
                if (strtolower($hold->reserveId) == $overDriveId) {
                    $this->debug("overdrive hold found");
                    return $hold;
                }
            }

        }
        //if it didn't work, an error should be logged from the connector
        return false;
    }


    public function doOverdriveCheckout($overDriveId, $user = false)
    {
        return $this->connector->doOverdriveCheckout(
            $overDriveId, $user = false
        );
    }


    //TODO implement phys desc
    public
    function getGeneralNotes()
    {

        $results = array();
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, false);
        if (isset($data->formats)) {
            foreach ($data->formats as $format) {
                $results[] = $format->name;
            }
        }
        return array("Formats" => $results);
    }

    public
    function getThumbnail(
        $size = 'small'
    )
    {
        if ($size == 'large') {
            $cover = "cover300Wide";
        } elseif ($size == 'medium') {
            $cover = "thumbnail";
        } elseif ($size == 'small') {
            $cover = 'cover150Wide';
        } else {
            $cover = "thumbnail";
        }
        $result = false;
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, false);
        if (isset($data->images)) {
            if (isset($data->images->{$cover})) {
                $result = $data->images->{$cover}->href;
            }
        }
        return $result;
    }

    public
    function getSummary()
    {
        //$this->debug("fields:".print_r($this->fields,true));
        return array("Summary" => $this->fields["description"]);
    }

    public
    function getRawData()
    {
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, true);
        $this->debug("raw data:" . print_r($data, true));
        return $data;
    }

    /**
     * @return mixed
     */
    public
    function getFormattedRawData()
    {
        $result = array();
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, true);
        $c_arr = array();
        foreach ($data['creators'] as $creator) {
            $c_arr[] = "<strong>{$creator["role"]}<strong>: "
                . $creator["name"];
        }
        $data['creators'] = implode("<br/>", $c_arr);

        $this->debug("raw data:" . print_r($data, true));
        return $data;
    }

    /**
     * @return bool|\stdClass
     */
    private
    function _getConfig()
    {
        $conf = new \stdClass();
        if (!$this->recordConfig) {
            $this->error(
                "Could not locate the Overdrive Record Driver configuration."
            );
            return false;
        }
        if ($this->recordConfig->API->productionMode == false) {
            $conf->discURL
                = $this->recordConfig->API->integrationDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->integrationCircURL;
            $conf->webID = $this->recordConfig->API->integrationWebsiteID;
            $conf->prodKey
                = $this->recordConfig->API->integrationProductsKey;
        } else {
            $conf->discURL
                = $this->recordConfig->API->productionDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->productionCircURL;
            $conf->webID = $this->recordConfig->API->productionWebsiteID;
            $conf->prodKey
                = $this->recordConfig->API->productionProductsKey;
        }

        $conf->clientKey = $this->recordConfig->API->clientKey;
        $conf->clientSecret = $this->recordConfig->API->clientSecret;
        $conf->tokenURL = $this->recordConfig->API->tokenURL;
        $conf->idField
            = $this->recordConfig->Overdrive->overdriveIdMarcField;
        $conf->idSubfield
            = $this->recordConfig->Overdrive->overdriveIdMarcSubfield;
        $conf->ILSname = $this->recordConfig->API->ILSname;
        //TODO
        $conf->isMarc = false;
        //$this->debug("OD Record driver config: ".print_r($this->recordConfig,true));
        return $conf;
    }


    public
    function getRealTimeTitleHold()
    {
        $od_id = $this->getOverdriveID();
        $rec_id = $this->getUniqueID();
        $this->debug("SOLR OD: GRTTH  $od_id");
        //TODO Fix this!
        return "/odtest/overdrive/Hold?od_id=$od_id&rec_id=$rec_id";
    }
}