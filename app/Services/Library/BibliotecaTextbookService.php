<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaTextoEntrega;
use App\Models\Library\BibliotecaTextoEntregaItem;
use App\Models\Library\BibliotecaTextoOrden;
use App\Models\Library\BibliotecaTextoRecepcion;
use App\Models\Library\BibliotecaTextoRecepcionItem;
use App\Models\Library\BibliotecaTextoTitulo;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BibliotecaTextbookService
{
    public function __construct(
        private readonly BibliotecaCodeService $codeService,
    ) {}

    public function createReception(array $payload, User $actor): BibliotecaTextoRecepcion
    {
        return DB::transaction(function () use ($payload, $actor) {
            $reception = BibliotecaTextoRecepcion::query()->create([
                'reception_code' => $this->codeService->next('REC'),
                'received_at' => $payload['received_at'],
                'source_name' => $payload['source_name'] ?? null,
                'document_reference' => $payload['document_reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'received_by_user_id' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($payload['items'] as $item) {
                $title = $this->resolveTitle(
                    $item,
                    isset($item['education_level_id']) ? (int) $item['education_level_id'] : null,
                    $actor,
                );

                $reception->items()->create([
                    'biblioteca_texto_titulo_id' => $title->id,
                    'education_level_id' => $item['education_level_id'] ?? $title->education_level_id,
                    'title' => $title->title,
                    'subject' => $title->subject,
                    'publisher' => $item['publisher'] ?? $title->publisher,
                    'quantity_received' => $item['quantity_received'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $reception->fresh([
                'items.textoTitulo:id,title,subject,publisher,isbn,education_level_id',
                'items.educationLevel:id,name',
                'receivedBy:id,name',
            ]);
        });
    }

    public function createOrder(array $payload, User $actor): BibliotecaTextoOrden
    {
        return DB::transaction(function () use ($payload, $actor) {
            $order = BibliotecaTextoOrden::query()->create([
                'order_code' => $this->codeService->next('OTE'),
                'academic_year_id' => $payload['academic_year_id'],
                'education_level_id' => $payload['education_level_id'] ?? null,
                'course_section_id' => $payload['course_section_id'] ?? null,
                'status' => 'borrador',
                'prepared_at' => $payload['prepared_at'] ?? now()->toDateString(),
                'notes' => $payload['notes'] ?? null,
                'prepared_by_user_id' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($payload['items'] as $item) {
                $title = $this->resolveTitle(
                    $item,
                    isset($payload['education_level_id']) ? (int) $payload['education_level_id'] : null,
                    $actor,
                );
                $available = $this->availableStock($title->id);
                $required = (int) $item['quantity_required'];

                $order->items()->create([
                    'biblioteca_texto_titulo_id' => $title->id,
                    'title' => $title->title,
                    'subject' => $title->subject,
                    'quantity_required' => $required,
                    'quantity_available' => $available,
                    'quantity_assigned' => 0,
                    'shortage_quantity' => max($required - $available, 0),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $order->fresh($this->orderRelations());
        });
    }

    public function generateRoster(BibliotecaTextoOrden $order, User $actor): BibliotecaTextoOrden
    {
        if (! $order->course_section_id) {
            throw ValidationException::withMessages([
                'course_section_id' => 'La orden debe estar asociada a un curso para generar el listado.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor) {
            $order->loadMissing('items');
            $enrollments = StudentEnrollment::query()
                ->with('studentProfile:id,first_name,last_name,registered_name,rut')
                ->where('academic_year_id', $order->academic_year_id)
                ->where('course_section_id', $order->course_section_id)
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->get();

            foreach ($enrollments as $enrollment) {
                $student = $enrollment->studentProfile;
                if (! $student) {
                    continue;
                }

                $delivery = BibliotecaTextoEntrega::query()->firstOrCreate(
                    [
                        'biblioteca_texto_orden_id' => $order->id,
                        'student_profile_id' => $student->id,
                    ],
                    [
                        'student_name_snapshot' => $student->registered_name_resolved,
                        'student_rut_snapshot' => $student->rut,
                        'status' => 'pendiente',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]
                );

                foreach ($order->items as $orderItem) {
                    $delivery->items()->firstOrCreate(
                        ['biblioteca_texto_orden_item_id' => $orderItem->id],
                        ['quantity' => 1, 'status' => 'pendiente']
                    );
                }
            }

            $order->forceFill([
                'status' => 'preparada',
                'prepared_at' => $order->prepared_at ?: now()->toDateString(),
                'updated_by' => $actor->id,
            ])->save();

            return $order->fresh($this->orderRelations());
        });
    }

    public function updateDelivery(BibliotecaTextoEntrega $delivery, array $payload, User $actor): BibliotecaTextoEntrega
    {
        return DB::transaction(function () use ($delivery, $payload, $actor) {
            $delivery->loadMissing(['items.orderItem', 'order.items']);
            $requestedItemStatuses = collect($payload['items'] ?? [])->keyBy('id');
            $targetStatus = $payload['status'];

            foreach ($delivery->items as $deliveryItem) {
                $itemPayload = $requestedItemStatuses->get($deliveryItem->id, []);
                $itemStatus = $itemPayload['status'] ?? ($targetStatus === 'entregado' ? 'entregado' : $deliveryItem->status);
                $quantity = (int) ($itemPayload['quantity'] ?? $deliveryItem->quantity ?: 1);

                if ($itemStatus === 'entregado') {
                    $this->assertStockAvailable($deliveryItem, $quantity);
                }

                $deliveryItem->forceFill([
                    'quantity' => $quantity,
                    'status' => $itemStatus,
                    'delivered_at' => $itemStatus === 'entregado' ? ($deliveryItem->delivered_at ?: now()) : null,
                    'pending_reason' => $itemPayload['pending_reason'] ?? ($itemStatus === 'pendiente' ? ($payload['pending_reason'] ?? null) : null),
                ])->save();
            }

            $statuses = $delivery->items()->pluck('status');
            $status = $statuses->every(fn ($value) => $value === 'entregado')
                ? 'entregado'
                : ($statuses->contains('entregado') ? 'parcial' : 'pendiente');

            $delivery->forceFill([
                'status' => $status,
                'delivered_at' => $status !== 'pendiente' ? ($delivery->delivered_at ?: now()) : null,
                'signature_data' => $payload['signature_data'] ?? $delivery->signature_data,
                'signature_name' => $payload['signature_name'] ?? $delivery->signature_name,
                'signature_rut' => $payload['signature_rut'] ?? $delivery->signature_rut,
                'signed_at' => ! empty($payload['signature_name']) || ! empty($payload['signature_data'])
                    ? now()
                    : $delivery->signed_at,
                'pending_reason' => $status === 'entregado' ? null : ($payload['pending_reason'] ?? $delivery->pending_reason),
                'notes' => $payload['notes'] ?? $delivery->notes,
                'delivered_by_user_id' => $status !== 'pendiente' ? $actor->id : $delivery->delivered_by_user_id,
                'updated_by' => $actor->id,
            ])->save();

            $this->refreshOrder($delivery->order, $actor);

            return $delivery->fresh([
                'student:id,first_name,last_name,registered_name,rut',
                'items.orderItem',
                'deliveredBy:id,name',
            ]);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function stockSummary(): array
    {
        return BibliotecaTextoRecepcionItem::query()
            ->with([
                'textoTitulo:id,title,subject,publisher,isbn,education_level_id',
                'textoTitulo.educationLevel:id,name',
            ])
            ->whereNotNull('biblioteca_texto_titulo_id')
            ->get()
            ->groupBy('biblioteca_texto_titulo_id')
            ->map(function ($items) {
                /** @var BibliotecaTextoRecepcionItem $first */
                $first = $items->first();
                $title = $first->textoTitulo;
                $received = (int) $items->sum('quantity_received');
                $delivered = $this->deliveredStock($first->biblioteca_texto_titulo_id);

                return [
                    'biblioteca_texto_titulo_id' => $first->biblioteca_texto_titulo_id,
                    'title' => $title?->title ?? $first->title,
                    'subject' => $title?->subject ?? $first->subject,
                    'publisher' => $title?->publisher ?? $first->publisher,
                    'isbn' => $title?->isbn,
                    'education_level_id' => $title?->education_level_id ?? $first->education_level_id,
                    'education_level' => $title?->educationLevel,
                    'received' => $received,
                    'delivered' => $delivered,
                    'available' => max($received - $delivered, 0),
                ];
            })
            ->sortBy('title')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function titleCatalog(): array
    {
        $stock = collect($this->stockSummary())->keyBy('biblioteca_texto_titulo_id');

        return BibliotecaTextoTitulo::query()
            ->with('educationLevel:id,name')
            ->where('active', true)
            ->orderBy('title')
            ->orderBy('subject')
            ->get()
            ->map(function (BibliotecaTextoTitulo $title) use ($stock) {
                $titleStock = $stock->get($title->id, []);

                return [
                    'id' => $title->id,
                    'title' => $title->title,
                    'subject' => $title->subject,
                    'publisher' => $title->publisher,
                    'isbn' => $title->isbn,
                    'education_level_id' => $title->education_level_id,
                    'education_level' => $title->educationLevel,
                    'received' => (int) ($titleStock['received'] ?? 0),
                    'delivered' => (int) ($titleStock['delivered'] ?? 0),
                    'available' => (int) ($titleStock['available'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function assertStockAvailable(BibliotecaTextoEntregaItem $deliveryItem, int $quantity): void
    {
        $orderItem = $deliveryItem->orderItem;
        $available = $this->availableStock(
            $orderItem->biblioteca_texto_titulo_id,
            $deliveryItem->id,
        );

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'No hay stock suficiente de "%s". Disponible: %d.',
                    $orderItem->title,
                    $available,
                ),
            ]);
        }
    }

    private function availableStock(int $titleId, ?int $excludingDeliveryItemId = null): int
    {
        $received = (int) BibliotecaTextoRecepcionItem::query()
            ->where('biblioteca_texto_titulo_id', $titleId)
            ->sum('quantity_received');

        return max($received - $this->deliveredStock($titleId, $excludingDeliveryItemId), 0);
    }

    private function deliveredStock(int $titleId, ?int $excludingDeliveryItemId = null): int
    {
        return (int) BibliotecaTextoEntregaItem::query()
            ->where('biblioteca_texto_entrega_items.status', 'entregado')
            ->when($excludingDeliveryItemId, fn ($query) => $query->where('id', '!=', $excludingDeliveryItemId))
            ->whereHas(
                'orderItem',
                fn ($query) => $query->where('biblioteca_texto_titulo_id', $titleId)
            )
            ->sum('quantity');
    }

    private function resolveTitle(array $item, ?int $educationLevelId, User $actor): BibliotecaTextoTitulo
    {
        if (! empty($item['biblioteca_texto_titulo_id'])) {
            return BibliotecaTextoTitulo::query()->findOrFail($item['biblioteca_texto_titulo_id']);
        }

        $title = trim($item['title']);
        $subject = trim($item['subject']);
        $identityKey = $this->titleIdentityKey($title, $subject, $educationLevelId);

        $textbookTitle = BibliotecaTextoTitulo::query()->firstOrCreate(
            ['identity_key' => $identityKey],
            [
                'title' => $title,
                'subject' => $subject,
                'publisher' => $item['publisher'] ?? null,
                'isbn' => $item['isbn'] ?? null,
                'education_level_id' => $educationLevelId,
                'active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $updates = [];
        foreach (['publisher', 'isbn'] as $field) {
            if (! $textbookTitle->{$field} && ! empty($item[$field])) {
                $updates[$field] = $item[$field];
            }
        }

        if ($updates !== []) {
            $updates['updated_by'] = $actor->id;
            $textbookTitle->forceFill($updates)->save();
        }

        return $textbookTitle;
    }

    private function titleIdentityKey(string $title, string $subject, ?int $educationLevelId): string
    {
        $normalize = static fn (string $value): string => mb_strtolower(
            trim((string) preg_replace('/\s+/u', ' ', $value)),
            'UTF-8'
        );

        return hash('sha256', implode('|', [
            $normalize($title),
            $normalize($subject),
            (string) ($educationLevelId ?? 0),
        ]));
    }

    private function refreshOrder(BibliotecaTextoOrden $order, User $actor): void
    {
        $order->loadMissing(['items.deliveryItems', 'deliveries']);

        foreach ($order->items as $item) {
            $assigned = (int) $item->deliveryItems->where('status', 'entregado')->sum('quantity');
            $available = $this->availableStock($item->biblioteca_texto_titulo_id);

            $item->forceFill([
                'quantity_assigned' => $assigned,
                'quantity_available' => $available,
                'shortage_quantity' => max((int) $item->quantity_required - ($assigned + $available), 0),
            ])->save();
        }

        $statuses = $order->deliveries()->pluck('status');
        $status = $statuses->isNotEmpty() && $statuses->every(fn ($value) => $value === 'entregado')
            ? 'cerrada'
            : ($statuses->contains(fn ($value) => in_array($value, ['parcial', 'entregado'], true)) ? 'parcial' : 'preparada');

        $order->forceFill(['status' => $status, 'updated_by' => $actor->id])->save();
    }

    /**
     * @return array<int, string>
     */
    private function orderRelations(): array
    {
        return [
            'academicYear:id,name,year',
            'educationLevel:id,name,type',
            'courseSection:id,display_name',
            'items.textoTitulo:id,title,subject,publisher,isbn,education_level_id',
            'deliveries.student:id,first_name,last_name,registered_name,rut',
            'deliveries.items.orderItem',
            'preparedBy:id,name',
        ];
    }
}
