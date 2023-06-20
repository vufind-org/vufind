<?php

namespace VuFind\Search\EPF;

class Options extends \VuFind\Search\Base\Options
{

    /**
     * Default view option
     *
     * @var ?string
     */
    protected $defaultView = null;

    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        $apiInfo = null
    ) {
        $this->searchIni = $this->facetsIni = 'EPF';
        $this->searchSettings = $configLoader->get($this->searchIni);

        parent::__construct($configLoader);

        $this->setOptionsFromConfig();
        $facetConf = $configLoader->get($this->facetsIni);
    }

    public function getSearchAction()
    {
        return 'epf-search';
    }

    public function getView()
    {
        return $this->defaultView;
    }

    public function getAdvancedSearchAction()
    {
        return false;
    }

    protected function setOptionsFromConfig()
    {
        // View preferences
        if (isset($this->searchSettings->General->default_view)) {
            $this->defaultView
                = 'list|' . $this->searchSettings->General->default_view;
        }
    }

    public function getEpfView()
    {
        $viewArr = explode('|', $this->defaultView);
        return (1 < count($viewArr)) ? $viewArr[1] : $this->defaultView;
    }
}