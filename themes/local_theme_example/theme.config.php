<?php
return [
    'extends' => 'bootstrap3',

    /**
     * The following is all custom attributes for this theme
     * in order to demonstrate the themeConfig view helper.
     */
    'home-content' => [
        'links' => [
            'Setup' => 'install-home',
            'Dev Tools' => 'devtools-home',
        ],
    ],
    'header-nav' => parse_ini_file('header-nav.ini', true),
    'extends' => 'bootstrap3',
    'mixins' => [
        'local_mixin_example'
    ],
];
