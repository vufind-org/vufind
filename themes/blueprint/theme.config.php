<?php
return array(
    'extends' => 'root',
    'css' => array(
        'blueprint/screen.css:screen, projection',
        'blueprint/print.css:print',
        'blueprint/ie.css:screen, projection:lt IE 8',
        'jquery-ui/css/smoothness/jquery-ui.css',
        'styles.css:screen, projection',
        'print.css:print',
        'ie.css:screen, projection:lt IE 8',
    ),
    'js' => array(
        'jquery.min.js',
        'jquery.form.js',
        'jquery.metadata.js',
        'jquery.validate.min.js',
        'jquery-ui/js/jquery-ui.js',
        'lightbox.js',
        'common.js',
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'invokables' => array(
            'layoutclass' => 'VuFind\Theme\Blueprint\Helper\LayoutClass',
            'search' => 'VuFind\Theme\Blueprint\Helper\Search',
        )
    )
);