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
        foreach ($this->getMarcReader()->getFields('368') as $field) {
            if ($res = $this->getSubfield($field, 'a')) {
                return MetadataUtils::ucFirst($res);
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
            foreach ($this->getMarcReader()->getFields($code) as $field) {
                $id = $this->getSubfield($field, '0');
                $name = $this->getSubfield($field, 'a');
                $role = $this->getSubfield($field, 'i');
                if (empty($role)) {
                    $role = $this->getSubfield($field, 'b');
                }
                $role = $role
                    ? $this->stripTrailingPunctuation($role, ': ')
                    : null;
                if (!$name || !$id) {
                    continue;
                }
                $result[] = [
                    'id' => $id,
                    'name' => $this->stripTrailingPunctuation($name, '. '),
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
        foreach ($this->getMarcReader()->getFields('680') as $field) {
            if ($res = $this->getSubfield($field, 'i')) {
                return $res;
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
        foreach ($this->getMarcReader()->getFields('046') as $field) {
            if ($res = $this->getSubfield($field, 'f')) {
                return $this->formatDate($res);
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
        foreach ($this->getMarcReader()->getFields('046') as $field) {
            if ($res = $this->getSubfield($field, 'g')) {
                return $this->formatDate($res);
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
        foreach ($this->getMarcReader()->getFields('678') as $field) {
            if ($subfield = $this->getSubfield($field, 'a')) {
                $result[] = $subfield;
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
        foreach ($this->getMarcReader()->getFields('670') as $field) {
            if (!$title = $this->getSubfield($field, 'a')) {
                continue;
            }
            $subtitle = null;
            $setting = 'authority_external_link_label_regex';
            $regex
                = $this->datasourceSettings[$this->getDatasource()][$setting]
                ?? null;

            if ($regex && preg_match($regex, $title, $matches)) {
                $title = $matches[1];
                $subtitle = $matches[2] ?? null;
            }
            $url = $this->getSubfield($field, 'u');
            $info = $this->getSubfield($field, 'b');
            $result[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'info' => $info ?: null,
                'url' => $url ?: null
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
            foreach ($this->getMarcReader()->getFields($fieldCode) as $field) {
                if ($subfield = $this->getSubfield($field, 'a')) {
                    $data = rtrim($subfield, ', ');
                    $detail = null;
                    if ($date = $this->getSubfield($field, 'd')) {
                        $detail = $date;
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
     * Return associated groups.
     *
     * @return array
     */
    public function getAssociatedGroups()
    {
        $result = [];
        foreach ($this->getMarcReader()->getFields('373') as $field) {
            if ($groups = ($this->getSubFieldArray($field, ['a'], false))) {
                if (count($groups) > 1) {
                    $result = array_merge(
                        array_map(
                            function ($group) {
                                return ['data' => $group];
                            },
                            $groups
                        ),
                        $result
                    );
                } else {
                    $start = $this->getSubfield($field, 's');
                    $end = $this->getSubfield($field, 't');
                    $detail = "$start-$end";
                    $result[] = ['data' => $groups[0], 'detail' => $detail];
                }
            }
        }
        return $result;
    }

    /**
     * Return related places.
     *
     * @return array
     */
    public function getRelatedPlaces()
    {
        $result = [];
        foreach ($this->getMarcReader()->getFields('370') as $field) {
            $place = $this->getSubfield($field, 'e')
                ?: $this->getSubfield($field, 'f');
            if ($place) {
                $startYear = $this->getSubfield($field, 's') ?: null;
                $endYear = $this->getSubfield($field, 't') ?: null;
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
        foreach ($this->getMarcReader()->getFields('024') as $field) {
            if ($id = ($this->getSubfield($field, 'a') ?: null)) {
                $type = $this->getSubfield($field, '2')
                    ?: $this->getSubfield($field, 'q');
                if ($type) {
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
