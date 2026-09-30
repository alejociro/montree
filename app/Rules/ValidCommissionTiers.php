<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida la lista de rangos de un esquema de comisión: contigua, empieza en
 * 0, sin huecos ni solapes, y solo el último rango es abierto. Se usa tanto
 * para el esquema global como para el propio de una agencia (mismo formato).
 *
 * @implements ValidationRule
 */
final class ValidCommissionTiers implements ValidationRule
{
    private const MAX_TIERS = 10;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            $fail(__('Agrega al menos un rango.'));

            return;
        }

        if (count($value) > self::MAX_TIERS) {
            $fail(__('No se admiten más de :max rangos.', ['max' => self::MAX_TIERS]));

            return;
        }

        foreach ($value as $index => $tier) {
            if (! $this->hasValidShape($tier)) {
                $fail(__('El rango :position no tiene un formato válido.', ['position' => $index + 1]));

                return;
            }
        }

        $from = array_column($value, 'from');
        $to = array_column($value, 'to');
        $rate = array_column($value, 'rate');

        if ((string) $from[0] !== '0' && (float) $from[0] !== 0.0) {
            $fail(__('El primer rango debe empezar en 0.'));

            return;
        }

        if ($to[count($to) - 1] !== null) {
            $fail(__('El último rango debe quedar abierto (sin "hasta").'));

            return;
        }

        foreach ($to as $index => $upperBound) {
            if ($upperBound === null && $index !== count($to) - 1) {
                $fail(__('Solo el último rango puede quedar abierto.'));

                return;
            }
        }

        for ($i = 0; $i < count($value); $i++) {
            if ((float) $rate[$i] < 0 || (float) $rate[$i] > 100) {
                $fail(__('El porcentaje del rango :position debe estar entre 0 y 100.', ['position' => $i + 1]));

                return;
            }

            if (! $this->hasAtMostTwoDecimals((string) $rate[$i])) {
                $fail(__('El porcentaje del rango :position admite máximo 2 decimales.', ['position' => $i + 1]));

                return;
            }

            if ((float) $from[$i] < 0) {
                $fail(__('El rango :position no puede empezar en un valor negativo.', ['position' => $i + 1]));

                return;
            }

            if ($to[$i] !== null && (float) $to[$i] <= (float) $from[$i]) {
                $fail(__('El rango :position debe terminar después de donde empieza.', ['position' => $i + 1]));

                return;
            }

            if ($i > 0 && (float) $from[$i] !== (float) $to[$i - 1]) {
                $fail(__('Los rangos deben ser contiguos: el rango :position debe empezar donde termina el anterior.', ['position' => $i + 1]));

                return;
            }
        }
    }

    private function hasValidShape(mixed $tier): bool
    {
        if (! is_array($tier)) {
            return false;
        }

        if (! array_key_exists('from', $tier) || ! is_numeric($tier['from'])) {
            return false;
        }

        if (! array_key_exists('to', $tier) || ($tier['to'] !== null && ! is_numeric($tier['to']))) {
            return false;
        }

        if (! array_key_exists('rate', $tier) || ! is_numeric($tier['rate'])) {
            return false;
        }

        return true;
    }

    private function hasAtMostTwoDecimals(string $value): bool
    {
        return (bool) preg_match('/^-?\d+(\.\d{1,2})?$/', $value);
    }
}
