<?php

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Every concrete Tool class under app/Mcp/Tools.
 *
 * @return list<class-string<Tool>>
 */
function mcpToolClasses(): array
{
    // Resolved from this file's location, not app_path(): datasets are built at
    // Pest collection time, before the application container is bound.
    $base = dirname(__DIR__, 3).'/app/Mcp/Tools';
    $classes = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace(
            [$base.DIRECTORY_SEPARATOR, '/', '.php'],
            ['', '\\', ''],
            $file->getPathname(),
        );
        $fqcn = 'App\\Mcp\\Tools\\'.$relative;

        if (! class_exists($fqcn)) {
            continue;
        }

        $reflection = new ReflectionClass($fqcn);
        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Tool::class)) {
            continue;
        }

        $classes[] = $fqcn;
    }

    sort($classes);

    return $classes;
}

it('classifies every MCP tool as exactly one of read-only or destructive', function (string $toolClass) {
    $reflection = new ReflectionClass($toolClass);
    $isReadOnly = $reflection->getAttributes(IsReadOnly::class) !== [];
    $isDestructive = $reflection->getAttributes(IsDestructive::class) !== [];

    expect($isReadOnly || $isDestructive)->toBeTrue(
        "{$toolClass} carries neither #[IsReadOnly] nor #[IsDestructive] — classify it (#184).",
    );
    expect($isReadOnly && $isDestructive)->toBeFalse(
        "{$toolClass} carries both #[IsReadOnly] and #[IsDestructive] — a tool is one or the other.",
    );
})->with(mcpToolClasses());

it('discovers the full tool surface', function () {
    expect(count(mcpToolClasses()))->toBeGreaterThanOrEqual(200);
});
