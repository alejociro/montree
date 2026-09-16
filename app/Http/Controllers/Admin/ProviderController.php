<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Logistics\SaveProviderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreProviderRequest;
use App\Http\Requests\Admin\Logistics\UpdateProviderRequest;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ProviderController extends Controller
{
    public function store(StoreProviderRequest $request, SaveProviderAction $saveProvider): RedirectResponse
    {
        $saveProvider->handle(null, $request->validated());

        return back()->with('success', __('Proveedor creado.'));
    }

    public function update(UpdateProviderRequest $request, Provider $provider, SaveProviderAction $saveProvider): RedirectResponse
    {
        $saveProvider->handle($provider, $request->validated());

        return back()->with('success', __('Proveedor actualizado.'));
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        Gate::authorize('logistics.manage');

        $usage = $provider->tourDates()->count();

        if ($usage > 0) {
            return back()->withErrors(['provider' => trans_choice(
                '{1}No se puede eliminar: el proveedor está en uso por :count salida.|[2,*]No se puede eliminar: el proveedor está en uso por :count salidas.',
                $usage,
                ['count' => $usage],
            )]);
        }

        $provider->delete();

        return back()->with('success', __('Proveedor eliminado.'));
    }
}
