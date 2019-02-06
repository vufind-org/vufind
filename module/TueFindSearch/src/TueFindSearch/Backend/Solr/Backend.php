<?php
namespace TueFindSearch\Backend\Solr;

class Backend extends \VuFindSearch\Backend\Solr\Backend {

    public function __construct(\VuFindSearch\Backend\Solr\Connector $connector)
    {
        parent::__construct($connector);
    }
}
?>
