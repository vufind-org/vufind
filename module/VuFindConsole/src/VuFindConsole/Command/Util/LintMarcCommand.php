<?php
namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LintMarcCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'util lintuser';

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // ...

        return 0;
    }
}