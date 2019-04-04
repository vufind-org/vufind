<?php
namespace TueFindSearch\Backend\Solr\Response\Json;

use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

class RecordCollectionFactory extends \VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory {
    public function __construct($recordFactory = null,
        $collectionClass = 'TueFindSearch\Backend\Solr\Response\Json\RecordCollection'
    ) {
        parent::__construct($recordFactory, $collectionClass);
    }
}
?>

