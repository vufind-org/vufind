<?php
return array(
	'extends' => 'root',
	'css' => array(
		'vendor/normalize.css',
		'vendor/foundation.min.css',
		'vendor/font-awesome.min.css',
		'default.css',
		),
	'js' => array(
		'vendor/base64.js:lt IE 10', // btoa polyfill
		'vendor/jquery.min.js',
		'vendor/modernizr.js',
		'vendor/fastclick.js',
		'vendor/rc4.js',
		//'vendor/bootstrap-modal.js',  // remove this and the actual file - Fixme - CK
		'foundation.min.js',		// This includes all components
		//	'foundation/foundation.js', 	// Activate this plus individual FNDTN component scripts below, if desired
		//	'foundation/foundation.topbar.js',
		'vendor/typeahead.js',
		'common.js',
		'lightbox.js',
	),
	/*
	   'less' => array(
		'active' => false,
		'compiled.less'
	),
	*/
	// previous block commented out by CK - FIXME - Chris, do we need a Sass-block here?
	// CSS-compiler

	/* Chris - I have started using sassc to compile:
             - https://github.com/sass/sassc
             - ~/sassc/bin/sassc -t compact themes/foundation5/scss/default.scss > themes/foundation5/css.default.css */

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
