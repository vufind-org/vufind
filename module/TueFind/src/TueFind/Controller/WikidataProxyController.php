<?php

namespace TueFind\Controller;

/**
 * Use Wikidata API to search for specific information (e.g. a picture)
 */
class WikidataProxyController extends \VuFind\Controller\AbstractBase
                              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    // https://ptah.ub.uni-tuebingen.de/wikidataproxy/load?search=Martin%20Luther
    public function loadAction()
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);

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
                $imgBinary = $this->searchImage($search, $language, $filters, $mandatoryFields);
                $response = $this->getResponse();
                $response->getHeaders()->addHeaderLine('Content-Type', 'image/jpeg');
                $response->setContent($imgBinary);
                return $response;
            } catch (\Exception $e) {
                // just continue and search for next image
                continue;
            }
        }

        throw new \Exception('No image found');
    }

    protected function searchImage($search, $language, $filters=[], $mandatoryFields=[]) {
        $entities = $this->wikidata()->searchAndGetEntities($search, $language);
        $entity = $this->getFirstMatchingEntity($entities, $filters, ['P18']);
        $imgFilename = $entity->claims->P18[0]->mainsnak->datavalue->value;
        $imgBinary = $this->wikidata()->getImage($imgFilename);
        return $imgBinary;
    }

    /**
     * Get first matching element for a single entry
     *
     * @param json $entities
     * @param array $filters
     * @param array $mandatoryFields
     *
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
