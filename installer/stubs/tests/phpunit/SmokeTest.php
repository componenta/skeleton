<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    #[Test]
    public function exposesApplicationRunFunction(): void
    {
        self::assertTrue(function_exists('Componenta\\App\\run'));
    }
}
