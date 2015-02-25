<?php
/**
 * Config SearchSpecsReader Test Class
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
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Config;
use VuFind\Config\SearchSpecsReader;

/**
 * Config SearchSpecsReader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SearchSpecsReaderTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test loading of a YAML file.
     *
     * @return void
     */
    public function testSearchSpecsRead()
    {
        // The searchspecs.yaml file should define author dismax fields (among many
        // other things).
        $reader = $this->getServiceManager()->get('VuFind\SearchSpecsReader');
        $specs = $reader->get('searchspecs.yaml');
        $this->assertTrue(
            isset($specs['Author']['DismaxFields'])
            && !empty($specs['Author']['DismaxFields'])
        );
    }

    /**
     * Test loading of a non-existent YAML file.
     *
     * @return void
     */
    public function testMissingFileRead()
    {
        $reader = $this->getServiceManager()->get('VuFind\SearchSpecsReader');
        $specs = $reader->get('notreallyasearchspecs.yaml');
        $this->assertEquals([], $specs);
    }
}