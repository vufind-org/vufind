<?php

/**
 * AuthorInfo Recommendations Module
 *
 * PHP version 8
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
 * @package  Recommendations
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use Exception;
use Laminas\I18n\Translator\TranslatorInterface;
use VuFind\Connection\Wikipedia;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindSearch\Query\Query;

use function count;

/**
 * AuthorInfo Recommendations Module
 *
 * This class gathers information from the Wikipedia API and publishes the results
 * to a module at the top of an author's results page
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 * @view     AuthorInfoFacets.phtml
 */
class AuthorInfo implements RecommendInterface, TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait {
        setTranslator as setTranslatorThroughTrait;
    }

    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
     */
    protected $client;

    /**
     * Wikipedia client
     *
     * @var Wikipedia
     */
    protected $wikipedia;

    /**
     * Saved search results
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $searchObject;

    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Should we use VIAF for authorized names?
     *
     * @var bool
     */
    protected $useViaf = false;

    /**
     * Sources of author data that may be used (comma-delimited string; currently
     * only 'wikipedia' is supported).
     *
     * @var string
     */
    protected $sources;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     * @param \Laminas\Http\Client                 $client  HTTP client
     * @param string                               $sources Source identifiers
     * (currently, only 'wikipedia' is supported)
     */
    public function __construct(
        \VuFind\Search\Results\PluginManager $results,
        \Laminas\Http\Client $client,
        $sources = 'wikipedia'
    ) {
        $this->resultsManager = $results;
        $this->client = $client;
        $this->wikipedia = new Wikipedia($client);
        $this->sources = $sources;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $parts = explode(':', $settings);
        if (
            isset($parts[0]) && !empty($parts[0])
            && strtolower(trim($parts[0])) !== 'false'
        ) {
            $this->useViaf = true;
        }
    }

    /**
     * Set a translator
     *
     * @param TranslatorInterface $translator Translator
     *
     * @return TranslatorAwareInterface
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->setTranslatorThroughTrait($translator);
        $this->wikipedia->setTranslator($translator);
        $this->wikipedia->setLanguage($this->getTranslatorLocale());
        return $this;
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
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // No action needed here.
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->searchObject = $results;
    }

    /**
     * Returns info from Wikipedia to the view
     *
     * @reference _parseWikipedia : Home.php (VuFind 1)
     * @refauthor Rushikesh Katikar <rushikesh.katikar@gmail.com>
     *
     * @return array info = {
     *              'description' : string : extracted/formatted Wikipedia text
     *              'image'       : string : url of the Wikipedia page's image
     *              'altimge'     : string : alt text for the image
     *              'name'        : string : title of Wikipedia article
     *              'wiki_lang'   : string : truncated from the lang. settings
     *           }
     */
    public function getAuthorInfo()
    {
        // Don't load Wikipedia content if Wikipedia is disabled:
        try {
            return stristr($this->sources, 'wikipedia') ? $this->wikipedia->get($this->getAuthor()) : null;
        } catch (Exception $e) {
            error_log("Unexpected error while loading author info: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Normalize an author name using internal logic.
     *
     * @param string $author Author name
     *
     * @return string
     */
    protected function normalizeName($author)
    {
        // remove dates
        $author = preg_replace('/[0-9]+-[0-9]*/', '', $author);
        // if name is rearranged by commas
        $author = trim($author, ', .');
        $nameParts = explode(', ', $author);
        $last = $nameParts[0];
        // - move all names up an index, move last name to last
        // - Last, First M. -> First M. Last
        for ($i = 1; $i < count($nameParts); $i++) {
            $nameParts[$i - 1] = $nameParts[$i];
        }
        $nameParts[count($nameParts) - 1] = $last;
        $author = implode(' ', $nameParts);
        return $author;
    }

    /**
     * Translate an LCCN to a Wikipedia name through the VIAF web service. Returns
     * false if no value can be found.
     *
     * @param string $lccn LCCN
     *
     * @return string|bool
     */
    protected function getWikipediaNameFromViaf($lccn)
    {
        $param = urlencode("LC|$lccn");
        $url = "http://viaf.org/viaf/sourceID/{$param}/justlinks.json";
        $result = $this->client->setUri($url)->setMethod('GET')->send();
        if (!$result->isSuccess()) {
            return false;
        }
        $details = json_decode($result->getBody());
        return $details->WKP[0] ?? false;
    }

    /**
     * Normalize an author name using VIAF.
     *
     * @param string $author Author name
     *
     * @return string
     */
    protected function normalizeNameWithViaf($author)
    {
        // Do authority search:
        $auth = $this->resultsManager->get('SolrAuth');
        $auth->getParams()->setBasicSearch('"' . $author . '"', 'MainHeading');
        $results = $auth->getResults();

        // Find first useful LCCN:
        foreach ($results as $current) {
            $lccn = $current->tryMethod('getRawLCCN');
            if (!empty($lccn)) {
                $name = $this->getWikipediaNameFromViaf($lccn);
                if (!empty($name)) {
                    return $name;
                }
            }
        }

        // No LCCN found?  Use the default normalization routine:
        return $this->normalizeName($author);
    }

    /**
     * Takes the search term and extracts a normal name from it
     *
     * @return string
     */
    protected function getAuthor()
    {
        $search = $this->searchObject->getParams()->getQuery();
        if ($search instanceof Query) {
            $author = $search->getString();
            // remove quotes
            $author = str_replace('"', '', $author);
            return $this->useViaf
                ? $this->normalizeNameWithViaf($author)
                : $this->normalizeName($author);
        }
        return '';
    }
}
