<?php

/**
 * Explain Mink test class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use function count;

/**
 * Explain Mink test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ExplainTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Set up a test by enabling explain.
     *
     * @return void
     */
    protected function setUpTest()
    {
        $this->changeConfigs(
            ['searches' =>
                [
                    'Explain' => ['enabled' => true],
                ],
            ]
        );
    }

    /**
     * Test that explain charts are displayed in the result list.
     *
     * @return void
     */
    public function testResultList()
    {
        $this->setUpTest();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=test&type=AllFields');
        $page = $session->getPage();
        $explainCharts = $page->findAll('css', '.bar-chart');
        $this->assertCount(20, $explainCharts);
        foreach ($explainCharts as $explainChart) {
            $this->assertGreaterThan(0, $explainChart->getAttribute('data-score'));
            $this->assertLessThanOrEqual(
                $explainChart->getAttribute('data-max-score'),
                $explainChart->getAttribute('data-score')
            );
        }
    }

    /**
     * Test that tests example 1.
     *
     * @return void
     */
    public function testExample1()
    {
        $this->testExplanation('/Record/<angle>brackets&ampersands/Explain?lookfor=test&type=AllFields');
    }

    /**
     * Test that tests example 2.
     *
     * @return void
     */
    public function testExample2()
    {
        $this->testExplanation('/Record/geo20001/Explain?lookfor=test&type=AllFields');
    }

    /**
     * Test that tests synonyms.
     *
     * @return void
     */
    public function testSynonym()
    {
        $this->testExplanation('/Record/subcollection2/Explain?lookfor=II&type=AllFields', 3);
    }

    /**
     * Test an explanation.
     *
     * @param string $path         Path of the explanation
     * @param int    $synonymCount Number of synonyms
     *
     * @return void
     */
    protected function testExplanation($path, $synonymCount = 1)
    {
        // set up test
        $this->setUpTest();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $explainOutput = $page->find('css', '.explain');

        // test table
        $tableRows = $explainOutput->findAll('css', 'tr');
        $this->assertGreaterThan(0, count($tableRows));
        foreach ($tableRows as $tableRow) {
            $this->assertNotEmpty($this->findCssAndGetText($tableRow, '.percentage'));
            $exactMatches = $tableRow->findAll('css', '.exact-match');
            $inexactMatches = $tableRow->findAll('css', '.inexact-match');
            $matches = array_merge($exactMatches, $inexactMatches);
            $this->assertCount($synonymCount, $matches);
            foreach ($matches as $match) {
                $this->assertNotEmpty($match->getText());
            }
            $fieldNames = $tableRow->findAll('css', '.field-name');
            $this->assertCount($synonymCount, $fieldNames);
        }

        // test pie chart
        $pieChart = $explainOutput->find('css', '#js-explain-pie-chart');
        $chartData = $pieChart->getAttribute('data-chart-data');
        $this->assertEquals(count($tableRows), count(explode(';', $chartData)));

        // test column chart
        $columnChart = $explainOutput->find('css', '#js-explain-column-chart');
        $score = $columnChart->getAttribute('data-score');
        $maxScore = $columnChart->getAttribute('data-max-score');
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual($maxScore, $score);
    }
}
