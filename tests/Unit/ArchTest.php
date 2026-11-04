<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\Przelewy24')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\Przelewy24\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\Przelewy24\Exceptions')
    ->toExtend('Beziworld\Przelewy24\Exceptions\Przelewy24Exception')
    ->ignoring('Beziworld\Przelewy24\Exceptions\Przelewy24Exception');
