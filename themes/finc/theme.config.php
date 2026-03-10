<?php
return [
    'extends' => 'bootstrap5',
    'js' => [
        ['file' => 'check_item_statuses.js', 'priority' => 410],
        ['file' => 'lightbox_form_cache.js', 'priority' => 420],
        ['file' => 'covers.js', 'priority' => 430],
        ['file' => 'common-finc.js', 'priority' => 440],
    ],
    'scss' => [
        'compiled.scss',
        'print.scss',
    ],
    'helpers' => [
        'aliases' => [
            'branchInfo' => 'finc\View\Helper\Root\BranchInfo',
            'externalCatalogueLink' => 'finc\View\Helper\Root\ExternalCatalogueLink',
            'enhancedRenderArray' => 'finc\View\Helper\Root\EnhancedRenderArray',
            'interlibraryloan' => 'finc\View\Helper\Root\InterlibraryLoanLink',
            'sideFacet' => 'finc\View\Helper\Root\SideFacet',
            'resultfeed' => 'finc\View\Helper\Root\ResultFeed',
            'recordLinker' => 'finc\View\Helper\Root\RecordLinker',
            'record' => 'finc\View\Helper\Root\Record',
            'flashmessages' => 'finc\View\Helper\Root\Flashmessages',
            'externalLink' => 'finc\View\Helper\Root\ExternalLink',
            'renderArray' => 'finc\View\Helper\Root\RenderArray',
        ],
        'factories' => [
            'finc\View\Helper\Root\BranchInfo' =>
                'finc\View\Helper\Root\BranchInfoViewHelperFactory',
            'finc\View\Helper\Root\EnhancedRenderArray' =>
                'Laminas\ServiceManager\Factory\InvokableFactory',
            'finc\View\Helper\Root\ExternalCatalogueLink' =>
                'finc\View\Helper\Root\ExternalCatalogueLinkHelperFactory',
            'finc\View\Helper\Root\InterlibraryLoanLink' =>
                'finc\View\Helper\Root\ViewHelperFactory',
            'finc\View\Helper\Root\SideFacet' =>
                'finc\View\Helper\Root\SideFacetViewHelperFactory',
            'finc\View\Helper\Root\Record' =>
                'finc\View\Helper\Root\RecordViewHelperFactory',
            'finc\View\Helper\Root\RecordLinker' =>
                'finc\View\Helper\Root\RecordLinkerViewHelperFactory',
            'VuFind\View\Helper\Root\Citation' =>
                'finc\View\Helper\Root\CitationViewHelperFactory',
            'VuFind\View\Helper\Root\OpenUrl' =>
                'finc\View\Helper\Root\OpenUrlViewHelperFactory',
            'VuFind\View\Helper\Root\RecordDataFormatter' =>
                'finc\View\Helper\Root\RecordDataFormatterFactory',
            'finc\View\Helper\Root\ResultFeed' =>
                'VuFind\View\Helper\Root\ResultFeedFactory',
            'finc\View\Helper\Root\Flashmessages' =>
                'VuFind\View\Helper\Root\FlashmessagesFactory',
            'finc\View\Helper\Root\ExternalLink' =>
                \finc\View\Helper\Root\ViewHelperFactory::class,
            'finc\View\Helper\Root\RenderArray' =>
                \finc\View\Helper\Root\ViewHelperFactory::class
        ]
    ]
];
