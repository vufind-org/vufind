<?php
return [
    'extends' => 'root',
    'css' => [
        //'vendor/bootstrap.min.css',
        //'vendor/bootstrap-accessibility.css',
        //'vendor/font-awesome.min.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'print.css:print',
        'flex-fallback.css::lt IE 10', // flex polyfill
    ],
    'js' => [
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        'vendor/bootstrap-accessibility.min.js',
        'vendor/validator.min.js',
        'lib/form-attr-polyfill.js', // input[form] polyfill, cannot load conditionally, since we need all versions of IE
        'lib/autocomplete.js',
        'common.js',
        'lightbox.js',
    ],
    'less' => [
        'active' => false,
        'compiled.less'
    ],
    'favicon' => 'vufind-favicon.ico',
    'helpers' => [
        'factories' => [
            'Zend\Form\View\Helper\Form' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormElement' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormElementErrors' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormElementInput' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormEmail' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormLabel' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormRow' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormSelect' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormSubmit' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormText' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormTextarea' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Zend\Form\View\Helper\FormUrl' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap3\Flashmessages' => 'VuFind\View\Helper\Root\FlashmessagesFactory',
            'VuFind\View\Helper\Bootstrap3\Highlight' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap3\LayoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClassFactory',
            'VuFind\View\Helper\Bootstrap3\Recaptcha' => 'VuFind\View\Helper\Root\RecaptchaFactory',
            'VuFind\View\Helper\Bootstrap3\Search' => 'Zend\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Flashmessages',
            'form' => 'Zend\Form\View\Helper\Form',
            'form_element' => 'Zend\Form\View\Helper\FormElement',
            'form_element_errors' => 'Zend\Form\View\Helper\FormElementErrors',
            'form_label' => 'Zend\Form\View\Helper\FormLabel',
            'formElementErrors' => 'Zend\Form\View\Helper\FormElementErrors',
            'formRow' => 'Zend\Form\View\Helper\FormRow',
            'formemail' => 'Zend\Form\View\Helper\FormEmail',
            'formselect' => 'Zend\Form\View\Helper\FormSelect',
            'formsubmit' => 'Zend\Form\View\Helper\FormSubmit',
            'formtext' => 'Zend\Form\View\Helper\FormText',
            'formtextarea' => 'Zend\Form\View\Helper\FormTextarea',
            'formurl' => 'Zend\Form\View\Helper\FormUrl',
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'layoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClass',
            'recaptcha' => 'VuFind\View\Helper\Bootstrap3\Recaptcha',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search'
        ]
    ]
];
