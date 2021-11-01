<?php
/**
 * Mink test class for combined search.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink test class for combined search.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class CombinedSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Get config settings for combined.ini.
     *
     * @return array
     */
    protected function getCombinedIniOverrides()
    {
        return [
            'Solr:one' => [
                'label' => 'Solr One',
                'hiddenFilter' => 'building:journals.mrc',
            ],
            'Solr:two' => [
                'label' => 'Solr Two',
                'hiddenFilter' => 'building:weird_ids.mrc',
            ]
        ];
    }

    /**
     * Several different methods perform the same query against different
     * configurations of the combined feature; this support method makes a
     * standard set of assertions against the final results.
     *
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function assertResultsForDefaultQuery($page)
    {
        $expectedResults = [
            "#combined_Solr____one" => "Journal of rational emotive therapy : "
                . "the journal of the Institute for Rational-Emotive Therapy.",
            "#combined_Solr____two" => "Pluses and Minuses of Pluses and Minuses",
        ];
        foreach ($expectedResults as $container => $title) {
            $this->assertEquals(
                $title,
                $this->findCss($page, "$container a.title")->getText()
            );
            // Check for sample driver location/call number in output (this will
            // only appear after AJAX returns):
            $this->assertEquals(
                'A1234.567',
                $this->findCss($page, "$container .callnumber")->getText()
            );
            $this->assertEquals(
                '3rd Floor Main Library',
                $this->findCss($page, "$container .location")->getText()
            );
        }
    }

    /**
     * Test that combined results work in non-AJAX mode.
     *
     * @return void
     */
    public function testCombinedSearchResults()
    {
        $this->changeConfigs(
            ['combined' => $this->getCombinedIniOverrides()],
            ['combined']
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Combined');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->clickCss($page, '.btn.btn-primary');
        $this->snooze();
        $this->assertResultsForDefaultQuery($page);
    }

    /**
     * Test that combined results work in AJAX mode.
     *
     * @return void
     */
    public function testCombinedSearchResultsAllAjax()
    {
        $config = $this->getCombinedIniOverrides();
        $config['Solr:one']['ajax'] = true;
        $config['Solr:two']['ajax'] = true;
        $this->changeConfigs(
            ['combined' => $config],
            ['combined']
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Combined');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->clickCss($page, '.btn.btn-primary');
        $this->snooze();
        $this->assertResultsForDefaultQuery($page);
    }

    /**
     * Test that combined results work in mixed AJAX/non-AJAX mode.
     *
     * @return void
     */
    public function testCombinedSearchResultsMixedAjax()
    {
        $config = $this->getCombinedIniOverrides();
        $config['Solr:one']['ajax'] = true;
        $this->changeConfigs(
            ['combined' => $config],
            ['combined']
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Combined');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->clickCss($page, '.btn.btn-primary');
        $this->snooze();
        $this->assertResultsForDefaultQuery($page);
    }
}
