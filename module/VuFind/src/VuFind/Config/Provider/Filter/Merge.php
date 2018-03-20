<?php

namespace VuFind\Config\Provider\Filter;

use Zend\EventManager\Filter\FilterIterator as Chain;

class Merge
{
    public function __invoke($provider, array $items, Chain $chain)
    {
        $data = array_column($items, 'data');
        $result = array_replace_recursive(...$data);
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }
}