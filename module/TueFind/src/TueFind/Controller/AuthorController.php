<?php
namespace TueFind\Controller;

class AuthorController extends \VuFind\Controller\AuthorController {

    public function searchAction()
    {
        $view = parent::searchAction();
        $author_id = $this->params()->fromQuery('author_id');
        $page = $this->params()->fromQuery('page');
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
        $relatedAuthors = [];
        foreach($view->results->getResults() as $res) {
            $updateData = $this->updateRelatedAuthor($res);
            if($updateData['relatedAuthorID'] != $author_id) {
                $relatedAuthors[] = $updateData;
            }
        }

        if(empty($relatedAuthors) && !empty($page)) {

            $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);
            $request = $this->getRequest()->getQuery()->toArray() + $this->getRequest()->getPost()->toArray();
            if(isset($request['page'])) {
                unset($request['page']);
            }
            $lastView = $this->getSearchMemory()->retrieveLastSetting($this->searchClassId, 'view');

            $view->results = $runner->run(
                $request, $this->searchClassId,
                null ?: $this->getSearchSetupCallback(),
                $lastView
            );

            foreach($view->results->getResults() as $res) {
                $updateData = $this->updateRelatedAuthor($res);
                if($updateData['relatedAuthorID'] != $author_id) {
                    $relatedAuthors[] = $updateData;
                }
            }
        }

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
