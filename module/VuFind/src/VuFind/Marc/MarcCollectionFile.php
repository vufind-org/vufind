<?php
/**
 * MARC collection class for streaming a file.
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Marc;

/**
 * MARC collection class for streaming a file.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MarcCollectionFile implements \Iterator
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
     * Serialized format stream
     *
     * @var Serialization\SerializationFileInterface
     */
    protected $stream;

    /**
     * Iteration position
     *
     * @var int
     */
    protected $position = 0;

    /**
     * Current record
     *
     * @var string
     */
    protected $record = '';

    /**
     * Constructor
     *
     * @param string $file MARC record collection file in MARCXML or ISO2709 format
     */
    public function __construct(string $file = '')
    {
        $this->setFile($file);
    }

    /**
     * Set MARC record file
     *
     * @param string $file MARC record collection file in MARCXML or ISO2709 format
     *
     * @throws \Exception
     * @return void
     */
    public function setFile(string $file): void
    {
        if (!file_exists($file)) {
            throw new \Exception("File '$file' does not exist");
        }
        $this->position = 0;
        $this->record = null;
        foreach ($this->serializations as $serialization) {
            if ($serialization::canParseCollectionFile($file)) {
                $this->stream = new $serialization();
                $this->stream->openCollectionFile($file);
                return;
            }
        }
        throw new \Exception('MARC collection file format not recognized');
    }

    /**
     * Iterator: Rewind to the beginning.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->stream->rewind();
        $this->position = 0;
        $this->record = null;
    }

    /**
     * Iterator: Return current record.
     *
     * @return mixed
     */
    public function current()
    {
        if (null === $this->record) {
            $this->record = $this->stream->getNextRecord();
        }
        return $this->record ? new MarcReader($this->record) : null;
    }

    /**
     * Iterator: Return current key.
     *
     * @return mixed
     */
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
        $this->record = null;
    }

    /**
     * Iterator: Check if current position is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        if (null === $this->record) {
            $this->record = $this->stream->getNextRecord();
        }
        return '' !== $this->record;
    }
}
