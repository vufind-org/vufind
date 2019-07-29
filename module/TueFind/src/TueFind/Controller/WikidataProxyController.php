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

        $search = $parameters['search'];
        $language = $this->getTranslatorLocale();

        $entities = $this->wikidata()->searchAndGetEntities($search, $language);

        // P18: image
        // P569: birthYear
        // P570: deathYear
        $filters = [];
        if (isset($parameters['birthYear']))
            $filters['P569'] = ['value' => $parameters['birthYear'], 'type' => 'year'];
        if (isset($parameters['deathYear']))
            $filters['P570'] = ['value' => $parameters['deathYear'], 'type' => 'year'];

        $entity = $this->getFirstMatchingEntity($entities, $filters, ['P18']);
        $imgFilename = $entity->claims->P18[0]->mainsnak->datavalue->value;
        $imgBinary = $this->wikidata()->getImage($imgFilename);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'image/jpeg');
        $response->setContent($imgBinary);
        return $response;
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
