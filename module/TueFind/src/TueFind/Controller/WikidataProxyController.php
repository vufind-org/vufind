<?php

namespace TueFind\Controller;

/**
 * Use Wikidata API to search for specific information (e.g. a picture)
 */
class WikidataProxyController extends \VuFind\Controller\AbstractBase
                              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    const API_URL = 'https://www.wikidata.org/w/api.php?action=wbsearchentities&format=json';

    // https://ptah.ub.uni-tuebingen.de/wikidataproxy/load?search=Martin%20Luther
    public function loadAction()
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);

        $search = $parameters['search'];
        $language = $this->getTranslatorLocale();
        $apiUrl = self::API_URL . '&search=' . urlencode($search) . '&language=' . $language;
        $match = $this->performSearch($apiUrl, '//img');

        $response = $this->getResponse();
        if ($match !== null) {
            $srcUrl = 'http:' . $match->getAttribute('src');
            $imgBinary = file_get_contents($srcUrl);
            $response->getHeaders()->addHeaderLine('Content-Type', 'image/jpeg');
            $response->setContent($imgBinary);
        }
        return $response;
    }

    /**
     * Query API & Search for first specified element in result list
     *
     * @param string $apiUrl
     * @param string $xpathString
     * @return \DOMElement
     */
    protected function performSearch($apiUrl, $xpathString) {
        $response = file_get_contents($apiUrl);
        if ($response) {
            $json = json_decode($response);
            if ($json && $json->success == 1) {
                foreach ($json->search as $entry) {
                    $entryUrl = 'https:' . $entry->url;
                    $match = $this->getFirstMatchingElement($entryUrl, $xpathString);
                    if ($match !== null)
                        return $match;
                }
            }
        }
        return null;
    }

    /**
     * Get first matching element for a single entry
     *
     * @param string $entryUrl
     * @param string $xpathString
     *
     * @return \DOMElement
     */
    protected function getFirstMatchingElement($entryUrl, $xpathString) {
        $html = file_get_contents($entryUrl);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->recover = true;
        $dom->strictErrorChecking = false;
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $matches = $xpath->query($xpathString);
        return $matches->item(0);
    }
}
