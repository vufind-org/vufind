<?php

namespace VuFind\Config\Filter;

use VuFind\Config\Manager;
use Zend\EventManager\Filter\FilterIterator;

class FlatIni
{
    public function __invoke($ctx, array $args, FilterIterator $chain): array
    {
        $sep = Manager::$iniReader->getNestSeparator();
        Manager::$iniReader->setNestSeparator(chr(0));
        $data = $chain->next($ctx, $args, $chain);
        Manager::$iniReader->setNestSeparator($sep);
        return $data;
    }
}