<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;

class QrValueService
{
    public function forCode(string $code): string
    {
        return rtrim(config('app.url'), '/') . '/inventario/ficha/' . $code;
    }

    public function forItem(InventoryItem $item): string
    {
        // El QR puede apuntar a la ficha del bien por código.
        // Ej: /inventario/ficha/INV-TEC-2026-0001
        return $this->forCode($item->code);
    }
}
