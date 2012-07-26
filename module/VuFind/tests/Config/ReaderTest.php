<?php
/**
 * Config Reader Test Class
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
namespace VuFind\Tests\Config;
use VuFind\Config\Reader;

/**
 * Config Reader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class ReaderTest extends \VuFind\Tests\TestCase
{
    /**
     * Test basic config.ini loading.
     *
     * @return void
     */
    public function testBasicRead()
    {
        // This should retrieve config.ini, which should have "Library Catalog"
        // set as the default system title.
        $config = Reader::getConfig();
        $this->assertEquals('Library Catalog', $config->Site->title);
    }

    /**
     * Test loading of a custom .ini file.
     *
     * @return void
     */
    public function testCustomRead()
    {
        // This should retrieve sms.ini, which should include a Carriers array.
        $config = Reader::getConfig('sms');
        $this->assertTrue(isset($config->Carriers) && count($config->Carriers) > 0);
    }

    /**
     * Test loading of a YAML file.
     *
     * @return void
     */
    public function testSearchSpecsRead()
    {
        // The searchspecs.yaml file should define author dismax fields (among many
        // other things).
        $specs = Reader::getSearchSpecs('searchspecs.yaml');
        $this->assertTrue(
            isset($specs['Author']['DismaxFields'])
            && !empty($specs['Author']['DismaxFields'])
        );
    }
}