<?php

declare(strict_types=1);

namespace ProdAudit\Console;

use ProdAudit\Audit\AuditRunner;
use ProdAudit\Audit\Baseline\BaselineRepository;
use ProdAudit\Audit\Collectors\ComposerCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Collectors\PhpConfigCollector;
use ProdAudit\Audit\Filtering\FindingFilter;
use ProdAudit\Audit\FindingAggregator;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Reporting\HistoryWriter;
use ProdAudit\Audit\Reporting\JsonReportWriter;
use ProdAudit\Audit\Reporting\MarkdownReportWriter;
use ProdAudit\Audit\Reporting\TrendAnalyzer;
use ProdAudit\Audit\Rules\PR_ERR_001_SwallowedExceptionsRule;
use ProdAudit\Audit\Rules\PR_HANG_001_InfiniteLoopRule;
use ProdAudit\Audit\Rules\PR_LOCK_001_LockRenewRule;
use ProdAudit\Audit\RuleScheduler;
use ProdAudit\Audit\Scoring\BandClassifier;
use ProdAudit\Audit\Scoring\ScoreEngine;
use ProdAudit\Audit\Suppression\SuppressionRepository;
use ProdAudit\Console\Commands\BaselineCommand;
use ProdAudit\Console\Commands\CompareCommand;
use ProdAudit\Console\Commands\ScanCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('prod-audit', '0.1.0-stage1');

        $runner = new AuditRunner(
            new RuleScheduler([
                new PR_ERR_001_SwallowedExceptionsRule(),
                new PR_HANG_001_InfiniteLoopRule(),
                new PR_LOCK_001_LockRenewRule(),
            ]),
            new FindingAggregator(),
            new FileCollector(),
            new PatternCollector(),
            new ComposerCollector(),
            new PhpConfigCollector(),
            new ScoreEngine(),
            new BandClassifier(),
            new MarkdownReportWriter(),
            new JsonReportWriter(),
            new HistoryWriter(),
            new TrendAnalyzer(),
            new FindingFilter(),
        );

        $profileRegistry = new ProfileRegistry();
        $baselineRepository = new BaselineRepository();
        $suppressionRepository = new SuppressionRepository();

        $this->add(new ScanCommand($runner, $profileRegistry, $baselineRepository, $suppressionRepository));
        $this->add(new BaselineCommand($runner, $profileRegistry, $baselineRepository));
        $this->add(new CompareCommand());
    }
}
