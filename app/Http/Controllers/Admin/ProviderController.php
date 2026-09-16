<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Logistics\DeleteProviderAction;
use App\Actions\Logistics\SaveProviderAction;
use App\Exceptions\LogisticsRecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreProviderRequest;
use App\Http\Requests\Admin\Logistics\UpdateProviderRequest;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;

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

    public function destroy(Provider $provider, DeleteProviderAction $deleteProvider): RedirectResponse
    {
        try {
            $deleteProvider->execute($provider);
        } catch (LogisticsRecordInUseException $inUse) {
            return back()->withErrors(['provider' => $inUse->getMessage()]);
        }

        return back()->with('success', __('Proveedor eliminado.'));
    }
}
