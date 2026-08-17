<?php

namespace App\Models\Operational;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalTransferDocument extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        ['value' => 'solicitud_pedagogica', 'label' => 'Solicitud pedagógica'],
        ['value' => 'cotizacion', 'label' => 'Cotización'],
        ['value' => 'dte', 'label' => 'DTE'],
        ['value' => 'respaldo_pago', 'label' => 'Respaldo de pago'],
        ['value' => 'pdf_solicitud', 'label' => 'PDF oficial de solicitud'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'operational_transfer_request_id', 'quote_id', 'uploaded_by_user_id', 'document_type',
        'file_path', 'file_name', 'file_type', 'file_size', 'official_snapshot', 'snapshot_stage', 'comments',
    ];

    protected $hidden = ['file_path'];

    protected $casts = ['file_size' => 'integer', 'official_snapshot' => 'boolean'];

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferRequest::class, 'operational_transfer_request_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(OperationalTransferQuote::class, 'quote_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
