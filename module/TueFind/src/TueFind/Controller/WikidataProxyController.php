<?php

namespace TueFind\Controller;

/**
 * Use Wikidata API to search for specific information (e.g. a picture)
 * Example call: https://ptah.ub.uni-tuebingen.de/wikidataproxy/load?search=Martin%20Luther
 */
class WikidataProxyController extends \VuFind\Controller\AbstractBase
                              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    public function loadAction()
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);

        if (isset($parameters['id'])) {
            $entities = $this->wikidata()->getEntities([$parameters['id']]);
            $entity = $this->getFirstMatchingEntity($entities);
            $image = $this->getImageFromEntity($entity);
            return $this->generateResponse($image);
        } else {
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

            foreach ($searches as $search) {
                try {
                    $entities = $this->wikidata()->searchAndGetEntities($search, $language);
                    $entity = $this->getFirstMatchingEntity($entities, $filters, ['P18']);
                    $image = $this->getImageFromEntity($entity);
                    return $this->generateResponse($image);
                } catch (\Exception $e) {
                    // just continue and search for next image
                    continue;
                }
            }
        }

        throw new \Exception('No image found');
    }

    protected function generateResponse(&$image) {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', $image['mime']);
        // See RFC 5988 + http://www.otsukare.info/2011/07/12/using-http-link-header-for-cc-licenses
        if ($image['licenseUrl'] !== null)
            $response->getHeaders()->addHeaderLine('Link', '<'.$image['licenseUrl'].'>; rel="license"; title="'.$image['license'].'"');
        if ($image['artist'] !== null)
            $response->getHeaders()->addHeaderLine('Artist', $image['artist']);
        $response->setContent($image['image']);
        return $response;
    }

    protected function getImageFromEntity(&$entity) {
        $imageFilename = $entity->claims->P18[0]->mainsnak->datavalue->value ?? null;
        if ($imageFilename == null)
            throw new \Exception('No image found');
        $image = $this->wikidata()->getImage($imageFilename);
        return $image;
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
        foreach ($entities->entities as $entity) {
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
}
