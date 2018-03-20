<?php

namespace VuFind\Config\Provider;

class Standard extends Classic
{
    public function __construct()
    {
        $pattern = '/config/vufind/**/*.{ini,yaml,json}';
        $patterns[] = APPLICATION_PATH . $pattern;
        if (LOCAL_OVERRIDE_DIR) {
            $patterns[] = LOCAL_OVERRIDE_DIR . $pattern;
        }
        parent::__construct($patterns);
    }
}