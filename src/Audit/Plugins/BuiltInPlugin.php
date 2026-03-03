<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Plugins;

use ProdAudit\Audit\Profiles\Dialer24x7Profile;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\Pack;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

final class BuiltInPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'built-in';
    }

    public function register(ProfileRegistry $profiles, RuleRegistry $rules, PackRegistry $packs): void
    {
        $profiles->register(new Dialer24x7Profile());

        $ruleClasses = [
            'ProdAudit\\Audit\\Rules\\PR_BOUND_002_UnboundedArrayGrowthRule',
            'ProdAudit\\Audit\\Rules\\PR_BOUND_003_UnboundedRedisKeyGrowthRule',
            'ProdAudit\\Audit\\Rules\\PR_BOUND_004_CacheWithoutEvictionRule',
            'ProdAudit\\Audit\\Rules\\PR_BOUND_005_UnboundedQueuePublishRule',
            'ProdAudit\\Audit\\Rules\\PR_BOUND_006_UnboundedLogGrowthRule',
            'ProdAudit\\Audit\\Rules\\PR_BOUND_007_UnboundedMapGrowthRule',
            'ProdAudit\\Audit\\Rules\\PR_BOUND_008_LargeArraysWithoutChunkingRule',
            'ProdAudit\\Audit\\Rules\\PR_CONC_001_SharedMutableStaticStateRule',
            'ProdAudit\\Audit\\Rules\\PR_CONF_002_GetenvScatteredUsageRule',
            'ProdAudit\\Audit\\Rules\\PR_CONF_003_MissingDefaultConfigRule',
            'ProdAudit\\Audit\\Rules\\PR_CONF_004_DangerousDefaultConfigRule',
            'ProdAudit\\Audit\\Rules\\PR_CONF_005_HardcodedPortsRule',
            'ProdAudit\\Audit\\Rules\\PR_CONF_006_HardcodedHostnamesRule',
            'ProdAudit\\Audit\\Rules\\PR_CONF_007_HardcodedCredentialsHeuristicRule',
            'ProdAudit\\Audit\\Rules\\PR_DEP_001_AbandonedPackagesRule',
            'ProdAudit\\Audit\\Rules\\PR_DEP_002_DevPackagesUsedInRuntimeRule',
            'ProdAudit\\Audit\\Rules\\PR_DEP_003_NoVersionConstraintsRule',
            'ProdAudit\\Audit\\Rules\\PR_DEP_004_WildcardDependenciesRule',
            'ProdAudit\\Audit\\Rules\\PR_DEP_005_OutdatedPackagesHeuristicRule',
            'ProdAudit\\Audit\\Rules\\PR_DOC_001_MissingReadmeRule',
            'ProdAudit\\Audit\\Rules\\PR_DOC_002_MissingRunbookRule',
            'ProdAudit\\Audit\\Rules\\PR_DOC_003_MissingFailureModesRule',
            'ProdAudit\\Audit\\Rules\\PR_DOC_004_MissingArchitectureDocsRule',
            'ProdAudit\\Audit\\Rules\\PR_DOC_005_MissingConfigDocsRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_001_SwallowedExceptionsRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_002_LoggingWithoutEscalationRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_003_SuppressedWarningsRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_004_SilentCatchWithFallbackReturnRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_005_PartialExceptionHandlingRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_006_IgnoredPromiseFutureResultRule',
            'ProdAudit\\Audit\\Rules\\PR_ERR_007_IgnoredReturnValueRule',
            'ProdAudit\\Audit\\Rules\\PR_HANG_001_InfiniteLoopRule',
            'ProdAudit\\Audit\\Rules\\PR_LOCK_001_LockRenewRule',
            'ProdAudit\\Audit\\Rules\\PR_LOCK_002_MissingFencingTokenRule',
            'ProdAudit\\Audit\\Rules\\PR_LOCK_003_LockTtlTooSmallRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_001_MissingLoggerContextRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_002_MissingJobIdentifiersRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_003_MissingCorrelationIdRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_004_LoggingWithoutContextRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_005_MissingErrorLogsRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_006_MissingStartupLogsRule',
            'ProdAudit\\Audit\\Rules\\PR_OBS_007_MissingShutdownLogsRule',
            'ProdAudit\\Audit\\Rules\\PR_RETRY_001_RetryLoopWithoutBackoffRule',
            'ProdAudit\\Audit\\Rules\\PR_RETRY_002_RetryWithoutMaxAttemptsRule',
            'ProdAudit\\Audit\\Rules\\PR_RETRY_003_FixedIntervalRetryRule',
            'ProdAudit\\Audit\\Rules\\PR_SEC_001_PossibleSecretsInCodeRule',
            'ProdAudit\\Audit\\Rules\\PR_SEC_002_Md5Sha1UsageRule',
            'ProdAudit\\Audit\\Rules\\PR_SEC_003_ShellExecUsageRule',
            'ProdAudit\\Audit\\Rules\\PR_SEC_004_UnsafeEvalUsageRule',
            'ProdAudit\\Audit\\Rules\\PR_SEC_005_DirectSqlQueryBuildingRule',
            'ProdAudit\\Audit\\Rules\\PR_SHUTDOWN_001_MissingSignalHandlingRule',
            'ProdAudit\\Audit\\Rules\\PR_SHUTDOWN_002_UnsafeShutdownCleanupRule',
            'ProdAudit\\Audit\\Rules\\PR_STATE_001_StateTransitionNotValidatedRule',
            'ProdAudit\\Audit\\Rules\\PR_STATE_002_StateFallbackUnsafeRule',
            'ProdAudit\\Audit\\Rules\\PR_TIME_001_ExternalCallTimeoutRule',
            'ProdAudit\\Audit\\Rules\\PR_TIME_002_DatabaseCallsWithoutTimeoutRule',
            'ProdAudit\\Audit\\Rules\\PR_TIME_003_RedisCallsWithoutTimeoutRule',
            'ProdAudit\\Audit\\Rules\\PR_TIME_004_SocketCallsWithoutTimeoutRule',
            'ProdAudit\\Audit\\Rules\\PR_TIME_005_BlockingIoWithoutTimeoutRule',
            'ProdAudit\\Audit\\Rules\\PR_TIME_006_InfiniteWaitLoopsRule',
        ];

        sort($ruleClasses, SORT_STRING);
        foreach ($ruleClasses as $ruleClass) {
            $rules->register(new $ruleClass());
        }

        $packs->register(new Pack(
            name: 'reliability',
            description: 'Reliability and safety-critical runtime behavior checks.',
            ruleIds: [
                'PR-LOCK-001',
                'PR-LOCK-002',
                'PR-LOCK-003',
                'PR-RETRY-001',
                'PR-RETRY-002',
                'PR-RETRY-003',
                'PR-SHUTDOWN-001',
                'PR-SHUTDOWN-002',
                'PR-STATE-001',
                'PR-STATE-002',
                'PR-CONC-001',
                'PR-HANG-001',
            ],
            defaultEnabled: true,
        ));
        $packs->register(new Pack(
            name: 'timeout',
            description: 'Timeout hygiene checks for external calls, io, and wait loops.',
            ruleIds: [
                'PR-TIME-001',
                'PR-TIME-002',
                'PR-TIME-003',
                'PR-TIME-004',
                'PR-TIME-005',
                'PR-TIME-006',
            ],
            defaultEnabled: true,
        ));
        $packs->register(new Pack(
            name: 'bounds',
            description: 'Memory, queue, cache, and growth-bound controls.',
            ruleIds: [
                'PR-BOUND-002',
                'PR-BOUND-003',
                'PR-BOUND-004',
                'PR-BOUND-005',
                'PR-BOUND-006',
                'PR-BOUND-007',
                'PR-BOUND-008',
            ],
            defaultEnabled: true,
        ));
        $packs->register(new Pack(
            name: 'error-handling',
            description: 'Exception and failure handling behavior checks.',
            ruleIds: [
                'PR-ERR-001',
                'PR-ERR-002',
                'PR-ERR-003',
                'PR-ERR-004',
                'PR-ERR-005',
                'PR-ERR-006',
                'PR-ERR-007',
            ],
            defaultEnabled: true,
        ));
        $packs->register(new Pack(
            name: 'observability',
            description: 'Logging and context propagation checks for incident response.',
            ruleIds: [
                'PR-OBS-001',
                'PR-OBS-002',
                'PR-OBS-003',
                'PR-OBS-004',
                'PR-OBS-005',
                'PR-OBS-006',
                'PR-OBS-007',
            ],
            defaultEnabled: true,
        ));
        $packs->register(new Pack(
            name: 'config-safety',
            description: 'Configuration defaults, host/port safety, and credential hygiene checks.',
            ruleIds: [
                'PR-CONF-002',
                'PR-CONF-003',
                'PR-CONF-004',
                'PR-CONF-005',
                'PR-CONF-006',
                'PR-CONF-007',
            ],
            defaultEnabled: false,
        ));
        $packs->register(new Pack(
            name: 'dependency',
            description: 'Dependency policy and composer manifest guardrails.',
            ruleIds: [
                'PR-DEP-001',
                'PR-DEP-002',
                'PR-DEP-003',
                'PR-DEP-004',
                'PR-DEP-005',
            ],
            defaultEnabled: false,
        ));
        $packs->register(new Pack(
            name: 'documentation',
            description: 'Operational documentation completeness checks.',
            ruleIds: [
                'PR-DOC-001',
                'PR-DOC-002',
                'PR-DOC-003',
                'PR-DOC-004',
                'PR-DOC-005',
            ],
            defaultEnabled: false,
        ));
        $packs->register(new Pack(
            name: 'security-baseline',
            description: 'Lightweight security baseline checks.',
            ruleIds: [
                'PR-SEC-001',
                'PR-SEC-002',
                'PR-SEC-003',
                'PR-SEC-004',
                'PR-SEC-005',
            ],
            defaultEnabled: true,
        ));
    }
}
