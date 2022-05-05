<?php

namespace TueFind\Controller;

/**
 * Use Wikidata API to search for specific information (e.g. a picture)
 * - Example call: https://ptah.ub.uni-tuebingen.de/wikidataproxy/load?search=Martin%20Luther
 * - For documentation, see: https://www.wikidata.org/w/api.php
 */
class WikidataProxyController extends AbstractProxyController
                              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    const API_URL = 'https://www.wikidata.org/w/api.php?format=json';
    const CACHE_ID = 'wikidata';

    public function loadAction()
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);

        if (isset($parameters['id'])) {
            try {
                $entities = $this->getEntities([$parameters['id']]);
                $entity = $this->getFirstMatchingEntity($entities);
                $image = $this->getBestImageFromEntity($entity);
                return $this->generateResponse($image);
            } catch (\Exception $e) {
                // return proper status code, see end of this function
            }
        } else {
            if (!isset($parameters['search']))
                throw new \VuFind\Exception\BadRequest('Invalid request parameters.');

            $searches = $parameters['search'];
            if (!is_array($searches))
                $searches = [$searches];
            $language = $this->getTranslatorLocale();

            // P18: image
            // P569: birthYear
            // P570: deathYear
            $mandatoryFields = ['P18'];
            $filters = [];
            if (isset($parameters['birthYear']))
                $filters['P569'] = ['value' => $parameters['birthYear'], 'type' => 'year'];
            if (isset($parameters['deathYear']))
                $filters['P570'] = ['value' => $parameters['deathYear'], 'type' => 'year'];

            if (count($filters) == 0)
                throw new \Exception('No suitable image found (at least one additional filter must be given!)');

            foreach ($searches as $search) {
                try {
                    $entities = $this->searchAndGetEntities($search, $language);
                    $entity = $this->getFirstMatchingEntity($entities, $filters, ['P18']);
                    $image = $this->getBestImageFromEntity($entity);
                    return $this->generateResponse($image);
                } catch (\Exception $e) {
                    // just continue and search for next image
                    continue;
                }
            }
        }

        $this->getResponse()->setStatusCode(404);
    }

    protected function normalizeHeaderContent($artist) {
        // We use htmlspecialchars_decode(htmlentities()) because HTTP headers only support ASCII.
        // This way we can keep HTML special characters without breaking non-ascii-characters.
        // It is necessary to set ENT_HTML5 instead of default ENT_HTML401,
        // because the entity table is a lot bigger (also contains e.g. cyrillic entities).
        // See also: get_html_translation_table
        return htmlspecialchars_decode(htmlentities(preg_replace("'(\r?\n)+'", ', ', trim(strip_tags($artist))), ENT_COMPAT | ENT_HTML5));
    }

    protected function generateResponse(&$image) {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', $image['mime']);
        // See RFC 5988 + http://www.otsukare.info/2011/07/12/using-http-link-header-for-cc-licenses
        if (isset($image['licenseUrl']))
            $response->getHeaders()->addHeaderLine('Link', htmlspecialchars_decode(htmlentities('<'.$image['licenseUrl'].'>; rel="license"; title="' . $this->normalizeHeaderContent($image['license']) . '"')));
        if (isset($image['artist']))
            $response->getHeaders()->addHeaderLine('Artist', $this->normalizeHeaderContent($image['artist']));
        $response->setContent($image['image']);
        return $response;
    }

    protected function getBestImageFromEntity(&$entity) {
        $images = $entity->claims->P18 ?? [];
        foreach ($images as $image) {
            $imageFilename = $image->mainsnak->datavalue->value ?? null;

            // TIFFs will be skipped, since they are not supported in Firefox+Chrome
            // Example: Helmut Kohl
            if (preg_match('"\.tiff?$"i', $imageFilename))
                continue;

            return $this->getImage($imageFilename);
        }

        throw new \Exception('No suitable image found');
    }

    /**
     * Get first matching element for a single entry
     *
     * @param json $entities
     * @param array $filters
     * @param array $mandatoryFields
     * @return \DOMElement or null if not found
     */
    protected function getFirstMatchingEntity(&$entities, $filters=[], $mandatoryFields=[]) {
        foreach ($entities->entities ?? [] as $entity) {
            $skip = false;

            // must have values
            foreach ($mandatoryFields as $field) {
                if (!isset($entity->claims->$field[0]->mainsnak->datavalue->value)) {
                    $skip = true;
                    break;
                }
            }

            // filters
            foreach ($filters as $field => $filterProperties) {
                // filters
                if (!isset($entity->claims->$field)) {
                    $skip = true;
                    break;
                }
                else {
                    foreach ($entity->claims->$field as $fieldValue) {
                        $compareValue = $fieldValue->mainsnak->property;
                        if ($filterProperties['type'] == 'year') {
                            $compareValue = $fieldValue->mainsnak->datavalue->value->time;
                            $compareValue = date('Y', strtotime($compareValue));
                        }

                        if ($compareValue != $filterProperties['value']) {
                            $skip = true;
                            break;
                        }
                    }
                }
            }

            if (!$skip)
                return $entity;
        }

        throw new \Exception('No valid entity found');
    }

    /**
     * Search for entities and get metadata of all found entities
     * (needs multiple API calls)
     *
     * @param type $search
     * @param type $language
     * @return object
     */
    public function searchAndGetEntities($search, $language) {
        $entities = $this->searchEntities($search, $language);
        $ids = [];
        foreach($entities->search as $entity) {
            $ids[] = $entity->id;
        }
        return $this->getEntities($ids);
    }

    /**
     * Search for entities and return a short metadata array
     * (wrapper for "wbsearchentities")
     *
     * @param string $search
     * @param string $language
     * @return object
     */
    public function searchEntities($search, $language) {
        $url = self::API_URL . '&action=wbsearchentities&search=' . urlencode($search) . '&language=' . $language;
        return $this->getCachedUrlContents($url, true);
    }

    /**
     * Get detailed metadata for objects with given IDs
     * (wrapper for "wbgetentities")
     *
     * @param array $ids
     * @return object
     */
    public function getEntities($ids) {
        $url = self::API_URL . '&action=wbgetentities&ids=' . urlencode(implode('|', $ids));
        return $this->getCachedUrlContents($url, true);
    }

    /**
     * Get image (binary contents + metadata) by a given unique filename
     *
     * @param string $filename
     * @return array
     */
    public function getImage($filename) {
        $metadata = $this->getImageMetadata($filename);
        $metadata['image'] = $this->getCachedUrlContents($metadata['url']);
        return $metadata;
    }

    /**
     * Get image metadata by a given unique filename
     *
     * @param string $filename
     * @return array
     */
    public function getImageMetadata($filename) {
        $lookupUrl = self::API_URL . '&action=query&prop=imageinfo&iiprop=url|mime|extmetadata&titles=File:' . urlencode($filename);
        $lookupResult = $this->getCachedUrlContents($lookupUrl, true);
        $subindex = '-1';

        $imageInfo = $lookupResult->query->pages->$subindex->imageinfo[0] ?? null;

        $imageUrl = $imageInfo->url ?? null;
        if ($imageUrl === null)
            throw new \Exception('Image URL could not be found for: ' . $filename);

        $mime = $imageInfo->mime;
        if ($mime === null)
            throw new \Exception('Mime type could not be found for: ' . $filename);

        $license = $imageInfo->extmetadata->LicenseShortName->value ?? null;
        if ($license === null)
            throw new \Exception('License could not be found for: ' . $filename);

        if (!preg_match('"^Public domain|CC "i', $license))
            throw new \Exception('Image not usable due to license restrictions (' . $license . '): ' . $filename);

        $licenseUrl = $imageInfo->extmetadata->LicenseUrl->value ?? null;
        if (!preg_match('"^Public domain$"i', $license) && $licenseUrl === null)
            throw new \Exception('License URL could not be found for: ' . $filename);

        $artist = $imageInfo->extmetadata->Artist->value ?? null;
        if ($artist === null)
            throw new \Exception('Artist could not be found for: ' . $filename);

        return ['url' => $imageUrl,
                'mime' => $mime,
                'license' => $license,
                'licenseUrl' => $licenseUrl,
                'artist' => $artist];
    }

    /**
     * Wikidata URLs must be resolved with a special content, else you might get the following error:
     * - HTTP/1.0 429 Too many requests. Please comply with the User-Agent policy: https://meta.wikimedia.org/wiki/User-Agent_policy
     *
     * Since this might be useful for other URLs as well, we generate the user agent for all proxy requests.
     *
     * (override parent function)
     */
    protected function getUrlContents($url) {
        $config = $this->getConfig();

        $siteTitle = $config->Site->title;
        $siteUrl = $config->Site->url;
        $siteEmail = $config->Site->email;

        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: " . $siteTitle . "/1.0 (" . $siteUrl . "; " . $siteEmail . ")\r\n"
            ]
        ];

        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }
}
