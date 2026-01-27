<?php

/**
 * Between helper for recommendation module placement
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  View_Helpers
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Recommend\Helper;

use VuFind\Search\Base\Results;

use function count;

/**
 * Between helper for recommendation module placement
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Between
{
    /**
     * Minimum placement index of any between recommendation.
     *
     * @var int
     */
    protected int $minPlacement = 2;

    /**
     * Get the appropriate placement within the search results for each between recommendation.
     *
     * @param array   $recommendations Between recommendations config
     * @param Results $results         The primary search results
     *
     * @return array An array containing a placement index (or false to hide) for each between recommendation.
     */
    public function getPlacements(array $recommendations, Results $results): array
    {
        if (empty($recommendations)) {
            return [];
        }

        $placements = array_fill(0, count($recommendations), false);
        $placements[0] = max(
            $this->minPlacement,
            $this->getMaxScoreDiffIndex($results->getScores())
        );

        return $placements;
    }

    /**
     * Return the maximum difference in relevancy score between two consecutive search results.
     *
     * @param array $scores Scores of the primary search results
     *
     * @return ?int The index of the second of the pair of results with the biggest diff score
     */
    public function getMaxScoreDiffIndex(array $scores): ?int
    {
        $maxDiff = 0;
        $maxDiffIndex = 0;
        $lastScore = null;
        foreach (array_values($scores) as $recordIndex => $score) {
            if (!$score) {
                continue;
            }
            if ($lastScore !== null) {
                $diff = $lastScore - $score;
                if ($diff > $maxDiff) {
                    $maxDiff = $diff;
                    $maxDiffIndex = $recordIndex;
                }
            }
            $lastScore = $score;
        }
        return $maxDiffIndex;
    }
}
