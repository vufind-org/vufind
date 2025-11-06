<?php

/**
 * Ontology Recommendations Module.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace Finna\Recommend;

use Finna\Connection\Finto;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Cookie\CookieManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Recommend\RecommendInterface;
use VuFind\Search\SearchRunner;
use VuFind\View\Helper\Root\Url;

use function count;
use function in_array;
use function is_object;
use function sprintf;

/**
 * Ontology Recommendations Module.
 *
 * This class provides ontology based recommendations.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class Ontology implements RecommendInterface, TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Name of the cookie used to store the times shown total value.
     *
     * @var string
     */
    public const COOKIE_NAME = 'ontologyRecommend';

    /**
     * Maximum number of search terms for recommendation processing. Setting to
     * null indicates an unlimited number of search terms.
     *
     * @var int|null
     */
    protected $maxSearchTerms = null;

    /**
     * Maximum number of API calls to make per search. Setting to null indicates
     * an unlimited number of API calls.
     *
     * @var int|null
     */
    protected $maxApiCalls = null;

    /**
     * Maximum number of recommendations to show per search. Setting to null
     * indicates an unlimited number of recommendations.
     *
     * @var int|null
     */
    protected $maxRecommendations = null;

    /**
     * Maximum total number for determining if the result set is small. Setting
     * to null indicates that all result sets should be considered small.
     *
     * @var int|null
     */
    protected $maxSmallResultTotal = null;

    /**
     * Minimum total number for determining if the result set is large. Setting
     * to null indicates that all result sets should be considered large.
     *
     * @var int|null
     */
    protected $minLargeResultTotal = null;

    /**
     * Maximum number of times ontology recommendations can be shown per browser
     * session. Setting to null indicates an unlimited number of ontology
     * recommendations shown.
     *
     * @var int|null
     */
    protected $maxTimesShownPerSession = null;

    /**
     * Maximum number of recommended searches to check for result set totals. This is
     * the total maximum for all search terms. Setting to null indicates that no
     * checks should be done. If checks are done and a recommendation would have more
     * recommended searches than set here, the recommendation will not contain any
     * recommended search links at all.
     *
     * @var int|null
     */
    protected $maxResultChecks = null;

    /**
     * Parameter object representing user request.
     *
     * @var \Laminas\Stdlib\Parameters
     */
    protected $request = null;

    /**
     * Current search query.
     *
     * @var string
     */
    protected $lookfor = null;

    /**
     * Search results object.
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results = null;

    /**
     * Ontology recommendations.
     *
     * @var array|null
     */
    protected $recommendations = null;

    /**
     * Current search query as separate terms.
     *
     * @var array
     */
    protected $lookforTerms = null;

    /**
     * Have the original search terms been combined in the case of a two-word search.
     *
     * @var bool
     */
    protected $combinedTerms = false;

    /**
     * Recommendation URIs array, used to check for existing identical
     * recommendations.
     *
     * @var array
     */
    protected $recommendationUris = [];

    /**
     * Total number of API calls made.
     *
     * @var int
     */
    protected $apiCallTotal = 0;

    /**
     * Total number of recommendations.
     *
     * @var int
     */
    protected $recommendationTotal = 0;

    /**
     * Ontology constructor.
     *
     * @param Finto                  $finto         Finto connection class
     * @param CookieManager          $cookieManager Cookie manager
     * @param Url                    $urlHelper     Url helper
     * @param ConfigManagerInterface $configManager Configuration loader
     * @param SearchRunner           $searchRunner  Search runner
     */
    public function __construct(
        protected Finto $finto,
        protected CookieManager $cookieManager,
        protected Url $urlHelper,
        protected ConfigManagerInterface $configManager,
        protected SearchRunner $searchRunner
    ) {
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * Ontology:[ini section]:[ini name]
     *       Provides ontology based recommendations as configured in the specified
     *       section of the specified ini file; if [ini name] is left out, it
     *       defaults to "searches" and if [ini section] is left out, it defaults to
     *       "OntologyModuleRecommendations".
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $settings = explode(':', $settings);
        $sectionName = empty($settings[0])
            ? 'OntologyModuleRecommendations' : $settings[0];
        $iniName = $settings[1] ?? 'searches';

        $config = $this->configManager->getConfigObject($iniName)->get($sectionName);

        $this->maxSearchTerms = $config->get('maxSearchTerms');
        $this->maxApiCalls = $config->get('maxApiCalls');
        $this->maxRecommendations = $config->get('maxRecommendations');
        $this->maxSmallResultTotal = $config->get('maxSmallResultTotal');
        $this->minLargeResultTotal = $config->get('minLargeResultTotal');
        $this->maxTimesShownPerSession = $config->get('maxTimesShownPerSession');
        $this->maxResultChecks = $config->get('maxResultChecks');
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     *                                            request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        $this->request = $request;

        // Collect the best possible search term(s):
        $this->lookfor = $request->get('lookfor');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->getQuery()->getAllTerms();
        }
        $this->lookfor = trim($this->lookfor);
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     * @throws \Exception
     */
    public function process($results)
    {
        // Processing is done at a later stage to get the search ID when not
        // running as deferred.
        $this->results = $results;
    }

    /**
     * Get ID of saved search.
     *
     * @return mixed|null
     */
    public function getSearchId()
    {
        return $this->request->get('searchId')
            ?? $this->results->getSearchId()
            ?? null;
    }

    /**
     * Get current search query.
     *
     * @return string|null
     */
    public function getLookfor(): ?string
    {
        return $this->lookfor;
    }

    /**
     * Get all ontology recommendations grouped by type.
     *
     * @return array|null
     * @throws \Exception
     */
    public function getRecommendations(): ?array
    {
        // Just return the results if we already have them.
        if (isset($this->recommendations)) {
            return $this->recommendations;
        }

        // Do nothing if lookfor is empty.
        if (empty($this->lookfor)) {
            return null;
        }

        // Get language, do nothing if it is not supported.
        $language = $this->getTranslatorLocale();
        $language = (str_starts_with($language, 'en-')) ? 'en' : $language;
        if (!$this->finto->isSupportedLanguage($language)) {
            return null;
        }

        // Set up search terms array with quoted words as one search term.
        $this->lookforTerms = str_getcsv($this->lookfor, ' ');

        // Further processing of tokenized terms.
        $this->lookforTerms = $this->processSearchTerms($this->lookforTerms);

        // Do nothing if the amount of processed search terms is zero or more than
        // the maximum.
        if (
            0 === count($this->lookforTerms)
            || (null !== $this->maxSearchTerms
            && count($this->lookforTerms) > $this->maxSearchTerms)
        ) {
            return null;
        }

        // Check cookie to find out how many times ontology recommendations have
        // already been shown in the current browser session. Do nothing if a
        // maximum value is set in configuration and it has been reached.
        $cookieValue = $this->cookieManager->get(self::COOKIE_NAME);
        $timesShownTotal = is_numeric($cookieValue) ? $cookieValue : 0;
        if (
            is_numeric($this->maxTimesShownPerSession)
            && $timesShownTotal > $this->maxTimesShownPerSession
        ) {
            return null;
        }

        // Get resultTotal.
        $resultTotal = $this->request->get('resultTotal')
            ?? $this->results->getResultTotal();

        // Set up recommendations array.
        $this->recommendations = [];

        // Process each term and make API calls if applicable.
        foreach ($this->lookforTerms as $term) {
            // Determine if the term can or should be searched for.
            if (!($this->canMakeApiCalls() && $this->canAddRecommendation())) {
                break;
            }

            // Determine if narrower concepts should be looked for if applicable.
            $narrower = ((null === $this->minLargeResultTotal
                || $resultTotal >= $this->minLargeResultTotal))
                && $this->canMakeApiCalls(2);

            // Make the Finto API call(s).
            $fintoTerm = $term . '*';
            while (str_contains($fintoTerm, '**')) {
                $fintoTerm = str_replace('**', '*', $fintoTerm);
            }
            $fintoResults = $this->finto->extendedSearch(
                $fintoTerm,
                $language,
                [],
                $narrower
            );
            $this->apiCallTotal += 1;

            // Continue to next term if no results or "other" results.
            if (
                !$fintoResults
                || Finto::TYPE_OTHER === $fintoResults[Finto::RESULT_TYPE]
            ) {
                continue;
            }

            // Process and add Finto results.
            if (Finto::TYPE_HYPONYM === $fintoResults[Finto::RESULT_TYPE]) {
                // Hyponym results have required an additional API call.
                $this->apiCallTotal += 1;
                // Get the URI of the searched term from the original results.
                $termUri = $fintoResults[Finto::RESULTS]['results'][0]['uri'];
                // Narrower results are used for hyponym recommendations.
                foreach ($fintoResults[Finto::NARROWER_RESULTS] as $fintoResult) {
                    $this->addOntologyResult(
                        $term,
                        $fintoResult,
                        $fintoResults[Finto::RESULT_TYPE],
                        $termUri
                    );
                }
            } else {
                foreach ($fintoResults[Finto::RESULTS]['results'] as $fintoResult) {
                    $this->addOntologyResult(
                        $term,
                        $fintoResult,
                        $fintoResults[Finto::RESULT_TYPE]
                    );
                }
            }
        }

        // Do result set total checks for recommended searches if so configured.
        $this->doResultChecks();

        if ($this->recommendationTotal > 0) {
            // There are recommendations, so set a new cookie value.
            $this->cookieManager->set(self::COOKIE_NAME, $timesShownTotal + 1);
        }

        return $this->recommendations;
    }

    /**
     * Additional normalization, selection and other processing of search terms.
     *
     * @param array $terms Search terms
     *
     * @return array Processed search terms
     */
    protected function processSearchTerms(array $terms): array
    {
        $processed = [];

        foreach ($terms as $term) {
            $term = trim($term);

            // Skip if not actually a search term.
            if (
                empty($term)
                || preg_match('/^https?:/', $term)
                || preg_match('/^topic_id_str_mv:/', $term)
                || in_array($term, ['AND', 'OR', 'NOT'])
            ) {
                continue;
            }

            // Strip possible outermost matching quotes.
            $term = preg_replace('/^"(.*)"$/', '$1', $term);

            $processed[] = $term;
        }

        // Special case for two-word searches, which will be processed as one
        // search term.
        if (
            2 === count($processed)
            && !str_contains($processed[0], ' ')
            && !str_contains($processed[1], ' ')
        ) {
            $processed = [implode(' ', $processed)];
            $this->combinedTerms = true;
        }

        return $processed;
    }

    /**
     * Can more API calls be made.
     *
     * @param int $count Number of API calls needed, defaults to 1.
     *
     * @return bool
     */
    protected function canMakeApiCalls(int $count = 1): bool
    {
        return is_numeric($this->maxApiCalls)
            ? ($this->apiCallTotal + $count) <= $this->maxApiCalls
            : true;
    }

    /**
     * Can another recommendation be added.
     *
     * @return bool
     */
    protected function canAddRecommendation(): bool
    {
        return is_numeric($this->maxRecommendations)
            ? $this->recommendationTotal < $this->maxRecommendations
            : true;
    }

    /**
     * Adds an ontology result to the recommendations array.
     *
     * @param string      $term        The term searched for
     * @param array       $fintoResult Finto result
     * @param string      $resultType  Result type
     * @param string|null $termUri     URI of the searched term if applicable
     *
     * @return void
     */
    protected function addOntologyResult(
        string $term,
        array $fintoResult,
        string $resultType,
        ?string $termUri = null
    ): void {
        // Do not add the result if the URI already exists in the original search.
        if (str_contains($this->lookfor, $fintoResult['uri'])) {
            return;
        }

        // Do not add the result if the URI is the same as in an already added
        // recommendation.
        if (in_array($fintoResult['uri'], $this->recommendationUris)) {
            return;
        }

        // Replace original search term with the recommended term in the lookfor.
        $recommendationLookfor = $this->replaceWithRecommendedTerm(
            $this->lookfor,
            $fintoResult['prefLabel'],
            $fintoResult['uri'],
            $term,
            $termUri
        );

        // Abort if the replacement failed for some reason.
        if ($recommendationLookfor === $this->lookfor) {
            return;
        }

        // Set up other recommendation link parameters and build the link.
        $params = $this->request->toArray();
        $params['lookfor'] = $recommendationLookfor;
        foreach (['mod', 'searchId', 'resultTotal'] as $key) {
            if (isset($params[$key])) {
                unset($params[$key]);
            }
        }
        $href = ($this->urlHelper)('search-results', [], ['query' => $params]);

        // Add result and increase counter if the result is for a new term.
        $this->recommendationUris[] = $fintoResult['uri'];
        if (!isset($this->recommendations[$resultType])) {
            $this->recommendations[$resultType] = [];
        }
        if (!isset($this->recommendations[$resultType][$term])) {
            $this->recommendations[$resultType][$term] = [];
            $this->recommendationTotal += 1;
        }
        $this->recommendations[$resultType][$term][] = [
            'label' => $fintoResult['prefLabel'],
            'href' => $href,
            'params' => $params,
        ];
    }

    /**
     * Replaces a term in the query string.
     *
     * @param string      $lookfor  Query string used in replacement
     * @param string      $repTerm  Replacement term
     * @param string      $repUri   Replacement URI
     * @param string      $origTerm Original term
     * @param string|null $origUri  Original URI (optional)
     *
     * @return string
     */
    protected function replaceWithRecommendedTerm(
        string $lookfor,
        string $repTerm,
        string $repUri,
        string $origTerm,
        ?string $origUri = null
    ): string {
        // Add quotes to multi-word terms if appropriate.
        if (str_contains($repTerm, ' ')) {
            $repTerm = '"' . addcslashes($repTerm, '"') . '"';
        }
        if (!$this->combinedTerms && (str_contains($origTerm, ' '))) {
            $origTerm = "\"$origTerm\"";
        }

        $replace = $this->getInQueryStringFormat($repTerm, $repUri);

        // If we have an original URI, attempt to replace an existing recommendation.
        if ($origUri) {
            $count = 0;
            $lookfor = str_replace(
                $this->getInQueryStringFormat($origTerm, $origUri),
                $replace,
                $lookfor,
                $count
            );
            if ($count > 0) {
                return $lookfor;
            }
        }

        // If an existing recommendation was not replaced, replace the original
        // search term only.
        return str_replace($origTerm, $replace, $lookfor);
    }

    /**
     * Returns a formatted query string containing both the provided term and URI.
     *
     * @param string $term Term
     * @param string $uri  URI
     *
     * @return string
     */
    protected function getInQueryStringFormat(string $term, string $uri): string
    {
        return sprintf('(%s OR topic_id_str_mv:("%s")^100000)', $term, $uri);
    }

    /**
     * Do search result checks for all recommendations and remove recommended
     * search links that do not meet configured criteria.
     *
     * @return void
     */
    protected function doResultChecks(): void
    {
        if (null === $this->maxResultChecks) {
            // No checks needed.
            return;
        }

        $resultChecksDone = 0;
        foreach ($this->recommendations as $type => $terms) {
            foreach ($terms as $term => $searches) {
                if (count($searches) > $this->maxResultChecks - $resultChecksDone) {
                    // Not possible to check all searches for this recommendation
                    // so remove all search links.
                    foreach (array_keys($searches) as $i) {
                        unset($this->recommendations[$type][$term][$i]['href']);
                    }
                    continue;
                }
                $grandTotal = 0;
                foreach ($searches as $i => $search) {
                    $results = $this->searchRunner->run($search['params']);
                    $resultTotal = $results->getResultTotal();
                    if (0 >= $resultTotal) {
                        // No results for this search so remove the link.
                        unset($this->recommendations[$type][$term][$i]['href']);
                    }
                    $this->recommendations[$type][$term][$i]['resultTotal'] = $resultTotal;
                    $grandTotal += $resultTotal;
                    $resultChecksDone += 1;
                }
                if (0 === $grandTotal) {
                    // None of the recommended searches have any results so remove
                    // the recommendation.
                    unset($this->recommendations[$type][$term]);
                    $this->recommendationTotal -= 1;
                }
            }
            if (0 === count($this->recommendations[$type])) {
                // All recommendations of this type have been removed.
                unset($this->recommendations[$type]);
            }
        }
    }
}
