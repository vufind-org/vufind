<?php

namespace KrimDok\Controller;

class SearchController extends \VuFind\Controller\SearchController
{
    /**
     * overwrite, only use "results" without additional params
     *
     * @return mixed
     */
    public function homeAction()
    {
        $page = $this->forward()->dispatch('StaticPage', [
            'action' => 'staticPage',
            'page' => 'Home'
        ]);
        return $this->createViewModel(
            [
                'page' => $page,
                'results' => null
            ]
        );
    }
}
