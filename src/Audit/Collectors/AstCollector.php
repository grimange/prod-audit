<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Collectors;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

final class AstCollector
{
    /**
     * @param array<int, array{path: string, relative_path: string, extension: string, size: int}> $files
     * @return array{
     *     summary: array{ok: int, failed: int},
     *     files: array<string, array{status: string, error: ?string}>,
     *     catch_blocks: array<int, array<string, mixed>>,
     *     loops: array<int, array<string, mixed>>,
     *     scopes: array<int, array<string, mixed>>
     * }
     */
    public function collect(array $files, FileCollector $fileCollector): array
    {
        $phpFiles = array_values(array_filter(
            $files,
            static fn (array $file): bool => (($file['extension'] ?? '') === 'php')
        ));

        usort(
            $phpFiles,
            static fn (array $a, array $b): int => strcmp((string) $a['relative_path'], (string) $b['relative_path'])
        );

        $parser = (new ParserFactory())->createForHostVersion();

        $ok = 0;
        $failed = 0;
        $fileStatuses = [];
        $catchBlocks = [];
        $loops = [];
        $scopes = [];
        $scopeCounter = 0;

        foreach ($phpFiles as $file) {
            $path = (string) $file['path'];
            $relativePath = (string) $file['relative_path'];
            $content = $fileCollector->readContent($path);
            if ($content === null) {
                $fileStatuses[$relativePath] = [
                    'status' => 'error',
                    'error' => 'Unable to read file content',
                ];
                ++$failed;
                continue;
            }

            try {
                $ast = $parser->parse($content);
            } catch (Error $error) {
                $fileStatuses[$relativePath] = [
                    'status' => 'error',
                    'error' => $error->getMessage(),
                ];
                ++$failed;
                continue;
            }

            if (!is_array($ast)) {
                $fileStatuses[$relativePath] = [
                    'status' => 'error',
                    'error' => 'Parser returned empty AST',
                ];
                ++$failed;
                continue;
            }

            $fileStatuses[$relativePath] = [
                'status' => 'ok',
                'error' => null,
            ];
            ++$ok;

            ++$scopeCounter;
            $scopes[] = [
                'id' => $scopeCounter,
                'file' => $relativePath,
                'name' => '__global__',
                'start_line' => 1,
                'end_line' => max(1, substr_count($content, "\n") + 1),
                'calls' => [],
            ];

            $scopeCounter = $this->walkNodes(
                $ast,
                $relativePath,
                $path,
                'scope:' . $scopeCounter,
                $scopeCounter,
                $catchBlocks,
                $loops,
                $scopes,
                $fileCollector
            );
        }

        ksort($fileStatuses, SORT_STRING);

        usort($catchBlocks, static fn (array $a, array $b): int => self::compareByFileAndLine($a, $b));
        usort($loops, static fn (array $a, array $b): int => self::compareByFileAndLine($a, $b));
        usort(
            $scopes,
            static fn (array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0))
        );

