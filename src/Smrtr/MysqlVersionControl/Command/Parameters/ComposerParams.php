<?php

namespace Smrtr\MysqlVersionControl;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class ComposerParams looks for custom command parameters in the composer.json of the project.
 *
 * {
 *   "extra": {
 *     "mysql-version-control": {
 *       "cli": {
 *         "mysql-bin": "/path/to/mysql/bin",
 *         "--install-provisional-version": true,
 *         "--provisional-version": "new-version"
 *       }
 *     }
 *   }
 * }
 *
 * @package Smrtr\MysqlVersionControl
 * @author Joe Green <joe.green@smrtr.co.uk>
 */
class ComposerParams
{
    /**
     * Applies parameters that are defined in the extra section of composer.json.
     *
     * If the Input object already has a value for the argument or parameter then the value in composer is ignored.
     *
     * @param Command $command
     * @param InputInterface $input
     *
     * @return $this
     */
    public function applyComposerParams(Command $command, InputInterface $input)
    {
        $params = $this->getComposerParams($command->getName());
        $definition = $command->getDefinition();

        foreach ($this->filterComposerParams($params, $definition) as $param => $value) {

            if (0 === strpos($param, "--")) { // option

                $option = substr($param, 2);
                $Option = $definition->getOption($option);

                if (!$Option->acceptValue() && false === $input->getOption($option)) {
                    $input->setOption($option, null);
                } elseif ($Option->acceptValue() && $Option->getDefault() === $input->getOption($option)) {
                    $input->setOption($option, $value);
                }

            } else { // argument
                $argument = $definition->getArgument($param);
                if ($argument->getDefault() === $input->getArgument($param)) {
                    $input->setArgument($param, $value);
                }
            }
        }

        return $this;
    }

    /**
     * @param string|null $commandName
     * @param string|null $composerJsonFilePath
     *
     * @return array
     */
    protected function getComposerParams($commandName = null, $composerJsonFilePath = null)
    {
        if (null === $composerJsonFilePath) {
            $composerJsonFilePath = realpath(__DIR__.'/../../../../../../composer.json');
        }

        if (!is_file($composerJsonFilePath) or !is_readable($composerJsonFilePath)) {
            return [];
        }

        $parsedJson = json_decode(file_get_contents($composerJsonFilePath), true);

        if (!isset($parsedJson["extra"]["mysql-version-control"]["cli"])) {
            return [];
        }

        $params = $parsedJson["extra"]["mysql-version-control"]["cli"];

        if (!is_array($params)) {
            return [];
        }

        $commandParams = isset($params[$commandName]) && is_array($params[$commandName]) ? $params[$commandName] : [];

        foreach ($params as $key => $val) {
            if (isset($commandParams[$key])) {
                $params[$key] = $commandParams[$key];
            }
            if (!is_scalar($val)) {
                unset($params[$key]);
            }
        }

        return $params;
    }

    /**
     * @param array $params
     * @param InputDefinition $definition
     *
     * @return array
     */
    protected function filterComposerParams(array $params, InputDefinition $definition)
    {
        foreach ($params as $param => $value) {
            if (0 === strpos($param, "--")) {
                if (!$definition->hasOption(substr($param, 2))) {
                    unset($params[$param]);
                }
            } else {
                if (!$definition->hasArgument($param)) {
                    unset($params[$param]);
                }
            }
        }
        return $params;
    }
}