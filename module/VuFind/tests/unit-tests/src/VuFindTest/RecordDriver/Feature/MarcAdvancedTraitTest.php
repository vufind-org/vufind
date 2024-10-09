<?php

/**
 * Record Driver Marc Advanced Trait Test Class
 *
 * PHP version 8
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
            ->onlyMethods(['getMarcReader', 'getUniqueId'])->getMock();
        $obj->expects($this->any())
            ->method('getMarcReader')
            ->will($this->returnValue($record));
        $obj->expects($this->any())
            ->method('getUniqueId')
            ->will($this->returnValue('123'));
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

    /**
     * Test getMarcFieldWithInd when a single indicator value is sent
     *
     * @return void
     */
    public function testGetMarcFieldWithIndOneValue(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        // Test when a single ind value is passed
        $this->assertEquals(
            ['upc'],
            $obj->getMarcFieldWithInd('024', null, [['1' => ['1']]])
        );
    }

    /**
     * Test getMarcFieldWithInd when multiple values for the indicator are sent
     *
     * @return void
     */
    public function testGetMarcFieldWithIndTwoValues(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        // Test when multiple values for the same ind are passed
        $this->assertEquals(
            ['upc', 'ismn'],
            $obj->getMarcFieldWithInd('024', null, [['1' => ['1', '2']]])
        );
    }

    /**
     * Test getMarcFieldWithInd when multiple indicators are requested
     * as AND conditions
     *
     * @return void
     */
    public function testGetMarcFieldWithIndMultAndInds(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        // Test when different ind values are passed for each ind
        $this->assertEquals(
            ['spa'],
            $obj->getMarcFieldWithInd('041', null, [['1' => ['1'], '2' => ['7']]])
        );
    }

    /**
     * Test getMarcFieldWithInd when multiple indicators are requested
     * as OR conditions
     *
     * @return void
     */
    public function testGetMarcFieldWithIndMultOrInds(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        // Test when different ind values are passed for each ind
        $this->assertEquals(
            ['ger', 'spa'],
            $obj->getMarcFieldWithInd('041', null, [['1' => ['0']], ['2' => ['7']]])
        );
    }

    /**
     * Test getMarcFieldWithInd when no indicator filters are sent
     *
     * @return void
     */
    public function testGetMarcFieldWithIndNoValues(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        // Test when no indicator is passed
        $this->assertEquals(
            ['upc', 'ismn', 'ian'],
            $obj->getMarcFieldWithInd('024', null, [])
        );
    }

    /**
     * Test calling getSummary to get expected marc data
     *
     * @return void
     */
    public function testGetSummary(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        $this->assertEquals(
            ['Summary.'],
            $obj->getSummary()
        );
    }

    /**
     * Test calling getSummaryNotes to get expected marc data
     *
     * @return void
     */
    public function testGetSummaryNotes(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        $this->assertEquals(
            ['Summary. Expanded.'],
            $obj->getSummaryNotes()
        );
    }

    /**
     * Test calling getAbstractNotes to get expected marc data
     *
     * @return void
     */
    public function testGetAbstractNotes(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        $this->assertEquals(
            ['Abstract. Expanded.'],
            $obj->getAbstractNotes()
        );
    }

    /**
     * Test calling getLocationOfArchivalMaterialsNotes to get expected marc data
     *
     * @return void
     */
    public function testGetLocationOfArchivalMaterialsNotes(): void
    {
        $obj = $this->getMockDriverFromFixture('marc/marctraits.xml');

        $this->assertEquals(
            ['Location of archival materials'],
            $obj->getLocationOfArchivalMaterialsNotes()
        );
    }
}
