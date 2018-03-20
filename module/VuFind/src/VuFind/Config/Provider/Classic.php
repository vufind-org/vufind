<?php

namespace VuFind\Config\Provider;

use VuFind\Config\Provider\Filter;

class Classic extends Basic
{
    public function __construct(array $patterns)
    {
        parent::__construct($patterns);
        $this->attach(new Filter\FlatIni, 3500000);
        $this->attach(new Filter\ParentIni, 2500000);
        $this->attach(new Filter\ParentYaml, 2500000);
        $this->attach(new Filter\UniqueSuffix, 1500000);
    }
}