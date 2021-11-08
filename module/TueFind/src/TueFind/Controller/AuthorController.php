<?php
namespace TueFind\Controller;

class AuthorController extends \VuFind\Controller\AuthorController {

    public function searchAction()
    {
        $view = parent::searchAction();
        $view->authorId = $this->params()->fromQuery('author_id');
        return $view;
    }
}
