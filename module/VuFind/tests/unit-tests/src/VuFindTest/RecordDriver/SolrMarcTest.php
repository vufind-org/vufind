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
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\RecordDriver;

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SolrMarcTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test a record that used to be known to cause problems because of the way
     * its linking fields are set up.
     *
     * Note: while Bug2 below is named for consistency with VuFind 1.x, this is
     * named Bug1 simply to fill the gap. It's related to a problem that was
     * discovered later. See VUFIND-1034 in JIRA.
     *
     * @return void
     */
    public function testBug1()
    {
        $configArr = ['Record' => ['marc_links' => '760,765,770,772,774,773,775,777,780,785']];
        $config = new \Zend\Config\Config($configArr);
        $record = new \VuFind\RecordDriver\SolrMarc($config);
        $fixture = $this->loadRecordFixture('testbug1.json');
        $record->setRawData($fixture['response']['docs'][0]);
        $expected = [
            ['title' => 'note_785_1', 'value' => 'Bollettino della Unione matematica italiana', 'link' => ['type' => 'bib', 'value' => '000343528']],
            ['title' => 'note_785_1', 'value' => 'Bollettino della Unione matematica', 'link' => ['type' => 'bib', 'value' => '000343529']],
            ['title' => 'note_785_8', 'value' => 'Bollettino della Unione matematica italiana', 'link' => ['type' => 'bib', 'value' => '000394898']],
        ];
        $this->assertEquals($expected, $record->getAllRecordLinks());
    }

    /**
     * Test a record that used to be known to cause problems because of the way
     * series name was handled (the old "Bug2" test from VuFind 1.x).
     *
     * @return void
     */
    public function testBug2()
    {
        $record = new \VuFind\RecordDriver\SolrMarc();
        $fixture = $this->loadRecordFixture('testbug2.json');
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

    /**
     * Load a fixture file.
     *
     * @param string $file File to load from fixture directory.
     *
     * @return array
     */
    protected function loadRecordFixture($file)
    {
        return json_decode(
            file_get_contents(
                realpath(
                    VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/misc/' . $file
                )
            ),
            true
        );
    }
}