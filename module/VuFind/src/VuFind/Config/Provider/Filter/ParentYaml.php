<?php

namespace VuFind\Config\Provider\Filter;

use VuFind\Config\Factory;
use Zend\EventManager\Filter\FilterIterator as Chain;

class ParentYaml
{
    public function __invoke($provider, array $items, Chain $chain): array
    {
        $result = array_map([$this, 'process'], $items);
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }

    protected function process(array $item): array
    {
        if ($item['ext'] !== 'yaml') {
            return $item;
        }
        $data = $this->mergeParent($item['data']);
        return array_merge($item, compact('data'));
    }

    protected function mergeParent(array $child)
    {
        if (!isset($child['@parent_yaml'])) {
            return $child;
        }
        $parent = Factory::fromFile($child['@parent_yaml']);
        unset($child['@parent_yaml']);
        return $this->mergeParent(array_replace($parent, $child));
    }
}