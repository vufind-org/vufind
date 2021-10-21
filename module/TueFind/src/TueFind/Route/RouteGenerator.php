<?php

namespace TueFind\Route;

class RouteGenerator extends \VuFind\Route\RouteGenerator
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
            $this->nonTabRecordActions[] = 'Publish';
        }
    }
}
