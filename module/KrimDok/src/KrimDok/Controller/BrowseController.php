<?php

namespace KrimDok\Controller;

class BrowseController extends \VuFind\Controller\BrowseController
{
    /**
     * Browse Author.
     * Extended because VuFind default doesnt use facet_prefix,
     * which we need, because author_facet can contain multiple values
     * for the same dataset.
     *
     * (without override, author->alphabetical->C would also contain stuff like "Europarat")
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function authorAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'lcc'          => 'By Call Number',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era'
        ];

        return $this->performBrowse('Author', $categoryList, true);
    }
}
