<?php

namespace VuFind\Config\Provider\Filter;

use VuFind\Config\Factory;
use Zend\EventManager\Filter\FilterIterator as Chain;

class FlatIni
{
    public function __invoke($provider, array $items, Chain $chain): array
    {
        $iniReader = Factory::getIniReader();
        $separator = $iniReader->getNestSeparator();
        $iniReader->setNestSeparator(chr(0));
        $result = $chain->next($provider, $items, $chain);
        $iniReader->setNestSeparator($separator);
        return $result;
    }
}