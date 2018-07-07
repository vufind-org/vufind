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
     * @var
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config mainConfig VuFind main configuration
     * @param \Zend\Config\Config $recordConfig Record-specific configuration
     * @param \VuFind\DigitalContent\OverdriveConnector Overdrive Connector
     */
    public function __construct(
        $mainConfig = null, $recordConfig = null,
        $connector = null
    ) {
        $this->connector = $connector;
        $this->config = $connector->getConfig();
        parent::__construct($mainConfig, $recordConfig, null);

        $this->debug("SolrOverdrive Rec Driver constructed");
    }

    /**
     * @param string $format
     * @param null $baseUrl
     * @param null $recordLink
     *
     * @return mixed|void
     *
     * public function getXML($format, $baseUrl = null, $recordLink = null)
     * {
     * $xml = parent::getXML('marc21');
     *
     * //return \VuFind\XSLT\Processor::process('record-marc.xsl', $this->driver->getXML('marc21');
     * }
     */
    /**
     * @return bool
     */
    public function supportsOpenUrl()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsCoinsOpenUrl()
    {
        return false;
    }


    public function getAvailableDigitalFormats()
    {
        $formats = array();
        //$allFormats = $this->getDigitalFormats();
        $formatNames = $this->connector->getFormatNames();
        $od_id = $this->getOverdriveID();

        if ($checkout = $this->connector->getCheckout($od_id, false)) {
            //$this->debug("hereiam" . print_r($checkout, true));
            //if we are already locked in, then we need free ones and locked in ones.
            if ($checkout->isFormatLockedIn) {
                foreach ($checkout->formats as $format) {
                    $formatType = $format->formatType;
                    $formats[$formatType] = $formatNames[$formatType];
                }
                //if we aren't locked in, we can show all formats
            } else {

                foreach ($this->getDigitalFormats() as $format) {
                    $formats[$format->id] = $format->name;
                }
            }
        }
        return $formats;
    }

    /**
     * Get Formats
     *
     * @param string $overDriveId Overdrive ReserveID
     *
     * @return array Array of formats.
     */
    public function getDigitalFormats()
    {
        $formats = array();
        $od_id = $this->getOverdriveID();

        ///if ($availableOnly) {

        //} else {
        if ($this->config->isMarc) {
            $od_id = $this->getOverdriveID();
            $fulldata = $this->connector->getMetadata(array($od_id));
            $data = $fulldata[strtolower($od_id)];
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }

        foreach ($data->formats as $format) {
            $formats[$format->id] = $format;
        }
        //}
        //$this->debug("Formats: " . print_r($formats, true));
        return $formats;
    }


    /**
     * Get an array of all the formats associated with the record. This array
     * is designed to be used in a template. For lower level
     *
     * @return array
     */
    public function getFormattedDigitalFormats()
    {

        $results = array();
        foreach ($this->getDigitalFormats() as $format) {
            $tmpresults = array();
            if ($format->fileSize > 0) {
                if ($format->fileSize > 1000000) {
                    $size = round($format->fileSize / 1000000);
                    $size .= " GB";
                } elseif ($format->fileSize > 1000) {
                    $size = round($format->fileSize / 1000);
                    $size .= " MB";
                } else {
                    $size = $format->fileSize;
                    $size .= " KB";
                }
                $tmpresults["File Size"] = $size;


            }
            if ($format->partCount) {
                $tmpresults["Parts"] = $format->partCount;
            }
            if ($format->identifiers) {
                foreach ($format->identifiers as $id) {
                    if (in_array($id->type, ["ISBN", "ASIN"])) {
                        $tmpresults[$id->type] = $id->value;
                    }
                }
            }
            if ($format->onSaleDate) {
                $tmpresults["Release Date"] = $format->onSaleDate;
            }
            $results[$format->name] = $tmpresults;
        }
        //}

        //$this->debug("returning formats array:" . print_r($results, true));
        return $results;
    }

    public function getPreviewLinks()
    {
        $results = array();
        if ($this->getIsMarc()) {
            $od_id = $this->getOverdriveID();
            $fulldata = $this->connector->getMetadata(array($od_id));
            $data = $fulldata[strtolower($od_id)];
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }

        if (isset($data->formats[0]->samples[0])) {
            //$format = $data->formats[0]->samples[0];
            foreach ($data->formats[0]->samples as $format) {
              if($format->formatType=='audiobook-overdrive' ||
                  $format->formatType=='ebook-overdrive'){
                  $results = $format;
              }
            }
        }
        $this->debug("previewlinks:" . print_r($results, true));
        return $results;
    }


    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return true;
    }


    /**
     * Get Overdrive Access
     *
     * Pass-through to the connector to determine whether logged-in user
     * has access to Overdrive actions
     *
     * @return boolean Whether the logged-in user has access to Overdrive.
     */
    public function getOverdriveAccess()
    {
        return $this->connector->getAccess();
    }

    /**
     * Is Logged in
     *
     * Returns whether the current user is logged in
     *
     * @return object|boolean User if logged in, false if not.
     */
    public function isLoggedIn()
    {
        return $this->connector->getUser();
    }

    /**
     * Get Overdrive ID
     *
     * Returns the Overdrive ID (or resource ID) for the current item. Note: for
     * records in marc format, this may be different than the Solr Record ID
     *
     *
     * @return string OverdriveID
     * @throws \Exception
     */
    public function getOverdriveID()
    {
        $result = 0;
        //$marc = $this->getMarcRecord();
        //$this->debug("marc: ".print_r($marc,true));
        //$this->config->isMarc = true;

        if ($this->config) {
            if ($this->config->isMarc) {
                $field = $this->config->idField;
                $subfield = $this->confif->idSubfield;
                $result = $this->getFieldArray($field, $subfield)[0];
                $this->debug("odid from marc: $result");
            } else {
                $result = $this->getUniqueID();
            }
        }
        $this->debug("odid: $result");
        return $result;
    }


    /*    public function getUniqueID()
        {
            if (!isset($this->fields['id'])) {
                throw new \Exception('ID not set!');
            }
            //$this->debug("id: " . $this->fields['id']);

            return $this->fields['id'];
        }*/

    /**
     * Returns the availability for the current record
     *
     * @return object|bool returns an object with the info in it (see URL above)
     * or false if there was a problem.
     */
    public function getOverdriveAvailability()
    {
        $overDriveId = $this->getOverdriveID();
        //$this->debug("idb4: $overDriveId");
        return $this->connector->getAvailability($overDriveId);
    }


    /**
     * isCheckedOut   Is this resource already checked out to the user?
     *
     *
     * @return object|bool Returns the checkout information if currently checked out
     *    by this user or false if not.
     */
    public function isCheckedOut()
    {
        $this->debug(" ischeckout", array(), true);
        $overdriveID = $this->getOverdriveID();
        $result = $this->connector->getCheckouts(true);
        // $this->debug("res: " . print_r($result, true));
        if ($result->status) {
            $checkouts = $result->data;
            foreach ($checkouts as $checkout) {
                $this->debug(
                    strtolower($checkout->reserveId) . " == " . $overdriveID
                );
                if (strtolower($checkout->reserveId) == $overdriveID) {
                    $this->debug("overdrive checkout found");
                    return $checkout;
                }
            }

        }
        //if it didn't work, an error should be logged from the connector
        return false;
    }


    /**
     * Is Held
     * Checks to see if the current record is on hold through Overcdrive.
     *
     * @param $user
     *
     * @return object|bool Returns the hold info if on hold or false if not.
     * @throws \Exception
     */
    public function isHeld($user)
    {
        $overDriveId = $this->getOverdriveID();
        $result = $this->connector->getHolds(true);
        if ($result->status) {
            $holds = $result->data;
            foreach ($holds as $hold) {
                if (strtolower($hold->reserveId) == $overDriveId) {
                    return $hold;
                }
            }
        }
        //if it didn't work, an error should be logged from the connector
        return false;
    }

    /**
     * Overdrvie Checkout.
     *
     * Passthru to the connector for checking out the current record.
     * NoteToSelf: Do we need ID? Don't we know the id?
     *
     * @since 5.0
     *
     * @see   Function/method/class relied on
     * @link  URL
     * @global type $varname Description.
     * @global type $varname Description.
     *
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * @return type Description.
     */
    public function doOverdriveCheckout($overDriveId, $user = false)
    {
        return $this->connector->doOverdriveCheckout(
            $overDriveId, $user = false
        );
    }

    /**
     *  this will be reomved after I fix my data to have short titles
     */
    public function getBreadcrumb()
    {
        if (!$this->getShortTitle()) {
            return $this->getTitle();
        } else {
            return $this->getShortTitle();
        }
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
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * @return type Description.
     */
    public
    function getGeneralNotes()
    {
        //return $this->getDigitalFormats();
        if ($this->config->isMarc) {
            return parent::getGeneralNotes();
        } else {

            return false;
        }
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
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * thumbnail:200
     * cover150Wide:150
     * cover:100
     * cover300Wide:300
     *
     * @return type Description.
     */
    public function getThumbnail(
        $size = 'small'
    ) {
        if ($size == 'large') {
            $cover = "cover300Wide";
        } elseif ($size == 'medium') {
            $cover = "cover150Wide";
        } elseif ($size == 'small') {
            $cover = 'thumbnail';
        } else {
            $cover = "cover";
        }

        //if the record is marc then the cover links probably aren't there.
        if ($this->config->isMarc) {
            $od_id = $this->getOverdriveID();
            $fulldata = $this->connector->getMetadata(array($od_id));
            $data = $fulldata[strtolower($od_id)];
        } else {
            $result = false;
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }

        if (isset($data->images)) {
            if (isset($data->images->{$cover})) {
                $result = $data->images->{$cover}->href;
            }
        }
        //$this->debug("thumbnaildata: ".print_r($fulldata,true));
        //$this->debug("thumbnail: $result");
        return $result;

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
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * @return type Description.
     */
    public
    function getSummary()
    {
        if ($this->config->isMarc) {
            return parent::getSummary();
        } else {
            $desc = $this->fields["description"];
            $newDesc = preg_replace('/<br(\s+)?\/?>/i', "\n", $desc);
            return array("Summary" => $newDesc);
        }
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
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * @return type Description.
     */
    public
    function getRawData()
    {

        if ($this->config->isMarc) {
            return parent::getRawData();
            /*            $xml = parent::getXML('marc21');

                        $json = json_encode(simplexml_load_string($xml));
                        $data = json_decode($json,TRUE);
                        $this->debug("rawmarc: ".print_r($data,true));
                        return $data;*/
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, true);
            return $data;
        }

    }

    /**
     *
     */
    public function getIsMarc()
    {
        $this->debug("ismarc: " . $this->config->isMarc);
        return $this->config->isMarc;
    }

    /**
     * @param bool $extended
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        if ($this->config) {
            if ($this->config->isMarc) {
                return parent::getAllSubjectHeadings($extended);
            } else {
                $headings = [];
                foreach (['topic', 'geographic', 'genre', 'era'] as $field) {
                    if (isset($this->fields[$field])) {
                        $headings = array_merge(
                            $headings, $this->fields[$field]
                        );
                    }
                }

                // The default index schema doesn't currently store subject headings in a
                // broken-down format, so we'll just send each value as a single chunk.
                // Other record drivers (i.e. SolrMarc) can offer this data in a more
                // granular format.
                $callback = function ($i) use ($extended) {
                    return $extended
                        ? ['heading' => [$i], 'type' => '', 'source' => '']
                        : [$i];
                };
                return array_map($callback, array_unique($headings));

            }
        } else {
            return array();
        }
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
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * @return type Description.
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
     * @param type $var Description.
     * @param type $var Optional. Description. Default.
     *
     * @return type Description.
     */
    public
    function getRealTimeTitleHold()
    {
        $od_id = $this->getOverdriveID();
        $rec_id = $this->getUniqueID();
        $urlDetails = [
            'action' => 'Hold',
            'record' => $rec_id,
            'query' => "od_id=$od_id&rec_id=$rec_id",
            'anchor' => ''
        ];
        return $urlDetails;
    }
}