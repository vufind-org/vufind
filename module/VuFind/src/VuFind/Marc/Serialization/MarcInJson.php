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
     * Current collection file
     *
     * @var array
     */
    protected $collection = null;

    /**
     * Current position in collection file
     *
     * @var int
     */
    protected $position = 0;

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
        // We need to cast any subfield with '0' as key to an object to, otherwise
        // it would be encoded as a simple array instead of an object:
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

    /**
     * Open a collection file
     *
     * @param string $file File name
     *
     * @return void
     */
    public function openCollectionFile(string $file): void
    {
        $data = file_get_contents($file);
        if (false === $data) {
            throw new \Exception("Cannot open file '$file' for reading");
        }
        $this->collection = json_decode($data, true);
        if (null === $this->collection) {
            throw
                new \Exception("File '$file' is invalid: " . json_last_error_msg());
        }
        $this->position = -1;
    }

    /**
     * Rewind the collection file
     *
     * @return void;
     */
    public function rewind(): void
    {
        if (null === $this->collection) {
            throw new \Exception('Collection not available');
        }
        $this->position = -1;
    }

    /**
     * Get next record from the file or an empty string on EOF
     *
     * @return string
     */
    public function getNextRecord(): string
    {
        ++$this->position;
        return isset($this->collection[$this->position])
            ? self::toString($this->collection[$this->position]) : '';
    }
}
