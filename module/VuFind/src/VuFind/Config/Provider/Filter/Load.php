<?php

namespace VuFind\Config\Provider\Filter;

use VuFind\Config\Factory;
use Zend\EventManager\Filter\FilterIterator as Chain;

class Load
{
    public function __invoke($provider, array $items, Chain $chain): array
    {
        $result = array_map([$this, 'load'], $items);
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }

    protected function load(array $item): array
    {
        $data = Factory::fromFile($item['path']);
        return array_merge($item, compact('data'));
    }
}