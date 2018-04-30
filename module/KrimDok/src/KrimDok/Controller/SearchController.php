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
        return $this->createViewModel(
            ['results' => null]
        );
    }
}
