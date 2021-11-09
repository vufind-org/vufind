<?php
namespace TueFind\Controller;

class AuthorController extends \VuFind\Controller\AuthorController {

    public function searchAction()
    {
        $view = parent::searchAction();
        $author_id = $this->params()->fromQuery('author_id');
        if(empty($author_id) && !empty($this->params()->fromQuery('lookfor'))) {
            $lookforID = explode(' ', $this->params()->fromQuery('lookfor'));
            if(!empty($lookforID[0])) {
                $lookforID = explode(':', $lookforID[0]);
                if(!empty($lookforID[1])) {
                    $author_id = $lookforID[1];
                }
            }
        }
        $view->authorId = $author_id;
        return $view;
    }
}
