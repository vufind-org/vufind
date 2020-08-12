<?php
/**
 * Model for Marc authority records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

use Finna\Util\MetadataUtils;

/**
 * Model for Forward authority records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrAuthMarc extends \VuFind\RecordDriver\SolrAuthMarc
{
    use MarcReaderTrait;
    use SolrCommonFinnaTrait;
    use SolrAuthFinnaTrait {
        getFormats as _getFormats;
    }

    /**
     * Return corporate record type.
     *
     * @return string
     */
    public function getCorporateType()
    {
        foreach ($this->getMarcRecord()->getFields('368') as $field) {
            if ($res = $field->getSubfield('a')) {
                return MetadataUtils::ucFirst($res->getData());
            }
        }
        return '';
    }

    /**
     * Return relations to other authority records.
     *
     * @return array
     */
    public function getRelations()
    {
        $result = [];
        foreach (['500', '510'] as $code) {
            foreach ($this->getMarcRecord()->getFields($code) as $field) {
                $id = $field->getSubfield('0');
                $name = $field->getSubfield('a');
                $role = $field->getSubfield('i');
                if (empty($role)) {
                    $role = $field->getSubfield('b');
                }
                $role = $role
                    ? $this->stripTrailingPunctuation($role->getData(), ': ')
                    : null;
                if (!$name || !$id) {
                    continue;
                }
                $id = $id->getData();
                $result[] = [
                    'id' => $id,
                    'name' =>
                        $this->stripTrailingPunctuation($name->getData(), '. '),
                    'role' => $role,
                    'type' => $code === '500' ? 'Personal Name' : 'Corporate Name'
                ];
            }
        }
        return $result;
    }

    /**
     * Return additional information.
     *
     * @return string
     */
    public function getAdditionalInformation()
    {
        foreach ($this->getMarcRecord()->getFields('680') as $field) {
            if ($res = $field->getSubfield('i')) {
                return $res->getData();
            }
        }
        return '';
    }

    /**
     * Return birth date.
     *
     * @param boolean $force Return established date for corporations?
     *
     * @return string
     */
    public function getBirthDate($force = false)
    {
        foreach ($this->getMarcRecord()->getFields('046') as $field) {
            if ($res = $field->getSubfield('f')) {
                return $this->formatDate($res->getData());
            }
        }
        return '';
    }

    /**
     * Return death date.
     *
     * @param boolean $force Return terminated date for corporations?
     *
     * @return string
     */
    public function getDeathDate($force = false)
    {
        foreach ($this->getMarcRecord()->getFields('046') as $field) {
            if ($res = $field->getSubfield('g')) {
                return $this->formatDate($res->getData());
            }
        }
        return '';
    }

    /**
     * Return historical information
     *
     * @return array
     */
    public function getHistory()
    {
        $result = [];
        foreach ($this->getMarcRecord()->getFields('678') as $field) {
            if ($subfield = $field->getSubfield('a')) {
                $result[] = $subfield->getData();
            }
        }

        return $result;
    }

    /**
     * Return authority data sources.
     *
     * @return array|null
     */
    public function getSources()
    {
        $result = [];
        foreach ($this->getMarcRecord()->getFields('670') as $field) {
            if (!$title = $field->getSubfield('a')) {
                continue;
            }
            $title = $title->getData();
            $subtitle = null;
            $setting = 'authority_external_link_label_regex';
            $regex
                = $this->datasourceSettings[$this->getDatasource()][$setting]
                ?? null;

            if ($regex && preg_match($regex, $title, $matches)) {
                $title = $matches[1];
                $subtitle = $matches[2] ?? null;
            }
            $url = $field->getSubfield('u');
            $info = $field->getSubfield('b');
            $result[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'info' => $info ? $info->getData() : null,
                'url' => $url ? $url->getData() : null
            ];
        }
        return $result;
    }

    /**
     * Get an array of alternative names for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $result = [];
        foreach (['400', '410'] as $fieldCode) {
            foreach ($this->getMarcRecord()->getFields($fieldCode) as $field) {
                if ($subfield = $field->getSubfield('a')) {
                    $data = rtrim($subfield->getData(), ', ');
                    $detail = null;
                    if ($date = $field->getSubfield('d')) {
                        $detail = $date->getData();
                    }
                    $result[] = compact('data', 'detail');
                }
            }
        }
        return $result;
    }

    /**
     * Return associated place.
     *
     * @return string|null
     */
    public function getAssociatedPlace()
    {
        return $this->fields['country'] ?? '';
    }

    /**
     * Return related places.
     *
     * @return array
     */
    public function getRelatedPlaces()
    {
        $result = [];
        foreach ($this->getMarcRecord()->getFields('370') as $field) {
            $data = $this->getFieldSubfields($field, ['e','f','s','t'], false);
            if ($place = $data['e'] ?? $data['f'] ?? null) {
                $startYear = $data['s'] ?? null;
                $endYear = $data['t'] ?? null;
                $date = null;
                if ($startYear !== null && $endYear !== null) {
                    $date = "{$startYear}-{$endYear}";
                } elseif ($startYear !== null) {
                    $date = "$startYear-";
                } elseif ($endYear !== null) {
                    $date = "-{$endYear}";
                }
                $result[] = ['data' => $place, 'detail' => $date];
            }
        }
        return $result;
    }

    /**
     * Get additional identifiers (isni etc).
     *
     * @return array
     */
    public function getOtherIdentifiers()
    {
        $result = [];
        foreach ($this->getMarcRecord()->getFields('024') as $field) {
            $data = $this->getFieldSubfields($field, ['a','2','q'], false);
            if ($id = ($data['a'] ?? null)) {
                $type = null;
                if ($type = $data['2'] ?? $data['q']) {
                    $type = mb_strtolower(rtrim($type, ': '), 'UTF-8');
                }
                $result[] = ['data' => $id, 'detail' => $type];
            }
        }
        return $result;
    }

    /**
     * Format date
     *
     * @param string $date   Date
     * @param string $format Format of converted date
     *
     * @return string
     */
    protected function formatDate($date, $format = 'd-m-Y')
    {
        if (!$this->dateConverter) {
            return $date;
        }
        try {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
                return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
            } elseif (preg_match('/^(\d{4})$/', $date)) {
                return $this->dateConverter->convertFromDisplayDate(
                    'Y',
                    $this->dateConverter->convertToDisplayDate('Y', $date)
                );
            } else {
                return $date;
            }
        } catch (\Exception $e) {
            return $date;
        }
    }
}
