<?php
return array(
    'extends' => false,
    'helpers' => array(
        'factories' => array(
            'auth' => function ($sm) {
                return new \VuFind\View\Helper\Root\Auth(
                    $sm->getServiceLocator()->get('VuFind\AuthManager')
                );
            },
            'cart' => function ($sm) {
                return new \VuFind\View\Helper\Root\Cart(
                    $sm->getServiceLocator()->get('VuFind\Cart')
                );
            },
            'displaylanguageoption' => function ($sm) {
                return new VuFind\View\Helper\Root\DisplayLanguageOption(
                    $sm->getServiceLocator()->get('VuFind\Translator')
                );
            },
            'ils' => function ($sm) {
                return new \VuFind\View\Helper\Root\Ils(
                    $sm->getServiceLocator()->get('VuFind\ILSConnection')
                );
            },
            'recordlink' => function ($sm) {
                return new \VuFind\View\Helper\Root\RecordLink(
                    $sm->getServiceLocator()->get('VuFind\RecordRouter')
                );
            },
            'searchoptions' => function ($sm) {
                return new VuFind\View\Helper\Root\SearchOptions(
                    $sm->getServiceLocator()->get('SearchManager')
                );
            },
        ),
        'invokables' => array(
            'addellipsis' => 'VuFind\View\Helper\Root\AddEllipsis',
            'addthis' => 'VuFind\View\Helper\Root\AddThis',
            'authornotes' => 'VuFind\View\Helper\Root\AuthorNotes',
            'browse' => 'VuFind\View\Helper\Root\Browse',
            'citation' => 'VuFind\View\Helper\Root\Citation',
            'context' => 'VuFind\View\Helper\Root\Context',
            'currentpath' => 'VuFind\View\Helper\Root\CurrentPath',
            'datetime' => 'VuFind\View\Helper\Root\DateTime',
            'excerpt' => 'VuFind\View\Helper\Root\Excerpt',
            'flashmessages' => 'VuFind\View\Helper\Root\Flashmessages',
            'getlastsearchlink' => 'VuFind\View\Helper\Root\GetLastSearchLink',
            'highlight' => 'VuFind\View\Helper\Root\Highlight',
            'jqueryvalidation' => 'VuFind\View\Helper\Root\JqueryValidation',
            'openurl' => 'VuFind\View\Helper\Root\OpenUrl',
            'printms' => 'VuFind\View\Helper\Root\Printms',
            'proxyurl' => 'VuFind\View\Helper\Root\ProxyUrl',
            'recommend' => 'VuFind\View\Helper\Root\Recommend',
            'record' => 'VuFind\View\Helper\Root\Record',
            'related' => 'VuFind\View\Helper\Root\Related',
            'renderarray' => 'VuFind\View\Helper\Root\RenderArray',
            'resultfeed' => 'VuFind\View\Helper\Root\ResultFeed',
            'reviews' => 'VuFind\View\Helper\Root\Reviews',
            'safemoneyformat' => 'VuFind\View\Helper\Root\SafeMoneyFormat',
            'sortfacetlist' => 'VuFind\View\Helper\Root\SortFacetList',
            'summon' => 'VuFind\View\Helper\Root\Summon',
            'syndeticsplus' => 'VuFind\View\Helper\Root\SyndeticsPlus',
            'systememail' => 'VuFind\View\Helper\Root\SystemEmail',
            'transesc' => 'VuFind\View\Helper\Root\TransEsc',
            'translate' => 'VuFind\View\Helper\Root\Translate',
            'truncate' => 'VuFind\View\Helper\Root\Truncate',
            'userlist' => 'VuFind\View\Helper\Root\UserList',
            'videoclips' => 'VuFind\View\Helper\Root\VideoClips',
        )
    ),
);
