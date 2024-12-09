<?php

/**
 * SolrMarc Record Driver Test Class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver\Response;

use VuFind\RecordDriver\Response\PublicationDetails;

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PublicationDetailsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getters
     *
     * @return void
     */
    public function testGetters()
    {
        $pd = new PublicationDetails('a', 'b', 'c');
        $this->assertEquals('a', $pd->getPlace());
        $this->assertEquals('b', $pd->getName());
        $this->assertEquals('c', $pd->getDate());
    }

    /**
     * Test __toString
     *
     * @return void
     */
    public function testToString()
    {
        $pd = new PublicationDetails('a', 'b', 'c');
        $this->assertEquals('a b c', $pd->__toString());

        $pd = new PublicationDetails('a', ' ', 'c');
        $this->assertEquals('a c', $pd->__toString());

        $pd = new PublicationDetails('a', ' ', ' ');
        $this->assertEquals('a', $pd->__toString());

        $pd = new PublicationDetails(' ', 'b', ' ');
        $this->assertEquals('b', $pd->__toString());

        $pd = new PublicationDetails(' ', ' ', 'c');
        $this->assertEquals('c', $pd->__toString());

        $pd = new PublicationDetails(' a ', ' b ', ' c ');
        $this->assertEquals('a b c', $pd->__toString());
    }
}
