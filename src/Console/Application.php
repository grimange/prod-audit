<?php

declare(strict_types=1);

namespace ProdAudit\Console;

use ProdAudit\Audit\AuditRunner;
use ProdAudit\Audit\Baseline\BaselineRepository;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\ComposerCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Collectors\PhpConfigCollector;
use ProdAudit\Audit\Filtering\FindingFilter;
use ProdAudit\Audit\FindingAggregator;
use ProdAudit\Audit\Plugins\PluginLoader;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Reporting\HistoryWriter;
use ProdAudit\Audit\Reporting\JsonReportWriter;
use ProdAudit\Audit\Reporting\MarkdownReportWriter;
use ProdAudit\Audit\Reporting\TrendAnalyzer;
use ProdAudit\Audit\Rules\RuleRegistry;
use ProdAudit\Audit\RuleScheduler;
use ProdAudit\Audit\Scoring\BandClassifier;
use ProdAudit\Audit\Scoring\ScoreEngine;
use ProdAudit\Audit\Suppression\SuppressionRepository;
use ProdAudit\Console\Commands\BaselineCommand;
use ProdAudit\Console\Commands\CompareCommand;
use ProdAudit\Console\Commands\ExplainRuleCommand;
use ProdAudit\Console\Commands\ScanCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('prod-audit', '0.4.0-stage4');

        $pluginLoader = new PluginLoader();
        $profileRegistry = new ProfileRegistry();
        $ruleRegistry = new RuleRegistry();
        $pluginLoader->load(getcwd() ?: '.', $profileRegistry, $ruleRegistry);

        $runner = new AuditRunner(
            new RuleScheduler($ruleRegistry),
            new FindingAggregator(),
            new FileCollector(),
            new AstCollector(),
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

        $baselineRepository = new BaselineRepository();
        $suppressionRepository = new SuppressionRepository();

        $this->add(new ScanCommand($runner, $profileRegistry, $baselineRepository, $suppressionRepository, $pluginLoader, $ruleRegistry));
        $this->add(new BaselineCommand($runner, $profileRegistry, $baselineRepository, $pluginLoader, $ruleRegistry));
        $this->add(new CompareCommand());
        $this->add(new ExplainRuleCommand($ruleRegistry, $pluginLoader, $profileRegistry));
    }
}