        return [
            'summary' => [
                'ok' => $ok,
                'failed' => $failed,
            ],
            'files' => $fileStatuses,
            'catch_blocks' => $catchBlocks,
            'loops' => $loops,
            'scopes' => $scopes,
        ];
    }

    /**
     * @param array<int, Node> $nodes
     * @param array<int, array<string, mixed>> $catchBlocks
     * @param array<int, array<string, mixed>> $loops
     * @param array<int, array<string, mixed>> $scopes
     */
    private function walkNodes(
        array $nodes,
        string $relativePath,
        string $path,
        string $scopeRef,
        int $scopeCounter,
        array &$catchBlocks,
        array &$loops,
        array &$scopes,
        FileCollector $fileCollector
    ): int {
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod || $node instanceof Expr\Closure) {
                ++$scopeCounter;
                $scopeRef = 'scope:' . $scopeCounter;
                $scopes[] = [
                    'id' => $scopeCounter,
                    'file' => $relativePath,
                    'name' => $this->scopeName($node),
                    'start_line' => $this->line($node, true),
                    'end_line' => $this->line($node, false),
                    'calls' => [],
                ];
            }

            if ($node instanceof Stmt\TryCatch) {
                foreach ($node->catches as $catchNode) {
                    $statementTypes = array_values(array_map(
                        static fn (Node $stmt): string => $stmt::class,
                        $catchNode->stmts
                    ));
                    $catchBlocks[] = [
                        'file' => $relativePath,
                        'scope_ref' => $scopeRef,
                        'start_line' => $this->line($catchNode, true),
                        'end_line' => $this->line($catchNode, false),
                        'statement_types' => $statementTypes,
                        'has_rethrow' => $this->containsRethrow($catchNode->stmts),
                        'has_observability_call' => $this->containsObservabilityCall($catchNode->stmts),
                        'only_control_flow' => $this->hasOnlyControlFlow($catchNode->stmts),
                        'is_empty' => $catchNode->stmts === [],
                        'snippet' => $fileCollector->snippet(
                            $path,
                            $this->line($catchNode, true),
                            $this->line($catchNode, false)
                        ),
                    ];
                }
            }

            if ($node instanceof Stmt\While_ || $node instanceof Stmt\For_) {
                if ($this->isInfiniteLoop($node)) {
                    $loopSignals = $this->loopSignals($node->stmts);
                    $loops[] = [
                        'file' => $relativePath,
                        'scope_ref' => $scopeRef,
                        'start_line' => $this->line($node, true),
                        'end_line' => $this->line($node, false),
                        'has_sleep' => $loopSignals['has_sleep'],
                        'has_yield' => $loopSignals['has_yield'],
                        'has_timeout_check' => $loopSignals['has_timeout_check'],
                        'has_budget_decrement' => $loopSignals['has_budget_decrement'],
                        'has_heartbeat_call' => $loopSignals['has_heartbeat_call'],
                        'body_inspected' => true,
                        'snippet' => $fileCollector->snippet($path, $this->line($node, true), $this->line($node, false)),
                    ];
                }
            }

            if ($node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall || $node instanceof Expr\FuncCall) {
                $callData = [
                    'name' => $this->callName($node),
                    'target' => $this->callTarget($node),
                    'start_line' => $this->line($node, true),
                    'end_line' => $this->line($node, false),
                    'has_lua_arg' => $this->hasLuaArgument($node),
                ];

                $scopeIndex = $this->findScopeIndexByRef($scopes, $scopeRef, $relativePath);
                if ($scopeIndex !== null) {
                    $scopes[$scopeIndex]['calls'][] = $callData;
                }
            }

            $children = $node->getSubNodeNames();
            foreach ($children as $childName) {
                $child = $node->{$childName};
                if (is_array($child)) {
                    $childNodes = array_values(array_filter($child, static fn ($item): bool => $item instanceof Node));
                    if ($childNodes !== []) {
                        $scopeCounter = $this->walkNodes(
                            $childNodes,
                            $relativePath,
                            $path,
                            $scopeRef,
                            $scopeCounter,
                            $catchBlocks,
                            $loops,
                            $scopes,
                            $fileCollector
                        );
                    }
                    continue;
                }

                if ($child instanceof Node) {
                    $scopeCounter = $this->walkNodes(
                        [$child],
                        $relativePath,
                        $path,
                        $scopeRef,
                        $scopeCounter,
                        $catchBlocks,
                        $loops,
                        $scopes,
                        $fileCollector
                    );
                }
            }
        }

        return $scopeCounter;
    }

    /**
     * @param array<int, Stmt> $stmts
     */
    private function containsRethrow(array $stmts): bool
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Throw_) {
                return true;
            }

            foreach ($stmt->getSubNodeNames() as $childName) {
                $child = $stmt->{$childName};
                if ($child instanceof Stmt\Throw_) {
                    return true;
                }
                if (is_array($child)) {
                    foreach ($child as $nested) {
                        if ($nested instanceof Stmt\Throw_) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, Stmt> $stmts
     */
    private function containsObservabilityCall(array $stmts): bool
    {
        $allowlist = ['log', 'logger', 'report', 'emit', 'notify'];

        foreach ($stmts as $stmt) {
            foreach ($stmt->getSubNodeNames() as $childName) {
                $child = $stmt->{$childName};

                if ($child instanceof Expr\MethodCall || $child instanceof Expr\StaticCall || $child instanceof Expr\FuncCall) {
                    $name = strtolower($this->callName($child));
                    if (in_array($name, $allowlist, true)) {
                        return true;
                    }
                }

                if (is_array($child)) {
                    foreach ($child as $nested) {
                        if ($nested instanceof Expr\MethodCall || $nested instanceof Expr\StaticCall || $nested instanceof Expr\FuncCall) {
                            $name = strtolower($this->callName($nested));
                            if (in_array($name, $allowlist, true)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, Stmt> $stmts
     */
    private function hasOnlyControlFlow(array $stmts): bool
    {
        if ($stmts === []) {
            return false;
        }

        foreach ($stmts as $stmt) {
            if (
                !$stmt instanceof Stmt\Return_
                && !$stmt instanceof Stmt\Break_
                && !$stmt instanceof Stmt\Continue_
            ) {
                return false;
            }
        }

        return true;
    }

    private function isInfiniteLoop(Stmt\While_|Stmt\For_ $node): bool
    {
        if ($node instanceof Stmt\For_) {
            return $node->cond === [];
        }

        return $node->cond instanceof Expr\ConstFetch
            && strtolower($node->cond->name->toString()) === 'true';
    }

    /**
     * @param array<int, Stmt> $stmts
     * @return array{has_sleep: bool, has_yield: bool, has_timeout_check: bool, has_budget_decrement: bool, has_heartbeat_call: bool}
     */
    private function loopSignals(array $stmts): array
    {
        $signals = [
            'has_sleep' => false,
            'has_yield' => false,
            'has_timeout_check' => false,
            'has_budget_decrement' => false,
            'has_heartbeat_call' => false,
        ];

        $heartbeatAllowlist = ['heartbeat', 'tick'];

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Expression) {
                $expr = $stmt->expr;
                if ($expr instanceof Expr\Yield_ || $expr instanceof Expr\YieldFrom) {
                    $signals['has_yield'] = true;
                }

                if (
                    $expr instanceof Expr\PreDec
                    || $expr instanceof Expr\PostDec
                    || $expr instanceof Expr\AssignOp\Minus
                ) {
                    $signals['has_budget_decrement'] = true;
                }

                if ($expr instanceof Expr\BinaryOp\Smaller || $expr instanceof Expr\BinaryOp\SmallerOrEqual || $expr instanceof Expr\BinaryOp\Greater || $expr instanceof Expr\BinaryOp\GreaterOrEqual) {
                    if ($this->isTimeComparison($expr->left) || $this->isTimeComparison($expr->right)) {
                        $signals['has_timeout_check'] = true;
                    }
                }

                if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\StaticCall || $expr instanceof Expr\FuncCall) {
                    $name = strtolower($this->callName($expr));
                    if ($name === 'sleep' || $name === 'usleep') {
                        $signals['has_sleep'] = true;
                    }
                    if (in_array($name, $heartbeatAllowlist, true)) {
                        $signals['has_heartbeat_call'] = true;
                    }
                }
            }

            if ($stmt instanceof Stmt\If_) {
                if ($stmt->cond instanceof Expr\BinaryOp\Smaller || $stmt->cond instanceof Expr\BinaryOp\SmallerOrEqual || $stmt->cond instanceof Expr\BinaryOp\Greater || $stmt->cond instanceof Expr\BinaryOp\GreaterOrEqual) {
                    if ($this->isTimeComparison($stmt->cond->left) || $this->isTimeComparison($stmt->cond->right)) {
                        $signals['has_timeout_check'] = true;
                    }
                }
            }
        }

        return $signals;
    }

    private function isTimeComparison(Expr $expr): bool
    {
        if (!$expr instanceof Expr\FuncCall) {
            return false;
        }

        $name = strtolower($this->callName($expr));

        return $name === 'time' || $name === 'microtime';
    }

    private function callName(Expr\MethodCall|Expr\StaticCall|Expr\FuncCall $call): string
    {
        if ($call instanceof Expr\FuncCall) {
            return $this->nameToString($call->name);
        }

        return $this->nameToString($call->name);
    }

    private function callTarget(Expr\MethodCall|Expr\StaticCall|Expr\FuncCall $call): string
    {
        if ($call instanceof Expr\MethodCall) {
            if ($call->var instanceof Expr\Variable && is_string($call->var->name)) {
                return $call->var->name;
            }
        }

        return '';
    }

    private function hasLuaArgument(Expr\MethodCall|Expr\StaticCall|Expr\FuncCall $call): bool
    {
        foreach ($call->args as $arg) {
            $value = $arg->value;
            if ($value instanceof Node\Scalar\String_ && trim($value->value) !== '') {
                return true;
            }

            if ($value instanceof Expr\Variable && is_string($value->name)) {
                $name = strtolower($value->name);
                if (str_contains($name, 'lua') || str_contains($name, 'script')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function nameToString(Name|Identifier|Expr|null $name): string
    {
        if ($name instanceof Name || $name instanceof Identifier) {
            return $name->toString();
        }

        return '';
    }

    private function scopeName(Stmt\Function_|Stmt\ClassMethod|Expr\Closure $node): string
    {
        if ($node instanceof Expr\Closure) {
            return 'closure';
        }

        return $node->name instanceof Identifier ? $node->name->toString() : 'anonymous';
    }

    /**
     * @param array<int, array<string, mixed>> $scopes
     */
    private function findScopeIndexByRef(array $scopes, string $scopeRef, string $file): ?int
    {
        if (!str_starts_with($scopeRef, 'scope:')) {
            return null;
        }

        $scopeId = (int) substr($scopeRef, strlen('scope:'));
        foreach ($scopes as $index => $scope) {
            if (((int) ($scope['id'] ?? 0)) === $scopeId && ((string) ($scope['file'] ?? '')) === $file) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function compareByFileAndLine(array $a, array $b): int
    {
        $fileCompare = strcmp((string) ($a['file'] ?? ''), (string) ($b['file'] ?? ''));
        if ($fileCompare !== 0) {
            return $fileCompare;
        }

        $lineCompare = ((int) ($a['start_line'] ?? 0)) <=> ((int) ($b['start_line'] ?? 0));
        if ($lineCompare !== 0) {
            return $lineCompare;
        }

        return ((int) ($a['end_line'] ?? 0)) <=> ((int) ($b['end_line'] ?? 0));
    }

    private function line(Node $node, bool $start): int
    {
        $line = $start ? $node->getStartLine() : $node->getEndLine();

        return $line > 0 ? $line : 1;
    }
}
