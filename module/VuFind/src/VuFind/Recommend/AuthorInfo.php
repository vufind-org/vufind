<?php
/**
 * AuthorInfo Recommendations Module
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
 * @package  Recommendations
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * AuthorInfo Recommendations Module
 *
 * This class gathers information from the Wikipedia API and publishes the results
 * to a module at the top of an author's results page
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 * @view     AuthorInfoFacets.phtml
 */
class AuthorInfo implements RecommendInterface, TranslatorAwareInterface
{
    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * Translator (or null if unavailable)
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = null;

    /**
     * Saved search results
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $searchObject;

    /**
     * Selected language
     *
     * @var string
     */
    protected $lang;

    /**
     * Search manager
     *
     * @var \VuFind\Search\Manager
     */
    protected $searchManager;

    /**
     * Should we use VIAF for authorized names?
     *
     * @var bool
     */
    protected $useViaf = false;

    /**
     * Log of Wikipedia pages already retrieved
     *
     * @var array
     */
    protected $pagesRetrieved = array();

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
     * @param \VuFind\Search\Manager $searchManager Search manager
     * @param \Zend\Http\Client      $client        HTTP client
     * @param string                 $sources       Source identifiers (currently,
     * only 'wikipedia' is supported)
     */
    public function __construct(\VuFind\Search\Manager $searchManager,
        \Zend\Http\Client $client, $sources = 'wikipedia'
    ) {
        $this->searchManager = $searchManager;
        $this->client = $client;
        $this->sources = $sources;
    }

    /**
     * setConfig
     *
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $translator = $this->getTranslator();
        $this->lang = is_object($translator) ? $translator->getLocale() : 'en';

        $parts = explode(':', $settings);
        if (isset($parts[0]) && !empty($parts[0])
            && strtolower(trim($parts[0])) !== 'false'
        ) {
            $this->useViaf = true;
        }
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return AuthorInfo
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Get translator object.
     *
     * @return \Zend\I18n\Translator\Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * init
     *
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // No action needed here.
    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
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
     * Check if a page has already been retrieved; if it hasn't, flag it as
     * retrieved for future reference.
     *
     * @param string $author Author being retrieved
     *
     * @return bool
     */
    protected function alreadyRetrieved($author)
    {
        if (isset($this->pagesRetrieved[$author])) {
            return true;
        }
        $this->pagesRetrieved[$author] = true;
        return false;
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
        return stristr($this->sources, 'wikipedia')
            ? $this->getWikipedia($this->getAuthor()) : null;
    }

    /**
     * getWikipedia
     *
     * This method is responsible for connecting to Wikipedia via the REST API
     * and pulling the content for the relevant author.
     *
     * @param string $author The author name to search for
     *
     * @return array
     */
    protected function getWikipedia($author)
    {
        // Don't retrieve the same page multiple times; this indicates a loop
        // that needs to be broken!
        if ($this->alreadyRetrieved($author)) {
            return array();
        }

        // Get information from Wikipedia API
        $uri = 'http://' . $this->lang . '.wikipedia.org/w/api.php' .
               '?action=query&prop=revisions&rvprop=content&format=php' .
               '&list=allpages&titles=' . urlencode($author);

        $response = $this->client->setUri($uri)->setMethod('GET')->send();
        if ($response->isSuccess()) {
            return $this->parseWikipedia(unserialize($response->getBody()));
        }
        return null;
    }

