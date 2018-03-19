<?php

namespace VuFind\Config\Provider;

class Standard
{
    public function __invoke()
    {
        $dirs = [APPLICATION_PATH . '/config/vufind'];
        if (LOCAL_OVERRIDE_DIR) {
            $dirs[] = LOCAL_OVERRIDE_DIR . '/config/vufind';
        }
        return (new Classic(...$dirs))();
    }
}