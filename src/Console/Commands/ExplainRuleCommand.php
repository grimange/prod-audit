<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use InvalidArgumentException;
use ProdAudit\Audit\Plugins\PluginLoader;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ExplainRuleCommand extends Command
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly PluginLoader $pluginLoader,
        private readonly ProfileRegistry $profileRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('explain-rule')
            ->setDescription('Show deterministic rule explanation')
            ->addArgument('rule-id', InputArgument::REQUIRED, 'Rule ID to explain');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->pluginLoader->load(getcwd() ?: '.', $this->profileRegistry, $this->ruleRegistry);
            $ruleId = strtoupper(trim((string) $input->getArgument('rule-id')));
            $rule = $this->ruleRegistry->get($ruleId);
            if ($rule === null) {
                throw new InvalidArgumentException(sprintf('Unknown rule "%s".', $ruleId));
            }

            $metadata = $rule->metadata();
            $output->writeln(sprintf('Rule: %s', $metadata->id));
            $output->writeln(sprintf('Title: %s', $metadata->title));
            $output->writeln(sprintf('Invariant: %s', $metadata->invariant ? 'yes' : 'no'));
            $output->writeln(sprintf('Description: %s', $metadata->description));
            $output->writeln('Confidence model:');
            foreach ($this->confidenceModel($metadata->id) as $line) {
                $output->writeln(sprintf('- %s', $line));
            }
            $output->writeln('Evidence types:');
            foreach ($this->evidenceTypes($metadata->id) as $line) {
                $output->writeln(sprintf('- %s', $line));
            }

            $docPath = 'docs/rules/' . $metadata->id . '.md';
            if (is_file($docPath)) {
                $output->writeln(sprintf('Documentation: %s', $docPath));
            }

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @return array<int, string>
     */
    private function confidenceModel(string $ruleId): array
    {
        return match ($ruleId) {
            'PR-ERR-001' => [
                'high when AST confirms swallowed catch body',
                'medium when regex fallback detects swallowed catch',
            ],
            'PR-HANG-001' => [
                'high when AST confirms infinite loop body lacks guards',
                'medium when regex fallback detects infinite loop without hints',
            ],
            'PR-LOCK-001' => [
                'medium when AST confirms expire-like call without eval/evalsha Lua scope',
                'low when regex fallback triggers heuristic risk',
            ],
            default => ['rule-specific confidence model unavailable'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function evidenceTypes(string $ruleId): array
    {
        return match ($ruleId) {
            'PR-ERR-001', 'PR-HANG-001', 'PR-LOCK-001' => [
                'ast_node (line range + snippet)',
                'file_snippet (regex fallback)',
            ],
            default => ['file_snippet'],
        };
    }
}
