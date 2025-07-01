<?php

namespace VuFind\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use VuFind\Log\Handler\VerbosityTrait;

class DatabaseHandler extends AbstractProcessingHandler
{
    use VerbosityTrait;
}