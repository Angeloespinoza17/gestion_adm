<?php

namespace App\Http\Requests\Operational;

use App\Models\Operational\OperationalTransferDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationalTransferDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'document_type' => ['required', Rule::in(array_column(OperationalTransferDocument::TYPE_OPTIONS, 'value'))],
            'quote_id' => ['nullable', 'integer', 'exists:operational_transfer_quotes,id'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
