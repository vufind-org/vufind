<?php

namespace VuFind\Config\Provider\Filter;

use Webmozart\Glob\Glob as Globber;
use Zend\EventManager\Filter\FilterIterator as Chain;

class Glob
{
    public function __invoke($provider, array $patterns = [], Chain $chain)
    {
        $result = array_merge(...array_map([$this, 'load'], $patterns));
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }

    public function load(string $pattern)
    {
        $base = Globber::getBasePath($pattern);
        return array_map(function ($path) use ($pattern, $base) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            return compact('base', 'ext', 'path', 'pattern');
        }, Globber::glob($pattern));
    }
}