<?php
return array(
    'extends' => false,
    'helpers' => array(
        'factories' => array(
            'addthis' => function ($sm) {
                $config = \VuFind\Config\Reader::getConfig();
                return new \VuFind\View\Helper\Root\AddThis(
                    isset($config->AddThis->key) ? $config->AddThis->key : false
                );
            },
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
            'export' => function ($sm) {
                return new \VuFind\View\Helper\Root\Export(
                    $sm->getServiceLocator()->get('VuFind\Export')
                );
            },
            'flashmessages' => function ($sm) {
                $messenger = $sm->getServiceLocator()->get('ControllerPluginManager')
                    ->get('FlashMessenger');
                return new \VuFind\View\Helper\Root\Flashmessages($messenger);
            },
            'ils' => function ($sm) {
                return new \VuFind\View\Helper\Root\Ils(
                    $sm->getServiceLocator()->get('VuFind\ILSConnection')
                );
            },
            'proxyurl' => function ($sm) {
                return new \VuFind\View\Helper\Root\ProxyUrl(
                    \VuFind\Config\Reader::getConfig()
                );
            },
            'openurl' => function ($sm) {
                $config = \VuFind\Config\Reader::getConfig();
                return new \VuFind\View\Helper\Root\OpenUrl(
                    $sm->get('context'),
                    isset($config->OpenURL) ? $config->OpenURL : null
                );
            },
            'record' => function ($sm) {
                return new \VuFind\View\Helper\Root\Record(
                    \VuFind\Config\Reader::getConfig()
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
            'syndeticsplus' => function ($sm) {
                $config = \VuFind\Config\Reader::getConfig();
                return new \VuFind\View\Helper\Root\SyndeticsPlus(
                    isset($config->Syndetics) ? $config->Syndetics : null
                );
            },
            'systememail' => function ($sm) {
                $config = \VuFind\Config\Reader::getConfig();
                return new \VuFind\View\Helper\Root\SystemEmail(
                    isset($config->Site->email) ? $config->Site->email : ''
                );
            },
        ),
        'invokables' => array(
            'addellipsis' => 'VuFind\View\Helper\Root\AddEllipsis',
            'authornotes' => 'VuFind\View\Helper\Root\AuthorNotes',
            'browse' => 'VuFind\View\Helper\Root\Browse',
            'citation' => 'VuFind\View\Helper\Root\Citation',
            'context' => 'VuFind\View\Helper\Root\Context',
            'currentpath' => 'VuFind\View\Helper\Root\CurrentPath',
            'datetime' => 'VuFind\View\Helper\Root\DateTime',
            'excerpt' => 'VuFind\View\Helper\Root\Excerpt',
            'getlastsearchlink' => 'VuFind\View\Helper\Root\GetLastSearchLink',
            'highlight' => 'VuFind\View\Helper\Root\Highlight',
            'jqueryvalidation' => 'VuFind\View\Helper\Root\JqueryValidation',
            'printms' => 'VuFind\View\Helper\Root\Printms',
            'recommend' => 'VuFind\View\Helper\Root\Recommend',
            'related' => 'VuFind\View\Helper\Root\Related',
            'renderarray' => 'VuFind\View\Helper\Root\RenderArray',
            'resultfeed' => 'VuFind\View\Helper\Root\ResultFeed',
            'reviews' => 'VuFind\View\Helper\Root\Reviews',
            'safemoneyformat' => 'VuFind\View\Helper\Root\SafeMoneyFormat',
            'sortfacetlist' => 'VuFind\View\Helper\Root\SortFacetList',
            'summon' => 'VuFind\View\Helper\Root\Summon',
            'transesc' => 'VuFind\View\Helper\Root\TransEsc',
            'translate' => 'VuFind\View\Helper\Root\Translate',
            'truncate' => 'VuFind\View\Helper\Root\Truncate',
            'userlist' => 'VuFind\View\Helper\Root\UserList',
            'videoclips' => 'VuFind\View\Helper\Root\VideoClips',
        )
    ),
);
