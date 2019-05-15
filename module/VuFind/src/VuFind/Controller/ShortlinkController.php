<?php

namespace VuFind\Controller;

class ShortlinkController extends AbstractBase
{
    /**
     * Resolve full version of shortlink & redirect to target.
     *
     * @return mixed
     */
    public function redirectAction()
    {
        $id = $this->params('id');
        if (!$id) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $config = $this->getConfig();
        $shortener = $config->Mail->url_shortener ? $config->Mail->url_shortener : 'none';
        $resolver =  $this->serviceLocator->get(\VuFind\UrlShortener\PluginManager::class)->get($shortener);
        $url = $resolver->resolve($id);

        if (!$url) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $this->redirect()->toUrl($url);
    }
}