    /**
     * _parseWikipedia
     *
     * This method is responsible for parsing the output from the Wikipedia
     * REST API.
     *
     * @param string $body The Wikipedia response to parse
     *
     * @return array
     * @author Rushikesh Katikar <rushikesh.katikar@gmail.com>
     */
    protected function parseWikipedia($body)
    {
        // Check if data exists or not
        if (isset($body['query']['pages']['-1'])) {
            return null;
        }

        // Loop through the pages and find the first that isn't a redirect:
        foreach ($body['query']['pages'] as $page) {
            $info['name'] = $page['title'];

            // Get the latest revision
            $page = array_shift($page['revisions']);
            // Check for redirection
            $as_lines = explode("\n", $page['*']);
            if (stristr($as_lines[0], '#REDIRECT')) {
                preg_match('/\[\[(.*)\]\]/', $as_lines[0], $matches);
                $redirectTo = $matches[1];
            } else {
                $redirectTo = false;
                break;
            }
        }

        // Recurse if we only found redirects:
        if ($redirectTo) {
            return $this->getWikipedia($redirectTo);
        }
        $body = $page;

        /* Infobox */

        // We are looking for the infobox inside "{{...}}"
        //   It may contain nested blocks too, thus the recursion
        preg_match_all('/\{([^{}]++|(?R))*\}/s', $body['*'], $matches);
        // print "<p>".htmlentities($body['*'])."</p>\n";
        foreach ($matches[1] as $m) {
            // If this is the Infobox
            if (substr($m, 0, 8) == "{Infobox") {
                // Keep the string for later, we need the body block that follows it
                $infoboxStr = "{".$m."}";
                // Get rid of the last pair of braces and split
                $infobox = explode("\n|", substr($m, 1, -1));
                // Look through every row of the infobox
                foreach ($infobox as $row) {
                    $data  = explode("=", $row);
                    $key   = trim(array_shift($data));
                    $value = trim(join("=", $data));

                    // At the moment we only want stuff related to the image.
                    switch (strtolower($key)) {
                    case "img":
                    case "image":
                    case "image:":
                    case "image_name":
                        $imageName = str_replace(' ', '_', $value);
                        break;
                    case "caption":
                    case "img_capt":
                    case "image_caption":
                        $image_caption = $value;
                        break;
                    default:
                        /* Nothing else... yet */
                        break;
                    }
                }
            }
        }

        /* Image */

        // If we didn't successfully extract an image from the infobox, let's see if
        // we can find one in the body -- we'll just take the first match:
        if (!isset($imageName)) {
            $pattern = '/(\x5b\x5b)Image:([^\x5d]*)(\x5d\x5d)/U';
            preg_match_all($pattern, $body['*'], $matches);
            if (isset($matches[2][0])) {
                $parts = explode('|', $matches[2][0]);
                $imageName = str_replace(' ', '_', $parts[0]);
                if (count($parts) > 1) {
                    $image_caption = strip_tags(
                        preg_replace('/({{).*(}})/U', '', $parts[count($parts) - 1])
                    );
                }
            }
        }

        // Given an image name found above, look up the associated URL:
        if (isset($imageName)) {
            $imageUrl = $this->getWikipediaImageURL($imageName);
        }

        /* Body */

        if (isset($infoboxStr)) {
            // Start of the infobox
            $start  = strpos($body['*'], $infoboxStr);
            // + the length of the infobox
            $offset = strlen($infoboxStr);
            // Every after the infobox
            $body   = substr($body['*'], $start + $offset);
        } else {
            // No infobox -- use whole thing:
            $body = $body['*'];
        }
        // Find the first heading
        $end    = strpos($body, "==");
        // Now cull our content back to everything before the first heading
        $body   = trim(substr($body, 0, $end));

        // Remove unwanted image/file links
        // Nested brackets make this annoying: We can't add 'File' or 'Image' as
        //    mandatory because the recursion fails, or as optional because then
        //    normal links get hit.
        //    ... unless there's a better pattern? TODO
        // eg. [[File:Johann Sebastian Bach.jpg|thumb|Bach in a 1748 portrait by
        //     [[Elias Gottlob Haussmann|Haussmann]]]]
        $open    = "\\[";
        $close   = "\\]";
        $content = "(?>[^\\[\\]]+)";  // Anything but [ or ]
        // We can either find content or recursive brackets:
        $recursive_match = "($content|(?R))*";
        preg_match_all("/".$open.$recursive_match.$close."/Us", $body, $new_matches);
        // Loop through every match (link) we found
        if (is_array($new_matches)) {
            foreach ($new_matches as $nm) {
                // Might be an array of arrays
                if (is_array($nm)) {
                    foreach ($nm as $n) {
                        // If it's a file link get rid of it
                        if (strtolower(substr($n, 0, 7)) == "[[file:"
                            || strtolower(substr($n, 0, 8)) == "[[image:"
                        ) {
                            $body = str_replace($n, "", $body);
                        }
                    }
                } else {
                    // Or just a normal array...
                    // If it's a file link get rid of it
                    if (strtolower(substr($n, 0, 7)) == "[[file:"
                        || strtolower(substr($n, 0, 8)) == "[[image:"
                    ) {
                        $body = str_replace($nm, "", $body);
                    }
                }
            }
        }

        // Initialize arrays of processing instructions
        $pattern = array();
        $replacement = array();

        // Convert wikipedia links
        $pattern[] = '/(\x5b\x5b)([^\x5d|]*)(\x5d\x5d)/Us';
        $replacement[]
            = '<a href="___baseurl___?lookfor=%22$2%22&amp;type=AllFields">$2</a>';
        $pattern[] = '/(\x5b\x5b)([^\x5d]*)\x7c([^\x5d]*)(\x5d\x5d)/Us';
        $replacement[]
            = '<a href="___baseurl___?lookfor=%22$2%22&amp;type=AllFields">$3</a>';

        // Fix pronunciation guides
        $pattern[] = '/({{)pron-en\|([^}]*)(}})/Us';
        $replacement[] = $this->getTranslator()->translate("pronounced") . " /$2/";

        // Fix dashes
        $pattern[] = '/{{ndash}}/';
        $replacement[] = ' - ';

        // Removes citations
        $pattern[] = '/({{)[^}]*(}})/Us';
        $replacement[] = "";
        //  <ref ... > ... </ref> OR <ref> ... </ref>
        $pattern[] = '/<ref[^\/]*>.*<\/ref>/Us';
        $replacement[] = "";
        //    <ref ... />
        $pattern[] = '/<ref.*\/>/Us';
        $replacement[] = "";

        // Removes comments followed by carriage returns to avoid excess whitespace
        $pattern[] = '/<!--.*-->\n*/Us';
        $replacement[] = '';

        // Formatting
        $pattern[] = "/'''([^']*)'''/Us";
        $replacement[] = '<strong>$1</strong>';

        // Trim leading newlines (which can result from leftovers after stripping
        // other items above).  We want this to be greedy.
        $pattern[] = '/^\n*/s';
        $replacement[] = '';

        // Convert multiple newlines into two breaks
        // We DO want this to be greedy
        $pattern[] = "/\n{2,}/s";
        $replacement[] = '<br/><br/>';

        $body = preg_replace($pattern, $replacement, $body);

        if (isset($imageUrl) && $imageUrl != false) {
            $info['image'] = $imageUrl;
            if (isset($image_caption)) {
                $info['altimage'] = $image_caption;
            }
        }
        $info['description'] = $body;
        $info['wiki_lang'] = $this->lang;

        return $info;
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
        for ($i=1;$i<count($nameParts);$i++) {
            $nameParts[$i-1] = $nameParts[$i];
        }
        $nameParts[count($nameParts)-1] = $last;
        $author = implode($nameParts, ' ');
        return $author;
    }

