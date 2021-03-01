<?php

namespace IxTheo\Recommend;

use Laminas\ServiceManager\Factory\InvokableFactory;

class PluginManager extends \TueFind\Recommend\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['bibleranges'] = BibleRanges::class;
        $this->factories[BibleRanges::class] = InvokableFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
