<?php

namespace KrimDok\Controller;

use VuFind\Exception\Mail as MailException;

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
