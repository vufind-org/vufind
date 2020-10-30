<?php
/**
 * MARC record reader class.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * MARC record reader class.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class MarcReader
{
    const SUBFIELD_INDICATOR = "\x1F";
    const END_OF_FIELD = "\x1E";
    const END_OF_RECORD = "\x1D";
    const LEADER_LEN = 24;

    const GET_NORMAL = 0;
    const GET_ALT = 1;
    const GET_BOTH = 2;

    /**
     * MARC is stored in a multidimensional array:
     *  [001] - "12345"
     *  [245] - i1: '0'
     *          i2: '1'
     *          s:  [{a => "Title"},
     *               {p => "Part"}
     *              ]
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
     * @return void
     */
    public function setData($data)
    {
        if (strncmp($data, '<', 1) === 0) {
            $this->parseXML($data);
        } else {
            $this->parseISO2709($data);
        }
    }

    /**
     * Serialize the record into XML
     *
     * @return string
     */
    public function toXML()
    {
        $xml = simplexml_load_string(
            "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n\n"
            . "<collection><record></record></collection>"
        );
        $record = $xml->record[0];

        if ($leader = $this->getLeader()) {
            $record->addChild('leader', $leader);
        }

        foreach ($this->fields as $tag => $fields) {
            if ($tag == '000') {
                continue;
            }
            foreach ($fields as $data) {
                if (!is_array($data)) {
                    $field = $record->addChild(
                        'controlfield', htmlspecialchars($data, ENT_NOQUOTES)
                    );
                    $field->addAttribute('tag', $tag);
                } else {
                    $field = $record->addChild('datafield');
                    $field->addAttribute('tag', $tag);
                    $field->addAttribute('ind1', $data['i1']);
                    $field->addAttribute('ind2', $data['i2']);
                    if (isset($data['s'])) {
                        foreach ($data['s'] as $subfield) {
                            $subfieldData = current($subfield);
                            $subfieldCode = key($subfield);
                            if ($subfieldData == '') {
                                continue;
                            }
                            $subfield = $field->addChild(
                                'subfield',
                                htmlspecialchars($subfieldData, ENT_NOQUOTES)
                            );
                            $subfield->addAttribute('code', $subfieldCode);
                        }
                    }
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Convert to ISO2709.
     *
     * Returns an empty string if record is too long.
     *
     * @return string
     */
    public function toISO2709()
    {
        $leader = $this->getLeader();

        $directory = '';
        $data = '';
        $datapos = 0;
        foreach ($this->fields as $tag => $fields) {
            if ($tag == '000') {
                continue;
            }
            if (strlen($tag) != 3) {
                continue;
            }
            foreach ($fields as $field) {
                if (is_array($field)) {
                    $fieldStr = $field['i1'] . $field['i2'];
                    if (isset($field['s']) && is_array($field['s'])) {
                        foreach ($field['s'] as $subfield) {
                            $subfieldCode = key($subfield);
                            $fieldStr .= self::SUBFIELD_INDICATOR
                                . $subfieldCode . current($subfield);
                        }
                    }
                } else {
                    $fieldStr = $field;
                }
                $fieldStr .= self::END_OF_FIELD;
                $len = strlen($fieldStr);
                if ($len > 9999) {
                    return '';
                }
                if ($datapos > 99999) {
                    return '';
                }
                $directory .= $tag . str_pad($len, 4, '0', STR_PAD_LEFT)
                    . str_pad($datapos, 5, '0', STR_PAD_LEFT);
                $datapos += $len;
                $data .= $fieldStr;
            }
        }
        $directory .= self::END_OF_FIELD;
        $data .= self::END_OF_RECORD;
        $dataStart = strlen($leader) + strlen($directory);
        $recordLen = $dataStart + strlen($data);
        if ($recordLen > 99999) {
            return '';
        }

        $leader = str_pad($recordLen, 5, '0', STR_PAD_LEFT)
            . substr($leader, 5, 7)
            . str_pad($dataStart, 5, '0', STR_PAD_LEFT)
            . substr($leader, 17);
        return $leader . $directory . $data;
    }

    /**
     * Return leader
     *
     * @return string
     */
    public function getLeader()
    {
        // Make sure leader is 24 characters. E.g. Voyager is often missing the last
        // '0' of the leader.
        return isset($this->fields['000'])
            ? str_pad(substr($this->fields['000'], 0, 24), 24) : '';
    }

    /**
     * Return an associative array for a data field, a string for a control field or
     * false if field does not exist
     *
     * @param string $fieldTag      The MARC field tag to get
     * @param array  $subfieldCodes The MARC subfield codes to get, or empty for all
     *
     * @return array|string|false
     */
    public function getField($fieldTag, $subfieldCodes = null)
    {
        $results = $this->getFields($fieldTag, $subfieldCodes);
        return $results[0] ?? false;
    }

    /**
     * Return an associative array of fields for data fields or an array of values
     * for control fields
     *
     * @param string $fieldTag      The MARC field tag to get
     * @param array  $subfieldCodes The MARC subfield codes to get, or empty for all
     * Ignored for control fields.
     *
     * @return array
     */
    public function getFields($fieldTag, $subfieldCodes = null)
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
                    'code' => key($subfield),
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
     * Return an array of all values extracted from the specified field/subfield
     * combination.  If multiple subfields are specified and $concat is true, they
     * will be concatenated together in the order listed -- each entry in the array
     * will correspond with a single MARC field.  If $concat is false, the return
     * array will contain separate entries for separate subfields.
     *
     * @param string $fieldTag      The MARC field tag to get
     * @param array  $subfieldCodes The MARC subfield codes to get
     * @param bool   $concat        Should we concatenate subfields?
     * @param string $separator     Separator string (used only if $concat === true)
     *
     * @return array
     */
    public function getFieldsSubfields($fieldTag, $subfieldCodes, $concat = true,
        $separator = ' '
    ) {
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
                if ($concat) {
                    $subfields[] = current($subfield);
                } else {
                    $result[] = current($subfield);
                }
            }
            if ($concat && $subfields) {
                $result[] = implode($separator, $subfields);
            }
        }

        return $result;
    }

    /**
     * Return first subfield with the given code in the provided MARC field
     *
     * @param array  $field        Result from MarcReader::getFields
     * @param string $subfieldCode The MARC subfield code to get
     *
     * @return string
     */
    public function getSubfield($field, $subfieldCode)
    {
        foreach ($field['subfields'] as $current) {
            if ($current['code'] == $subfieldCode) {
                return trim($current['data']);
            }
        }

        return '';
    }

    /**
     * Return all subfields with the given code in the provided MARC field
     *
     * @param array  $field        Result from MarcReader::getFields
     * @param string $subfieldCode The MARC subfield code to get
     *
     * @return array
     */
    public function getSubfields($field, $subfieldCode)
    {
        $result = [];
        foreach ($field['subfields'] as $current) {
            if ($current['code'] == $subfieldCode) {
                $result[] = trim($current['data']);
            }
        }

        return $result;
    }

    /**
     * Parse MARCXML
     *
     * @param string $marc MARCXML
     *
     * @throws Exception
     * @return void
     */
    protected function parseXML($marc)
    {
        $xmlHead = '<?xml version';
        if (strcasecmp(substr($marc, 0, strlen($xmlHead)), $xmlHead) === 0) {
            $decl = substr($marc, 0, strpos($marc, '?>'));
            if (strstr($decl, 'encoding') === false) {
                $marc = $decl . ' encoding="utf-8"' . substr($marc, strlen($decl));
            }
        } else {
            $marc = '<?xml version="1.0" encoding="utf-8"?>' . "\n\n$marc";
        }
        $xml = $this->loadXML($marc);

        // Move to the record element if we were given a collection
        if ($xml->record) {
            $xml = $xml->record;
        }

        $this->fields['000'] = isset($xml->leader) ? (string)$xml->leader[0] : '';

        foreach ($xml->controlfield as $field) {
            $tag = (string)$field['tag'];
            if ('000' === $tag) {
                continue;
            }
            $this->fields[$tag][] = (string)$field;
        }

        foreach ($xml->datafield as $field) {
            $newField = [
                'i1' => str_pad((string)$field['ind1'], 1),
                'i2' => str_pad((string)$field['ind2'], 1)
            ];
            foreach ($field->subfield as $subfield) {
                $newField['s'][] = [(string)$subfield['code'] => (string)$subfield];
            }
            $this->fields[(string)$field['tag']][] = $newField;
        }
    }

    /**
     * Load XML into SimpleXMLElement
     *
     * @param string $xml XML
     *
     * @return \SimpleXMLElement
     */
    protected function loadXML($xml)
    {
        $saveUseErrors = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $doc = \simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_COMPACT);
            if (false === $doc) {
                $errors = libxml_get_errors();
                $messageParts = [];
                foreach ($errors as $error) {
                    $messageParts[] = '[' . $error->line . ':' . $error->column
                        . '] Error ' . $error->code . ': ' . $error->message;
                }
                throw new \Exception(implode("\n", $messageParts));
            }
            libxml_use_internal_errors($saveUseErrors);
            return $doc;
        } catch (\Exception $e) {
            libxml_use_internal_errors($saveUseErrors);
            throw $e;
        }
    }

    /**
     * Parse ISO2709 exchange format
     *
     * @param string $marc ISO2709 string
     *
     * @throws Exception
     * @return void
     */
    protected function parseISO2709($marc)
    {
        // When indexing over HTTP, SolrMarc may use entities instead of
        // certain control characters; we should normalize these:
        $marc = str_replace(
            ['#29;', '#30;', '#31;'], ["\x1D", "\x1E", "\x1F"], $marc
        );

        $this->fields['000'] = substr($marc, 0, 24);
        $dataStart = 0 + (int)substr($marc, 12, 5);
        $dirLen = $dataStart - self::LEADER_LEN - 1;

        $offset = 0;
        while ($offset < $dirLen) {
            $tag = substr($marc, self::LEADER_LEN + $offset, 3);
            $len = substr($marc, self::LEADER_LEN + $offset + 3, 4);
            $dataOffset
                = (int)substr($marc, self::LEADER_LEN + $offset + 7, 5);

            $tagData = substr($marc, $dataStart + $dataOffset, $len);

            if (substr($tagData, -1, 1) == self::END_OF_FIELD) {
                $tagData = substr($tagData, 0, -1);
            } else {
                throw new \Exception(
                    "Invalid MARC record (end of field not found): $marc"
                );
            }

            if (strstr($tagData, self::SUBFIELD_INDICATOR)) {
                $newField = [
                    'i1' => $tagData[0],
                    'i2' => $tagData[1]
                ];
                $subfields = explode(
                    self::SUBFIELD_INDICATOR, substr($tagData, 3)
                );
                foreach ($subfields as $subfield) {
                    $newField['s'][] = [
                        (string)$subfield[0] => substr($subfield, 1)
                    ];
                }
                $this->fields[$tag][] = $newField;
            } else {
                $this->fields[$tag][] = $tagData;
            }

            $offset += 12;
        }
    }
}
