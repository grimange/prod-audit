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
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;
use ProdAudit\Audit\RuleScheduler;
use ProdAudit\Audit\Scoring\BandClassifier;
use ProdAudit\Audit\Scoring\ScoreEngine;
use ProdAudit\Audit\Suppression\SuppressionRepository;
use ProdAudit\Audit\Tasks\TaskMap;
use ProdAudit\Audit\Tasks\TaskRecommender;
use ProdAudit\Console\Commands\BaselineCommand;
use ProdAudit\Console\Commands\CompareCommand;
use ProdAudit\Console\Commands\DocsCheckCommand;
use ProdAudit\Console\Commands\ExplainRuleCommand;
use ProdAudit\Console\Commands\ForecastCommand;
use ProdAudit\Console\Commands\QualityCommand;
use ProdAudit\Console\Commands\ReproduceCommand;
use ProdAudit\Console\Commands\ScanCommand;
use ProdAudit\Console\Commands\TriageCommand;
use ProdAudit\Console\Commands\TriageListCommand;
use ProdAudit\Console\Commands\TriageSuggestCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('prod-audit', '0.6.0-stage7');

        $pluginLoader = new PluginLoader();
        $profileRegistry = new ProfileRegistry();
        $ruleRegistry = new RuleRegistry();
        $packRegistry = new PackRegistry();
        $pluginLoader->load(getcwd() ?: '.', $profileRegistry, $ruleRegistry, $packRegistry);

        $runner = new AuditRunner(
            new RuleScheduler($ruleRegistry, $packRegistry),
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
            new TaskRecommender(new TaskMap()),
        );

        $baselineRepository = new BaselineRepository();
        $suppressionRepository = new SuppressionRepository();

        $this->add(new ScanCommand($runner, $profileRegistry, $baselineRepository, $suppressionRepository, $pluginLoader, $ruleRegistry, $packRegistry));
        $this->add(new BaselineCommand($runner, $profileRegistry, $baselineRepository, $pluginLoader, $ruleRegistry, $packRegistry));
        $this->add(new CompareCommand());
        $this->add(new ExplainRuleCommand($ruleRegistry, $pluginLoader, $profileRegistry, $packRegistry));
        $this->add(new DocsCheckCommand($ruleRegistry, $pluginLoader, $profileRegistry, $packRegistry));
        $this->add(new TriageCommand());
        $this->add(new TriageListCommand());
        $this->add(new ForecastCommand());
        $this->add(new QualityCommand($ruleRegistry));
        $this->add(new ReproduceCommand());
        $this->add(new TriageSuggestCommand());
    }
}
