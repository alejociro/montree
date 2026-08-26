<?php

declare(strict_types=1);

namespace App\Actions\Logistics;

use App\Models\Provider;
use Illuminate\Support\Facades\DB;

/**
 * Guarda un proveedor con sus tarifas y sus documentos.
 *
 * Cada lista se reescribe solo si el formulario la mandó: parchear el teléfono
 * desde otra superficie no puede borrarle las tarifas negociadas.
 */
final class SaveProviderAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Provider $provider, array $data): Provider
    {
        $rates = $data['rates'] ?? null;
        $documents = $data['documents'] ?? null;
        unset($data['rates'], $data['documents']);

        return DB::transaction(function () use ($provider, $data, $rates, $documents): Provider {
            $provider = $provider === null ? Provider::create($data) : tap($provider)->update($data);

            if (is_array($rates)) {
                $provider->rates()->delete();

                foreach (array_values($rates) as $index => $rate) {
                    $provider->rates()->create([
                        'position' => $index + 1,
                        'concept' => (string) $rate['concept'],
                        'amount' => $rate['amount'] ?? null,
                        'unit' => (string) $rate['unit'],
                    ]);
                }
            }

            if (is_array($documents)) {
                $provider->documents()->delete();

                foreach (array_values($documents) as $index => $document) {
                    $provider->documents()->create([
                        'position' => $index + 1,
                        'kind' => (string) $document['kind'],
                        'number' => $this->trimmedOrNull($document['number'] ?? null),
                        'expires_at' => $document['expires_at'] ?? null,
                    ]);
                }
            }

            return $provider->fresh(['rates', 'documents']) ?? $provider;
        });
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
