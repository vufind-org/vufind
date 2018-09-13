<?php
/**
 * SolrDefault Record Driver Test Class
 *
 * PHP version 7
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
namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\SolrDefault;

/**
 * SolrDefault Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrDefaultTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test an OpenURL for a book.
     *
     * @return void
     */
    public function testBookOpenURL()
    {
        $driver = $this->getDriver();
        $this->assertEquals('url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3AUTF-8&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator&rft.title=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft.date=1992&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Abook&rft.genre=book&rft.btitle=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft.series=Vico%2C+Giambattista%2C+1668-1744.+Works.+1982+%3B&rft.au=Vico%2C+Giambattista%2C+1668-1744.&rft.pub=Centro+di+Studi+Vichiani%2C&rft.edition=Fictional+edition.&rft.isbn=8820737493', $driver->getOpenUrl());
    }

    /**
     * Test an OpenURL for an article.
     *
     * @return void
     */
    public function testArticleOpenURL()
    {
        $overrides = [
            'format' => ['Article'],
            'container_title' => 'Fake Container',
            'container_volume' => 'XVII',
            'container_issue' => '6',
            'container_start_page' => '12',
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals('url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3AUTF-8&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator&rft.date=1992&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Ajournal&rft.genre=article&rft.issn=&rft.isbn=8820737493&rft.volume=XVII&rft.issue=6&rft.spage=12&rft.jtitle=Fake+Container&rft.atitle=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft.au=Vico%2C+Giambattista%2C+1668-1744.&rft.format=Article&rft.language=Italian', $driver->getOpenUrl());
    }

    /**
     * Test an OpenURL for a journal.
     *
     * @return void
     */
    public function testJournalOpenURL()
    {
        $overrides = [
            'format' => ['Journal'],
            'issn' => ['1234-5678'],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals('url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3AUTF-8&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator&rft.title=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Adc&rft.creator=Vico%2C+Giambattista%2C+1668-1744.&rft.pub=Centro+di+Studi+Vichiani%2C&rft.format=Journal&rft.language=Italian&rft.issn=1234-5678', $driver->getOpenUrl());
    }

    /**
     * Test an OpenURL for an unknown material type.
     *
     * @return void
     */
    public function testUnknownTypeOpenURL()
    {
        $overrides = [
            'format' => ['Thingie'],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals('url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3AUTF-8&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator&rft.title=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft.date=1992&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Adc&rft.creator=Vico%2C+Giambattista%2C+1668-1744.&rft.pub=Centro+di+Studi+Vichiani%2C&rft.format=Thingie&rft.language=Italian', $driver->getOpenUrl());
    }

    /**
     * Test Dublin Core conversion.
     *
     * @return void
     */
    public function testDublinCore()
    {
        $expected = <<<XML
<?xml version="1.0"?>
<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd"><dc:title>La congiura dei Principi Napoletani 1701 : (prima e seconda stesura) /</dc:title><dc:creator>Vico, Giambattista, 1668-1744.</dc:creator><dc:creator>Pandolfi, Claudia.</dc:creator><dc:language>Italian</dc:language><dc:language>Latin</dc:language><dc:publisher>Centro di Studi Vichiani,</dc:publisher><dc:date>1992</dc:date><dc:subject>Naples (Kingdom) History Spanish rule, 1442-1707 Sources</dc:subject></oai_dc:dc>

XML;
        $xml = $this->getDriver()->getXML('oai_dc');
        $this->assertEquals($expected, $xml);
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Fixture fields to override.
     *
     * @return SolrDefault
     */
    protected function getDriver($overrides = [])
    {
        $fixture = json_decode(
            file_get_contents(
                realpath(
                    VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/misc/testbug2.json'
                )
            ),
            true
        );

        $record = new SolrDefault();
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }
}
