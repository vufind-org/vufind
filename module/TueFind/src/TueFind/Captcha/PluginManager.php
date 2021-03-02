<?php

namespace TueFind\Captcha;

class PluginManager extends \VuFind\Captcha\PluginManager
{
    public function __construct($configOrContainerInstance = null,
        array $v3config = [])
    {
        parent::__construct($configOrContainerInstance, $v3config);
        $this->factories[\VuFind\Captcha\Image::class] = ImageFactory::class;
    }
}
