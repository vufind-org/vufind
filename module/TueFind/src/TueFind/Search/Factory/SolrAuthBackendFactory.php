<?php

namespace TueFind\Search\Factory;

use TueFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\HandlerMap;


class SolrAuthBackendFactory extends \VuFind\Search\Factory\SolrAuthBackendFactory implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Same code as parent, but uses TueFind's QueryBuilder instead.
     */
    protected function createQueryBuilder()
    {
        $specs   = $this->loadSpecs();
        $config = $this->config->get($this->mainConfig);
        $defaultDismax = isset($config->Index->default_dismax_handler)
            ? $config->Index->default_dismax_handler : 'dismax';
        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $search = $this->config->get($this->searchConfig);
        $caseSensitiveBooleans
            = isset($search->General->case_sensitive_bools)
            ? $search->General->case_sensitive_bools : true;
        $caseSensitiveRanges
            = isset($search->General->case_sensitive_ranges)
            ? $search->General->case_sensitive_ranges : true;
        $helper = new LuceneSyntaxHelper(
            $caseSensitiveBooleans, $caseSensitiveRanges
        );
        $builder->setLuceneHelper($helper);

        return $builder;
    }


    protected function createConnector()
    {
        $config = $this->config->get($this->mainConfig);
        $this->setTranslator($this->serviceLocator->get(\Laminas\Mvc\I18n\Translator::class));
        $current_lang = $this->getTranslatorLocale();

        // On the Solr side we use different naming scheme
        // so map traditional and simplified chinese accordingly
        $chinese_lang_map = [ "zh" => "hant", "zh-cn" => "hans"];
        if (array_key_exists($current_lang, $chinese_lang_map))
            $current_lang = $chinese_lang_map[$current_lang];

        $handlers = [
            'select' => [
                'fallback' => true,
                'defaults' => ['fl' => '*,score', 'lang' => $current_lang,
                               'defType' => 'multiLanguageQueryParser', 'df' => 'allfields'
                              ],
                'appends'  => ['fq' => []],
            ],
            'term' => [
                'functions' => ['terms'],
            ],
        ];
        foreach ($this->getHiddenFilters() as $filter) {
            array_push($handlers['select']['appends']['fq'], $filter);
        }

        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $client = $httpService->createClient();

        $connector = new $this->connectorClass(
            $this->getSolrUrl(),
            new HandlerMap($handlers),
            $this->uniqueKey,
            $client
        );
        $connector->setTimeout($config->Index->timeout ?? 30);

        if ($this->logger) {
            $connector->setLogger($this->logger);
        }

        if (!empty($searchConfig->SearchCache->adapter)) {
            $cacheConfig = $searchConfig->SearchCache->toArray();
            $options = $cacheConfig['options'] ?? [];
            if (empty($options['namespace'])) {
                $options['namespace'] = 'Index';
            }
            if (empty($options['ttl'])) {
                $options['ttl'] = 300;
            }
            $settings = [
                'name' => $cacheConfig['adapter'],
                'options' => $options,
            ];
            $cache = $this->serviceLocator
                ->get(\Laminas\Cache\Service\StorageAdapterFactory::class)
                ->createFromArrayConfiguration($settings);
            $connector->setCache($cache);
        }
        return $connector;
    }

}
