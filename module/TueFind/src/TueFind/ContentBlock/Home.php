<?php

namespace TueFind\ContentBlock;

class Home implements \VuFind\ContentBlock\ContentBlockInterface, RequestAwareInterface
{
    protected $target = '.searchHomeContent';
    protected $request;

    public function setConfig($settings)
    {
        $this->target = empty($settings) ? $this->target : $settings;
    }

    public function setRequest(\Laminas\Http\Request $request)
    {
        $this->request = $request;
    }

    public function getContext()
    {
        // subpage mechanics are necessary to have pages without a html container,
        // e.g. other Home-like pages with panels only and full-width backgrounds.
        $subpage = $this->request->getQuery('subpage', 'Home');

        if ($subpage != 'Home') {
            $subpage = 'subpage/' . $subpage;
        }

        // Expose the block object directly by default.
        return ['target' => $this->target,
                'subpage' => $subpage ];
    }
}
