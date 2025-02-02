<?php

declare(strict_types=1);

namespace App\Commands\Servers;

use App\Command;
use App\Libs\Config;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class ViewCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('servers:view')
            ->setDescription('View Servers settings.')
            ->addOption(
                'servers-filter',
                's',
                InputOption::VALUE_OPTIONAL,
                'View selected servers, comma seperated. \'s1,s2\'.',
                ''
            )
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Use Alternative config file.')
            ->addArgument(
                'filter',
                InputArgument::OPTIONAL,
                'Can be any key from config, use dot notion to access sub keys, for example "webhook.token"'
            );
    }

    /**
     * @throws Exception
     */
    protected function runCommand(InputInterface $input, OutputInterface $output): int
    {
        // -- Use Custom servers.yaml file.
        if (($config = $input->getOption('config'))) {
            try {
                Config::save('servers', Yaml::parseFile($this->checkCustomServersFile($config)));
            } catch (RuntimeException $e) {
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                return self::FAILURE;
            }
        }

        $list = [];
        $serversFilter = (string)$input->getOption('servers-filter');
        $selected = array_map('trim', explode(',', $serversFilter));
        $isCustom = !empty($serversFilter) && count($selected) >= 1;
        $filter = $input->getArgument('filter');

        foreach (Config::get('servers', []) as $serverName => $server) {
            if ($isCustom && !in_array($serverName, $selected, true)) {
                $output->writeln(
                    sprintf(
                        '<comment>Ignoring \'%s\' as requested by [-s, --servers-filter] flag.</comment>',
                        $serverName
                    ),
                    OutputInterface::VERBOSITY_DEBUG
                );
                continue;
            }

            $list[$serverName] = ['name' => $serverName, ...$server];
        }

        if (empty($list)) {
            throw new RuntimeException(
                $isCustom ? '--servers-filter/-s did not return any server.' : 'No server were found.'
            );
        }

        $x = 0;
        $count = count($list);

        $rows = [];
        foreach ($list as $serverName => $server) {
            $x++;
            $rows[] = [
                $serverName,
                trim(Yaml::dump(ag($server, $filter, 'Not configured, or invalid key.'), 8, 2))
            ];

            if ($x < $count) {
                $rows[] = new TableSeparator();
            }
        }

        (new Table($output))->setStyle('box')
            ->setHeaders(['Server', 'Filter: ' . (empty($filter) ? 'None' : $filter)]
            )->setRows($rows)
            ->render();

        return self::SUCCESS;
    }
}
