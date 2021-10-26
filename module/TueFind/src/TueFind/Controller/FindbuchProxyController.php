<?php

namespace TueFind\Controller;

/**
 * Use Findbuch API to search for BEACON links (via GND number)
 * - Sample API call: https://beacon.findbuch.de/seealso/pnd-aks?format=seealso&id=100001718
 * - API documentation: https://beacon.findbuch.de/seealso/pnd-aks
 */
class FindbuchProxyController extends AbstractProxyController
{
    // Subsections like "/-ixtheo/" mean that the corresponding BEACON file will be ignored.
    const API_URL = 'http://beacon.findbuch.de/seealso/pnd-aks/-archivportal/-ixtheo/-kalliope/-pw_imslp/-pw_discogs/-pw_munzinger_pop/-pw_allmusic/-relbib/-wikidata/-cultword/?format=seealso&id=';
    const CACHE_DIR = '/tmp/proxycache/findbuch';

    public function loadAction()
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);

        if (!isset($parameters['gnd']))
            throw new \Exception('Mandatory parameter "gnd" is missing!');

        $json = $this->callAPI($parameters['gnd']);
        return $this->generateResponse($json);
    }

    protected function callAPI($gndNumber)
    {
        $url = self::API_URL . urlencode($gndNumber);
        $response = $this->getCachedUrlContents($url);
        return $response;
    }

    protected function generateResponse($json) {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->setContent($json);
        return $response;
    }
}
