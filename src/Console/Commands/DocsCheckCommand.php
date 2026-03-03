<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Plugins\PluginLoader;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;
use Throwable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DocsCheckCommand extends Command
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly PluginLoader $pluginLoader,
        private readonly ProfileRegistry $profileRegistry,
        private readonly PackRegistry $packRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('docs-check')
            ->setDescription('Validate rule documentation parity for all registered rules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->pluginLoader->load(getcwd() ?: '.', $this->profileRegistry, $this->ruleRegistry, $this->packRegistry);

            $missing = [];
            foreach ($this->ruleRegistry->ids() as $ruleId) {
                $docPath = 'docs/rules/' . $ruleId . '.md';
                if (!is_file($docPath)) {
                    $missing[] = $docPath;
                }
            }

            sort($missing, SORT_STRING);

            if ($missing === []) {
                $output->writeln('Docs parity check passed.');

                return 0;
            }

            $output->writeln('Missing rule docs:');
            foreach ($missing as $path) {
                $output->writeln('- ' . $path);
            }

            return 7;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }
}
