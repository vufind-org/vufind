<?php

namespace TueFind\ContentBlock;

use Zend\Config\Config;
use Zend\Http\Request as Request;
use VuFind\Config\PluginManager as ConfigManager;
use VuFind\ContentBlock\PluginManager as BlockManager;
use VuFind\Search\Base\Options;
use VuFind\Search\Options\PluginManager as OptionsManager;

class BlockLoader extends \VuFind\ContentBlock\BlockLoader
{

    public function __construct(OptionsManager $om, ConfigManager $cm,
        BlockManager $bm, Request $request
    ) {
        $this->optionsManager = $om;
        $this->configManager = $cm;
        $this->blockManager = $bm;
        $this->request = $request;
    }


    public function getFromConfigObject(Config $config, $section = 'HomePage',
        $setting = 'content'
    ) {
        $blocks = parent::getFromConfigObject($config, $section, $setting);
        $blocks2 = [];
        foreach ($blocks as $block) {
            if ($block instanceof RequestAwareInterface) {
                $block->setRequest($this->request);
            }
            $blocks2[] = $block;
        }
        return $blocks2;
    }
}
