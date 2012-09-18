<?php
/**
 * SolrMarc Record Driver Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
namespace VuFind\Test\RecordDriver;

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class SolrMarcTest extends \VuFind\Tests\TestCase
{
    /**
     * Test a record that used to be known to cause problems because of the way
     * series name was handled (the old "Bug2" test from VuFind 1.x).
     *
     * @return void
     */
    public function testBug2()
    {
    $fixture = json_decode(
        file_get_contents(
        realpath(
            VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/misc/testbug2.json'
        )
        ),
        true
    );

    $record = new \VuFind\RecordDriver\SolrMarc();
    $record->setRawData($fixture['response']['docs'][0]);

    $this->assertEquals(
        $record->getPrimaryAuthor(),
        'Vico, Giambattista, 1668-1744.'
    );
    $secondary = $record->getSecondaryAuthors();
    $this->assertEquals(count($secondary), 1);
    $this->assertTrue(in_array('Pandolfi, Claudia.', $secondary));
    $series = $record->getSeries();
    $this->assertEquals(count($series), 1);
    $this->assertEquals(
        'Vico, Giambattista, 1668-1744. Works. 1982 ;', $series[0]['name']
    );
    $this->assertEquals('2, pt. 1.', $series[0]['number']);
    }
}