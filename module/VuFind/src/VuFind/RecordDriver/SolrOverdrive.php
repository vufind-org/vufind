<?php
/**
 * VuFind Record Driver for SolrOverdrive Records
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

use VuFind\DigitalContent\OverdriveConnector;
use Zend\Config\Config;
use Zend\Log\LoggerAwareInterface;

/**
 * VuFind Record Driver for SolrOverdrive Records
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrOverdrive extends SolrMarc implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Overdrive Connector
     *
     * @var OverdriveConnector $connector Overdrive Connector
     */
    protected $connector;

    /**
     * Overdrive Configuration Object
     *
     * @var object
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config             $mainConfig   VuFind main configuration
     * @param Config             $recordConfig Record-specific configuration
     * @param OverdriveConnector $connector    Overdrive Connector
     */
    public function __construct(
        Config $mainConfig = null, $recordConfig = null,
        OverdriveConnector $connector = null
    ) {
        $this->connector = $connector;
        $this->config = $connector->getConfig();
        parent::__construct($mainConfig, $recordConfig, null);

        $this->debug("SolrOverdrive Rec Driver constructed");
    }

    /**
     * Supports OpenURL
     *
     * @return bool
     */
    public function supportsOpenUrl()
    {
        return false;
    }

    /**
     * Supports coins OpenURL
     *
     * @return bool
     */
    public function supportsCoinsOpenUrl()
    {
        return false;
    }

    /**
     * Get Available Digital Formats
     *
     * Return the digital download formats that are available for linking to.
     *
     * @return array
     * @throws \Exception
     */
    public function getAvailableDigitalFormats()
    {
        $formats = [];
        $formatNames = $this->connector->getFormatNames();
        $od_id = $this->getOverdriveID();

        if ($checkout = $this->connector->getCheckout($od_id, false)) {
            //if we are already locked in, then we need free ones and locked in ones.
            if ($checkout->isFormatLockedIn) {
                foreach ($checkout->formats as $format) {
                    $formatType = $format->formatType;
                    $formats[$formatType] = $formatNames[$formatType];
                }
                //if we aren't locked in, we can show all formats
            } else {
                foreach ($this->getDigitalFormats() as $format) {
                    $formats[$format->id] = $formatNames[$format->id];
                }
            }
        }
        return $formats;
    }

    /**
     * Get Formats
     *
     * Returns an array of digital formats for this resource.
     *
     * @return array Array of formats.
     * @throws \Exception
     */
    public function getDigitalFormats()
    {
        $formats = [];
        $formatNames = $this->connector->getFormatNames();
        if ($this->config->isMarc) {
            $od_id = $this->getOverdriveID();
            $fulldata = $this->connector->getMetadata([$od_id]);
            $data = $fulldata[strtolower($od_id)];
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }

        foreach ($data->formats as $format) {
            $format->name = $formatNames[$format->id];
            $formats[$format->id] = $format;
        }

        return $formats;
    }

    /**
     * Get an array of all the formats associated with the record with metadata
     * associated with it. This array is designed to be used in a template.
     * The key for each entry is the translatable token for the format name
     *
     * @return array
     * @throws \Exception
     */
    public function getFormattedDigitalFormats()
    {
        $results = [];
        foreach ($this->getDigitalFormats() as $key=>$format) {
            $tmpresults = [];
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

        return $results;
    }

    /**
     * Returns links for showing previews
     *
     * @return array      an array of links
     * @throws \Exception
     */
    public function getPreviewLinks()
    {
        $results = [];
        if ($this->getIsMarc()) {
            $od_id = $this->getOverdriveID();
            $fulldata = $this->connector->getMetadata([$od_id]);
            $data = $fulldata[strtolower($od_id)];
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }

        if (isset($data->formats[0]->samples[0])) {
            foreach ($data->formats[0]->samples as $format) {
                if ($format->formatType == 'audiobook-overdrive'
                    || $format->formatType == 'ebook-overdrive'
                ) {
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
        //Future: add this as an overdrive configuration to turn it off
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
     * @return string OverdriveID
     * @throws \Exception
     */
    public function getOverdriveID()
    {
        $result = 0;

        if ($this->config) {
            if ($this->config->isMarc) {
                $field = $this->config->idField;
                $subfield = $this->confif->idSubfield;
                $result = strtolower(
                    $this->getFieldArray($field, $subfield)[0]
                );
            } else {
                $result = strtolower($this->getUniqueID());
            }
        }
        $this->debug("odid: $result");
        return $result;
    }

    /**
     * Returns the availability for the current record
     *
     * @return object|bool returns an object with the info in it (see URL above)
     * or false if there was a problem.
     * @throws \Exception
     */
    public function getOverdriveAvailability()
    {
        $overDriveId = $this->getOverdriveID();
        return $this->connector->getAvailability($overDriveId);
    }

    /**
     * Is Checked Out
     *
     * Is this resource already checked out to the user?
     *
     * @return object Returns the checkout information if currently checked out
     *    by this user or false if not.
     * @throws \Exception
     */
    public function isCheckedOut()
    {
        $this->debug(" ischeckout", [], true);
        $overdriveID = $this->getOverdriveID();
        $result = $this->connector->getCheckouts(true);
        if ($result->status) {
            $checkedout = false;
            $checkouts = $result->data;
            foreach ($checkouts as $checkout) {
                if (strtolower($checkout->reserveId) == $overdriveID) {
                    $checkedout = true;
                    $result->status = true;
                    $result->data = $checkout;
                }
            }
            if (!$checkedout) {
                $result->data = false;
            }
        }
        //if it didn't work, an error should be logged from the connector
        return $result;
    }

    /**
     * Is Held
     * Checks to see if the current record is on hold through Overcdrive.
     *
     * @return object|bool Returns the hold info if on hold or false if not.
     * @throws \Exception
     */
    public function isHeld()
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
     * Get Bread Crumb
     *
     * @return string
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
     * Get Marc Record
     *
     * Override the base marc trait to return a fake marc obj.
     *
     * @return \File_MARCBASE
     * @throws \File_MARC_Exception
     */
    public function getMarcRecord()
    {
        if ($this->getIsMarc()) {
            return parent::getMarcRecord();
        } else {
            //return new fake marc class
            return new class {
                /**
                 * Get the field
                 *
                 * @param string $f Fieldname
                 *
                 * @return string
                 */
                public function getField($f)
                {
                    return "";
                }

                /**
                 * Get the fields
                 *
                 * @param array $f Fieldnames
                 *
                 * @return array
                 */
                public function getFields($f)
                {
                    return [];
                }
            };
        }
    }

    /**
     * Get Subtitle
     *
     * @return string
     */
    public function getSubtitle()
    {
        if ($this->getIsMarc()) {
            return parent::getSubtitle();
        } else {
            return $this->fields['title_sub'];
        }
    }

    /**
     * Get Title Section
     *
     * @return string
     */
    public function getTitleSection()
    {
        if ($this->getIsMarc()) {
            return parent::getTitleSection();
        } else {
            //I don't think overdrive has this metadata
            return "";
        }
    }

    /**
     * Get Short Title
     *
     * @return string
     */
    public function getShortTitle()
    {
        if ($this->getIsMarc()) {
            return parent::getShortTitle();
        } else {
            return $this->fields['title_short'];
        }
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        if ($this->config->isMarc) {
            return parent::getGeneralNotes();
        } else {
            return [];
        }
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the
     * record if an image is available in an external system; an array of
     * parameters to send to VuFind's internal cover generator if no fixed URL
     * exists; or false if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small
     *                     is
     *                     default).
     *
     * @return string|array|bool
     * @throws \Exception
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
            $fulldata = $this->connector->getMetadata([$od_id]);
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
        return $result;
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        if ($this->config->isMarc) {
            return parent::getSummary();
        } else {
            $desc = $this->fields["description"];

            $newDesc = preg_replace("/&#8217;/i", "", $desc);
            $newDesc = strip_tags($newDesc);
            return ["Summary" => $newDesc];
        }
    }

    /**
     * Retrieve raw data from object (primarily for use in staff view and
     * autocomplete; avoid using whenever possible).
     *
     * @return mixed
     */
    public function getRawData()
    {
        if ($this->config->isMarc) {
            return parent::getRawData();
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, true);
            return $data;
        }
    }

    /**
     * Is Marc Based Record
     *
     * Return whether this is a marc-based record.
     *
     * @return bool
     */
    public function getIsMarc()
    {
        return $this->config->isMarc;
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     *                       keys:
     *                       - heading: the actual subject heading chunks
     *                       - type: heading type
     *                       - source: source vocabulary
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

                // The default index schema doesn't currently store subject
                // headings in a
                // broken-down format, so we'll just send each value as a
                // single chunk.
                // Other record drivers (i.e. SolrMarc) can offer this data
                // in a more granular format.
                $callback = function ($i) use ($extended) {
                    return $extended
                        ? ['heading' => [$i], 'type' => '', 'source' => '']
                        : [$i];
                };
                return array_map($callback, array_unique($headings));
            }
        } else {
            return [];
        }
    }

    /**
     * Get Formatted Raw Data
     *
     * Returns the raw data formatted for staff display tab
     *
     * @return array Multidimensional array with data
     */
    public function getFormattedRawData()
    {
        $result = [];
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, true);
        $c_arr = [];
        foreach ($data['creators'] as $creator) {
            $c_arr[] = "<strong>{$creator["role"]}<strong>: "
                . $creator["name"];
        }
        $data['creators'] = implode("<br/>", $c_arr);

        $this->debug("raw data:" . print_r($data, true));
        return $data;
    }

    /**
     * Get a link for placing a title level hold.
     *
     * @return mixed A url if a hold is possible, boolean false if not
     */
    public function getRealTimeTitleHold()
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
