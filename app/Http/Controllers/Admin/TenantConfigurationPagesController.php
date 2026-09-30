<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tenant\StoreBrandingAssetsAction;
use App\Actions\Tenant\UpdateTenantConfigurationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tenant\UpdateTenantConfigurationRequest;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Services\Tenant\TermsRenderer;
use Illuminate\Http\RedirectResponse;
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

    public function index(): Response
    {
        $configuration = $this->currentConfiguration();

        return Inertia::render('Admin/Tenant/Configuration', [
            'terms' => [
                'body' => $configuration->terms_body,
                'is_default' => $this->terms->isDefault($configuration),
                // T13: el editor se precarga con el texto por defecto vigente
                // (en el idioma del panel) cuando la agencia todavía no tiene
                // uno propio, y también sirve para "Restaurar texto por
                // defecto" cuando sí lo tiene.
                'default_body' => $this->terms->defaultBody(),
            ],
        ]);
    }

    public function update(
        UpdateTenantConfigurationRequest $request,
        StoreBrandingAssetsAction $storeAssets,
        UpdateTenantConfigurationAction $updateConfiguration,
    ): RedirectResponse {
        $tenant = $this->currentTenant();
        $configuration = $tenant->configuration()->firstOrCreate(['tenant_id' => $tenant->id]);

        $updateConfiguration->execute($configuration, $request->configuration());

        $storeAssets->execute($configuration, $request->brandingAssets());

        return redirect()->route('admin.tenant.configuration')->with('success', __('Configuración guardada.'));
    }

    private function currentConfiguration(): TenantConfiguration
    {
        $configuration = $this->currentTenant()->configuration;

        if ($configuration === null) {
            throw new NotFoundHttpException(__('No tenant for this host.'));
        }

        return $configuration;
    }

    private function currentTenant(): Tenant
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new NotFoundHttpException(__('No tenant for this host.'));
        }

        return $tenant;
    }
}
