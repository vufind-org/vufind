<?php
/**
 * Model for EAD3 records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2020.
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
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for EAD3 records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan O'Carragain <Eoghan.OCarragan@gmail.com>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Lutz Biedinger <lutz.Biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrEad3 extends SolrEad
{
    // Image types
    const IMAGE_MEDIUM = 'medium';
    const IMAGE_LARGE = 'large';
    const IMAGE_FULLRES = 'fullres';
    const IMAGE_OCR = 'ocr';

    // Image type map
    const IMAGE_MAP = [
        'Bittikartta - Fullres - Jakelukappale' => self::IMAGE_FULLRES,
        'Bittikartta - Pikkukuva - Jakelukappale' => self::IMAGE_MEDIUM,
        'OCR-data - Alto - Jakelukappale' => self::IMAGE_OCR
    ];

    // Altformavail labels
    const ALTFORM_LOCATION = 'location';
    const ALTFORM_TYPE = 'type';
    const ALTFORM_DIGITAL_TYPE = 'digitalType';
    const ALTFORM_FORMAT = 'format';
    const ALTFORM_ACCESS = 'access';
    const ALTFORM_ONLINE = 'online';

    // Altformavail label map
    const ALTFORM_MAP = [
        'Tietopalvelun tarjoamispaikka' => self::ALTFORM_LOCATION,
        'Tekninen tyyppi' => self::ALTFORM_TYPE,
        'Digitaalisen ilmentymän tyyppi' => self::ALTFORM_DIGITAL_TYPE,
        'Tallennusalusta' => self::ALTFORM_FORMAT,
        'Digitaalisen aineiston tiedostomuoto' => self::ALTFORM_FORMAT,
        'Ilmentym&#xE4;n kuntoon perustuva k&#xE4;ytt&#xF6;rajoitus'
            => self::ALTFORM_ACCESS,
        'Internet - ei fyysistä toimipaikkaa' => self::ALTFORM_ONLINE
    ];

    // Accessrestrict types
    const ACCESS_RESTRICT_TYPES = [
        'general',
        'ahaa:KR5', 'ahaa:KR7', 'ahaa:KR9', 'ahaa:KR4', 'ahaa:KR3', 'ahaa:KR1'
    ];

    // Relation types
    const RELATION_CONTINUED_FROM = 'continued-from';
    const RELATION_PART_OF = 'part-of';
    const RELATION_CONTAINS = 'contains';
    const RELATION_SEE_ALSO = 'see-also';

    // Relation type map
    const RELATION_MAP = [
        'On jatkoa' => self::RELATION_CONTINUED_FROM,
        'Sisältyy' => self::RELATION_PART_OF,
        'Sisältää' => self::RELATION_CONTAINS,
        'Katso myös' => self::RELATION_SEE_ALSO
    ];

    // Linktitle attribute for a daoset->dao image
    const DAO_LINK_TITLE_IMAGE = 'Kuva/Aukeama';

    /**
     * Get the institutions holding the record.
     *
     * @return array
     */
    public function getInstitutions()
    {
        $result = parent::getInstitutions();

        if (! $this->preferredLanguage) {
            return $result;
        }
        if ($name = $this->getRepositoryName()) {
            return [$name];
        }

        return $result;
    }

    /**
     * Return building from index.
     *
     * @return array
     */
    public function getBuilding()
    {
        $result = parent::getBuilding();

        if (! $this->preferredLanguage) {
            return $result;
        }
        if ($name = $this->getRepositoryName()) {
            return [$name];
        }

        return $result;
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
        $urls = [];
        $url = '';
        $record = $this->getXmlRecord();
        foreach ($record->did->xpath('//daoset/dao') as $node) {
            $attr = $node->attributes();
            // Discard image urls
            if (isset($attr->linktitle)
                && strpos((string)$attr->linktitle, self::DAO_LINK_TITLE_IMAGE) === 0
                || ! $attr->href
            ) {
                continue;
            }
            $url = (string)$attr->href;
            $desc = $attr->linktitle ?? $url;
            if (!$this->urlBlocked($url, $desc)) {
                $urls[] = [
                    'url' => $url,
                    'desc' => (string)$desc
                ];
            }
        }
        return $this->resolveUrlTypes($urls);
    }

    /**
     * Get origination
     *
     * @return string
     */
    public function getOrigination()
    {
        if ($origination = $this->getOriginationExtended()) {
            return $origination['name'];
        }
        return null;
    }

    /**
     * Get extended origination info
     *
     * @return array
     */
    public function getOriginationExtended()
    {
        $record = $this->getXmlRecord();
        if (!isset($record->relations->relation)) {
            return null;
        }

        foreach ($record->relations->relation as $relation) {
            $attr = $relation->attributes();
            foreach (['relationtype', 'href', 'arcrole'] as $key) {
                if (!isset($attr->{$key})) {
                    continue;
                }
            }
            if ((string)$attr->relationtype !== 'cpfrelation'
                || (string)$attr->arcrole !== 'Arkistonmuodostaja'
            ) {
                continue;
            }

            $name = $this->getDisplayLabel($relation, 'relationentry');
            if (!$name || !$name[0]) {
                $name = $this->getOrigination();
            }

            return [
                'name' => $name[0],
                'id' => (string)$attr->href,
                'type' => 'author-id'
            ];
        }

        return null;
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $result = [];
        $xml = $this->getXmlRecord();
        if (!isset($xml->relations->relation)) {
            return $result;
        }

        foreach ($xml->controlaccess->name as $node) {
            $attr = $node->attributes();
            $relator = (string)$attr->relator;
            if ($relator === 'Arkistonmuodostaja') {
                continue;
            }
            $role = $this->translateRole((string)$attr->localtype, $relator);
            $name = $this->getDisplayLabel($node);
            if (empty($name) || !$name[0]) {
                continue;
            }
            $result[] = [
               'id' => (string)$node->attributes()->identifier,
               'role' => $role,
               'name' => $name[0]
            ];
        }

        return $result;
    }

    /**
     * Get location info to be used in ExternalData-record page tab.
     *
     * @param string $id If defined, return only the item with the given id
     *
     * @return array
     */
    public function getAlternativeItems($id = null)
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->altformavail->altformavail)) {
            return [];
        }

        // Collect daoset > dao ids. This list is used to separate non-online
        // altformavail items.
        $onlineIds = [];
        if (isset($xml->did->daoset)) {
            foreach ($xml->did->daoset as $daoset) {
                if (isset($daoset->descriptivenote->p)) {
                    $onlineIds[] = (string)$daoset->descriptivenote->p;
                }
            }
        }

        $onlineTypes = array_keys(
            array_filter(
                self::ALTFORM_MAP,
                function ($label, $type) {
                    return $type === self::ALTFORM_ONLINE;
                }, ARRAY_FILTER_USE_BOTH
            )
        );

        //$onlineType = 'Internet - ei fyysistä toimipaikkaa';
        $results = [];
        foreach ($xml->altformavail->altformavail as $altform) {
            $itemId = (string)$altform->attributes()->id;
            if ($id && $id !== $itemId) {
                continue;
            }
            $result = ['id' => $itemId, 'online' => in_array($itemId, $onlineIds)];
            $owner = null;
            foreach ($altform->list->defitem ?? [] as $defitem) {
                $type = self::ALTFORM_MAP[(string)$defitem->label] ?? null;
                if (!$type) {
                    continue;
                }
                $val = (string)$defitem->item;
                switch ($type) {
                case self::ALTFORM_LOCATION:
                    $result['location'] = $val;
                    if (in_array($val, $onlineTypes)) {
                        $result['online'] = true;
                    } else {
                        $result['service'] = true;
                    }
                    break;
                case self::ALTFORM_TYPE:
                    $result['type'] = $val;
                    break;
                case self::ALTFORM_DIGITAL_TYPE:
                    $result['digitalType'] = $val;
                    break;
                case self::ALTFORM_FORMAT:
                    $result['format'] = $val;
                    break;
                case self::ALTFORM_ACCESS:
                    $result['accessRestriction'] = $val;
                    break;
                }
            }
            if ($id) {
                return $result;
            }
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Get unit ids
     *
     * @return array
     */
    public function getUnitIds()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->did->unitid)) {
            return [];
        }

        $ids = [];
        foreach ($xml->did->unitid as $id) {
            $label = (string)$id->attributes()->label;
            $val = (string)$id;
            if (!$val) {
                $val = (string)$id->attributes()->identifier;
                $label = 'unique';
            }
            if (!$label || !$val) {
                continue;
            }
            $ids[] = [
                'data' => $val,
                'detail' => $this->translate("Unit ID:$label", [], $label)
            ];
        }

        return $ids;
    }

    /**
     * Get notes on bibliography content.
     *
     * @return string[] Notes
     */
    public function getBibliographyNotes()
    {
        return [];
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        $xml = $this->getXmlRecord();

        if (!empty($xml->scopecontent)) {
            $desc = [];
            foreach ($xml->scopecontent as $el) {
                if (isset($el->attributes()->encodinganalog)) {
                    continue;
                }
                if (! isset($el->head) || (string)$el->head !== 'Tietosisältö') {
                    continue;
                }
                if ($desc = $this->getDisplayLabel($el, 'p', true)) {
                    return $desc;
                }
            }
        }
        return parent::getSummary();
    }

    /**
     * Get identifier
     *
     * @return array
     */
    public function getIdentifier()
    {
        $xml = $this->getXmlRecord();
        if (isset($xml->did->unitid)) {
            foreach ($xml->did->unitid as $unitId) {
                if (isset($unitId->attributes()->identifier)) {
                    return [(string)$unitId->attributes()->identifier];
                }
            }
        }
        return [];
    }

    /**
     * Get item history
     *
     * @return null|string
     */
    public function getItemHistory()
    {
        $xml = $this->getXmlRecord();

        if (!empty($xml->scopecontent)) {
            foreach ($xml->scopecontent as $el) {
                if (! isset($el->attributes()->encodinganalog)
                    || (string)$el->attributes()->encodinganalog !== 'AI10'
                ) {
                    continue;
                }
                if ($desc = $this->getDisplayLabel($el, 'p')) {
                    return $desc[0];
                }
            }
        }
        return null;
    }

    /**
     * Get external data (images, physical items).
     *
     * @return array
     */
    public function getExternalData()
    {
        return [
            'fullResImages' => $this->getFullResImages(),
            'OCRImages' => $this->getOCRImages(),
            'physicalItems' => $this->getPhysicalItems()
        ];
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - urls        Image URLs
     *   - small     Small image (mandatory)
     *   - medium    Medium image (mandatory)
     *   - large     Large image (optional)
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
     * @return array
     */
    public function getAllImages(
        $language = 'fi', $includePdf = false
    ) {
        $result = $images = [];
        $xml = $this->getXmlRecord();
        if (isset($xml->did->daoset)) {
            foreach ($xml->did->daoset as $daoset) {
                if (!isset($daoset->dao)) {
                    continue;
                }
                $attr = $daoset->attributes();
                $localtype = (string)($attr->localtype ?? null);
                $size = self::IMAGE_MAP[$localtype] ?? self::IMAGE_FULLRES;
                $size = $size === self::IMAGE_FULLRES ? self::IMAGE_LARGE : $size;
                if (!isset($images[$size])) {
                    $image[$size] = [];
                }

                $descId = isset($daoset->descriptivenote->p)
                    ? (string)$daoset->descriptivenote->p : null;

                foreach ($daoset->dao as $dao) {
                    // Loop daosets and collect URLs for different sizes
                    $urls = [];
                    $attr = $dao->attributes();
                    if (! isset($attr->linktitle)
                        || strpos(
                            (string)$attr->linktitle, self::DAO_LINK_TITLE_IMAGE
                        ) !== 0
                        || ! $attr->href
                    ) {
                        continue;
                    }
                    $href = (string)$attr->href;
                    if ($this->urlBlocked($href) || !$this->isUrlLoadable($href)) {
                        continue;
                    }
                    $images[$size][] = [
                        'description' => (string)$attr->linktitle,
                        'rights' => null,
                        'url' => $href,
                        'descId' => $descId,
                        'sort' => (string)$attr->label
                    ];
                }
            }

            if (empty($images)) {
                return [];
            }

            foreach ($images as $size => &$sizeImages) {
                $this->sortImageUrls($sizeImages);
            }

            foreach ($images['large'] ?? $images['medium'] as $id => $img) {
                $large = $images['large'][$id] ?? null;
                $medium = $images['medium'][$id] ?? null;

                $data = $img;
                $data['urls'] = [
                    'small' => $medium['url'] ?? $large['url'] ?? null,
                    'medium' => $medium['url'] ?? $large['url'] ?? null,
                    'large' => $large['url'] ?? $medium['url'] ?? null,
                ];

                $result[] = $data;
            }
        }
        return $result;
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->did->physdesc)) {
            return [];
        }

        return $this->getDisplayLabel($xml->did, 'physdesc', true);
    }

    /**
     * Get description of content.
     *
     * @return string
     */
    public function getContentDescription()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->controlaccess->genreform)) {
            return [];
        }

        foreach ($xml->controlaccess->genreform as $genre) {
            if (! isset($genre->attributes()->encodinganalog)
                || (string)$genre->attributes()->encodinganalog !== 'ahaa:AI46'
            ) {
                continue;
            }
            if ($label = $this->getDisplayLabel($genre)) {
                return $label[0];
            }
        }

        return null;
    }

    /**
     * Get the statement of responsibility that goes with the title (i.e. "by John
     * Smith").
     *
     * @return string
     */
    public function getTitleStatement()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->bibliography->p)) {
            return null;
        }
        $label = $this->getDisplayLabel($xml->bibliography, 'p', true);
        return $label ? $label[0] : null;
    }

    /**
     * Get extended access restriction notes for the record.
     *
     * @return string[]
     */
    public function getExtendedAccessRestrictions()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->accessrestrict->accessrestrict)) {
            return [];
        }
        $restrictions = [];
        foreach (self::ACCESS_RESTRICT_TYPES as $type) {
            $restrictions[$type] = [];
        }
        foreach ($xml->accessrestrict->accessrestrict as $accessNode) {
            if (!isset($accessNode->accessrestrict)) {
                continue;
            }
            foreach ($accessNode->accessrestrict as $access) {
                $attr = $access->attributes();
                if (! isset($attr->encodinganalog)) {
                    $restrictions['general']
                        = $this->getDisplayLabel($access, 'p', true);
                } else {
                    $type = (string)$attr->encodinganalog;
                    if (in_array($type, self::ACCESS_RESTRICT_TYPES)) {
                        $label = $type === 'ahaa:KR7'
                            ? $this->getDisplayLabel(
                                $access->p->name, 'part', true
                            ) : $this->getDisplayLabel($access, 'p');
                        if ($label) {
                            $restrictions[$type] = $label;
                        }
                    }
                }
            }
        }

        // Sort and discard empty
        $result = [];
        foreach ($restrictions as $type => $values) {
            if (empty($values)) {
                unset($restrictions[$type]);
            }
            $result[$type] = $values;
        }

        return $result;
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
        if (! $restrictions = $this->getAccessRestrictions()) {
            return false;
        }
        $copyright = $restrictions[0];
        $data = [];
        $data['copyright'] = $copyright;
        if ($link = $this->getRightsLink(strtoupper($copyright), $language)) {
            $data['link'] = $link;
        }
        return $data;
    }

    /**
     * Return image rights.
     *
     * @param string $language       Language
     * @param bool   $skipImageCheck Whether to check that images exist
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language, $skipImageCheck = false)
    {
        if (!$skipImageCheck && !$this->getAllImages()) {
            return false;
        }

        $rights = [];

        if ($type = $this->getAccessRestrictionsType($language)) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }
        $desc = $this->getAccessRestrictions();
        if ($desc && count($desc)) {
            $rights['description'] = $desc[0];
        }

        return isset($rights['copyright']) || isset($rights['description'])
            ? $rights : false;
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        $headings = [];
        $headings = $this->getTopics();

        foreach (['geographic', 'genre', 'era'] as $field) {
            if (isset($this->fields[$field])) {
                $headings = array_merge($headings, $this->fields[$field]);
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

    /**
     * Get the unitdate field.
     *
     * @return array
     */
    public function getUnitDates()
    {
        $unitdate = parent::getUnitDate();

        $record = $this->getXmlRecord();
        if (!isset($record->did->unittitle)) {
            return $unitdate;
        }
        $result = [];
        foreach ($record->did->unitdate as $date) {
            $attr = $date->attributes();
            if ($desc = $attr->normal ?? null) {
                $desc = $attr->label ?? null;
            }
            $date = (string)$date;
            $result[] = ['data' => (string)$date, 'detail' => (string)$desc];
        }
        return $result;
    }

    /**
     * Get related records (used by RecordDriverRelated - Related module)
     *
     * Returns an associative array of group => records, where each item in
     * records is either a record id or an array that has a 'wildcard' key
     * with a Solr compatible pattern as it's value.
     *
     * Notes on wildcard queries:
     *  - Only the first record from the wildcard result set is returned.
     *  - The wildcard query includes a filter that limits the results to
     *    the same datasource as the issuing record.
     *
     * The array may contain the following keys:
     *   - continued-from
     *   - part-of
     *   - contains
     *   - see-also
     *
     * Examples:
     * - continued-from
     *     - source1.1234
     *     - ['wildcard' => '*1234']
     *     - ['wildcard' => 'source*1234*']
     *
     * @return array
     */
    public function getRelatedRecords()
    {
        $record = $this->getXmlRecord();

        if (!isset($record->relations->relation)) {
            return [];
        }

        $relations = [];
        foreach ($record->relations->relation as $relation) {
            $attr = $relation->attributes();
            foreach (['encodinganalog', 'relationtype', 'href', 'arcrole'] as $key) {
                if (!isset($attr->{$key})) {
                    continue 2;
                }
            }
            if ((string)$attr->encodinganalog !== 'ahaa:AI30'
                || (string)$attr->relationtype !== 'resourcerelation'
            ) {
                continue;
            }
            $role = self::RELATION_MAP[(string)$attr->arcrole] ?? null;
            if (!$role) {
                continue;
            }
            if (!isset($relations[$role])) {
                $relations[$role] = [];
            }
            // Use a wildcard since the id is prefixed with hierarchy_parent_id
            $relations[$role][] = ['wildcard' => '*' . (string)$attr->href];
        }
        return $relations;
    }

    /**
     * Whether the record has related records declared in metadata.
     * (used by RecordDriverRelated related module).
     *
     * @return bool
     */
    public function hasRelatedRecords()
    {
        return !empty($this->getRelatedRecords());
    }

    /**
     * Get fullresolution images.
     *
     * @return array
     */
    protected function getFullResImages()
    {
        $images = $this->getAllImages();
        $items = [];
        foreach ($images as $img) {
            $items[]
                = ['label' => $img['description'], 'url' => $img['urls']['large']];
        }
        $info = [];

        if (isset($images[0]['descId'])) {
            $altItem = $this->getAlternativeItems($images[0]['descId']);
            if (isset($altItem['format'])) {
                $info[] = $altItem['format'];
            }
        }

        $items = $items ? compact('info', 'items') : [];
        return $items;
    }

    /**
     * Get OCR images.
     *
     * @return array
     */
    protected function getOCRImages()
    {
        $items = [];
        $xml = $this->getXmlRecord();
        $descId = null;
        if (isset($xml->did->daoset)) {
            foreach ($xml->did->daoset as $daoset) {
                if (!isset($daoset->dao)) {
                    continue;
                }
                $attr = $daoset->attributes();
                $localtype = (string)$attr->localtype ?? null;
                if ($localtype !== self::IMAGE_OCR) {
                    continue;
                }
                if (isset($daoset->descriptivenote->p)) {
                    $descId = (string)$daoset->descriptivenote->p;
                }

                foreach ($daoset->dao as $idx => $dao) {
                    $attr = $dao->attributes();
                    if (! isset($attr->linktitle)
                        || strpos((string)$attr->linktitle, 'Kuva/Aukeama') !== 0
                        || ! $attr->href
                    ) {
                        continue;
                    }
                    $href = (string)$attr->href;
                    $desc = (string)$attr->linktitle;
                    $sort = (string)$attr->label;
                    $items[] = [
                        'label' => linktitle, $desc, 'url' => $href, 'sort' => $sort
                    ];
                }
            }
        }

        $this->sortImageUrls($items);

        $info = [];
        if ($descId) {
            $altItem = $this->getAlternativeItems($descId);
            if ($format = $altItem['format'] ?? null) {
                $info[] = $format;
            }
        }

        return !empty($items) ? compact('info', 'items') : [];
    }

    /**
     * Sort an array of image URLs in place.
     *
     * @param array  $urls  URLs
     * @param string $field Field to use for sorting.
     * The field value is casted to int before sorting.
     *
     * @return void
     */
    protected function sortImageUrls(&$urls, $field = 'sort')
    {
        usort(
            $urls, function ($a, $b) use ($field) {
                $f1 = (int)$a[$field];
                $f2 = (int)$b[$field];
                if ($f1 === $f2) {
                    return 0;
                } elseif ($f1 < $f2) {
                    return -1;
                } else {
                    return 1;
                }
            }
        );
    }

    /**
     * Return physical items.
     *
     * @return array
     */
    protected function getPhysicalItems()
    {
        return array_filter(
            $this->getAlternativeItems(),
            function ($item) {
                return empty($item['online']) && !empty($item['location']);
            }
        );
    }

    /**
     * Get topics.
     *
     * @return string[]
     */
    protected function getTopics()
    {
        $record = $this->getXmlRecord();

        $topics = [];
        if (isset($record->controlaccess->subject)) {
            foreach ($record->controlaccess->subject as $subject) {
                if (!isset($subject->attributes()->relator)
                    || (string)$subject->attributes()->relator !== 'aihe'
                ) {
                    continue;
                }
                if ($topic = $this->getDisplayLabel($subject, 'part', true, false)) {
                    $topics[] = $topic[0];
                }
            }
        }
        return $topics;
    }

    /**
     * Return translated repository display name from metadata.
     *
     * @return string
     */
    protected function getRepositoryName()
    {
        $record = $this->getXmlRecord();

        if (isset($record->did->repository->corpname)) {
            foreach ($record->did->repository->corpname as $corpname) {
                if ($name = $this->getDisplayLabel($corpname, 'part', true)) {
                    return $name[0];
                }
            }
        }
        return null;
    }

    /**
     * Helper function for returning a specific language version of a display label.
     *
     * @param SimpleXMLElement $node                  XML node
     * @param string           $childNodeName         Name of the child node that
     * contains the display label.
     * @param bool             $obeyPreferredLanguage If true, returns the
     * translation that corresponds with the current locale.
     * If false, the default language version 'fin' is returned. If not found,
     * the first display label is retured.
     *
     * @return string[]
     */
    protected function getDisplayLabel(
        $node,
        $childNodeName = 'part',
        $obeyPreferredLanguage = false
    ) {
        if (! isset($node->$childNodeName)) {
            return null;
        }
        $defaultLanguage = 'fin';
        $language = $this->preferredLanguage
            ? $this->mapLanguageCode($this->preferredLanguage)
            : null;

        $getTermLanguage = function ($node) use ($language, $defaultLanguage) {
            if (!isset($node->attributes()->lang)) {
                return null;
            }
            $lang = (string)$node->attributes()->lang;
            return [
               'default' => $defaultLanguage === $lang,
               'preferred' => $language === $lang
            ];
        };

        $allResults = [];
        $defaultLanguageResults = [];
        $languageResults = [];
        $lang = $getTermLanguage($node);
        $resolveLangFromChildNode = $lang === null;
        foreach ($node->{$childNodeName} as $child) {
            $name = trim((string)$child);
            $allResults[] = $name;

            if ($resolveLangFromChildNode) {
                foreach ($child->attributes() as $key => $val) {
                    $lang = $getTermLanguage($child);
                    if ($lang) {
                        break;
                    }
                }
            }
            if ($lang['default']) {
                $defaultLanguageResults[] = $name;
            }
            if ($lang['preferred']) {
                $languageResults[] = $name;
            }
        }

        if ($obeyPreferredLanguage) {
            return $languageResults;
        }
        if (! empty($languageResults)) {
            return $languageResults;
        } elseif (! empty($defaultLanguageResults)) {
            return $defaultLanguageResults;
        }

        return $allResults;
    }

    /**
     * Convert Finna language codes to EAD3 codes.
     *
     * @param string $languageCode Language code
     *
     * @return string
     */
    protected function mapLanguageCode($languageCode)
    {
        $langMap = ['fi' => 'fin', 'sv' => 'swe', 'en-gb' => 'eng'];
        return $langMap[$languageCode] ?? $languageCode;
    }

    /**
     * Get role translation key
     *
     * @param string $role     EAD3 role
     * @param string $fallback Fallback to use when no supported role is found
     *
     * @return string Translation key
     */
    protected function translateRole($role, $fallback = null)
    {
        // Map EAD3 roles to CreatorRole translations
        $roleMap = [
            'http://rdaregistry.info/Elements/e/P20047' => 'ive',
            'http://rdaregistry.info/Elements/e/P20032' => 'ivr',
            'http://rdaregistry.info/Elements/w/P10046' => 'pbl',
            'http://www.rdaregistry.info/Elements/w/#P10311' => 'fac',
            'http://rdaregistry.info/Elements/e/P20042' => 'ctg',
            'http://rdaregistry.info/Elements/a/P50190' => 'cng',
            'http://rdaregistry.info/Elements/w/P10058' => 'art',
            'http://rdaregistry.info/Elements/w/P10066' => 'drt',
            'http://rdaregistry.info/Elements/e/P20033' => 'drm',
            'http://rdaregistry.info/Elements/e/P20024' => 'spk',
            'http://rdaregistry.info/Elements/w/P10204' => 'lyr',
            'http://rdaregistry.info/Elements/e/P20029' => 'arr',
            'http://rdaregistry.info/Elements/w/P10053' => 'cmp',
            'http://rdaregistry.info/Elements/w/P10065' => 'aut',
            'http://rdaregistry.info/Elements/w/P10298' => 'edt',
            'http://rdaregistry.info/Elements/w/P10064' => 'pro',
            'http://www.rdaregistry.info/Elements/u/P60429' => 'pht',
            'http://www.rdaregistry.info/Elements/e/#P20052' => 'rpy',
            'http://rdaregistry.info/Elements/w/P10304' => 'rpy',

            'http://rdaregistry.info/Elements/w/P10061' => 'rda:per',
            'http://rdaregistry.info/Elements/w/P10061' => 'rda:host',
            'http://rdaregistry.info/Elements/w/P10061' => 'rda:writer',
            'http://rdaregistry.info/Elements/a/P50045' => 'rda:collector',
            'http://www.rdaregistry.info/Elements/i/#P40019' => 'rda:former-owner'
        ];

        return $roleMap[$role] ?? $fallback;
    }

    /**
     * Returns an array of 0 or more record label constants, or null if labels
     * are not enabled in configuration.
     *
     * @return array|null
     */
    public function getRecordLabels()
    {
        if (!$this->getRecordLabelsEnabled()) {
            return null;
        }
        $labels = [];
        if ($this->hasRestrictedMetadata()) {
            $labels[] = FinnaRecordLabelInterface::R2_RESTRICTED_METADATA_AVAILABLE;
        }
        return $labels;
    }
}
