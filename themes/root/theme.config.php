<?php
return array(
    'extends' => false,
    'helpers' => array(
        'factories' => array(
            'addthis' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                return new \VuFind\View\Helper\Root\AddThis(
                    isset($config->AddThis->key) ? $config->AddThis->key : false
                );
            },
            'auth' => function ($sm) {
                return new \VuFind\View\Helper\Root\Auth(
                    $sm->getServiceLocator()->get('VuFind\AuthManager')
                );
            },
            'authornotes' => function ($sm) {
                return new \VuFind\View\Helper\Root\AuthorNotes(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'cart' => function ($sm) {
                return new \VuFind\View\Helper\Root\Cart(
                    $sm->getServiceLocator()->get('VuFind\Cart')
                );
            },
            'citation' => function ($sm) {
                return new \VuFind\View\Helper\Root\Citation(
                    $sm->getServiceLocator()->get('VuFind\DateConverter')
                );
            },
            'datetime' => function ($sm) {
                return new \VuFind\View\Helper\Root\DateTime(
                    $sm->getServiceLocator()->get('VuFind\DateConverter')
                );
            },
            'displaylanguageoption' => function ($sm) {
                return new VuFind\View\Helper\Root\DisplayLanguageOption(
                    $sm->getServiceLocator()->get('VuFind\Translator')
                );
            },
            'excerpt' => function ($sm) {
                return new \VuFind\View\Helper\Root\Excerpt(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'export' => function ($sm) {
                return new \VuFind\View\Helper\Root\Export(
                    $sm->getServiceLocator()->get('VuFind\Export')
                );
            },
            'feedback' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                $enabled = isset($config->Feedback->tab_enabled)
                    ? $config->Feedback->tab_enabled : false;
                return new \VuFind\View\Helper\Root\Feedback($enabled);
            },
            'flashmessages' => function ($sm) {
                $messenger = $sm->getServiceLocator()->get('ControllerPluginManager')
                    ->get('FlashMessenger');
                return new \VuFind\View\Helper\Root\Flashmessages($messenger);
            },
            'googleanalytics' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                $key = isset($config->GoogleAnalytics->apiKey)
                    ? $config->GoogleAnalytics->apiKey : false;
                return new \VuFind\View\Helper\Root\GoogleAnalytics($key);
            },
            'ils' => function ($sm) {
                return new \VuFind\View\Helper\Root\Ils(
                    $sm->getServiceLocator()->get('VuFind\ILSConnection')
                );
            },
            'proxyurl' => function ($sm) {
                return new \VuFind\View\Helper\Root\ProxyUrl(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'openurl' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                return new \VuFind\View\Helper\Root\OpenUrl(
                    $sm->get('context'),
                    isset($config->OpenURL) ? $config->OpenURL : null
                );
            },
            'record' => function ($sm) {
                return new \VuFind\View\Helper\Root\Record(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'recordlink' => function ($sm) {
                return new \VuFind\View\Helper\Root\RecordLink(
                    $sm->getServiceLocator()->get('VuFind\RecordRouter')
                );
            },
            'related' => function ($sm) {
                return new \VuFind\View\Helper\Root\Related(
                    $sm->getServiceLocator()->get('VuFind\RelatedPluginManager')
                );
            },
            'reviews' => function ($sm) {
                return new \VuFind\View\Helper\Root\Reviews(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'searchoptions' => function ($sm) {
                return new VuFind\View\Helper\Root\SearchOptions(
                    $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')
                );
            },
            'searchtabs' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                $config = isset($config->SearchTabs)
                    ? $config->SearchTabs->toArray() : array();
                return new VuFind\View\Helper\Root\SearchTabs(
                    $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
                    $config, $sm->get('url')
                );
            },
            'syndeticsplus' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                return new \VuFind\View\Helper\Root\SyndeticsPlus(
                    isset($config->Syndetics) ? $config->Syndetics : null
                );
            },
            'systememail' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                return new \VuFind\View\Helper\Root\SystemEmail(
                    isset($config->Site->email) ? $config->Site->email : ''
                );
            },
            'videoclips' => function ($sm) {
                return new \VuFind\View\Helper\Root\VideoClips(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'worldcat' => function ($sm) {
                return new \VuFind\View\Helper\Root\WorldCat(
                    $sm->getServiceLocator()->get('VuFind\Search\BackendManager')->get('WorldCat')->getConnector()
                );
            }
        ),
        'invokables' => array(
            'addellipsis' => 'VuFind\View\Helper\Root\AddEllipsis',
            'browse' => 'VuFind\View\Helper\Root\Browse',
            'context' => 'VuFind\View\Helper\Root\Context',
            'currentpath' => 'VuFind\View\Helper\Root\CurrentPath',
            'getlastsearchlink' => 'VuFind\View\Helper\Root\GetLastSearchLink',
            'highlight' => 'VuFind\View\Helper\Root\Highlight',
            'jqueryvalidation' => 'VuFind\View\Helper\Root\JqueryValidation',
            'printms' => 'VuFind\View\Helper\Root\Printms',
            'recommend' => 'VuFind\View\Helper\Root\Recommend',
            'renderarray' => 'VuFind\View\Helper\Root\RenderArray',
            'resultfeed' => 'VuFind\View\Helper\Root\ResultFeed',
            'safemoneyformat' => 'VuFind\View\Helper\Root\SafeMoneyFormat',
            'sortfacetlist' => 'VuFind\View\Helper\Root\SortFacetList',
            'summon' => 'VuFind\View\Helper\Root\Summon',
            'transesc' => 'VuFind\View\Helper\Root\TransEsc',
            'translate' => 'VuFind\View\Helper\Root\Translate',
            'truncate' => 'VuFind\View\Helper\Root\Truncate',
            'userlist' => 'VuFind\View\Helper\Root\UserList',
        )
    ),
);
