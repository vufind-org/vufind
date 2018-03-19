<?php

namespace VuFind\Config\Filter;

use Zend\Config\Factory;
use Zend\EventManager\Filter\FilterIterator;

class Load
{
    public function __invoke($ctx, array $args, FilterIterator $chain): array
    {
        list($path) = $args;
        $data = Factory::fromFile($path);
        return $chain->isEmpty() ? $data
            : $chain->next($ctx, [$path, $data], $chain);
    }
}