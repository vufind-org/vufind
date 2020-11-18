<?php
/**
 * Model for LRMI records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for LRMI records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrLrmi extends SolrQdc
{
    /**
     * File formats that are downloadable
     *
     * @var array
     */
    protected $downloadableFileFormats = [
        'pdf', 'pptx', 'ppt', 'docx', 'mp4', 'mp3', 'html',
        'avi', 'odt', 'rtf', 'txt', 'odp', 'png', 'jpeg', 'm4a',
        'mbz', 'doc'
    ];

    /**
     * Usage rights map
     *
     * @var array
     */
    protected $usageRightsMap = [
        'CCBY4.0' => 'CC BY 4.0',
        'CCBYSA4.0' => 'CC BY-SA 4.0',
        'CCBYND4.0' => 'CC BY-ND 4.0',
        'CCBYNCND4.0' => 'CC BY-NC-ND 4.0',
        'CCBYNCSA4.0' => 'CC BY-NC-SA 4.0',
        'CCBYNC4.0' => 'CC BY-NC-SA 4.0'
    ];

    /**
     * Returns a list of downloadable file formats.
     *
     * @return array
     */
    public function getDownloadableFileFormats()
    {
        return $this->downloadableFileFormats;
    }

    /**
     * Return type of access restriction for the record.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType($language)
    {
        $xml = $this->getXmlRecord();
        $rights = [];
        $locale = $this->getLocale();
        if (!empty($xml->rights)) {
            $copyrights = (string)$xml->rights;
            $rights['copyrights']
                = $this->usageRightsMap[$copyrights] ?? $copyrights;
            $rights['link'] = $this->getRightsLink($rights['copyrights'], $locale);
            return $rights;
        }
        return false;
    }

    /**
     * Get an array of summary strings for the record
     *
     * @return array
     */
    public function getSummary()
    {
        $xml = $this->getXmlRecord();
        $locale = $this->getLocale();
        foreach ($xml->description as $d) {
            if (!empty($d['format'])) {
                continue;
            }
            if ($locale === (string)$d['lang']) {
                return [(string)$d];
            }
        }
        return [];
    }

    /**
     * Get educational audiences
     *
     * @return array
     */
    public function getEducationalAudiences()
    {
        return $this->fields['educational_audience_str_mv'] ?? [];
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $xml = $this->getXmlRecord();
        $result = [];
        foreach ($xml->author->person ?? [] as $author) {
            $result[] = [
                'name' => trim((string)$author->name),
                'affiliation' => trim((string)$author->affiliation)
            ];
        }
        foreach ($xml->author->organization ?? [] as $org) {
            $result[] = [
                'name' => trim((string)$org->legalName)
            ];
        }
        return $result;
    }

    /**
     * Return educational levels
     *
     * @return array
     */
    public function getEducationalLevels()
    {
        return $this->fields['educational_level_str_mv'] ?? [];
    }

    /**
     * Return root educational levels
     *
     * @return array
     */
    public function getRootEducationalLevels()
    {
        $rootLevels = [];
        foreach ($this->fields['educational_level_str_mv'] ?? [] as $level) {
            if (substr($level, 0, 1) === '0') {
                $rootLevels[] = trim((string)$level);
            }
        }
        return $rootLevels;
    }

    /**
     * Return url to external LRMI record page based on the record ID
     * or false if an external link template is not provided.
     *
     * @return string|boolean
     */
    public function getExternalLink()
    {
        $config = $this->recordConfig->Record;
        $src = $this->getDataSource();
        if (isset($config->lrmi_external_link_template[$src])) {
            $link = $config->lrmi_external_link_template[$src];
            $recordId = $this->getUniqueID();
            $recordId = substr($recordId, (strrpos($recordId, '.') + 1));
            return str_replace(
                ['{materialId}', '{lang}'],
                [$recordId, $this->getLocale()],
                $link
            );
        }
        return false;
    }

    /**
     * Return link to external LRMI record rating page based on the record ID
     * or false if an external link template is not provided or there is no
     * external rating page.
     *
     * @return array|boolean
     */
    public function getExternalRatingLink()
    {
        // Only AOE records supported currently.
        if ('aoe' === $this->getDataSource()) {
            return $this->getExternalLink();
        }
        return false;
    }

    /**
     * Get educational subjects
     *
     * @return array
     */
    public function getEducationalSubjects()
    {
        return $this->fields['educational_subject_str_mv'] ?? [];
    }

    /**
     * Get educational material type
     *
     * @return array
     */
    public function getEducationalMaterialType()
    {
        return $this->fields['educational_material_type_str_mv'] ?? [];
    }

    /**
     * Get topics
     *
     * @param string $type defaults to /onto/yso/
     *
     * @return array
     */
    public function getTopics($type = '/onto/yso/')
    {
        $xml = $this->getXmlRecord();
        $topics = [];
        foreach ($xml->about as $about) {
            $thing = $about->thing;
            $name = trim((string)$thing->name);
            if ($name && strpos((string)$thing->identifier, $type) !== false
            ) {
                $topics[] = $name;
            }
        }
        return $topics;
    }

    /**
     * Is the provided filetype allowed for download?
     *
     * @param string $format file format
     *
     * @return boolean
     */
    protected function isDownloadableFileFormat($format)
    {
        return in_array($format, $this->downloadableFileFormats);
    }

    /**
     * Get file format
     *
     * @param string $filename file name
     *
     * @return string
     */
    protected function getFileFormat($filename)
    {
        return pathinfo($filename)['extension'] ?? '';
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - url         Image URL
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return mixed
     */
    public function getAllImages($language = 'fi', $includePdf = true)
    {
        $xml = $this->getXmlRecord();
        $uniqueId = $this->getUniqueID();
        $result = [];
        $images = ['image/png', 'image/jpeg'];
        foreach ($xml->description as $desc) {
            $attr = $desc->attributes();
            $format = trim((string)($attr['format'] ?? ''));
            if ($format && in_array($format, $images)) {
                $url = (string)$desc;
                if (!$this->urlBlocked($url)
                    && $this->isUrlLoadable($url, $uniqueId)
                ) {
                    $result[] = [
                        'urls' => [
                            'small' => $url,
                            'medium' => $url,
                            'large' => $url
                        ],
                        'description' => '',
                        'rights' => []
                    ];
                }
            }
        }

        // Attempt to find a PDF file to be converted to a coverimage
        if ($includePdf && empty($result) && $materials = $this->getMaterials()) {
            foreach ($materials as $material) {
                if ($material['format'] === 'pdf') {
                    $url = $material['url'];
                    if (!$this->urlBlocked($url)) {
                        $result[] = [
                            'urls' => [
                                'small' => $url,
                                'medium' => $url,
                                'large' => $url
                            ],
                            'description' => '',
                            'rights' => []
                        ];
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Return array of materials with keys:
     * -url: download link for allowed file types, otherwise empty
     * -title: material title
     * -format: material format
     * -filesize: material file size in bytes
     * -position: order of listing
     *
     * @return array
     */
    public function getMaterials()
    {
        $xml = $this->getXmlRecord();
        $materials = [];
        $locale = $this->getLocale();
        foreach ($xml->material as $material) {
            if (isset($material->format)) {
                $mime = (string)$material->format;
                $format = $mime === 'text/html'
                    ? 'html'
                    : $this->getFileFormat((string)$material->url);

                $url = $this->isDownloadableFileFormat($format)
                    ? (string)$material->url : '';
                $titles = $this->getMaterialTitles($material->name, $locale);
                $title = $titles[$locale] ?? $titles['default'];
                $position = (int)$material->position ?? 0;
                $filesize = (string)$material->filesize ?? null;
                $materials[] = compact(
                    'url', 'title', 'format', 'filesize', 'position'
                );
            }
        }

        usort(
            $materials, function ($a, $b) {
                return (int)$a['position'] <=> (int)$b['position'];
            }
        );

        return $materials;
    }

    /**
     * Get material titles in an assoc array
     *
     * @param object $names to look for
     *
     * @return array
     */
    public function getMaterialTitles($names)
    {
        $titles = ['default' => (string)$names];

        foreach ($names as $name) {
            $attr = $name->attributes();
            $titles[(string)$attr->lang] = (string)$name;
        }
        return $titles;
    }

    /**
     * Get creation date
     *
     * @return string|false
     */
    public function getDateCreated()
    {
        $xml = $this->getXmlRecord();
        if ($created = $xml->dateCreated) {
            return $this->dateConverter->convertToDisplayDate('Y-m-d H:i', $created);
        }
        return false;
    }

    /**
     * Get last modified date
     *
     * @return string|false
     */
    public function getDateModified()
    {
        $xml = $this->getXmlRecord();
        if ($mod = $xml->dateModified) {
            return $this->dateConverter->convertToDisplayDate('Y-m-d H:i', $mod);
        }
        return false;
    }

    /**
     * Get educational use
     *
     * @return array
     */
    public function getEducationalUse()
    {
        $xml = $this->getXmlRecord();
        $uses = [];
        foreach ($xml->educationalUse as $use) {
            $uses[] = trim((string)$use);
        }
        return $uses;
    }

    /**
     * Get educational aim
     *
     * @return array
     */
    public function getEducationalAim()
    {
        $aims = [];
        foreach ($this->fields['educational_aim_str_mv'] ?? [] as $aim) {
            $aims[] = trim((string)$aim);
        }
        return $aims;
    }

    /**
     * Get accessibility features
     *
     * @return array
     */
    public function getAccessibilityFeatures()
    {
        $xml = $this->getXmlRecord();
        $features = [];
        foreach ($xml->accessibilityFeature as $feature) {
            $features[] = trim((string)$feature);
        }
        return $features;
    }

    /**
     * Get accessibility hazards
     *
     * @return array
     */
    public function getAccessibilityHazards()
    {
        $xml = $this->getXmlRecord();
        $hazards = [];
        foreach ($xml->accessibilityHazard as $hazard) {
            $hazards[] = trim((string)$hazard);
        }
        return $hazards;
    }
}
