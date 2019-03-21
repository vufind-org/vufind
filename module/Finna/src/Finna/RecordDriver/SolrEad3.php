<?php
/**
 * Model for EAD3 records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2019.
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
            $url = (string)$node->attributes()->href;
            $desc = $node->attributes()->linktitle ?? $url;
            if (!$this->urlBlacklisted($url, $desc)) {
                $urls[] = [
                    'url' => $url,
                    'desc' => $desc
                ];
            }
        }
        $urls = $this->checkForAudioUrls($urls);
        return $urls;
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
               'type' => 'author-id',
               'role' => $role,
               'name' => $name[0]
            ];
        }

        return $result;
    }

    /**
     * Get location info to be used in LoacationsEad3-record page tab.
     *
     * @return array
     */
    public function getLocations()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->altformavail->altformavail)) {
            return [];
        }

        $result = [];
        foreach ($xml->altformavail->altformavail as $altform) {
            $id = (string)$altform->attributes()->id;
            $owner = $label = $serviceLocation = $itemType = null;
            foreach ($altform->list->defitem as $defitem) {
                $type = $defitem->label;
                $val = (string)$defitem->item;
                switch ($type) {
                case 'Tallennusalusta':
                    $label = $val;
                    break;
                case 'Säilyttävä toimipiste':
                    $owner = $val;
                    break;
                case 'Tietopalvelun tarjoamispaikka':
                    $serviceLocation = $val;
                    break;
                case 'Tekninen tyyppi':
                    $itemType = $val;
                    break;
                }
            }

            if (!$owner) {
                $owner = $serviceLocation;
            }

            if (!$id || !$owner || !$label || $itemType !== 'Analoginen') {
                continue;
            }

            if (!isset($result[$owner]['items'])) {
                $result[$owner] = [
                    'providesService' =>
                        $serviceLocation === $owner ? true : $serviceLocation,
                    'items' => []
                ];
            }

            $result[$owner]['items'][] = compact('label', 'id');
        }

        return $result;
    }

    /**
     * Get unit ids
     *
     * @return string[]
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
            $label = $this->translate("Unit ID:$label");
            $ids[$label] = $val;
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
     * @param string $language Language for copyright information
     *
     * @return array
     */
    public function getAllImages($language = 'fi')
    {
        $result = [];

        $xml = $this->getXmlRecord();
        if (isset($xml->did->daoset->dao)) {
            foreach ($xml->did->daoset->dao as $dao) {
                $urls = [];
                $attr = $dao->attributes();
                // TODO properly detect image urls
                if (! isset($attr->linktitle)
                    || strpos((string)$attr->linktitle, 'Kuva/Aukeama') !== 0
                    || ! $attr->href
                ) {
                    continue;
                }

                $href = (string)$attr->href;
                $result[] = [
                    'urls' => ['small' => $href, 'medium' => $href],
                    'description' => (string)$attr->linktitle,
                    'rights' => null
                ];
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
        if (!isset($xml->accessrestrict)) {
            return [];
        }
        $restrictions = [];
        $types = [
            'general',
            'ahaa:KR5', 'ahaa:KR7', 'ahaa:KR9', 'ahaa:KR4', 'ahaa:KR3', 'ahaa:KR1'
        ];
        foreach ($types as $type) {
            $restrictions[$type] = [];
        }
        foreach ($xml->accessrestrict as $access) {
            $attr = $access->attributes();
            if (! isset($attr->encodinganalog)) {
                $restrictions['general']
                    = $this->getDisplayLabel($access, 'p', true);
            } else {
                $type = (string)$attr->encodinganalog;
                if (in_array($type, $types)) {
                    $label = $type === 'ahaa:KR7'
                        ? $this->getDisplayLabel($access->p->name, 'part', true)
                        : $this->getDisplayLabel($access, 'p', true);
                    if ($label) {
                        $restrictions[$type] = $label;
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
     * @return string
     */
    public function getUnitDate()
    {
        $unitdate = parent::getUnitDate();

        $record = $this->getXmlRecord();
        if (!isset($record->did->unittitle)) {
            return $unitdate;
        }
        foreach ($record->did->unittitle as $title) {
            $attributes = $title->attributes();
            if (! isset($attributes->encodinganalog)
                || (string)$attributes->encodinganalog !== 'ahaa:AI55'
            ) {
                continue;
            }
            return sprintf('%s (%s)', $unitdate, (string)$title);
        }
        return $unitdate;
    }

    /**
     * Get related records (used by RecordDriverRelated - Related module)
     *
     * Returns an associative array of record ids.
     * The array may contain the following keys:
     *   - parents
     *   - children
     *   - continued-from
     *   - other
     *
     * @return array
     */
    public function getRelatedItems()
    {
        $record = $this->getXmlRecord();

        if (!isset($record->relations->relation)) {
            return [];
        }

        $relationMap = [
            'On jatkoa' => 'continued-from',
            'Sisältyy' => 'part-of',
            'Sisältää' => 'contains',
            'Katso myös' => 'see-also'
        ];

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
            $role = (string)$attr->arcrole;
            if (!isset($relationMap[$role])) {
                continue;
            }
            $role = $relationMap[$role];
            if (!isset($relations[$role])) {
                $relations[$role] = [];
            }
            $relations[$role][] = (string)$attr->href;
        }

        return $relations;
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
}
