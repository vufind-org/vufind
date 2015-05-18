<?php
return array(
	'extends' => 'root',
	'css' => array(
		'normalize.css',
		'foundation.min.css',
		'vendor/font-awesome.min.css',
		'default.css',
		'mqueries.css',
	),
	'js' => array(
		'vendor/base64.js:lt IE 10', // btoa polyfill
		'vendor/modernizr.js',
		'vendor/jquery.min.js',
		'foundation.min.js',		// This includes all components
	//	'foundation/foundation.js', 	// Activate this plus individual FNDTN component scripts below, if desired
	//	'foundation/foundation.topbar.js',
		'vendor/bootstrap-modal.js',
		'vendor/fastclick.js',
		'vendor/typeahead.js',
		'vendor/rc4.js',
		'common.js',
		'lightbox.js',
	),
	/*
	   'less' => array(
		'active' => false,
		'compiled.less'
	),
	*/
	// previous block commented out by CK - FIXME - check and use LESS, or better, find solution using SASS, which is FNDTN's preferred CSS-compiler

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
