<?php
namespace TueFind\Controller;

class SearchController extends \VuFind\Controller\SearchController
{
    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        $page = $this->forward()->dispatch('StaticPage', array(
            'action' => 'staticPage',
            'page' => 'Home'
        ));
        return $this->createViewModel(
            [
                'page' => $page
            ]
        );
    }

        /**
     * Results action.
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // overwrite to suppress special Tag handling as provided by standard
        // VuFind
        return parent::resultsAction();
    }

}
