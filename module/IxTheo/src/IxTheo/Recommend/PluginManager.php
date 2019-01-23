<?php

namespace IxTheo\Recommend;

class PluginManager extends \VuFind\Recommend\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['bibleranges'] = 'IxTheo\Recommend\BibleRanges';
        $this->factories['IxTheo\Recommend\BibleRanges'] = 'Zend\ServiceManager\Factory\InvokableFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
