<?php

/**
 * Composer package template generator command.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Generate;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use VuFind\Exception\FileAccess as FileAccessException;

/**
 * Composer package template generator command.
 *
 * @category VuFind
 * @package  Console
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/composerpackagetemplate',
    description: 'Composer package template generator'
)]
class ComposerPackageTemplateCommand extends AbstractCommand
{
    /**
     * InputInterface for re-use in certain methods.
     *
     * @var InputInterface
     */
    protected InputInterface $input;

    /**
     * OutputInterface for re-use in certain methods.
     *
     * @var OutputInterface
     */
    protected OutputInterface $output;

    /**
     * Filesystem for re-use in certain methods.
     *
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    protected string $vuFindHomeDirAbsolute;

    protected string $targetDirAbsolute;

    protected string $gitOrganizationName = 'my-organization';

    protected string $gitRepositoryName = 'my-repository';

    protected string $composerJsonName = 'composer.json';

    protected string $composerJsonTargetPathAbsolute;

    protected string $composerJsonTemplatePathAbsolute;

    protected string $configIniDirAbsolute;

    protected string $configIniDirRelative = 'config';

    protected string $configIniFileAbsolute;

    protected string $configIniFileName = 'mymodule.ini';

    protected string $configIniFileRelative;

    protected string $moduleTargetDirAbsolute;

    protected string $moduleTargetDirRelative = 'src';

    protected string $moduleNamespace = 'MyModule';

    protected string $moduleTemplateName = 'VuFindLocalTemplate';

    protected string $moduleTemplateDirAbsolute;

    protected string $moduleConfigPhpFileRelative = 'config/module.config.php';

    protected string $moduleConfigPhpFileAbsolute;

    protected string $modulePhpFileRelative = 'Module.php';

    protected string $modulePhpFileAbsolute;

    protected string $mixinName = 'my_mixin';

    protected string $mixinTemplateName = 'local_mixin_example';

    protected string $mixinTemplateDirAbsolute;

    protected string $mixinTargetDirRelative = 'mixin';

    protected string $mixinTargetDirAbsolute;

    protected string $qaTargetDirRelative = 'tests';

    protected string $qaTargetDirAbsolute;

    protected string $qaTemplateDirRelative = 'tests';

    protected string $qaTemplateDirAbsolute;

    protected string $gitHubTargetDirRelative = '.github';

    protected string $gitHubTargetDirAbsolute;

    protected string $gitHubTemplateDirRelative = '.github';

    protected string $gitHubTemplateDirAbsolute;

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setHelp('Creates a skeleton module for composer in a given directory with code examples.')
            ->addArgument(
                'target_directory',
                InputArgument::REQUIRED,
                'the path of the target directory where the module should be created'
            )->addOption(
                'module',
                null,
                InputOption::VALUE_NONE,
                'when set, also create a custom module'
            )->addOption(
                'config',
                null,
                InputOption::VALUE_NONE,
                'when set, also create a custom ' . $this->configIniFileName . ' file'
            )
            ->addOption(
                'mixin',
                null,
                InputOption::VALUE_NONE,
                'when set, also create a custom mixin'
            )->addOption(
                'build',
                null,
                InputOption::VALUE_NONE,
                'when set, also create build.xml'
            )
            ->addOption(
                'qa',
                null,
                InputOption::VALUE_NONE,
                'when set, also create QA tool configuration'
            )->addOption(
                'workflows',
                null,
                InputOption::VALUE_NONE,
                'when set, also create custom GitHub-workflows'
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->vuFindHomeDirAbsolute = getenv('VUFIND_HOME');
        $this->filesystem = new Filesystem();

        $this->targetDirAbsolute = rtrim($input->getArgument('target_directory'), DIRECTORY_SEPARATOR);
        $this->output->writeln('Generating skeleton module in ' . $this->targetDirAbsolute . '...');
        if ($this->filesystem->exists($this->targetDirAbsolute)) {
            $this->filesystem->remove($this->targetDirAbsolute);
        }
        $this->filesystem->mkdir($this->targetDirAbsolute);

        if ($this->input->getOption('module')) {
            $this->generateModule();
        }
        if ($this->input->getOption('config')) {
            $this->generateConfigIni();
        }
        if ($this->input->getOption('mixin')) {
            $this->generateMixin();
        }
        if ($this->input->getOption('qa')) {
            $this->generateQAToolConfig();
        }
        if ($this->input->getOption('build')) {
            $this->generateBuildXml();
        }
        if ($this->input->getOption('workflows')) {
            $this->generateGitHubWorkflows();
        }

        $this->generateComposerJson();

        return self::SUCCESS;
    }

    /**
     * Generate composer.json.
     *
     * @return void
     */
    protected function generateComposerJson(): void
    {
        $this->composerJsonTargetPathAbsolute =
            $this->targetDirAbsolute . DIRECTORY_SEPARATOR . $this->composerJsonName;
        $this->composerJsonTemplatePathAbsolute =
            $this->vuFindHomeDirAbsolute . DIRECTORY_SEPARATOR . $this->composerJsonName;
        $this->output->writeln('Generating ' . $this->composerJsonName . '...');

        $templateJson = $this->readFile($this->composerJsonTemplatePathAbsolute);
        $templateArray = json_decode($templateJson, true);

        $targetArray = [
            'name' => $this->gitOrganizationName . '/' . $this->gitRepositoryName,
            'description' => 'My custom composer package for VuFind',
            'license' => $templateArray['license'],
            'authors' => [
                [
                    'name' => 'My Name',
                    'email' => 'my@email.com',
                ],
            ],
            'config' => [
                'platform' => [
                    'php' => $templateArray['config']['platform']['php'],
                ],
            ],
            'require' => [
                'php' => $templateArray['require']['php'],
                'vufind/vufind' => \VuFind\Config\Version::getBuildVersion(),
            ],
            'require-dev' => [
                'phing/phing' => $templateArray['require']['phing/phing'],
            ],

            // This might be necessary to avoid dependency problems
            // when including vufind directly (even productive versions)
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ];

        if ($this->input->getOption('module')) {
            $targetArray['autoload']['psr-4'][$this->moduleNamespace . '\\'] = $this->moduleTargetDirRelative . '/';
        }
        if ($this->input->getOption('mixin')) {
            // depends on optimization from FINC/alex-pu
            $targetArray['extra']['vufind']['themes'][$this->mixinTargetDirRelative] = $this->mixinName;
        }
        if ($this->input->getOption('qa')) {
            // We might need to insert all standard vufind require-dev entries to run checks / workflows later
            foreach ($templateArray['require-dev'] as $dependency => $version) {
                $targetArray['require-dev'][$dependency] = $version;
            }
        }

        $json = json_encode($targetArray, JSON_PRETTY_PRINT);
        $this->filesystem->dumpFile($this->composerJsonTargetPathAbsolute, $json);
    }

    /**
     * Generate sample module.
     *
     * @return void
     */
    protected function generateModule(): void
    {
        //  Should we share code with \VuFindConsole\Command\InstallCommand?
        $this->moduleTemplateDirAbsolute =
            $this->vuFindHomeDirAbsolute . DIRECTORY_SEPARATOR
            . 'module' . DIRECTORY_SEPARATOR . $this->moduleTemplateName;
        $this->moduleTargetDirAbsolute =
            $this->targetDirAbsolute . DIRECTORY_SEPARATOR . $this->moduleTargetDirRelative;
        $this->output->writeln('Generating module in ' . $this->moduleTargetDirRelative . '...');
        $this->filesystem->mirror($this->moduleTemplateDirAbsolute, $this->moduleTargetDirAbsolute);

        // rewrite namespace
        $this->modulePhpFileAbsolute =
            $this->moduleTargetDirAbsolute . DIRECTORY_SEPARATOR . $this->modulePhpFileRelative;
        $this->rewriteNamespace($this->modulePhpFileAbsolute);
        $this->moduleConfigPhpFileAbsolute =
            $this->moduleTargetDirAbsolute . DIRECTORY_SEPARATOR . $this->moduleConfigPhpFileRelative;
        $this->rewriteNamespace($this->moduleConfigPhpFileAbsolute);
    }

    /**
     * Rewrite namespace in a PHP code file.
     *
     * @param string $path Path to PHP file
     *
     * @return void
     */
    protected function rewriteNamespace(string $path): void
    {
        $config = $this->readFile($path);
        $config = str_replace($this->moduleTemplateName, $this->moduleNamespace, $config);
        $this->filesystem->dumpFile($path, $config);
    }

    /**
     * Generate build.xml.
     *
     * @return void
     */
    protected function generateBuildXml(): void
    {
        $buildXmlTemplateFileAbsolute = $this->vuFindHomeDirAbsolute . DIRECTORY_SEPARATOR . 'build.xml';
        $buildXmlTargetFileAbsolute = $this->targetDirAbsolute . DIRECTORY_SEPARATOR . 'build.xml';
        $this->output->writeln('Generating build.xml...');
        $dom = new \DOMDocument();
        $dom->load($buildXmlTemplateFileAbsolute);

        $dom->documentElement->setAttribute('name', $this->moduleNamespace);

        // TODO: Which properties do we need to remove/modify/add?
        $dom->save($buildXmlTargetFileAbsolute);
    }

    /**
     * Generate config.ini (or mymodule.ini).
     *
     * This method so far only creates a sample config folder with a config.ini file.
     * - How will the file be accessed from the module, themes, ...?
     * - Should we also add a sample config reader to the module? (is adding a module a prerequisite)?
     *
     * @return void
     */
    protected function generateConfigIni(): void
    {
        $this->configIniDirAbsolute = $this->targetDirAbsolute . DIRECTORY_SEPARATOR . $this->configIniDirRelative;
        $this->configIniFileRelative = $this->configIniDirRelative . DIRECTORY_SEPARATOR . $this->configIniFileName;
        $this->configIniFileAbsolute = $this->configIniDirAbsolute . DIRECTORY_SEPARATOR . $this->configIniFileName;

        $this->output->writeln('Generating ' . $this->configIniFileRelative . '...');

        $config = <<<CONFIG
            ;
            ; Custom Module Configuration
            ;
            [Sample]
            enabled=true

            CONFIG;

        $this->filesystem->dumpFile($this->configIniFileAbsolute, $config);
    }

    /**
     * Generate sample mixin.
     *
     * @return void
     */
    protected function generateMixin(): void
    {
        // Should we share code with ThemeMixinCommand + \VuFindTheme\MixinGenerator?
        $this->mixinTemplateDirAbsolute =
            $this->vuFindHomeDirAbsolute . DIRECTORY_SEPARATOR
            . 'themes' . DIRECTORY_SEPARATOR . $this->mixinTemplateName;
        $this->mixinTargetDirAbsolute =
            $this->targetDirAbsolute . DIRECTORY_SEPARATOR . $this->mixinTargetDirRelative;
        $this->output->writeln('Generating mixin in ' . $this->mixinTargetDirRelative . '...');
        $this->filesystem->mirror($this->mixinTemplateDirAbsolute, $this->mixinTargetDirAbsolute);
    }

    /**
     * Generate QA tool configs (copy from VuFind & adjust paths).
     *
     * @return void
     */
    protected function generateQAToolConfig(): void
    {
        $this->qaTemplateDirAbsolute =
            $this->vuFindHomeDirAbsolute . DIRECTORY_SEPARATOR . $this->qaTemplateDirRelative;
        $this->qaTargetDirAbsolute =
            $this->targetDirAbsolute . DIRECTORY_SEPARATOR . $this->qaTargetDirRelative;

        $this->output->writeln('Generating checks in ' . $this->qaTargetDirRelative . '...');

        $this->filesystem->mirror($this->qaTemplateDirAbsolute, $this->qaTargetDirAbsolute);
        $this->filesystem->remove($this->qaTargetDirAbsolute . DIRECTORY_SEPARATOR . 'data');

        $this->rewritePhpCsPaths([$this->moduleTargetDirRelative, $this->qaTargetDirRelative]);
        $this->rewritePhpCsFixerPaths(
            'vufind.php-cs-fixer.php',
            [$this->moduleTargetDirRelative, $this->qaTargetDirRelative]
        );
        $this->rewritePhpCsFixerPaths('vufind_templates.php-cs-fixer.php', [$this->mixinTargetDirRelative]);
        $this->rewriteRectorPaths([$this->moduleTargetDirRelative, $this->qaTargetDirRelative]);
    }

    /**
     * Rewrite paths in a PHP-CS related file (phpcs.xml).
     *
     * @param array $pathsToInsert Paths that PHP-CS should check in this configuration.
     *
     * @return void
     */
    protected function rewritePhpCsPaths(array $pathsToInsert): void
    {
        $phpCsPath = $this->qaTargetDirAbsolute . DIRECTORY_SEPARATOR . 'phpcs.xml';

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $dom->load($phpCsPath);

        $description = null;

        $child = $dom->documentElement->firstChild;
        while ($child != null) {
            $next = $child->nextSibling;
            if ($child instanceof \DOMElement) {
                if ($child->tagName == 'description') {
                    $description = $child;
                } elseif ($child->tagName == 'file') {
                    $dom->documentElement->removeChild($child);
                }
            }

            $child = $next;
        }

        if ($description != null) {
            foreach ($pathsToInsert as $pathToInsert) {
                $file = $dom->createElement('file');
                $file->textContent = '../' . $pathToInsert;
                $dom->documentElement->insertBefore($file, $description);
            }
        } else {
            throw new \Exception('<description> element not found in ' . $phpCsPath);
        }

        $dom->save($phpCsPath);
    }

    /**
     * Rewrite paths in a PHP-CS-Fixer related file.
     *
     * @param string $configFilename Name of the config file
     * @param array  $pathsToInsert  Paths that PHP-CS-Fixer should check in this configuration.
     *
     * @return void
     */
    protected function rewritePhpCsFixerPaths(string $configFilename, array $pathsToInsert): void
    {
        $configPath = $this->qaTargetDirAbsolute . DIRECTORY_SEPARATOR . $configFilename;
        $config = $this->readFile($configPath);

        $pattern = '"(->in\([^)]+\)\s*)+"';
        $replace = '';
        $i = 0;
        foreach ($pathsToInsert as $pathToInsert) {
            if ($i > 0) {
                $replace .= PHP_EOL . '    ';
            }

            $replace .= '->in(__DIR__ . \'/../' . $pathToInsert . '\')';
            ++$i;
        }

        $configModified = preg_replace($pattern, $replace, $config);
        $this->filesystem->dumpFile($configPath, $configModified);
    }

    /**
     * Rewrite paths in a Rector related file.
     *
     * @param array $pathsToInsert Paths that Rector should check in this configuration.
     *
     * @return void
     */
    protected function rewriteRectorPaths(array $pathsToInsert): void
    {
        $configPath = $this->qaTargetDirAbsolute . DIRECTORY_SEPARATOR . 'rector.php';
        $config = $this->readFile($configPath);

        $pattern = '"->withPaths\(\[(\s|[^\]])+\]\)"';
        $replace = '->withPaths([';
        foreach ($pathsToInsert as $pathToInsert) {
            $replace .= PHP_EOL . '        ';
            $replace .= '__DIR__ . \'/../' . $pathToInsert . '\',';
        }
        $replace .= PHP_EOL . '    ])';

        $configModified = preg_replace($pattern, $replace, $config);
        $this->filesystem->dumpFile($configPath, $configModified);
    }

    /**
     * Generate .github directory with workflows.
     *
     * @return void
     */
    protected function generateGitHubWorkflows(): void
    {
        $this->output->writeln('Generating GitHub-Workflows in ' . $this->gitHubTargetDirRelative . '...');
        $this->gitHubTargetDirAbsolute =
            $this->targetDirAbsolute . DIRECTORY_SEPARATOR . $this->gitHubTargetDirRelative;
        $this->gitHubTemplateDirAbsolute =
            $this->vuFindHomeDirAbsolute . DIRECTORY_SEPARATOR . $this->gitHubTemplateDirRelative;
        $this->filesystem->mirror($this->gitHubTemplateDirAbsolute, $this->gitHubTargetDirAbsolute);
    }

    /**
     * Call file_get_contents and throw FileAccessException on error.
     *
     * $this->filesystem->readFile() is only available in newer Symfony versions
     * https://symfony.com/doc/current/components/filesystem.html
     *
     * @param string $path Path for file_get_contents
     *
     * @return string
     */
    protected function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        if (!$contents) {
            throw new FileAccessException('Could not read file ' . $path);
        }
        return $contents;
    }
}
