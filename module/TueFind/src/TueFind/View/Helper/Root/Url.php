<?php

namespace TueFind\View\Helper\Root;

class Url extends \VuFind\View\Helper\Root\Url
{
    public function addQueryParametersToAuthority($params, $reuseMatchedParams = true)
    {

        $requestQuery = (null !== $this->request)
              ? $this->request->getQuery()->toArray() : [];

        if(isset($requestQuery['id'])) {
            unset($requestQuery['id']);
        }
        
        $options = [
            'query' => array_merge($requestQuery, $params),
            'normalize_path' => false, // fix for VUFIND-1392
        ];
        return $this->__invoke(null, [], $options, $reuseMatchedParams);
    }
}
