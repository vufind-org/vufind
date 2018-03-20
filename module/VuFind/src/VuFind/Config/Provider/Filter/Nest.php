<?php

namespace VuFind\Config\Provider\Filter;

use Zend\EventManager\Filter\FilterIterator as Chain;

class Nest
{
    public function __invoke($provider, array $items, Chain $chain): array
    {
        $result = array_map([$this, 'nest'], $items);
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }

    protected function nest(array $item)
    {
        $baseLen = strlen($item['base']) + 1;
        $path = substr_replace($item['path'], "", 0, $baseLen);
        $offset = strlen(pathinfo($path, PATHINFO_EXTENSION)) + 1;
        $path = trim(substr_replace($path, '', -$offset), '/');
        foreach (array_reverse(explode('/', $path)) as $key) {
            $item['data'] = [$key => $item['data']];
        }
        return $item;
    }
}