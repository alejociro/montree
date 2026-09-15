<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenant\TermsRenderer;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * WHY: los términos llegan como prop de esta página y no dentro de
 * `TenantConfigurationResource`, que viaja compartido en TODA respuesta Inertia.
 * El cuerpo crudo admite 20.000 caracteres: mandarlo en cada carga del catálogo
 * público es peso muerto para el único lugar que lo edita.
 */
final class TenantConfigurationPagesController extends Controller
{
    public function __construct(private TermsRenderer $terms) {}

    public function __invoke(): Response
    {
        $configuration = Tenant::current()?->configuration;

        if ($configuration === null) {
            throw new NotFoundHttpException(__('No tenant for this host.'));
        }

        return Inertia::render('Admin/Tenant/Configuration', [
            'terms' => [
                'body' => $configuration->terms_body,
                'is_default' => $this->terms->isDefault($configuration),
            ],
        ]);
    }
}
