<?php

namespace Tests\Unit\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\TestCase;

class NormalizesNullableFieldsTest extends TestCase
{
    public function test_it_converts_blank_optional_values_to_null(): void
    {
        $request = new class extends FormRequest
        {
            use NormalizesNullableFields;

            public function normalize(array $fields): void
            {
                $this->normalizeNullableFields($fields);
            }
        };

        $request->replace([
            'blank' => '   ',
            'empty' => '',
            'already_null' => null,
            'zero' => 0,
            'text' => 'contenido',
        ]);

        $request->normalize(['blank', 'empty', 'already_null', 'zero', 'text', 'missing']);

        $this->assertNull($request->input('blank'));
        $this->assertNull($request->input('empty'));
        $this->assertNull($request->input('already_null'));
        $this->assertSame(0, $request->input('zero'));
        $this->assertSame('contenido', $request->input('text'));
        $this->assertFalse($request->exists('missing'));
    }
}
