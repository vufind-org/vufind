<?php

namespace VuFind\Config\Provider;

use VuFind\Config\Provider\Filter;
use Zend\ConfigAggregator\ConfigAggregator;

class Basic extends Base
{
    public function __construct(array $patterns)
    {
        parent::__construct($patterns);
        $this->attach(new Filter\Glob, 4000000);
        $this->attach(new Filter\Load, 3000000);
        $this->attach(new Filter\Nest, 2000000);
        $this->attach(new Filter\Merge, 1000000);
    }

    public function __invoke(): array
    {
        $cacheOpts = [ConfigAggregator::ENABLE_CACHE => true];
        return array_replace($cacheOpts, parent::__invoke());
    }
}