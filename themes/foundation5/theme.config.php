<?php
return array(
  'extends' => 'root',
  'css' => array(
    'vendor/normalize.css',
    // import foundation into default.scss and leave next line out
    'vendor/font-awesome.css',
    'default.css',
    ),
  'js' => array(
    'vendor/base64.js:lt IE 10', // btoa polyfill
    'vendor/jquery.min.js',
    'vendor/modernizr.js', // html5 for older browsers
    'vendor/fastclick.js',  // improves experience for mobile users
    'vendor/rc4.js',
    'foundation.min.js', // This includes all components
    //	'foundation/foundation.js', // Activate this plus individual FNDTN component scripts below, if desired
    //	'foundation/foundation.topbar.js',
    'vendor/typeahead.js',
    'common.js',
    'lightbox.js',
  ),

// CSS-compiler: We use Sass and compile per grunt

  'favicon' => 'vufind-favicon.ico',
  'helpers' => array(
    'factories' => array(
      'flashmessages' => 'VuFind\View\Helper\Foundation\Factory::getFlashmessages',
      'layoutclass' => 'VuFind\View\Helper\Foundation\Factory::getLayoutClass',
    ),
    'invokables' => array(
      'highlight' => 'VuFind\View\Helper\Foundation\Highlight',
      'search' => 'VuFind\View\Helper\Foundation\Search',
      'vudl' => 'VuDL\View\Helper\Foundation\VuDL',
    )
  )
);
