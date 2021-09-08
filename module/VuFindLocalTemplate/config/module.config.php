<?php
namespace VuFindLocalTemplate\Module\Configuration;

$config = [
    'vufind' => [
        // List of prefixes leading to simpler (non-default) inflection.
        // Required to allow VuFind to load templates associated with this module
        // from themes, instead of using the default Laminas template loading logic.
        'template_injection' => ['VuFindLocalTemplate/'],
    ]
];

return $config;
