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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
            [
                'searches' => ['Explain' => ['enabled' => true]],
                'Search2' => ['Explain' => ['enabled' => true]],
            ]
        );
        // change searchspecs2 to test that the Search2 backend is being used.
        $this->changeYamlConfigs(
            [
                'searchspecs2' => [
                    'AllFields' => [
                        'DismaxFields' => [
                            'title^500',
                        ],
                    ],
                ],
            ],
            ['searchspecs2']
        );
    }

    /**
     * Data provider for testResultList().
     *
     * @return \Iterator
     */
    public static function resultListTestProvider(): \Iterator
    {
        yield 'Search' => ['Search'];
        yield 'Search2' => ['Search2'];
    }

    /**
     * Test that explain charts are displayed in the result list.
     *
     * @param string $searchPath Path for search
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('resultListTestProvider')]
    public function testResultList(string $searchPath)
    {
        $this->setUpTest();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/' . $searchPath . '/Results?lookfor=test&type=AllFields');
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
     * Data provider for testExplanation().
     *
     * @return \Iterator
     */
    public static function explanationTestProvider(): \Iterator
    {
        yield 'Example1' => [
            '/Record/<angle>brackets&ampersands/Explain?lookfor=test&type=AllFields',
            ['topic', 'allfields'],
        ];
        yield 'Example2' => [
            '/Record/geo20001/Explain?lookfor=test&type=AllFields',
            ['title_short', 'allfields', 'title', 'allfields_unstemmed', 'title_full', 'title_full_unstemmed'],
        ];
        yield 'Synonyms' => [
            '/Record/subcollection2/Explain?lookfor=II&type=AllFields',
            ['title_short', 'allfields', 'title_full', 'title'],
            3,
        ];
        yield 'Search2' => [
            '/Search2Record/geo20001/Explain?lookfor=test&type=AllFields',
            ['title'],
        ];
    }

    /**
     * Test an explanation.
     *
     * @param string $path           Path of the explanation
     * @param array  $expectedFields Expected fields in explanation
     * @param int    $synonymCount   Number of synonyms
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('explanationTestProvider')]
    public function testExplanation(string $path, array $expectedFields, int $synonymCount = 1)
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

        // test field names
        $fieldNames = array_unique(array_map(
            fn ($field) => $field->getText(),
            $explainOutput->findAll('css', '.field-name')
        ));
        $this->assertEqualsCanonicalizing($expectedFields, $fieldNames);

        // test pie chart
        $pieChart = $explainOutput->find('css', '#js-explain-pie-chart');
        $chartData = $pieChart->getAttribute('data-chart-data');
        $this->assertCount(count($tableRows), explode(';', $chartData));

        // test column chart
        $columnChart = $explainOutput->find('css', '#js-explain-column-chart');
        $score = $columnChart->getAttribute('data-score');
        $maxScore = $columnChart->getAttribute('data-max-score');
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual($maxScore, $score);
    }
}
