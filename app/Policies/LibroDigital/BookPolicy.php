<?php

namespace App\Policies\LibroDigital;

use App\Models\LibroDigital\Book;
use App\Models\User;
use App\Services\LibroDigital\LibroDigitalAccessContext;

class BookPolicy
{
    public function __construct(private readonly LibroDigitalAccessContext $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('libro_digital.books.view');
    }

    public function view(User $user, Book $book): bool
    {
        return $user->hasPermission('libro_digital.books.view')
            && $this->access->canAccessSchool($user, (int) $book->school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('libro_digital.books.manage');
    }

    public function update(User $user, Book $book): bool
    {
        return $user->hasPermission('libro_digital.books.manage')
            && $this->access->canAccessSchool($user, (int) $book->school_id);
    }

    public function manage(User $user, Book $book): bool
    {
        return $this->update($user, $book);
    }
}
