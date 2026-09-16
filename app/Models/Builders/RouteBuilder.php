<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\Route;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Route>
 */
final class RouteBuilder extends Builder
{
    /**
     * El buscador de la barra es uno solo para los tres catálogos y promete
     * «nombre, municipio, contacto o tarifa»: buscar solo por nombre dejaba
     * fuera justo lo que se busca a mano.
     */
    public function matching(?string $search): self
    {
        if ($search === null || $search === '') {
            return $this;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->where(fn (self $query) => $query
            ->where('name', 'like', $term)
            ->orWhere('city', 'like', $term)
            ->orWhere('state', 'like', $term)
            ->orWhere('description', 'like', $term)
            ->orWhereHas('stops', fn (Builder $stops) => $stops->where('name', 'like', $term)));
    }
}
