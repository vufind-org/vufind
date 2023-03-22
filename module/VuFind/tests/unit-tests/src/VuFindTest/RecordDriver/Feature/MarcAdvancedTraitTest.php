<?php

/**
 * Record Driver Marc Advanced Trait Test Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021.
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

namespace VuFindTest\RecordDriver\Feature;

use VuFind\RecordDriver\SolrMarc;

/**
 * Record Driver Marc Advanced Trait Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcAdvancedTraitTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get a mock record driver from a MARC fixture.
     *
     * @param string $fixture Fixture filename
     *
     * @return SolrMarc
     */
    protected function getMockDriverFromFixture(string $fixture): SolrMarc
    {
        $record = new \VuFind\Marc\MarcReader($this->getFixture($fixture));
        $obj = $this->getMockBuilder(SolrMarc::class)
            ->onlyMethods(['getMarcReader'])->getMock();
        $obj->expects($this->any())
            ->method('getMarcReader')
            ->will($this->returnValue($record));
        return $obj;
    }

    /**
     * Test methods in MarcAdvancedTrait.
     *
     * Note that some methods are covered by the other tests.
     *
     * @return void
     */
    public function testMarcAdvancedTrait(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        $this->assertEquals(['Classified.'], $obj->getAccessRestrictions());
        $this->assertEquals(['VuFind Golden Award, 2020'], $obj->getAwards());
        $this->assertEquals(['Bibliography: p. 122'], $obj->getBibliographyNotes());
        $this->assertMatchesRegularExpression(
            '/<collection.*?>.*<record.*>.*<\/record>.*<\/collection>/s',
            $obj->getFilteredXML()
        );
        $this->assertEquals(['Finding aid available'], $obj->getFindingAids());
        $this->assertEquals(
            ['General notes here.', 'Translation.'],
            $obj->getGeneralNotes()
        );
        $this->assertEquals(
            ['2020', '2020'],
            $obj->getHumanReadablePublicationDates()
        );
        $this->assertEquals(
            ['Place :', 'Location :'],
            $obj->getPlacesOfPublication()
        );
        $this->assertEquals(['00:20:10', '01:30:55'], $obj->getPlayingTimes());
        $this->assertEquals(['Producer: VuFind'], $obj->getProductionCredits());
        $this->assertEquals(
            ['Frequency varies, 2020-'],
            $obj->getPublicationFrequency()
        );
        $this->assertEquals(
            ['Merged with several branches'],
            $obj->getRelationshipNotes()
        );
        $this->assertEquals(
            [
                ['name' => 'Development Series &\'><"'],
                ['name' => 'Development', 'number' => 'no. 2'],
            ],
            $obj->getSeries()
        );
        $this->assertEquals(['Summary.'], $obj->getSummary());
        $this->assertEquals(['Data in UTF-8'], $obj->getSystemDetails());
        $this->assertEquals(['Developers'], $obj->getTargetAudienceNotes());
        $this->assertEquals('2. Return', $obj->getTitleSection());
        $this->assertEquals('Test Author.', $obj->getTitleStatement());
        $this->assertEquals(
            ['Zoolandia -- City.', 'Funland -- Funtown.'],
            $obj->getHierarchicalPlaceNames()
        );
        $this->assertEquals(
            [
                [
                    'url' => 'https://vufind.org/vufind/',
                    'desc' => 'VuFind Home Page',
                ],
            ],
            $obj->getURLs()
        );
        $this->assertEquals(['(FOO)123', '(Baz)456'], $obj->getConsortialIDs());
        $this->assertEquals('ismn', $obj->getCleanISMN());
        $this->assertEquals(
            ['nbn' => 'NBN12', 'source' => 'NB'],
            $obj->getCleanNBN()
        );
        $marc21Xml = $obj->getXML('marc21');

        $collection
            = simplexml_load_string($this->getFixture('marc/marctraits.xml'));
        $this->assertXmlStringEqualsXmlString(
            $collection->record->asXML(),
            $marc21Xml
        );

        $rdfXml = $obj->getRDFXML();
        $this->assertStringContainsString(
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"'
            . ' xmlns="http://www.loc.gov/mods/v3">',
            $rdfXml
        );
        $this->assertStringContainsString('<nonSort>The </nonSort>', $rdfXml);
        $this->assertStringContainsString(
            '<namePart>Author, Test</namePart>',
            $rdfXml
        );
        $this->assertStringContainsString(
            '<identifier type="isbn">978-3-16-148410-0</identifier>',
            $rdfXml
        );
    }

    /**
     * Test missing ISMN case.
     *
     * @return void
     */
    public function testMissingISMN(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/missingismn.xml');
        $this->assertFalse($obj->getCleanISMN());
    }

    /**
     * Test alternative script methods in MarcAdvancedTrait.
     *
     * @return void
     */
    public function testMarcAdvancedTraitAltScript(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/altscript.xml');

        $this->assertEquals(
            ['Русская народная поэзия : лирическая поэзия /'],
            $obj->getTitlesAltScript()
        );
        $this->assertEquals(
            ['Русская народная поэзия : лирическая поэзия / 1'],
            $obj->getFullTitlesAltScript()
        );
        $this->assertEquals(
            ['Русская народная поэзия :'],
            $obj->getShortTitlesAltScript()
        );
        $this->assertEquals(
            ['лирическая поэзия /'],
            $obj->getSubTitlesAltScript()
        );
        $this->assertEquals(
            ['1'],
            $obj->getTitleSectionsAltScript()
        );
    }
}
