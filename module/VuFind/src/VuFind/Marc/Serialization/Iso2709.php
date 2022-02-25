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
class Iso2709 extends AbstractSerializationFile implements SerializationInterface
{
    public const SUBFIELD_INDICATOR = "\x1F";
    public const END_OF_FIELD = "\x1E";
    public const END_OF_RECORD = "\x1D";
    public const LEADER_LEN = 24;
    public const MAX_LENGTH = 99999;

    /**
     * Serialized record file handle
     *
     * @var resource
     */
    protected $file = null;

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
     * Check if the serialization class can parse the given MARC collection string
     *
     * @param string $marc MARC
     *
     * @return bool
     */
    public static function canParseCollection(string $marc): bool
    {
        // A pretty naïve check, but it's enough to tell the different formats apart
        return ctype_digit(substr($marc, 0, 5));
    }

    /**
     * Check if the serialization class can parse the given MARC collection file
     *
     * @param string $file File name
     *
     * @return bool
     */
    public static function canParseCollectionFile(string $file): bool
    {
        if (false === ($f = fopen($file, 'rb'))) {
            throw new \Exception("Cannot open file '$file' for reading");
        }
        $s = '';
        do {
            $s .= fgets($f, 10);
        } while (strlen(ltrim($s)) < 5 && !feof($f));
        fclose($f);

        return self::canParseCollection($s);
    }

    /**
     * Parse MARC collection from a string into an array
     *
     * @param string $collection MARC record collection in the format supported by
     * the serialization class
     *
     * @throws \Exception
     * @return array
     */
    public static function collectionFromString(string $collection): array
    {
        return array_slice(
            array_map(
                function ($record) {
                    // Clean up any extra characters between records and append an
                    // end-of-record marker lost in explode:
                    return ltrim($record, "\x00\x0a\x0d") . self::END_OF_RECORD;
                },
                explode(self::END_OF_RECORD, $collection)
            ),
            0,
            -1
        );
    }

    /**
     * Parse an ISO2709 string
     *
     * @param string $marc ISO2709
     *
     * @throws \Exception
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
                $fields[] = [$tag => $tagData];
            } else {
                $newField = [
                    'ind1' => $tagData[0] ?? ' ',
                    'ind2' => $tagData[1] ?? ' '
                ];
                $subfields = explode(
                    self::SUBFIELD_INDICATOR,
                    substr($tagData, 3)
                );
                foreach ($subfields as $subfield) {
                    if ('' === $subfield) {
                        continue;
                    }
                    $newField['subfields'][] = [
                        (string)$subfield[0] => substr($subfield, 1)
                    ];
                }
                $fields[] = [$tag => $newField];
            }

            $offset += 12;
        }

        $result = compact('leader', 'fields');
        if ($invalid) {
            $result['warnings'] = ['Invalid MARC record (end of field not found)'];
        }
        return $result;
    }

    /**
     * Convert record to an ISO2709 string
     *
     * @param array $record Record data
     *
     * @return string
     */
    public static function toString(array $record): string
    {
        $directory = '';
        $data = '';
        $datapos = 0;
        foreach ($record['fields'] as $fieldData) {
            $tag = (string)key($fieldData);
            $field = current($fieldData);
            if (is_array($field)) {
                $fieldStr = str_pad(substr($field['ind1'], 0, 1), 1)
                    . str_pad(substr($field['ind2'], 0, 1), 1);
                foreach ((array)($field['subfields'] ?? []) as $subfield) {
                    $subfieldCode = (string)key($subfield);
                    $fieldStr .= self::SUBFIELD_INDICATOR
                        . $subfieldCode . current($subfield);
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
        $directory .= self::END_OF_FIELD;
        $data .= self::END_OF_RECORD;
        $leader = str_pad(substr($record['leader'], 0, 24), 24);
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

    /**
     * Open a collection file
     *
     * @param string $file File name
     *
     * @return void
     */
    public function openCollectionFile(string $file): void
    {
        if (false === ($this->file = fopen($file, 'rb'))) {
            throw new \Exception("Cannot open file '$file' for reading");
        }
    }

    /**
     * Rewind the collection file
     *
     * @return void;
     */
    public function rewind(): void
    {
        if (null === $this->file) {
            throw new \Exception('Collection file not open');
        }
        rewind($this->file);
    }

    /**
     * Get next record from the file or an empty string on EOF
     *
     * @return string
     */
    public function getNextRecord(): string
    {
        if (null === $this->file) {
            throw new \Exception('Collection file not open');
        }
        $record = ltrim(
            stream_get_line(
                $this->file,
                self::MAX_LENGTH,
                self::END_OF_RECORD
            ),
            "\x00\x0a\x0d"
        );

        return $record ? ($record . self::END_OF_RECORD) : '';
    }
}
