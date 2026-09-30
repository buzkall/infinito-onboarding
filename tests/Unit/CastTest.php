<?php

use Arzcode\InfinitoOnboarding\Support\Cast;

it('casts scalars to strings', function (mixed $value, string $expected): void {
    expect(Cast::string($value))->toBe($expected);
})->with([
    'string' => ['admin', 'admin'],
    'empty string' => ['', ''],
    'integer' => [42, '42'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
]);

it('returns null for values that are not strings', function (mixed $value): void {
    expect(Cast::string($value))->toBeNull();
})->with([
    'null' => [null],
    'array' => [['admin']],
    'object' => [new stdClass],
]);

it('casts numeric values to integers', function (mixed $value, int $expected): void {
    expect(Cast::int($value))->toBe($expected);
})->with([
    'integer' => [2000, 2000],
    'numeric string' => ['2000', 2000],
    'float' => [1.9, 1],
    'negative string' => ['-5', -5],
]);

it('returns null for values that are not numeric', function (mixed $value): void {
    expect(Cast::int($value))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'word' => ['abc'],
    'bool' => [true],
    'array' => [[1]],
]);
