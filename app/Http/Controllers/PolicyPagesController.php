<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Tenant\TermsRenderer;
use Inertia\Inertia;
use Inertia\Response;

final class PolicyPagesController extends Controller
{
    public function __construct(private readonly TermsRenderer $renderer) {}

    public function terms(): Response
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404);

        $configuration = $tenant->loadMissing('configuration')->configuration;
        $isDefault = $this->renderer->isDefault($configuration);

        return Inertia::render('Policies/Terms', [
            'terms' => [
                'html' => $this->renderer->html($this->renderer->bodyFor($configuration)),
                'is_default' => $isDefault,
                'updated_at' => $isDefault ? null : $configuration?->updated_at?->toIso8601String(),
            ],
            'agency' => ['name' => $tenant->name],
        ]);
    }
}
