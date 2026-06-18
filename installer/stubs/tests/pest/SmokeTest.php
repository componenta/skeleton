<?php

declare(strict_types=1);

it('exposes the application run function', function (): void {
    expect(function_exists('Componenta\\App\\run'))->toBeTrue();
});
