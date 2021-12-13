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
        'contact' => parse_ini_file('contacts-demo.ini'),
    ],
];
