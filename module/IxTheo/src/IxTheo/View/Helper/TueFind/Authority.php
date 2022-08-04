<?php

namespace IxTheo\View\Helper\TueFind;

class Authority extends \TueFind\View\Helper\TueFind\Authority {

    public function getTopicsCloudFieldname($translatorLocale=null): string
    {
        return 'topic_cloud_' . $translatorLocale;
    }
}
