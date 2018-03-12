<?php

namespace VuFind\Config\Provider;

class Classic
{
    public function __invoke()
    {
        $default = new Glob("**/*.ini", APPLICATION_PATH . '/config/vufind/');
        $local = new Glob("**/*.ini", LOCAL_OVERRIDE_DIR . '/config/vufind/');
        return array_replace_recursive($default(), $local());
    }
}
