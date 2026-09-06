<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StorePublicContactMessageRequest extends FormRequest
{
    private const MINIMUM_COMPLETION_SECONDS = 3;

    private const MAXIMUM_FORM_AGE_SECONDS = 7200;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'correo' => Str::lower(trim((string) $this->input('correo'))),
            'telefono' => trim((string) $this->input('telefono')),
            'asunto' => trim((string) $this->input('asunto')),
            'mensaje' => trim((string) $this->input('mensaje')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:191'],
            'correo' => ['required', 'email', 'max:191'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'asunto' => ['required', 'string', 'min:4', 'max:191'],
            'mensaje' => ['required', 'string', 'min:15', 'max:5000'],
            'website' => ['nullable', 'prohibited'],
            'contact_started_at' => ['required', 'string', 'max:1024'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('contact_started_at')) {
                $this->validateChallenge($validator);
            }

            if ($this->filled('telefono') && ! $this->hasValidChileanPhone((string) $this->input('telefono'))) {
                $validator->errors()->add(
                    'telefono',
                    'Ingresa un teléfono chileno válido de 9 dígitos, con o sin el prefijo +56.',
                );
            }

            if ($this->letterCount((string) $this->input('asunto')) < 3) {
                $validator->errors()->add('asunto', 'Escribe un asunto descriptivo que incluya al menos 3 letras.');
            }

            $content = Str::squish($this->input('asunto', '').' '.$this->input('mensaje', ''));

            if ($this->containsExternalLink($content)) {
                $validator->errors()->add(
                    'mensaje',
                    'Por seguridad, el formulario no acepta enlaces web. Puedes describir la información en el mensaje.',
                );
            }

            if ($this->containsPromotionalSpam($content)) {
                $validator->errors()->add('mensaje', 'No fue posible enviar contenido promocional o publicitario.');
            }
        }];
    }

    private function validateChallenge(Validator $validator): void
    {
        try {
            $issuedAt = filter_var(
                Crypt::decryptString((string) $this->input('contact_started_at')),
                FILTER_VALIDATE_INT,
            );
        } catch (DecryptException) {
            $issuedAt = false;
        }

        $age = $issuedAt === false ? null : now()->timestamp - (int) $issuedAt;

        if ($age === null || $age < self::MINIMUM_COMPLETION_SECONDS || $age > self::MAXIMUM_FORM_AGE_SECONDS) {
            $validator->errors()->add(
                'contact_started_at',
                'No fue posible validar el formulario. Recarga la página e intenta nuevamente.',
            );
        }
    }

    private function hasValidChileanPhone(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '56')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^[2-9]\d{8}$/', $digits) === 1;
    }

    private function letterCount(string $value): int
    {
        return preg_match_all('/\p{L}/u', $value) ?: 0;
    }

    private function containsExternalLink(string $value): bool
    {
        return preg_match(
            '/(?:https?:\/\/|www\.|(?<!@)\b(?:[a-z0-9](?:[a-z0-9-]{0,62})\.)+[a-z]{2,24}(?:\/\S*)?)/iu',
            $value,
        ) === 1;
    }

    private function containsPromotionalSpam(string $value): bool
    {
        return preg_match(
            '/(?:\bpromo(?:tional)?\s+code\b|\bjackpot\b|\bonline\s+poker\b|\bcasino\s+bonus\b|\bguaranteed\s+profit\b|онлайн\s+покер|джекпот|промокод)/iu',
            $value,
        ) === 1;
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa tu nombre.',
            'nombre.min' => 'El nombre debe contener al menos 2 caracteres.',
            'correo.required' => 'Ingresa tu correo electrónico.',
            'correo.email' => 'Ingresa un correo electrónico válido.',
            'asunto.required' => 'Ingresa el asunto.',
            'asunto.min' => 'El asunto debe contener al menos 4 caracteres.',
            'mensaje.required' => 'Ingresa tu mensaje.',
            'mensaje.min' => 'El mensaje debe contener al menos 15 caracteres.',
            'website.prohibited' => 'No fue posible enviar el mensaje.',
            'contact_started_at.required' => 'Recarga la página antes de enviar el formulario.',
        ];
    }
}
