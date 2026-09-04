<?php

namespace App\Services\Supply;

use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\Supply\SupplyDelivery;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplyStockService
{
    public function __construct(private readonly SupplyRecipientService $recipientService) {}

    public function receive(array $payload, User $actor): SupplyReceipt
    {
        return DB::transaction(function () use ($payload, $actor): SupplyReceipt {
            $items = $this->lockItems($payload['items']);
            $this->assertSection($items, $payload['section']);
            $this->assertStoreroom($items, $payload['section'], $payload['storeroom_id'] ?? null);

            $receipt = SupplyReceipt::query()->create([
                'folio' => 'PENDING-'.Str::uuid(),
                'section' => $payload['section'],
                'storeroom_id' => $payload['storeroom_id'] ?? null,
                'purchased_at' => $payload['purchased_at'],
                'supplier_id' => $payload['supplier_id'] ?? null,
                'document_type' => $payload['document_type'] ?? null,
                'document_number' => $payload['document_number'] ?? null,
                'total_amount' => $payload['total_amount'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'received_by' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $receipt->update(['folio' => sprintf('ABA-ING-%s-%06d', $receipt->purchased_at->format('Y'), $receipt->id)]);

            foreach ($payload['items'] as $index => $line) {
                $supplyItem = $items->get((int) $line['supply_item_id']);
                $inventoryItem = $supplyItem->inventoryItem;
                $quantity = round((float) $line['quantity'], 2);
                $previous = round((float) ($inventoryItem->stock_quantity ?? 0), 2);
                $newStock = round($previous + $quantity, 2);

                $receipt->items()->create([
                    'supply_item_id' => $supplyItem->id,
                    'quantity' => $quantity,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'unit_snapshot' => $inventoryItem->unit_of_measure,
                    'previous_stock' => $previous,
                    'new_stock' => $newStock,
                ]);

                $this->writeMovement($inventoryItem, 'in', $quantity, $previous, $newStock, "Ingreso {$receipt->folio}", $actor);
            }

            return $receipt->load($this->receiptRelations());
        });
    }

    public function deliver(array $payload, User $actor): SupplyDelivery
    {
        return DB::transaction(function () use ($payload, $actor): SupplyDelivery {
            $items = $this->lockItems($payload['items']);
            $this->assertSection($items, $payload['section']);
            $this->assertStoreroom($items, $payload['section'], $payload['storeroom_id'] ?? null);
            $recipient = $this->recipientService->resolve((int) $payload['recipient_staff_id']);

            foreach ($payload['items'] as $index => $line) {
                $available = round((float) ($items->get((int) $line['supply_item_id'])->inventoryItem->stock_quantity ?? 0), 2);
                $requested = round((float) $line['quantity'], 2);

                if ($requested > $available) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Stock insuficiente. Disponible: {$available}.",
                    ]);
                }
            }

            $delivery = SupplyDelivery::query()->create([
                'folio' => 'PENDING-'.Str::uuid(),
                'section' => $payload['section'],
                'storeroom_id' => $payload['storeroom_id'] ?? null,
                'delivered_at' => $payload['delivered_at'],
                ...$this->recipientService->snapshots($recipient),
                'destination' => $payload['destination'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'delivered_by' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $delivery->update(['folio' => sprintf('ABA-ENT-%s-%06d', $delivery->delivered_at->format('Y'), $delivery->id)]);

            foreach ($payload['items'] as $line) {
                $supplyItem = $items->get((int) $line['supply_item_id']);
                $inventoryItem = $supplyItem->inventoryItem;
                $quantity = round((float) $line['quantity'], 2);
                $previous = round((float) ($inventoryItem->stock_quantity ?? 0), 2);
                $newStock = round($previous - $quantity, 2);

                $delivery->items()->create([
                    'supply_item_id' => $supplyItem->id,
                    'quantity' => $quantity,
                    'item_name_snapshot' => $inventoryItem->name,
                    'unit_snapshot' => $inventoryItem->unit_of_measure,
                    'previous_stock' => $previous,
                    'new_stock' => $newStock,
                    'notes' => $line['notes'] ?? null,
                ]);

                $this->writeMovement($inventoryItem, 'out', $quantity, $previous, $newStock, "Entrega {$delivery->folio}", $actor);
            }

            return $delivery->load($this->deliveryRelations());
        });
    }

    private function lockItems(array $lines)
    {
        $ids = collect($lines)->pluck('supply_item_id')->map(fn ($id) => (int) $id)->sort()->values();

        $items = SupplyItem::query()
            ->whereIn('id', $ids)
            ->with(['inventoryItem' => fn ($query) => $query->lockForUpdate()])
            ->lockForUpdate()
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($items->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Uno o más insumos ya no están disponibles.']);
        }

        return $items;
    }

    private function assertSection($items, string $section): void
    {
        if ($items->contains(fn (SupplyItem $item) => $item->section !== $section || ! $item->inventoryItem?->active)) {
            throw ValidationException::withMessages(['items' => 'Todos los insumos deben estar activos y pertenecer al submódulo seleccionado.']);
        }
    }

    private function assertStoreroom($items, string $section, mixed $storeroomId): void
    {
        if ($section !== SupplyItem::SECTION_MAINTENANCE_STOREROOM) {
            return;
        }

        $resolvedId = (int) $storeroomId;
        if ($resolvedId <= 0 || $items->contains(fn (SupplyItem $item): bool => (int) $item->storeroom_id !== $resolvedId)) {
            throw ValidationException::withMessages([
                'items' => 'Todos los artículos deben pertenecer a la bodega del pañol seleccionada.',
            ]);
        }
    }

    private function writeMovement(
        InventoryItem $item,
        string $type,
        float $quantity,
        float $previous,
        float $newStock,
        string $reason,
        User $actor
    ): void {
        InventoryStockMovement::query()->create([
            'inventory_item_id' => $item->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'previous_stock' => $previous,
            'new_stock' => $newStock,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        $item->update(['stock_quantity' => $newStock, 'updated_by' => $actor->id]);
    }

    public function receiptRelations(): array
    {
        return [
            'storeroom:id,code,name',
            'supplier:id,name,business_name,rut',
            'receiver:id,name',
            'items.supplyItem.inventoryItem:id,code,name,unit_of_measure,stock_quantity',
        ];
    }

    public function deliveryRelations(): array
    {
        return [
            'storeroom:id,code,name',
            'recipient:id,full_name',
            'deliveredBy:id,name',
            'items.supplyItem.inventoryItem:id,code,name,unit_of_measure,stock_quantity',
        ];
    }
}
