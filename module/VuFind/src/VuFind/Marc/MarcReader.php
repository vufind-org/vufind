<?php
/**
 * MARC record reader class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020-2021.
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
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\Marc;

/**
 * MARC record reader class.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class MarcReader
{
    /**
     * Supported serialization formats
     *
     * @var array
     */
    protected $serializations = [
        'ISO2709' => Serialization\Iso2709::class,
        'MARCXML' => Serialization\MarcXml::class,
    ];

    /**
     * MARC leader
     *
     * @var string
     */
    protected $leader;

    /**
     * MARC is stored in a multidimensional array:
     *  [001] - "12345"
     *  [245] - i1: '0'
     *          i2: '1'
     *          s:  [
     *                  ['a' => 'Title'],
     *                  ['k' => 'Form'],
     *                  ['k' => 'Another'],
     *                  ['p' => 'Part'],
     *              ]
     *
     * @var array
     */
    protected $fields;

    /**
     * Constructor
     *
     * @param string $data MARC record in MARCXML or ISO2709 format
     */
    public function __construct($data)
    {
        $this->setData($data);
    }

    /**
     * Set MARC record data
     *
     * @param string $data MARC record in MARCXML or ISO2709 format
     *
     * @throws Exception
     * @return void
     */
    public function setData(string $data): void
    {
        $leader = null;
        $valid = false;
        foreach ($this->serializations as $serialization) {
            if ($serialization::canParse($data)) {
                [$leader, $this->fields] = $serialization::fromString($data);
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new \Exception('MARC record format not recognized');
        }
        // Make sure leader is 24 characters
        $this->leader = $leader ? str_pad(substr($leader, 0, 24), 24) : '';
    }

    /**
     * Serialize the record
     *
     * @param string $format Format to return (e.g. 'ISO2709' or 'MARCXML')
     *
     * @return string
     */
    public function toFormat(string $format): string
    {
        $serialization = $this->serializations[$format] ?? null;
        if (null === $serialization) {
            throw new \Exception("Unknown MARC format '$format' requested");
        }
        return $serialization::toString($this->getLeader(), $this->fields);
    }

    /**
     * Return leader
     *
     * @return string
     */
    public function getLeader(): string
    {
        return $this->leader;
    }

    /**
     * Return an associative array for a data field, a string for a control field or
     * an empty array if field does not exist
     *
     * @param string $fieldTag      The MARC field tag to get
     * @param array  $subfieldCodes The MARC subfield codes to get, or empty for all
     *
     * @return array|string
     */
    public function getField(string $fieldTag, ?array $subfieldCodes = null)
    {
        $results = $this->getFields($fieldTag, $subfieldCodes);
        return $results[0] ?? [];
    }

    /**
     * Return an associative array of fields for data fields or an array of values
     * for control fields
     *
     * @param string $fieldTag      The MARC field tag to get
     * @param array  $subfieldCodes The MARC subfield codes to get, or empty for all.
     * Ignored for control fields.
     *
     * @return array
     */
    public function getFields(string $fieldTag, ?array $subfieldCodes = null): array
    {
        $result = [];

        foreach ($this->fields[$fieldTag] ?? [] as $field) {
            if (!is_array($field)) {
                // Control field
                $result[] = $field;
                continue;
            }
            $subfields = [];
            foreach ($field['s'] ?? [] as $subfield) {
                if ($subfieldCodes
                    && !in_array((string)key($subfield), $subfieldCodes)
                ) {
                    continue;
                }
                $subfields[] = [
                    'code' => (string)key($subfield),
                    'data' => current($subfield),
                ];
            }
            if ($subfields) {
                $result[] = [
                    'tag' => $fieldTag,
                    'i1' => $field['i1'],
                    'i2' => $field['i2'],
                    'subfields' => $subfields
                ];
            }
        }

        return $result;
    }

    /**
     * Return first subfield with the given code in the MARC field provided by
     * getField or getFields
     *
     * @param array  $field        Result from MarcReader::getFields
     * @param string $subfieldCode The MARC subfield code to get
     *
     * @return string
     */
    public function getSubfield(array $field, string $subfieldCode): string
    {
        foreach ($field['subfields'] ?? [] as $current) {
            if ($current['code'] == $subfieldCode) {
                return trim($current['data']);
            }
        }

        return '';
    }

    /**
     * Return all subfields with the given code in the MARC field provided by
     * getField or getFields. Returns all subfields if subfieldCode is empty.
     *
     * @param array  $field        Result from MarcReader::getFields
     * @param string $subfieldCode The MARC subfield code to get
     *
     * @return array
     */
    public function getSubfields(array $field, string $subfieldCode = ''): array
    {
        $result = [];
        foreach ($field['subfields'] ?? [] as $current) {
            if ('' === $subfieldCode || $current['code'] == $subfieldCode) {
                $result[] = trim($current['data']);
            }
        }

        return $result;
    }

    /**
     * Return an array of all values extracted from the specified field/subfield
     * combination.  If multiple subfields and a separator are specified, the
     * subfields will be concatenated together in the order listed -- each entry in
     * the array will correspond with a single MARC field.  If $separator is null,
     * the return array will contain separate entries for all subfields.
     *
     * @param string $fieldTag      The MARC field tag to get
     * @param array  $subfieldCodes The MARC subfield codes to get
     * @param string $separator     Subfield separator string. Set to null to disable
     * concatenation of subfields.
     *
     * @return array
     */
    public function getFieldsSubfields(
        string $fieldTag,
        array $subfieldCodes,
        ?string $separator = ' '
    ): array {
        $result = [];

        foreach ($this->fields[$fieldTag] ?? [] as $field) {
            if (!isset($field['s'])) {
                continue;
            }
            $subfields = [];
            foreach ($field['s'] ?? [] as $subfield) {
                if ($subfieldCodes
                    && !in_array((string)key($subfield), $subfieldCodes)
                ) {
                    continue;
                }
                if (null !== $separator) {
                    $subfields[] = current($subfield);
                } else {
                    $result[] = current($subfield);
                }
            }
            if (null !== $separator && $subfields) {
                $result[] = implode($separator, $subfields);
            }
        }

        return $result;
    }

    /**
     * Return an associative array for a linked field such as 880 (Alternate Graphic
     * Representation) or an empty array if field does not exist
     *
     * @param string $fieldTag       The MARC field that contains the linked fields
     * @param string $linkedFieldTag The linked MARC field tag to get
     * @param string $occurrence     The occurrence number to get; empty string for
     * whatever comes first
     * @param array  $subfieldCodes  The MARC subfield codes to get, or empty for all
     *
     * @return array
     */
    public function getLinkedField(
        string $fieldTag,
        string $linkedFieldTag,
        string $occurrence = '',
        ?array $subfieldCodes = null
    ): array {
        $results
            = $this->getLinkedFields($fieldTag, $linkedFieldTag, $subfieldCodes);
        foreach ($results as $field) {
            if (empty($occurrence) || $occurrence === $field['link']['occurrence']) {
                return $field;
            }
        }
        return [];
    }

    /**
     * Return an array of associative arrays for a linked field such as 880
     * (Alternate Graphic Representation)
     *
     * @param string $fieldTag       The MARC field that contains the linked fields
     * @param string $linkedFieldTag The linked MARC field tag to get
     * @param array  $subfieldCodes  The MARC subfield codes to get, or empty for all
     *
     * @return array
     */
    public function getLinkedFields(
        string $fieldTag,
        string $linkedFieldTag,
        ?array $subfieldCodes = null
    ): array {
        $result = [];

        foreach ($this->fields[$fieldTag] ?? [] as $field) {
            if (!is_array($field)) {
                // Control field
                continue;
            }
            $link
                = $this->parseLinkageField($this->getInternalSubfield($field, '6'));
            if ($link['field'] !== $linkedFieldTag) {
                continue;
            }
            $subfields = [];
            foreach ($field['s'] ?? [] as $subfield) {
                if ($subfieldCodes
                    && !in_array((string)key($subfield), $subfieldCodes)
                ) {
                    continue;
                }
                $subfields[] = [
                    'code' => (string)key($subfield),
                    'data' => current($subfield),
                ];
            }
            if ($subfields) {
                $result[] = [
                    'tag' => $fieldTag,
                    'i1' => $field['i1'],
                    'i2' => $field['i2'],
                    'subfields' => $subfields,
                    'link' => $link
                ];
            }
        }

        return $result;
    }

    /**
     * Return an array of all values extracted from the specified linked
     * field/subfield combination.  If multiple subfields and a separator are
     * specified, the subfields will be concatenated together in the order listed
     * -- each entry in the array will correspond with a single MARC field.  If
     * $separator is null, the return array will contain separate entries for all
     * subfields.
     *
     * @param string $fieldTag       The MARC field that contains the linked fields
     * @param string $linkedFieldTag The linked MARC field tag to get
     * @param array  $subfieldCodes  The MARC subfield codes to get
     * @param string $separator      Subfield separator string. Set to null to
     * disable concatenation of subfields.
     *
     * @return array
     */
    public function getLinkedFieldsSubfields(
        string $fieldTag,
        string $linkedFieldTag,
        array $subfieldCodes,
        ?string $separator = ' '
    ): array {
        $result = [];
        foreach ($this->getLinkedFields($fieldTag, $linkedFieldTag, $subfieldCodes)
            as $field
        ) {
            $subfields = $this->getSubfields($field);
            if (null !== $separator) {
                $result[] = implode($separator, $subfields);
            } else {
                $result = array_merge($result, $subfields);
            }
        }
        return $result;
    }

    /**
     * Get linked field data from subfield 6
     *
     * @param array $field Field
     *
     * @return array
     */
    public function getFieldLink(array $field): array
    {
        return $this->parseLinkageField($this->getSubfield($field, '6'));
    }

    /**
     * Parse a linkage field
     *
     * @param string $link Linkage field
     *
     * @return array
     */
    public function parseLinkageField(string $link): array
    {
        $linkParts = explode('/', $link, 3);
        $targetParts = explode('-', $linkParts[0]);
        return [
            'field' => $targetParts[0],
            'occurrence' => $targetParts[1] ?? '',
            'script' => $linkParts[1] ?? '',
            'orientation' => $linkParts[2] ?? ''
        ];
    }

    /**
     * Return first subfield with the given code in the internal MARC field
     *
     * @param array  $field        Internal MARC field
     * @param string $subfieldCode The MARC subfield code to get
     *
     * @return string
     */
    protected function getInternalSubfield(
        array $field,
        string $subfieldCode
    ): string {
        foreach ($field['s'] ?? [] as $subfield) {
            if (key($subfield) == $subfieldCode) {
                return trim(current($subfield));
            }
        }
        return '';
    }
}
