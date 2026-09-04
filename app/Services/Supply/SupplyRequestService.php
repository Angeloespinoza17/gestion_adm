<?php

namespace App\Services\Supply;

use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyRequest;
use App\Models\Supply\SupplyRequestItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplyRequestService
{
    public function create(array $payload, User $actor): SupplyRequest
    {
        $request = DB::transaction(function () use ($payload, $actor): SupplyRequest {
            $catalogItems = $this->catalogItems($payload['items']);
            $request = SupplyRequest::query()->create([
                'folio' => 'PENDING-'.Str::uuid(),
                'title' => $payload['title'],
                'destination' => $payload['destination'] ?? null,
                'needed_by' => $payload['needed_by'] ?? null,
                'status' => SupplyRequest::STATUS_SUBMITTED,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'submitted_at' => now(),
            ]);
            $request->update(['folio' => sprintf('SOL-ABA-%s-%06d', now()->format('Y'), $request->id)]);

            foreach ($payload['items'] as $index => $line) {
                $catalogItem = ! empty($line['supply_item_id'])
                    ? $catalogItems->get((int) $line['supply_item_id'])
                    : null;
                $inventory = $catalogItem?->inventoryItem;
                $quantity = round((float) $line['requested_quantity'], 2);

                $item = $request->items()->create([
                    'supply_item_id' => $catalogItem?->id,
                    'item_name_snapshot' => $inventory?->name ?? trim((string) $line['name']),
                    'description_snapshot' => $inventory?->description ?? ($line['description'] ?? null),
                    'unit_snapshot' => $inventory?->unit_of_measure ?? $line['unit'],
                    'requested_quantity' => $quantity,
                    'final_quantity' => $quantity,
                    'sort_order' => $index + 1,
                ]);

                if (($line['photo'] ?? null) instanceof UploadedFile) {
                    $this->replacePhoto($item, $line['photo']);
                }
            }

            $request->statusLogs()->create([
                'from_status' => null,
                'to_status' => SupplyRequest::STATUS_SUBMITTED,
                'note' => 'Solicitud creada y enviada a revisión.',
                'changed_by' => $actor->id,
            ]);

            return $request;
        });

        return $this->load($request);
    }

    public function review(SupplyRequest $request, array $payload, User $actor): SupplyRequest
    {
        $filesToDelete = [];

        DB::transaction(function () use ($request, $payload, $actor, &$filesToDelete): void {
            $locked = SupplyRequest::query()->lockForUpdate()->findOrFail($request->id);
            $existingItems = $locked->items()->lockForUpdate()->get()->keyBy('id');
            $submittedIds = collect($payload['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id);

            foreach ($submittedIds as $itemId) {
                if (! $existingItems->has($itemId)) {
                    throw ValidationException::withMessages(['items' => 'Una línea no pertenece a esta solicitud.']);
                }
            }

            $catalogItems = $this->catalogItems($payload['items']);
            foreach ($payload['items'] as $index => $line) {
                $item = ! empty($line['id']) ? $existingItems->get((int) $line['id']) : new SupplyRequestItem();
                $catalogItem = ! empty($line['supply_item_id'])
                    ? $catalogItems->get((int) $line['supply_item_id'])
                    : null;
                $requestedQuantity = $item->exists
                    ? (float) $item->requested_quantity
                    : (float) ($line['requested_quantity'] ?? $line['final_quantity']);

                $item->fill([
                    'supply_request_id' => $locked->id,
                    'supply_item_id' => $catalogItem?->id,
                    'item_name_snapshot' => trim((string) $line['name']),
                    'description_snapshot' => $line['description'] ?? null,
                    'unit_snapshot' => $line['unit'],
                    'requested_quantity' => round($requestedQuantity, 2),
                    'final_quantity' => round((float) $line['final_quantity'], 2),
                    'sort_order' => $index + 1,
                ]);
                $item->save();

                if (($line['photo'] ?? null) instanceof UploadedFile) {
                    $previousPath = $item->reference_photo_path;
                    $this->replacePhoto($item, $line['photo'], false);
                    if ($previousPath) {
                        $filesToDelete[] = $previousPath;
                    }
                }
            }

            $removed = $existingItems->reject(fn (SupplyRequestItem $item) => $submittedIds->contains($item->id));
            foreach ($removed as $item) {
                if ($item->reference_photo_path) {
                    $filesToDelete[] = $item->reference_photo_path;
                }
                $item->delete();
            }

            $oldStatus = $locked->status;
            $newStatus = $payload['status'];
            $locked->update([
                'title' => $payload['title'],
                'destination' => $payload['destination'] ?? null,
                'needed_by' => $payload['needed_by'] ?? null,
                'status' => $newStatus,
                'review_notes' => $payload['review_notes'] ?? null,
                'updated_by' => $actor->id,
                'reviewed_by' => $actor->id,
                'quoted_at' => $newStatus === SupplyRequest::STATUS_QUOTED ? ($locked->quoted_at ?? now()) : $locked->quoted_at,
            ]);

            if ($oldStatus !== $newStatus) {
                $locked->statusLogs()->create([
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'note' => $payload['review_notes'] ?? null,
                    'changed_by' => $actor->id,
                ]);
            }
        });

        foreach (array_unique($filesToDelete) as $path) {
            Storage::disk('local')->delete($path);
        }

        return $this->load($request);
    }

    public function load(SupplyRequest $request): SupplyRequest
    {
        return $request->fresh()->load($this->relations());
    }

    public function relations(): array
    {
        return [
            'creator:id,name',
            'updater:id,name',
            'reviewer:id,name',
            'items.supplyItem:id,inventory_item_id,reference_photo_path,updated_at',
            'items.supplyItem.inventoryItem:id,code,name,description,stock_quantity,unit_of_measure,active',
            'statusLogs.actor:id,name',
        ];
    }

    public function photoPath(SupplyRequestItem $item): ?string
    {
        $item->loadMissing('supplyItem');

        return $item->reference_photo_path ?: $item->supplyItem?->reference_photo_path;
    }

    private function catalogItems(array $lines): Collection
    {
        $ids = collect($lines)->pluck('supply_item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $items = SupplyItem::query()
            ->where('section', SupplyItem::SECTION_CLEANING)
            ->whereIn('id', $ids)
            ->with('inventoryItem:id,name,description,unit_of_measure,active')
            ->get()
            ->keyBy('id');

        if ($items->count() !== $ids->count() || $items->contains(fn (SupplyItem $item) => ! $item->inventoryItem?->active)) {
            throw ValidationException::withMessages(['items' => 'Todos los productos precargados deben ser insumos de aseo activos.']);
        }

        return $items;
    }

    private function replacePhoto(SupplyRequestItem $item, UploadedFile $photo, bool $deletePrevious = true): void
    {
        $extension = $photo->extension() ?: $photo->getClientOriginalExtension() ?: 'jpg';
        $path = Storage::disk('local')->putFileAs(
            "supplies/requests/{$item->supply_request_id}/items/{$item->id}",
            $photo,
            'reference_'.now()->format('Ymd_His').'_'.uniqid().'.'.$extension,
        );

        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'No se pudo guardar la foto de referencia.']);
        }

        $previous = $item->reference_photo_path;
        $item->update(['reference_photo_path' => $path]);
        if ($deletePrevious && $previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }
}
