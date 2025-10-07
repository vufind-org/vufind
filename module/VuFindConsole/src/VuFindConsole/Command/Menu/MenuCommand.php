<?php

/**
 * Console command: interactive menu.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/administration:command_line_utilities Wiki
 */

namespace VuFindConsole\Command\Menu;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use VuFindConsole\Command\PluginManager;

use function count;
use function in_array;

/**
 * Console command: interactive menu.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'menu/menu',
    description: 'Interactive menu'
)]
class MenuCommand extends Command
{
    /**
     * Menu option for running a command.
     *
     * @var string
     */
    protected string $runCommand = 'Run Command';

    /**
     * Menu option for exiting a command without running it.
     *
     * @var string
     */
    protected string $exitCommand = 'Exit Command';

    /**
     * Constructor
     *
     * @param array         $config         Menu configuration
     * @param PluginManager $commandManager Plugin manager for internal console commands
     * @param string|null   $name           The name of the command; passing null means it must
     * be set in configure()
     */
    public function __construct(protected array $config, protected PluginManager $commandManager, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setHelp('Display an interactive menu of commands.');
    }

    /**
     * Display a submenu.
     *
     * @param InputInterface  $input   Input object
     * @param OutputInterface $output  Output object
     * @param array           $options Menu options
     *
     * @return int
     */
    protected function displaySubmenu(InputInterface $input, OutputInterface $output, array $options): int
    {
        $legalOptions = [];
        foreach ($options as $i => $option) {
            if (!isset($option['label'])) {
                throw new Exception("Option $i is unlabeled!");
            }
            if (in_array($option['label'], $legalOptions)) {
                throw new Exception('Duplicate label!');
            }
            $legalOptions[] = $option['label'];
        }
        $exitOption = 'Exit Menu';
        if (in_array($exitOption, $legalOptions)) {
            throw new Exception("Reserved label '$exitOption' used in configuration.");
        }
        $legalOptions[] = $exitOption;

        $question = new ChoiceQuestion(
            'Choose an option:',
            $legalOptions,
            count($legalOptions) - 1
        );
        while (true) {
            $choice = $this->getHelper('question')->ask($input, $output, $question);
            if ($choice === $exitOption) {
                return Command::SUCCESS;
            }
            $index = array_search($choice, $legalOptions);
            if ($index !== false) {
                $this->displayOptions($input, $output, $options[$index]);
            }
        }
    }

    /**
     * Build the external command string from configuration and user input.
     *
     * @param array $config         Command configuration
     * @param array $argumentValues User argument values
     * @param array $optionValues   User option values
     *
     * @return string
     * @throws Exception
     */
    protected function buildExternalCommand(array $config, array $argumentValues, array $optionValues): string
    {
        if (PHP_OS_FAMILY === 'Windows' && isset($config['winCommand'])) {
            $baseCommand = APPLICATION_PATH . '\\' . $config['winCommand'];
        } elseif (isset($config['command'])) {
            $baseCommand = APPLICATION_PATH . '/' . $config['command'];
        } elseif (isset($config['phpCommand'])) {
            $baseCommand = 'php ' . APPLICATION_PATH . DIRECTORY_SEPARATOR . $config['phpCommand'];
        } else {
            throw new Exception('No actionable command found in configuration.');
        }
        $optionsString = '';
        foreach ($config['options'] ?? [] as $i => $option) {
            if (isset($optionValues[$i])) {
                $optionsString .= ' ' . $option['switch'];
                if (($option['type'] ?? 'string') !== 'no-value') {
                    $optionsString .= ' ' . $optionValues[$i];
                }
            }
        }
        return trim($baseCommand . $optionsString . ' ' . implode(' ', $argumentValues));
    }

    /**
     * Determine which action to take on an external command, using user input when appropriate.
     *
     * @param InputInterface  $input          Input object
     * @param OutputInterface $output         Output object
     * @param array           $options        Option configuration
     * @param array           $optionValues   User-provided option values
     * @param array           $arguments      Argument configuration
     * @param array           $argumentValues User-provided argument values
     * @param string          $fullCommand    Command to run based on current settings
     *
     * @return string
     */
    protected function getExternalCommandAction(
        InputInterface $input,
        OutputInterface $output,
        array $options,
        array $optionValues,
        array $arguments,
        array $argumentValues,
        string $fullCommand
    ): string {
        // If there are no arguments or options to prompt the user about, run the command immediately:
        if (count($options) === 0 && count($arguments) === 0) {
            return $this->runCommand;
        }
        $helper = $this->getHelper('question');
        $menu = [];
        if (count($options) > 0) {
            foreach ($options as $i => $currentOption) {
                if (($currentOption['type'] ?? 'string') === 'no-value') {
                    $currentValue = ($optionValues[$i] ?? $currentOption['default'] ?? false) ? 'ON' : 'OFF';
                } else {
                    $currentValue = ($optionValues[$i] ?? $currentOption['default'] ?? '--unset--');
                }
                $menu[] = "Set Option $i ({$currentOption['label']}); current value: " . $currentValue;
            }
        }
        if (count($arguments) > 0) {
            foreach ($arguments as $i => $currentArgument) {
                $menu[] = "Set Argument $i ({$currentArgument['label']}); current value: "
                    . ($argumentValues[$i] ?? $currentArgument['default'] ?? '--unset--');
            }
        }
        $menu[] = $this->exitCommand;
        $menu[] = $this->runCommand . ': ' . $fullCommand;
        $question = new ChoiceQuestion(
            'Choose an option: ',
            $menu
        );
        return $helper->ask($input, $output, $question);
    }

    /**
     * Run the provided command line; return true on success or false if it fails.
     *
     * @param string $command Command to run
     *
     * @return bool
     */
    protected function runCommand(string $command): bool
    {
        $passthruSuccess = passthru($command, $resultCode);
        return $passthruSuccess !== false && $resultCode === 0;
    }

    /**
     * Run an external (non-Symfony) command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     * @param array           $config Configuration of command to run
     *
     * @return int
     */
    protected function runExternalCommand(InputInterface $input, OutputInterface $output, array $config): int
    {
        $options = $config['options'] ?? [];
        $optionValues = [];
        $arguments = $config['arguments'] ?? [];
        $argumentValues = [];
        $helper = $this->getHelper('question');
        // Collect default and required arguments:
        foreach ($arguments as $i => $arg) {
            if (!isset($arg['label'])) {
                throw new Exception("Configuration error: argument $i missing label.");
            }
            if (isset($arg['default'])) {
                $argumentValues[$i] = $arg['default'];
            } elseif ($arg['required'] ?? false) {
                $question = new Question($arg['label'] . ': ');
                $argumentValues[$i] = $helper->ask($input, $output, $question);
            }
        }
        // Collect any additional optional details from the user:
        while (true) {
            $fullCommand = $this->buildExternalCommand($config, $argumentValues, $optionValues);
            $result = $this->getExternalCommandAction(
                $input,
                $output,
                $options,
                $optionValues,
                $arguments,
                $argumentValues,
                $fullCommand
            );
            // Bail out if the user wants to exit:
            if ($result === $this->exitCommand) {
                return Command::SUCCESS;
            }
            // Run the command if ready!
            if (str_starts_with($result, $this->runCommand)) {
                $success = $this->runCommand($fullCommand);
                $output->writeln($success ? '<info>Command successful.</info>' : '<error>Command failed.</error>');
                if ($success || (empty($arguments) && empty($options))) {
                    return $success ? Command::SUCCESS : Command::FAILURE;
                }
            }
            // If we got this far, we need to process additional user input:
            $resultParts = explode(' ', $result);
            $index = $resultParts[2] ?? null;
            switch ($resultParts[1] ?? '') {
                case 'Option':
                    $option = $options[$index];
                    switch ($option['type'] ?? 'string') {
                        case 'string':
                            $valueQuestion = new Question(
                                "Enter new value for {$option['label']}: ",
                                $option['default'] ?? null
                            );
                            $optionValues[$index] = $helper->ask($input, $output, $valueQuestion);
                            break;
                        case 'no-value':
                            if (!($optionValues[$index] ?? false)) {
                                $optionValues[$index] = true;
                            } else {
                                unset($optionValues[$index]);
                            }
                            break;
                        default:
                            throw new Exception("Unknown option type {$option['type']}.");
                    }
                    break;
                case 'Argument':
                    $argument = $arguments[$index];
                    $valueQuestion = new Question(
                        "Enter new value for {$argument['label']}: ",
                        $argument['default'] ?? null
                    );
                    $argumentValues[$index] = $helper->ask($input, $output, $valueQuestion);
                    break;
            }
        }
    }

    /**
     * Run an internal (Symfony) command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     * @param array           $config Configuration of command to run
     *
     * @return int
     */
    protected function runInternalCommand(InputInterface $input, OutputInterface $output, array $config): int
    {
        // Get the command object, and use it to create an external command configuration:
        $command = $this->commandManager->get($config['command']);
        $newConfig = [
            'label' => $config['label'],
            'type' => 'external-command',
            'phpCommand' => 'public' . DIRECTORY_SEPARATOR . 'index.php ' . $config['command'],
            'arguments' => [],
            'options' => [],
        ];
        $definition = $command->getDefinition();
        foreach ($definition->getArguments() as $argument) {
            $newConfig['arguments'][] = [
                'label' => $argument->getDescription(),
                'required' => $argument->isRequired(),
                'default' => $argument->getDefault(),
            ];
        }
        foreach ($definition->getOptions() as $option) {
            $newConfig['options'][] = [
                'label' => $option->getDescription(),
                'switch' => '--' . $option->getName(),
                'type' => $option->acceptValue() ? 'string' : 'no-value',
                'default' => $option->getDefault(),
            ];
        }
        return $this->runExternalCommand($input, $output, $newConfig);
    }

    /**
     * Display a summary of the menu system.
     *
     * @param OutputInterface $output Output object
     * @param array           $config Configuration of option to summarize
     * @param string          $indent Indentation level to apply (used for recursion)
     *
     * @return int
     */
    protected function displaySummary(OutputInterface $output, array $config, string $indent = ''): int
    {
        $output->writeln($indent . ($config['label'] ?? ''));
        foreach ($config['contents'] ?? [] as $content) {
            if (($content['type'] ?? '') !== 'summary') {
                $this->displaySummary($output, $content, $indent . '    ');
            }
        }
        return Command::SUCCESS;
    }

    /**
     * Display a menu or prompt for the provided configuration.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     * @param array           $config Configuration of option to display
     *
     * @return int
     */
    protected function displayOptions(InputInterface $input, OutputInterface $output, array $config): int
    {
        $output->writeln($config['label'] ?? '');
        $type = $config['type'] ?? 'unknown';
        $label = $config['label'] ?? 'none';
        switch ($type) {
            case 'menu':
                return $this->displaySubmenu($input, $output, $config['contents'] ?? []);
            case 'external-command':
                return $this->runExternalCommand($input, $output, $config);
            case 'internal-command':
                return $this->runInternalCommand($input, $output, $config);
            case 'summary':
                $output->writeln('');
                return $this->displaySummary($output, $this->config['main']);
            default:
                $output->writeln("Unknown menu type '$type' with label '$label'");
                return Command::FAILURE;
        }
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->displayOptions($input, $output, $this->config['main']);
    }
}
