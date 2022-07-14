<?php
/**
 * MARC collection class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Marc;

/**
 * MARC collection class.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MarcCollection implements \Iterator
{
    /**
     * Supported serialization formats
     *
     * @var array
     */
    protected $serializations = [
        'ISO2709' => Serialization\Iso2709::class,
        'MARCXML' => Serialization\MarcXml::class,
        'JSON' => Serialization\MarcInJson::class,
    ];

    /**
     * Records in the collection
     *
     * @var array
     */
    protected $records = [];

    /**
     * Iteration position
     *
     * @var int
     */
    protected $position = 0;

    /**
     * Constructor
     *
     * @param string $data MARC record collection in MARCXML or ISO2709 format
     */
    public function __construct(string $data = '')
    {
        $this->setData($data);
    }

    /**
     * Set MARC record data
     *
     * @param string $data MARC record in MARCXML or ISO2709 format
     *
     * @throws \Exception
     * @return void
     */
    public function setData(string $data): void
    {
        $this->position = 0;
        $this->records = [];
        if (!$data) {
            return;
        }
        $valid = false;
        foreach ($this->serializations as $serialization) {
            if ($serialization::canParseCollection($data)) {
                $this->records = $serialization::collectionFromString($data);
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new \Exception('MARC collection format not recognized');
        }
    }

    /**
     * Iterator: Rewind to the beginning.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Iterator: Return current record.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return new MarcReader($this->records[$this->position]);
    }

    /**
     * Iterator: Return current key.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->position;
    }

    /**
     * Iterator: Advance to the next record.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Iterator: Check if current position is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->records[$this->position]);
    }
}