    /**
     * Translate an LCCN to a Wikipedia name through the VIAF web service.  Returns
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
        return isset($details->WKP[0]) ? $details->WKP[0] : false;
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
        $auth = $this->searchManager->setSearchClassId('SolrAuth')->getResults();
        $auth->getParams()->setBasicSearch('"' . $author . '"', 'MainHeading');
        $results = $auth->getResults();

        // Find first useful LCCN:
        foreach ($results as $i => $current) {
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
        $search = $this->searchObject->getParams()->getSearchTerms();
        if (isset($search[0]['lookfor'])) {
            $author = $search[0]['lookfor'];
            // remove quotes
            $author = str_replace('"', '', $author);
            return $this->useViaf
                ? $this->normalizeNameWithViaf($author)
                : $this->normalizeName($author);
        }
        return '';
    }

    /**
     * This method is responsible for obtaining an image URL based on a name.
     *
     * @param string $imageName The image name to look up
     *
     * @return mixed            URL on success, false on failure
     */
    protected function getWikipediaImageURL($imageName)
    {
        $url = "http://{$this->lang}.wikipedia.org/w/api.php" .
               '?prop=imageinfo&action=query&iiprop=url&iiurlwidth=150&format=php' .
               '&titles=Image:' . urlencode($imageName);

        try {
            $result = $this->client->setUri($url)->setMethod('GET')->send();
        } catch (\Exception $e) {
            return false;
        }
        if (!$result->isSuccess()) {
            return false;
        }

        if ($response = $result->getBody()) {
            if ($imageinfo = unserialize($response)) {
                if (isset($imageinfo['query']['pages']['-1']['imageinfo'][0]['url'])
                ) {
                    $imageUrl
                        = $imageinfo['query']['pages']['-1']['imageinfo'][0]['url'];
                }

                // Hack for wikipedia api, just in case we couldn't find it
                //   above look for a http url inside the response.
                if (!isset($imageUrl)) {
                    preg_match('/\"http:\/\/(.*)\"/', $response, $matches);
                    if (isset($matches[1])) {
                        $imageUrl = 'http://' .
                            substr($matches[1], 0, strpos($matches[1], '"'));
                    }
                }
            }
        }

        return isset($imageUrl) ? $imageUrl : false;
    }
}