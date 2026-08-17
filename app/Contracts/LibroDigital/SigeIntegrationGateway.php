<?php

namespace App\Contracts\LibroDigital;

use App\Models\LibroDigital\Book;
use App\Models\User;
use Illuminate\Http\Request;

interface SigeIntegrationGateway
{
    /** @return array<string, mixed> */
    public function reconcile(Book $book, array $input, User $actor, ?Request $request = null): array;

    public function driver(): string;
}
