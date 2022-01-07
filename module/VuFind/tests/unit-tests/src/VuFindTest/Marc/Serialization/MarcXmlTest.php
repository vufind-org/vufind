<?php
/**
 * MarcXml Serialization Test Class
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Marc\Serialization;

/**
 * MarcXml Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcXmlTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test rewind
     *
     * @return void
     */
    public function testRewindWithoutFile()
    {
        $this->expectExceptionMessage('Collection file not open');
        $class = new \VuFind\Marc\Serialization\MarcXml();
        $class->rewind();
    }

    /**
     * Test getNextRecord
     *
     * @return void
     */
    public function testGetNextRecordWithoutFile()
    {
        $this->expectExceptionMessage('Collection file not open');
        $class = new \VuFind\Marc\Serialization\MarcXml();
        $class->getNextRecord();
    }
}
