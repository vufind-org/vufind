<?php
namespace IxTheo\Route;

class RouteGenerator extends \TueFind\Route\RouteGenerator
{
    /**
     * Constructor
     *
     * @param array $nonTabRecordActions List of non-tab record actions (null
     * for default).
     */
    public function __construct(array $nonTabRecordActions = null)
    {
        parent::__construct($nonTabRecordActions);
        if (null === $nonTabRecordActions) {
            $this->nonTabRecordActions[] = 'PDASubscribe';
            $this->nonTabRecordActions[] = 'Subscribe';
        }
    }
}
