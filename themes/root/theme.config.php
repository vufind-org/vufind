<?php
return [
    'extends' => false,
    'helpers' => [
        'factories' => [
            'Laminas\View\Helper\HeadTitle' => 'VuFind\View\Helper\Root\HeadTitleFactory',
            'VuFind\View\Helper\Root\AccountCapabilities' => 'VuFind\View\Helper\Root\AccountCapabilitiesFactory',
            'VuFind\View\Helper\Root\AddEllipsis' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\AddThis' => 'VuFind\View\Helper\Root\AddThisFactory',
            'VuFind\View\Helper\Root\AlphaBrowse' => 'VuFind\View\Helper\Root\AlphaBrowseFactory',
            'VuFind\View\Helper\Root\Auth' => 'VuFind\View\Helper\Root\AuthFactory',
            'VuFind\View\Helper\Root\AuthorNotes' => 'VuFind\View\Helper\Root\ContentLoaderFactory',
            'VuFind\View\Helper\Root\Browse' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Captcha' => 'VuFind\View\Helper\Root\CaptchaFactory',
            'VuFind\View\Helper\Root\Cart' => 'VuFind\View\Helper\Root\CartFactory',
            'VuFind\View\Helper\Root\Citation' => 'VuFind\View\Helper\Root\CitationFactory',
            'VuFind\View\Helper\Root\Config' => 'VuFind\View\Helper\Root\ConfigFactory',
            'VuFind\View\Helper\Root\Content' => 'VuFind\View\Helper\Root\ContentFactory',
            'VuFind\View\Helper\Root\ContentBlock' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Context' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\CurrentPath' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\DateTime' => 'VuFind\View\Helper\Root\DateTimeFactory',
            'VuFind\View\Helper\Root\DisplayLanguageOption' => 'VuFind\View\Helper\Root\DisplayLanguageOptionFactory',
            'VuFind\View\Helper\Root\Doi' => 'VuFind\View\Helper\Root\DoiFactory',
            'VuFind\View\Helper\Root\Export' => 'VuFind\View\Helper\Root\ExportFactory',
            'VuFind\View\Helper\Root\Feedback' => 'VuFind\View\Helper\Root\FeedbackFactory',
            'VuFind\View\Helper\Root\Flashmessages' => 'VuFind\View\Helper\Root\FlashmessagesFactory',
            'VuFind\View\Helper\Root\GeoCoords' => 'VuFind\View\Helper\Root\GeoCoordsFactory',
            'VuFind\View\Helper\Root\GoogleAnalytics' => 'VuFind\View\Helper\Root\GoogleAnalyticsFactory',
            'VuFind\View\Helper\Root\HelpText' => 'VuFind\View\Helper\Root\HelpTextFactory',
            'VuFind\View\Helper\Root\Highlight' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\HistoryLabel' => 'VuFind\View\Helper\Root\HistoryLabelFactory',
            'VuFind\View\Helper\Root\Ils' => 'VuFind\View\Helper\Root\IlsFactory',
            'VuFind\View\Helper\Root\JsTranslations' => 'VuFind\View\Helper\Root\JsTranslationsFactory',
            'VuFind\View\Helper\Root\KeepAlive' => 'VuFind\View\Helper\Root\KeepAliveFactory',
            'VuFind\View\Helper\Root\Linkify' => 'VuFind\View\Helper\Root\LinkifyFactory',
            'VuFind\View\Helper\Root\LocalizedNumber' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Markdown' => 'VuFind\View\Helper\Root\MarkdownFactory',
            'VuFind\View\Helper\Root\Matomo' => 'VuFind\View\Helper\Root\MatomoFactory',
            'VuFind\View\Helper\Root\Metadata' => 'VuFind\View\Helper\Root\MetadataFactory',
            'VuFind\View\Helper\Root\OpenUrl' => 'VuFind\View\Helper\Root\OpenUrlFactory',
            'VuFind\View\Helper\Root\Overdrive' => 'VuFind\View\Helper\Root\OverdriveFactory',
            'VuFind\View\Helper\Root\Permission' => 'VuFind\View\Helper\Root\PermissionFactory',
            'VuFind\View\Helper\Root\Piwik' => 'VuFind\View\Helper\Root\PiwikFactory',
            'VuFind\View\Helper\Root\Printms' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\ProxyUrl' => 'VuFind\View\Helper\Root\ProxyUrlFactory',
            'VuFind\View\Helper\Root\Recommend' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Record' => 'VuFind\View\Helper\Root\RecordFactory',
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'VuFind\View\Helper\Root\RecordDataFormatterFactory',
            'VuFind\View\Helper\Root\RecordLink' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\RecordLinker' => 'VuFind\View\Helper\Root\RecordLinkerFactory',
            'VuFind\View\Helper\Root\Relais' => 'VuFind\View\Helper\Root\RelaisFactory',
            'VuFind\View\Helper\Root\Related' => 'VuFind\View\Helper\Root\RelatedFactory',
            'VuFind\View\Helper\Root\RenderArray' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\ResultFeed' => 'VuFind\View\Helper\Root\ResultFeedFactory',
            'VuFind\View\Helper\Root\SafeMoneyFormat' => 'VuFind\View\Helper\Root\SafeMoneyFormatFactory',
            'VuFind\View\Helper\Root\SearchBox' => 'VuFind\View\Helper\Root\SearchBoxFactory',
            'VuFind\View\Helper\Root\SearchMemory' => 'VuFind\View\Helper\Root\SearchMemoryFactory',
            'VuFind\View\Helper\Root\SearchOptions' => 'VuFind\View\Helper\Root\SearchOptionsFactory',
            'VuFind\View\Helper\Root\SearchParams' => 'VuFind\View\Helper\Root\SearchParamsFactory',
            'VuFind\View\Helper\Root\SearchTabs' => 'VuFind\View\Helper\Root\SearchTabsFactory',
            'VuFind\View\Helper\Root\ShortenUrl' => 'VuFind\View\Helper\Root\ShortenUrlFactory',
            'VuFind\View\Helper\Root\SortFacetList' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Summaries' => 'VuFind\View\Helper\Root\ContentLoaderFactory',
            'VuFind\View\Helper\Root\Summon' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\SyndeticsPlus' => 'VuFind\View\Helper\Root\SyndeticsPlusFactory',
            'VuFind\View\Helper\Root\SystemEmail' => 'VuFind\View\Helper\Root\SystemEmailFactory',
            'VuFind\View\Helper\Root\TransEsc' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\TransEscAttr' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\TransEscWithPrefix' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Translate' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Truncate' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\Url' => 'VuFind\View\Helper\Root\UrlFactory',
            'VuFind\View\Helper\Root\UserList' => 'VuFind\View\Helper\Root\UserListFactory',
            'VuFind\View\Helper\Root\UserTags' => 'VuFind\View\Helper\Root\UserTagsFactory',
            'Laminas\View\Helper\ServerUrl' => 'VuFind\View\Helper\Root\ServerUrlFactory',
        ],
        'aliases' => [
            'accountCapabilities' => 'VuFind\View\Helper\Root\AccountCapabilities',
            'addEllipsis' => 'VuFind\View\Helper\Root\AddEllipsis',
            'addThis' => 'VuFind\View\Helper\Root\AddThis',
            'alphabrowse' => 'VuFind\View\Helper\Root\AlphaBrowse',
            'auth' => 'VuFind\View\Helper\Root\Auth',
            'authorNotes' => 'VuFind\View\Helper\Root\AuthorNotes',
            'browse' => 'VuFind\View\Helper\Root\Browse',
            'captcha' => 'VuFind\View\Helper\Root\Captcha',
            'cart' => 'VuFind\View\Helper\Root\Cart',
            'citation' => 'VuFind\View\Helper\Root\Citation',
            'config' => 'VuFind\View\Helper\Root\Config',
            'content' => 'VuFind\View\Helper\Root\Content',
            'contentBlock' => 'VuFind\View\Helper\Root\ContentBlock',
            'context' => 'VuFind\View\Helper\Root\Context',
            'currentPath' => 'VuFind\View\Helper\Root\CurrentPath',
            'dateTime' => 'VuFind\View\Helper\Root\DateTime',
            'displayLanguageOption' => 'VuFind\View\Helper\Root\DisplayLanguageOption',
            'doi' => 'VuFind\View\Helper\Root\Doi',
            'export' => 'VuFind\View\Helper\Root\Export',
            'feedback' => 'VuFind\View\Helper\Root\Feedback',
            'flashmessages' => 'VuFind\View\Helper\Root\Flashmessages',
            'geocoords' => 'VuFind\View\Helper\Root\GeoCoords',
            'googleanalytics' => 'VuFind\View\Helper\Root\GoogleAnalytics',
            'helpText' => 'VuFind\View\Helper\Root\HelpText',
            'highlight' => 'VuFind\View\Helper\Root\Highlight',
            'historylabel' => 'VuFind\View\Helper\Root\HistoryLabel',
            'ils' => 'VuFind\View\Helper\Root\Ils',
            'jsTranslations' => 'VuFind\View\Helper\Root\JsTranslations',
            'keepAlive' => 'VuFind\View\Helper\Root\KeepAlive',
            'linkify' => 'VuFind\View\Helper\Root\Linkify',
            'localizedNumber' => 'VuFind\View\Helper\Root\LocalizedNumber',
            'markdown' => 'VuFind\View\Helper\Root\Markdown',
            'matomo' => 'VuFind\View\Helper\Root\Matomo',
            'metadata' => 'VuFind\View\Helper\Root\Metadata',
            'openUrl' => 'VuFind\View\Helper\Root\OpenUrl',
            'overdrive' => 'VuFind\View\Helper\Root\Overdrive',
            'permission' => 'VuFind\View\Helper\Root\Permission',
            'piwik' => 'VuFind\View\Helper\Root\Piwik',
            'printms' => 'VuFind\View\Helper\Root\Printms',
            'proxyUrl' => 'VuFind\View\Helper\Root\ProxyUrl',
            'recommend' => 'VuFind\View\Helper\Root\Recommend',
            'record' => 'VuFind\View\Helper\Root\Record',
            'recordDataFormatter' => 'VuFind\View\Helper\Root\RecordDataFormatter',
            'recordLink' => 'VuFind\View\Helper\Root\RecordLink',
            'recordLinker' => 'VuFind\View\Helper\Root\RecordLinker',
            'relais' => 'VuFind\View\Helper\Root\Relais',
            'related' => 'VuFind\View\Helper\Root\Related',
            'renderArray' => 'VuFind\View\Helper\Root\RenderArray',
            'resultfeed' => 'VuFind\View\Helper\Root\ResultFeed',
            'safeMoneyFormat' => 'VuFind\View\Helper\Root\SafeMoneyFormat',
            'searchMemory' => 'VuFind\View\Helper\Root\SearchMemory',
            'searchOptions' => 'VuFind\View\Helper\Root\SearchOptions',
            'searchParams' => 'VuFind\View\Helper\Root\SearchParams',
            'searchTabs' => 'VuFind\View\Helper\Root\SearchTabs',
            'searchbox' => 'VuFind\View\Helper\Root\SearchBox',
            'shortenUrl' => 'VuFind\View\Helper\Root\ShortenUrl',
            'sortFacetList' => 'VuFind\View\Helper\Root\SortFacetList',
            'summaries' => 'VuFind\View\Helper\Root\Summaries',
            'summon' => 'VuFind\View\Helper\Root\Summon',
            'syndeticsPlus' => 'VuFind\View\Helper\Root\SyndeticsPlus',
            'systemEmail' => 'VuFind\View\Helper\Root\SystemEmail',
            'transEsc' => 'VuFind\View\Helper\Root\TransEsc',
            'transEscAttr' => 'VuFind\View\Helper\Root\TransEscAttr',
            'transEscWithPrefix' => 'VuFind\View\Helper\Root\TransEscWithPrefix',
            'translate' => 'VuFind\View\Helper\Root\Translate',
            'truncate' => 'VuFind\View\Helper\Root\Truncate',
            'userlist' => 'VuFind\View\Helper\Root\UserList',
            'usertags' => 'VuFind\View\Helper\Root\UserTags',
            'Laminas\View\Helper\Url' => 'VuFind\View\Helper\Root\Url',
        ]
    ],
];
