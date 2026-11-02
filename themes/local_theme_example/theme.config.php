<?php
return [
    'extends' => 'bootstrap5',

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
];
