<?php
namespace TAMU\Module\Config;

return [
    'vufind' => [
        'plugin_managers' => [
            'ils_driver' => [
                'factories' => [
                    'TAMU\\ILS\\Driver\\Folio' => 'VuFind\\ILS\\Driver\\FolioFactory',
                ],
                'aliases' => [
                    'VuFind\\ILS\\Driver\\Folio' => 'TAMU\\ILS\\Driver\\Folio',
                ]
            ],
        ],
    ],
];
