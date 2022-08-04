<?php
/**
 * ISO2709 MARC exchange format support class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020-2022.
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
namespace VuFind\Marc\Serialization;

/**
 * ISO2709 exchange format support class.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class Iso2709 implements SerializationInterface
{
    public const SUBFIELD_INDICATOR = "\x1F";
    public const END_OF_FIELD = "\x1E";
    public const END_OF_RECORD = "\x1D";
    public const LEADER_LEN = 24;

    /**
     * Check if this class can parse the given MARC string
     *
     * @param string $marc MARC
     *
     * @return bool
     */
    public static function canParse(string $marc): bool
    {
        // A pretty naïve check, but it's enough to tell the different formats apart
        return ctype_digit(substr($marc, 0, 4));
    }

    /**
     * Parse MARCXML string
     *
     * @param string $marc MARCXML
     *
     * @throws Exception
     * @return array
     */
    public static function fromString(string $marc): array
    {
        $leader = substr($marc, 0, 24);
        $fields = [];
        $dataStart = 0 + (int)substr($marc, 12, 5);
        $dirLen = $dataStart - self::LEADER_LEN - 1;
        $invalid = false;

        $offset = 0;
        while ($offset < $dirLen) {
            $tag = substr($marc, self::LEADER_LEN + $offset, 3);
            $len = (int)substr($marc, self::LEADER_LEN + $offset + 3, 4);
            $dataOffset
                = (int)substr($marc, self::LEADER_LEN + $offset + 7, 5);

            $tagData = substr($marc, $dataStart + $dataOffset, $len);

            if (substr($tagData, -1, 1) == self::END_OF_FIELD) {
                $tagData = substr($tagData, 0, -1);
            } else {
                $invalid = true;
            }

            if (ctype_digit($tag) && $tag < 10) {
                $fields[$tag][] = $tagData;
            } else {
                $newField = [
                    'i1' => $tagData[0] ?? ' ',
                    'i2' => $tagData[1] ?? ' '
                ];
                $subfields = explode(
                    self::SUBFIELD_INDICATOR,
                    substr($tagData, 3)
                );
                foreach ($subfields as $subfield) {
                    if ('' === $subfield) {
                        continue;
                    }
                    $newField['s'][] = [
                        (string)$subfield[0] => substr($subfield, 1)
                    ];
                }
                $fields[$tag][] = $newField;
            }

            $offset += 12;
        }

        return [
            $leader,
            $fields,
            $invalid ? ['Invalid MARC record (end of field not found)'] : []
        ];
    }

    /**
     * Convert record to ISO2709 string
     *
     * @param string $leader Leader
     * @param array  $fields Record fields
     *
     * @return string
     */
    public static function toString(string $leader, array $fields): string
    {
        $directory = '';
        $data = '';
        $datapos = 0;
        foreach ($fields as $tag => $fields) {
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
        $leader = str_pad(substr($leader, 0, 24), 24);
        $dataStart = 24 + strlen($directory);
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
}
