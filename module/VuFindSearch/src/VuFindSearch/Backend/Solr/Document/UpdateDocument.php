<?php

/**
 * SOLR update document class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Solr\Document;

use VuFindSearch\Backend\Solr\Record\SerializableRecordInterface;
use SplObjectStorage;
use XMLWriter;

/**
 * SOLR update document class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class UpdateDocument extends AbstractDocument
{
    /**
     * Records and index attributes.
     *
     * @var SplObjectStorage
     */
    protected $records;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->records = new SplObjectStorage();
    }

    /**
     * Return serialized JSON representation.
     *
     * @return string
     */
    public function asJSON()
    {
        // @todo Implement
    }

    /**
     * Return serialized XML representation.
     *
     * @return string
     */
    public function asXML()
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument();
        $writer->startElement('add');
        foreach ($this->records as $record) {
            $writer->startElement('doc');
            $indexAttr = $this->records->offsetGet($record);
            foreach ($indexAttr as $name => $value) {
                $writer->writeAttribute($name, $value);
            }
            foreach ($record->getFields() as $name => $values) {
                $values = is_array($values) ? $values : [$values];
                foreach ($values as $value) {
                    $writer->startElement('field');
                    $writer->writeAttribute('name', $name);
                    $writer->text($value);
                    $writer->endElement();
                }
            }
            $writer->endElement();
        }
        $writer->endElement();
        $writer->endDocument();
        return $writer->flush();
    }

    /**
     * Add record.
     *
     * @param SerializableRecordInterface $record    Record
     * @param array                       $indexAttr Index attributes
     *
     * @return void
     */
    public function addRecord(SerializableRecordInterface $record,
        array $indexAttr = []
    ) {
        $this->records->attach($record, $indexAttr);
    }

}