<?php

namespace TueFind\Search\SolrAuthorFacets;

class Params extends \VuFind\Search\SolrAuthorFacets\Params
{
    protected $authorId;

    public function getBackendParametersAuthorAndIdFacet()
    {
        $backendParams = $this->getBackendParameters();

        // TueFind: Use author_id_and_facet instead of regular parameters
        $backendParams->remove('fl');
        $backendParams->add('fl', 'id,author_and_id_facet');
        $backendParams->remove('facet.field');
        $backendParams->add('facet.field', 'author_and_id_facet');

        return $backendParams;
    }

    public function getQuery()
    {
        $query = parent::getQuery();
        if ($this->authorId != null)
            $query->setString('author_id:' . $this->authorId . ' OR author2_id=' . $this->authorId . ' OR author_corporate_id=' . $this->authorId);
        return $query;
    }

    public function initFromRequest($request)
    {
        parent::initFromRequest($request);
        $this->authorId = $request->get('author_id');
    }
}
