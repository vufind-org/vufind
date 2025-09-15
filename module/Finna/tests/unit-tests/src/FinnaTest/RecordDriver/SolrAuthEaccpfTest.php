<?php

/**
 * SolrAuthEaccpf Test Class
 *
 * PHP version 8
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\RecordDriver;

use Finna\RecordDriver\SolrAuthEaccpf;

/**
 * SolrAuthEaccpf Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrAuthEaccpfTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test getAlternativeTitles
     *
     * @return void
     */
    public function testGetAlternativeTitles(): void
    {
        $driver = $this->getDriver();
        $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
        $titles = [
          [
            'data' => 'Tokanimi, Etunimi',
            'detail' => "1930 {$ndash} 1944, 1.1.1949 {$ndash} 2.2.1950",
          ],
          [
            'data' => 'Testi, Testaaja',
            'detail' => "12.12.1900 {$ndash} 2.2.1920, 5.5.1925",
          ],
          [
            'data' => 'Testeri, Test',
            'detail' => "1901 {$ndash} 1930, 13.10.1940, 12.12.1941 {$ndash} 11.11.1942",
          ],
        ];
        $this->assertEquals($titles, $driver->getAlternativeTitles());
    }

    /**
     * Test getRelatedPublication
     *
     * @return void
     */
    public function testGetRelatedPublications(): void
    {
        $driver = $this->getDriver();
        $publications = [
          [
            'title' => 'Kansallisbiografia',
            'searchTitle' => '',
            'label' => '',
            'url' => 'https://kansallisbiografia.fi/',
            'isbn' => '',
          ],
          [
            'title' => 'Ylioppilasmatrikkeli 1983',
            'searchTitle' => '',
            'label' => '',
            'url' => 'https://ylioppilasmatrikkeli.helsinki.fi/1853-1899/',
            'isbn' => '',
          ],
          [
            'title' => 'Julkaisu ilman linkkiä',
            'searchTitle' => '',
            'label' => '',
            'url' => '',
            'isbn' => '',
          ],
        ];
        $this->assertEquals($publications, $driver->getRelatedPublications());
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides    Fixture fields to override.
     * @param array $searchConfig Search configuration.
     *
     * @return SolrAuthEaccpf
     */
    protected function getDriver($overrides = [], $searchConfig = []): SolrAuthEaccpf
    {
        $fixture = $this->getFixture('eaccpf/eaccpf_test.xml', 'Finna');
        $dateConverter = new \VuFind\Date\Converter(['displayDateFormat' => 'j.n.Y']);
        $record = new SolrAuthEaccpf(
            null,
            null,
            new \VuFind\Config\Config($searchConfig)
        );
        $record->attachDateConverter($dateConverter);
        $record->setRawData(['fullrecord' => $fixture]);
        return $record;
    }
}
