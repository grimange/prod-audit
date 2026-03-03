<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Tasks;

use ProdAudit\Audit\Profiles\ProfileInterface;

final class TaskRecommender
{
    public function __construct(
        private readonly TaskMap $taskMap,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @return array<int, TaskRecommendation>
     */
    public function recommend(array $findings, ProfileInterface $profile): array
    {
        $mapping = $this->taskMap->mapping();

        /** @var array<string, array{template: array<string, mixed>, findings: array<int, array<string, mixed>>, has_invariant: bool, highest_weight: int}> $grouped */
        $grouped = [];

        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $ruleId = (string) ($finding['rule_id'] ?? '');
            if ($ruleId === '' || !isset($mapping[$ruleId])) {
                continue;
            }

            $template = $mapping[$ruleId];
            $taskId = (string) ($template['id'] ?? '');
            if ($taskId === '') {
                continue;
            }

            if (!isset($grouped[$taskId])) {
                $grouped[$taskId] = [
                    'template' => $template,
                    'findings' => [],
                    'has_invariant' => false,
                    'highest_weight' => 0,
                ];
            }

            $grouped[$taskId]['findings'][] = $finding;
            $grouped[$taskId]['has_invariant'] = $grouped[$taskId]['has_invariant'] || ((bool) ($finding['invariant_failure'] ?? false));
            $grouped[$taskId]['highest_weight'] = max(
                $grouped[$taskId]['highest_weight'],
                $this->severityWeight((string) ($finding['severity'] ?? 'info'))
            );
        }

        ksort($grouped, SORT_STRING);

        $tasks = [];
        foreach ($grouped as $taskId => $entry) {
            $template = $entry['template'];
            $evidenceRefs = [];
            $relatedRules = [];

            foreach ($entry['findings'] as $finding) {
                $relatedRules[(string) ($finding['rule_id'] ?? '')] = true;

                $fingerprint = (string) ($finding['fingerprint'] ?? '');
                if ($fingerprint !== '') {
                    $evidenceRefs[$fingerprint] = $fingerprint;
                }
            }

            $ruleList = array_keys(array_filter($relatedRules, static fn (bool $set): bool => $set));
            sort($ruleList, SORT_STRING);
            $refs = array_values($evidenceRefs);
            sort($refs, SORT_STRING);

            $tasks[] = [
                'task' => new TaskRecommendation(
                    id: $taskId,
                    title: (string) ($template['title'] ?? ''),
                    why: (string) ($template['why'] ?? ''),
                    relatedRules: $ruleList,
                    effort: (string) ($template['effort'] ?? 'M'),
                    riskReduction: (string) ($template['risk_reduction'] ?? 'Med'),
                    steps: is_array($template['steps'] ?? null)
                        ? array_values(array_map(static fn (mixed $step): string => (string) $step, $template['steps']))
                        : [],
                    evidenceRefs: $refs,
                ),
                'has_invariant' => $entry['has_invariant'],
                'is_critical_or_major' => $entry['highest_weight'] >= 3,
                'findings_count' => count($entry['findings']),
                'effort_rank' => $this->effortRank((string) ($template['effort'] ?? 'M')),
                'id' => $taskId,
            ];
        }

        usort($tasks, static function (array $a, array $b): int {
            if ($a['has_invariant'] !== $b['has_invariant']) {
                return $a['has_invariant'] ? -1 : 1;
            }

            if ($a['is_critical_or_major'] !== $b['is_critical_or_major']) {
                return $a['is_critical_or_major'] ? -1 : 1;
            }

            if ($a['findings_count'] !== $b['findings_count']) {
                return $b['findings_count'] <=> $a['findings_count'];
            }

            if ($a['effort_rank'] !== $b['effort_rank']) {
                return $a['effort_rank'] <=> $b['effort_rank'];
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        return array_values(array_map(static fn (array $item): TaskRecommendation => $item['task'], $tasks));
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'major' => 3,
            'minor' => 2,
            default => 1,
        };
    }

    private function effortRank(string $effort): int
    {
        return match (strtoupper($effort)) {
            'S' => 1,
            'M' => 2,
            'L' => 3,
            default => 4,
        };
    }
}
