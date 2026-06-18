<?php

declare(strict_types=1);

namespace App;

use Componenta\Http\Router\Attribute\Route;

final class Welcome
{
    #[Route('welcome', '/', 'GET')]
    public function __invoke(): array|string
    {
        if (function_exists('view')) {
            return view('welcome');
        }

        return [
            'status' => 'ok',
            'message' => 'Componenta Framework skeleton is running.',
        ];
    }
}
