<?php

namespace App\Services\LibroDigital\Sige;

use App\Contracts\LibroDigital\SigeIntegrationGateway;
use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\User;
use Illuminate\Http\Request;

class DisabledSigeGateway implements SigeIntegrationGateway
{
    public function reconcile(Book $book, array $input, User $actor, ?Request $request = null): array
    {
        throw new LibroDigitalException(
            'La integración SIGE permanece deshabilitada hasta contar con un contrato oficial o un flujo de archivo aprobado.',
            'COMPLIANCE_BLOCKER_SIGE_DISABLED',
            409,
        );
    }

    public function driver(): string
    {
        return 'disabled';
    }
}
