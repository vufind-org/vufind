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
            $view = $this->getSearchResultsView();
            $relatedAuthors = [];
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

    protected function getSearchResultsView($setupCallback = null)
    {
        $view = $this->createViewModel();

        // Handle saved search requests:
        $savedId = $this->params()->fromQuery('saved', false);
        if ($savedId !== false) {
            return $this->redirectToSavedSearch($savedId);
        }

        $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();


        // Remove the "page" parameter from the request array, to fix "sorting form"
        if(isset($request['page'])) {
            unset($request['page']);
        }

        $lastView = $this->getSearchMemory()
            ->retrieveLastSetting($this->searchClassId, 'view');
        try {
            $view->results = $results = $runner->run(
                $request, $this->searchClassId,
                $setupCallback ?: $this->getSearchSetupCallback(),
                $lastView
            );
        } catch (\VuFindSearch\Backend\Exception\DeepPagingException $e) {
            return $this->redirectToLegalSearchPage($request, $e->getLegalPage());
        }
        $view->params = $results->getParams();

        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $view->parseError = true;
        } else {
            // If a "jumpto" parameter is set, deal with that now:
            if ($jump = $this->processJumpTo($results)) {
                return $jump;
            }

            // Remember the current URL as the last search.
            $this->rememberSearch($results);

            // Add to search history:
            if ($this->saveToHistory) {
                $this->saveSearchToHistory($results);
            }

            // Set up results scroller:
            if ($this->resultScrollerActive()) {
                $this->resultScroller()->init($results);
            }

            foreach ($results->getErrors() as $error) {
                $this->flashMessenger()->addErrorMessage($error);
            }
        }

        // Special case: If we're in RSS view, we need to render differently:
        if (isset($view->params) && $view->params->getView() == 'rss') {
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-type', 'text/xml');
            $feed = $this->getViewRenderer()->plugin('resultfeed');
            $response->setContent($feed($view->results)->export('rss'));
            return $response;
        }

        // Search toolbar
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $view->showBulkOptions = isset($config->Site->showBulkOptions)
          && $config->Site->showBulkOptions;

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
