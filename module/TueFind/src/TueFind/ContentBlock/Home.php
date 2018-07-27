<?php

namespace TueFind\ContentBlock;

class Home implements \VuFind\ContentBlock\ContentBlockInterface
{
    /**
     * Target selector for status message.
     *
     * @var string
     */
    protected $target = '.searchHomeContent';

    /**
     * Store the configuration of the content block.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $this->target = empty($settings) ? $this->target : $settings;
    }

    /**
     * Return context variables used for rendering the block's template.
     *
     * @return array
     */
    public function getContext()
    {
        // Expose the block object directly by default.
        return ['target' => $this->target];
    }
}
