<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withCache(__DIR__ . '/.rector.cache')
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    ->withSkip([
        // Pint's config keeps strict types off.
        SafeDeclareStrictTypesRector::class,
        // User and tenant ids arrive as int or string; the casts are intentional.
        RecastingRemovalRector::class,
        AppToResolveRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
