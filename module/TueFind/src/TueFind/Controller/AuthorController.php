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
                $lookforID = explode('"', $lookforID[0]);
                if(!empty($lookforID[1])) {
                    $author_id = $lookforID[1];
                }
            }
        }
        
        $view->authorId = $author_id;
        $relatedAuthors = [];
        foreach($view->results->getResults() as $res) {
            $updateData = $this->updateRelatedAuthor($res);
            if(empty($updateData['relatedAuthorID']) || $updateData['relatedAuthorID'] != $view->authorId) {
                $relatedAuthors[] = $updateData;
            }
        }

        $view->results->setResultTotal(count($relatedAuthors));
        $view->relatedAuthors = $relatedAuthors;
        return $view;
    }

    private function updateRelatedAuthor($originalAuthorData): array {
       $explodeData = explode(':', $originalAuthorData['value']);
       $relatedAuthorID = '';
       if(isset($explodeData[1])) {
          $relatedAuthorID = $explodeData[1];
       }
       $originalAuthorData['relatedAuthorID'] = $relatedAuthorID;
       $originalAuthorData['relatedAuthorTitle'] = $explodeData[0];
       return $originalAuthorData;
    }
}
