<?php
/**
 * Base Search Object Parameters Test
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
namespace VuFindTest\Search\Base;

/**
 * Base Search Object Parameters Test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ParamsTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test a record that used to be known to cause problems because of the way
     * series name was handled (the old "Bug2" test from VuFind 1.x).
     *
     * @return void
     */
    public function testSpellingReplacements()
    {
        // Use Solr since some Base components are abstract:
        $params = $this->getServiceManager()->get('VuFind\SearchParamsPluginManager')
            ->get('Solr');

        // Key test: word boundaries:
        $params->setBasicSearch('go good googler');
        $this->assertEquals(
            'run good googler',
            $params->getDisplayQueryWithReplacedTerm('go', 'run')
        );

        // Key test: replacement of wildcard queries:
        $params->setBasicSearch('oftamologie*');
        $this->assertEquals(
            'ophtalmologie*',
            $params->getDisplayQueryWithReplacedTerm(
                'oftamologie*', 'ophtalmologie*'
            )
        );
    }
}