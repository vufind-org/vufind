<?php

$app = include __DIR__ . '/application.php';
return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet(
    $app->getServiceManager()->get(\Doctrine\ORM\EntityManager::class)
);
