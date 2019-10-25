<?php
namespace KrimDok\Controller\Plugin;

class NewItems extends \VuFind\Controller\Plugin\NewItems {

    public function getSolrFilter($range)
    {
        // use tue_local_indexed_date instead of first_indexed
        return 'tue_local_indexed_date:[NOW-' . $range . 'DAY TO NOW]';
    }
}
