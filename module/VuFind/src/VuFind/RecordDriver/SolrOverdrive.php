<?php

/**
 * VuFind Record Driver for SolrOverdrive Records
 *
 * PHP version 8
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

use Laminas\Config\Config;
use Laminas\Log\LoggerAwareInterface;
use VuFind\DigitalContent\OverdriveConnector;

use function in_array;

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
        Config $mainConfig = null,
        $recordConfig = null,
        OverdriveConnector $connector = null
    ) {
        $this->connector = $connector;
        $this->config = $connector->getConfig();
        parent::__construct($mainConfig, $recordConfig, null);
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
            // If we're already locked in, then we need free ones and locked in ones.
            if ($checkout->isFormatLockedIn) {
                foreach ($checkout->formats as $format) {
                    $formatType = $format->formatType;
                    $formats[$formatType] = $formatNames[$formatType];
                }
            } else {
                // Not locked in, we can show all formats
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
        if ($this->getIsMarc()) {
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
        foreach ($this->getDigitalFormats() as $key => $format) {
            $tmpresults = [];
            if ($format->fileSize > 0) {
                if ($format->fileSize > 1000000) {
                    $size = round($format->fileSize / 1000000);
                    $size .= ' GB';
                } elseif ($format->fileSize > 1000) {
                    $size = round($format->fileSize / 1000);
                    $size .= ' MB';
                } else {
                    $size = $format->fileSize;
                    $size .= ' KB';
                }
                $tmpresults['File Size'] = $size;
            }
            if ($format->partCount) {
                $tmpresults['Parts'] = $format->partCount;
            }
            if ($format->identifiers) {
                foreach ($format->identifiers as $id) {
                    if (in_array($id->type, ['ISBN', 'ASIN'])) {
                        $tmpresults[$id->type] = $id->value;
                    }
                }
            }
            if ($format->onSaleDate) {
                $tmpresults['Release Date'] = $format->onSaleDate;
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
            $data = $fulldata[strtolower($od_id)] ?? null;
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }

        $samples = $data->formats[0]->samples ?? [];
        $previewFormats = ['audiobook-overdrive', 'ebook-overdrive', 'magazine-overdrive'];
        foreach ($samples as $format) {
            if (in_array($format->formatType ?? '', $previewFormats) && isset($format->url)) {
                $results[] = $format->url;
            }
        }
        return $results;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return $this->config->enableAjaxStatus ?? true;
    }

    /**
     * Get Overdrive Access
     *
     * Pass-through to the connector to determine whether logged-in user
     * has access to Overdrive actions
     *
     * @return bool Whether the logged-in user has access to Overdrive.
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
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->connector->getUser() ? true : false;
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
            if ($this->getIsMarc()) {
                $field = $this->config->idField;
                $subfield = $this->config->idSubfield;
                $result = strtolower(
                    $this->getFieldArray($field, $subfield)[0] ?? ''
                );
            } else {
                $result = strtolower($this->getUniqueID());
            }
        }
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
     * Returns a boolean indicating if patron actions are supported
     *
     * @return bool
     */
    public function supportsPatronActions()
    {
        return $this->config->usePatronAPI;
    }

    /**
     * Is Checked Out
     *
     * Is this resource already checked out to the user?
     *
     * @return object Returns the checkout information if currently checked out
     *    by this user or false in the data property if not.
     * @throws \Exception
     */
    public function isCheckedOut()
    {
        $result = $this->connector->getResultObject();
        if ($this->isLoggedIn() && $this->supportsPatronActions()) {
            $overdriveID = $this->getOverdriveID();
            $result = $this->connector->getCheckouts(true);
            if ($result->status) {
                $checkedout = false;
                $checkouts = $result->data;
                // In case of a magazine issue, we have to get all the checkouts to see if the
                // current title is the parentID of one of the user's checkouts. Return data as
                // array in case there are multiple issues checked out to the user.
                $result->data = [];
                $result->isMagazine = false;
                foreach ($checkouts as $checkout) {
                    if ($checkout->metadata->mediaType == 'Magazine') {
                        $idToCheck = strtolower($checkout->metadata->parentMagazineReferenceId);
                    } else {
                        $idToCheck = $checkout->reserveId;
                    }
                    if (strtolower($idToCheck) == $overdriveID) {
                        $checkedout = true;
                        $result->status = true;
                        $result->data[] = $checkout;
                        //this checkout is a magazine issue of the current title
                        if ($checkout->metadata->mediaType == 'Magazine') {
                            $result->isMagazine =  true;
                        }
                    }
                }
                if (!$checkedout) {
                    $result->data = false;
                }
            }
        }
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
        if ($this->isLoggedIn() && $this->supportsPatronActions()) {
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
        }
        // If it didn't work, an error should be logged from the connector
        return false;
    }

    /**
     * Get Bread Crumb
     *
     * @return string
     */
    public function getBreadcrumb()
    {
        $short = $this->getShortTitle();
        return $short ? $short : $this->getTitle();
    }

    /**
     * Get Marc Reader
     *
     * Override the base marc trait to return an empty marc reader object if no MARC
     * is available.
     *
     * @return \VuFind\Marc\MarcReader
     */
    public function getMarcReader()
    {
        return $this->getIsMarc()
            ? parent::getMarcReader()
            : new $this->marcReaderClass('<record></record>');
    }

    /**
     * Get Title Section
     *
     * @return string
     */
    public function getTitleSection()
    {
        return $this->getIsMarc()
            ? parent::getTitleSection()
            : ''; // I don't think Overdrive has this metadata
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return $this->getIsMarc() ? parent::getGeneralNotes() : [];
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
    public function getThumbnail($size = 'small')
    {
        $coverMap = [
            'large' => 'cover300Wide',
            'medium' => 'cover150Wide',
            'small' => 'thumbnail',
        ];
        $cover = $coverMap[$size] ?? 'cover';

        // If the record is marc then the cover links probably aren't there.
        if ($this->getIsMarc()) {
            $od_id = $this->getOverdriveID();
            $fulldata = $this->connector->getMetadata([$od_id]);
            $data = $fulldata[strtolower($od_id)] ?? null;
        } else {
            $jsonData = $this->fields['fullrecord'];
            $data = json_decode($jsonData, false);
        }
        return $data->images->{$cover}->href ?? false;
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        if ($this->getIsMarc()) {
            return parent::getSummary();
        }
        // Non-MARC case:
        $desc = $this->fields['description'] ?? '';

        $newDesc = preg_replace('/&#8217;/i', '', $desc);
        $newDesc = strip_tags($newDesc);
        return [$newDesc];
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
     * Get all subject headings associated with this record. Each heading is
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
        if (!$this->config) {
            return [];
        }
        return $this->getIsMarc()
            ? parent::getAllSubjectHeadings($extended)
            : DefaultRecord::getAllSubjectHeadings($extended);
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
        $jsonData = $this->fields['fullrecord'];
        $data = json_decode($jsonData, true);
        $c_arr = [];
        foreach ($data['creators'] as $creator) {
            $c_arr[] = "<strong>{$creator['role']}<strong>: "
                . $creator['name'];
        }
        $data['creators'] = implode('<br>', $c_arr);
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
            'anchor' => '',
        ];
        return $urlDetails;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        return $this->getIsMarc()
            ? parent::getURLs()
            : $this->getPermanentLink();
    }

    /**
     * Get Permanent Link to the resource on your institution's OverDrive site
     *
     * @return array the permanent link to the resource
     */
    public function getPermanentLink()
    {
        if (!empty($libraryURL = $this->config->libraryURL)) {
            $data = json_decode($this->fields['fullrecord'], false);
            $desc = $this->translate('od_resource_page');
            $permlink = "$libraryURL/media/" . $data->crossRefId;
            return  [['url' => $permlink, 'desc' => $desc ?: $permlink]];
        } else {
            return [];
        }
    }
}
