<?php

/**
 * Solr Explanation
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @package  Search_Solr
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Solr;

use VuFindSearch\Backend\Solr\Command\RawJsonSearchCommand;
use VuFindSearch\ParamBag;

use function count;
use function floatval;
use function strlen;

/**
 * Solr Explanation
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Explanation extends \VuFind\Search\Base\Explanation
{
    /**
     * Value of the relevance score of the best match.
     *
     * @var float
     */
    protected $maxScore;

    /**
     * Relevance score of the title with the recordId.
     *
     * @var float
     */
    protected $totalScore;

    /**
     * Relevance score of the title with the recordId without modifiers (boost / coord).
     *
     * @var float
     */
    protected $baseScore;

    /**
     * Value of boost.
     *
     * @var float
     */
    protected $boost;

    /**
     * Value of coord. If only 2 out of 4 search query parts match, then coord would be 1/2.
     * It adjusts the score so that the 2 other search query parts also influence the score.
     *
     * @var float
     */
    protected $coord;

    /**
     * The main result of the explain class,
     * an array with every match and its values.
     *
     * @var array
     */
    protected $explanation = [];

    /**
     * Describes the rest. It has restValue and the percentage from total value.
     *
     * @var ?array
     */
    protected $rest = null;

    /**
     * Contains the fields that were removed from the main explanation.
     *
     * @var array
     */
    protected $explanationForRest = [];

    /**
     * Raw explanation.
     *
     * @var string
     */
    protected $rawExplanation = null;

    /**
     * Get relevance value of best scoring title.
     *
     * @return float
     */
    public function getMaxScore()
    {
        return $this->maxScore;
    }

    /**
     * Get relevance score of this title.
     *
     * @return float
     */
    public function getTotalScore()
    {
        return $this->totalScore;
    }

    /**
     * Get relevance score of this title without modifier (boost/coord).
     *
     * @return float
     */
    public function getBaseScore()
    {
        return $this->baseScore;
    }

    /**
     * Get value of the boost used in Solr query.
     *
     * @return float
     */
    public function getBoost()
    {
        return $this->boost;
    }

    /**
     * Get value of coord.
     *
     * @return float
     */
    public function getCoord()
    {
        return $this->coord;
    }

    /**
     * Get the explanation, parsed from Solr response.
     *
     * @return array
     */
    public function getExplanation()
    {
        return $this->explanation;
    }

    /**
     * Get rest. It has restValue and the percentage from total value.
     *
     * @return array
     */
    public function getRest()
    {
        return $this->rest;
    }

    /**
     * Get the explanation for the rest.
     *
     * @return array
     */
    public function getExplanationForRest()
    {
        return $this->explanationForRest;
    }

    /**
     * Get the raw explanation.
     *
     * @return string
     */
    public function getRawExplanation()
    {
        return $this->rawExplanation;
    }

    /**
     * Get the maximal number of fields to be included.
     *
     * @return int
     */
    public function getMaxFields()
    {
        return $this->config['Explain']['maxFields'] ?? -1;
    }

    /**
     * Get the minimal percentage for fields to be included.
     *
     * @return float
     */
    public function getMinPercentage()
    {
        return $this->config['Explain']['minPercent'] ?? 0;
    }

    /**
     * Get number of decimal places for to be shown in the explanation.
     *
     * @return int
     */
    public function getDecimalPlaces()
    {
        return $this->config['Explain']['decimalPlaces'] ?? 2;
    }

    /**
     * Performing request and creating explanation.
     *
     * @param string $recordId Record Id
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     * @return void
     */
    public function performRequest($recordId)
    {
        // get search query
        $query  = $this->getParams()->getQuery();

        // prepare search params
        $params = $this->getParams()->getBackendParameters();
        $params->set('spellcheck', 'false');
        $explainParams = new ParamBag([
            'fl' => 'id,score',
            'facet' => 'true',
            'debug' => 'true',
            'indent' => 'true',
            'param' => 'q',
            'echoParams' => 'all',
            'explainOther' => 'id:"' . addcslashes($recordId, '"') . '"',
        ]);
        $params->mergeWith($explainParams);

        // perform request
        $explainCommand = new RawJsonSearchCommand(
            'Solr',
            $query,
            0,
            0,
            $params,
            true
        );
        $explainCommand = $this->searchService->invoke($explainCommand);
        $response = $explainCommand->getResult();

        // build explanation
        $this->build($response, $recordId);
    }

    /**
     * Builds explanation and sets up debug message to see raw Solr response.
     *
     * @param array  $response Solr response
     * @param string $recordId recordId of title for Solr explainOther
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     * @return void
     */
    protected function build($response, $recordId)
    {
        // prepare parsing
        $recordId = str_replace(['\(', '\)'], ['(', ')'], $recordId);

        if (empty($lines = $response['debug']['explainOther'][$recordId])) {
            throw new \VuFindSearch\Backend\Exception\BackendException(
                "No explainOther was returned for record {$recordId}"
            );
        }

        $this->rawExplanation = $lines;
        $lines = $this->cleanLines($lines);

        // get basic values
        $this->lookfor = strtolower($response['debug']['rawquerystring']);
        $this->recordId = $recordId;
        $this->maxScore = $response['response']['maxScore'];
        $this->totalScore = $this->parseLine($lines[0])['value'];
        $this->baseScore = $this->totalScore;

        // handle boost
        if (($response['responseHeader']['params']['boost'] ?? false) && count($lines) > 1) {
            $this->boost = $this->parseLine(array_pop($lines));
            if ($this->boost['value'] > 0) {
                $this->baseScore = $this->baseScore / $this->boost['value'];
            }
        }

        // handle coord
        if (!empty($lines) && str_contains($this->parseLine(end($lines))['description'], 'coord')) {
            $this->coord = $this->parseLine(end($lines));
            if ($this->coord['value'] > 0) {
                $this->baseScore = $this->baseScore / $this->coord['value'];
            }
        }

        // build explanation
        $this->buildRecursive(array_reverse($lines), 1);

        // sort explanation descending by value
        usort($this->explanation, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        // remove fields that exceed the fields limit and add them to rest
        $maxFields = $this->getMaxFields();
        if ($maxFields >= 0 && count($this->explanation) > $maxFields) {
            $explanationForRest = array_splice($this->explanation, $maxFields, count($this->explanation) - $maxFields);
            $this->explanationForRest = array_merge($this->explanationForRest, $explanationForRest);
        }

        // handle rest
        if (count($this->explanationForRest) > 0) {
            usort($this->explanationForRest, function ($a, $b) {
                return $b['value'] <=> $a['value'];
            });

            $restValue = array_sum(array_column($this->explanationForRest, 'value'));
            if ($this->baseScore > 0) {
                $this->rest = ['value' => $restValue, 'percent' => 100 * $restValue / $this->baseScore];
            } else {
                $this->rest = ['value' => $restValue, 'percent' => 0];
            }
        }
    }

    /**
     * Norms the response by replacing expressions to support
     * all versions of Solr. Removes empty lines.
     *
     * @param string $lines raw lines
     *
     * @return array normed lines
     */
    protected function cleanLines($lines)
    {
        $lines = preg_replace('/\\n\), product/', '), product', $lines);
        $lines = preg_replace('/ \(MATCH\)/', '', $lines);
        $lines = preg_replace('/ max of/', 'max plus 0 times others of', $lines);
        $lines = preg_replace('/ConstantScore/', 'const weight', $lines);
        $lines = preg_replace('/No match/', 'Failure to meet condition(s)', $lines);
        $lines = explode("\n", $lines);
        $lines = array_values(array_filter($lines, function ($value) {
            return !empty($value);
        }));
        return $lines;
    }

    /**
     * Goes through each line of explainOther response
     * adding a matched field to $explanation.
     *
     * @param array $lines    Solr lines
     * @param float $modifier 1 (* tieValue)
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     * @return string Solr lines without the last inspected line
     */
    protected function buildRecursive($lines, $modifier)
    {
        $line = array_pop($lines);
        $curLevel = $this->getLevel($line);

        $info = $this->parseLine($line);
        $value = $info['value'];
        $description = $info['description'];

        if (str_contains($description, 'Failure to meet condition(s)')) {
            throw new \VuFindSearch\Backend\Exception\BackendException(
                "Record {$this->getRecordId()} fails to match arguments."
            );
        }

        $isMaxPlusOthers = preg_match(
            '/max plus (?<tieValue>[0-9.]*(E-\d+)?) times others of:/',
            $description,
            $matches
        );

        // get max child
        if ($isMaxPlusOthers) {
            $maxValue = 0;
            $maxChild = null;
            foreach ($this->getChildLines($lines, $curLevel) as $child) {
                if ($this->parseLine($child)['value'] > $maxValue) {
                    $maxValue = $this->parseLine($child)['value'];
                    $maxChild = $child;
                }
            }
        }

        // summary of lower children
        if (
            (
                (str_contains($description, 'product of:') || str_contains($description, 'sum of') || $isMaxPlusOthers)
                && !str_contains($description, 'weight') && !str_contains($description, 'FunctionQuery')
            )
            || str_contains($description, 'weight(FunctionScoreQuery')
        ) {
            // build children
            while (!empty($lines) && $this->getLevel(end($lines)) > $curLevel) {
                if (!$isMaxPlusOthers || end($lines) == $maxChild) {
                    $lines = $this->buildRecursive($lines, $modifier);
                } else {
                    $lines = $this->buildRecursive($lines, $modifier * $matches['tieValue']);
                }
            }
            // match in field
        } elseif (
            (str_contains($description, 'weight') || str_contains($description, 'FunctionQuery'))
            && !str_contains($description, 'FunctionScoreQuery')
        ) {
            // parse explaining element
            $currentValue = $value * $modifier;
            if ($this->baseScore > 0) {
                $percentage = 100 * $currentValue / $this->baseScore;
            } else {
                $percentage = 0;
            }

            // get fieldModifier and remove unused higher level lines
            $fieldModifier = null;
            if (str_contains($description, 'const weight')) {
                $fieldModifier = 0;
            }
            while (!empty($lines) && $curLevel < $this->getLevel(end($lines))) {
                $childLine = array_pop($lines);
                $childInfo = $this->parseLine($childLine);
                $childValue = $childInfo['value'];
                $childDescription = $childInfo['description'];
                if ($childDescription === ' boost') {
                    $fieldModifier = $childValue;
                }
            }

            // add to rest if lower than min percentage
            $explainElement = $this->parseExplainElement($currentValue, $description, $percentage, $fieldModifier);
            if ($percentage < $this->getMinPercentage()) {
                $this->explanationForRest[] = $explainElement;
            } else {
                $this->explanation[] = $explainElement;
            }
        }
        return $lines;
    }

    /**
     * Returns indent of a line.
     *
     * @param string $line Line
     *
     * @return int
     */
    protected function getLevel($line)
    {
        return (strlen($line) - strlen(ltrim($line))) / 2;
    }

    /**
     * Gets all lines with one level higher than the parent line.
     *
     * @param array $lines Lines
     * @param int   $level Level
     *
     * @return array
     */
    protected function getChildLines($lines, $level)
    {
        $res = [];
        while (!empty($lines) && $this->getLevel(end($lines)) > $level) {
            $line = array_pop($lines);
            if ($this->getLevel($line) == $level + 1) {
                $res[] = $line;
            }
        }
        return $res;
    }

    /**
     * Extracts value and description of a line.
     *
     * @param string $line Line
     *
     * @return array
     */
    protected function parseLine($line)
    {
        $info = explode('=', $line, 2);
        return [
            'value' => floatval($info[0]),
            'description' => $info[1],
        ];
    }

    /**
     * Unites all infos of a match to an explainElement.
     *
     * @param float  $value         Value
     * @param string $description   Description
     * @param float  $percentage    Percentage
     * @param float  $fieldModifier Field Modifier
     *
     * @return array
     */
    protected function parseExplainElement($value, $description, $percentage, $fieldModifier)
    {
        $res = [
            'value' => $value,
            'percent' => $percentage,
            'fieldName' => ['unknown'],
            'fieldValue' => ['unknown'],
            'exactMatch' => ['unknown'],
        ];
        if (
            preg_match(
                '/weight\(Synonym\((?<synonyms>([^:]+:(\"([^\"]+\s?)+[^\"]+\"|\w+)\s?)+)\)(.+?(?= in))?/u',
                $description,
                $matches
            )
        ) {
            preg_match_all(
                '/(?<fieldName>[^:\s]+):(?<fieldValue>\"[^"]+\"|\w+)/u',
                $matches['synonyms'],
                $synonymMatches
            );
            $fieldValues = array_map(function ($fieldValue) {
                return str_replace('"', '', $fieldValue);
            }, $synonymMatches['fieldValue']);
            $res['fieldName'] = $synonymMatches['fieldName'];
            $res['fieldValue'] = $fieldValues;
            // extra space to only exact match whole words
            $res['exactMatch'] = array_map(function ($fieldValue) {
                return str_contains($this->lookfor . ' ', $fieldValue . ' ') ? 'exact' : 'inexact';
            }, $fieldValues);
        } elseif (
            preg_match(
                '/weight\((?<fieldName>[^:]+):(?<fieldValue>\"[^"]+\"|\w+)(.+?(?= in))?/u',
                $description,
                $matches
            )
        ) {
            $fieldValue = str_replace('"', '', $matches['fieldValue']);
            $res['fieldName'] = [$matches['fieldName']];
            $res['fieldValue'] = [$fieldValue];
            // extra space to only exact match whole words
            $res['exactMatch'] = [str_contains($this->lookfor . ' ', $fieldValue . ' ') ? 'exact' : 'inexact'];
        } elseif (
            preg_match(
                '/FunctionQuery\((?<function>.*)\), product of:/',
                $description,
                $matches
            )
        ) {
            $res['function'] = $matches['function'];
        }
        if ($fieldModifier !== null) {
            $res['fieldModifier'] = $fieldModifier;
        }
        return $res;
    }
}
