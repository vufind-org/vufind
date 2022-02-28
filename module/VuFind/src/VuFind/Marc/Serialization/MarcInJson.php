<?php
/**
 * MARC-in-JSON format support class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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

use pcrov\JsonReader\JsonReader;

/**
 * MARC-in-JSON format support class.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class MarcInJson extends AbstractSerializationFile implements SerializationInterface
{
    /**
     * Current file
     *
     * @var string
     */
    protected $fileName = '';

    /**
     * JSON Reader for current file
     *
     * @var JsonReader
     */
    protected $reader = null;

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
        return substr(trim($marc), 0, 1) === '{';
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
        return substr(trim($marc), 0, 1) === '[';
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
        return json_decode($collection, true);
    }

    /**
     * Parse MARC-in-JSON
     *
     * @param string $marc JSON
     *
     * @throws \Exception
     * @return array
     */
    public static function fromString(string $marc): array
    {
        return json_decode($marc, true);
    }

    /**
     * Convert record to ISO2709 string
     *
     * @param array $record Record data
     *
     * @return string
     */
    public static function toString(array $record): string
    {
        return self::jsonEncode($record);
    }

    /**
     * Open a collection file
     *
     * @param string $file File name
     *
     * @return void
     *
     * @throws \Exception
     */
    public function openCollectionFile(string $file): void
    {
        $this->fileName = $file;
        $this->reader = new JsonReader();
        $this->reader->open($file);
        // Move into the record array:
        $this->reader->read();
    }

    /**
     * Rewind the collection file
     *
     * @return void
     *
     * @throws \Exception
     */
    public function rewind(): void
    {
        if ('' === $this->fileName) {
            throw new \Exception('Collection file not open');
        }
        $this->openCollectionFile($this->fileName);
    }

    /**
     * Get next record from the file or an empty string on EOF
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getNextRecord(): string
    {
        if (null === $this->reader) {
            throw new \Exception('Collection file not open');
        }
        // We have to rely on the depth since the elements are anonymous:
        if ($this->reader->depth() === 0) {
            // Level 0 is the array enclosing the record objects, read into it:
            $this->reader->read();
        } else {
            // Level 1 is an object, get the next one:
            $this->reader->next();
        }
        $value = $this->reader->value();
        return $value ? self::jsonEncode($value) : '';
    }

    /**
     * Convert a record array to a JSON string
     *
     * @param array $record Record
     *
     * @return string
     */
    protected static function jsonEncode(array $record): string
    {
        // We need to cast any subfield with '0' as key to an object; otherwise it
        // would be encoded as a simple array instead of an object:
        foreach ($record['fields'] as &$fieldData) {
            $field = current($fieldData);
            if (!is_array($field)) {
                continue;
            }
            foreach ($field['subfields'] as &$subfield) {
                if (key($subfield) == 0) {
                    $subfield = (object)$subfield;
                }
            }
            unset($subfield);
            $fieldData = [key($fieldData) => $field];
        }
        unset($fieldData);
        return json_encode($record, JSON_UNESCAPED_UNICODE);
    }
}
