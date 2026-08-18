<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAiProviderConfigRequest;
use App\Models\AiProviderConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderConfigController extends Controller
{
    public function edit(Request $request): Response
    {
        $configs = $request->user()
            ->aiProviderConfigs()
            ->latest()
            ->get()
            ->map(fn (AiProviderConfig $config) => [
                'id' => $config->id,
                'provider' => $config->provider->value,
                'provider_label' => $config->provider->label(),
                'name' => $config->name,
                'base_url' => $config->base_url,
                'default_model' => $config->default_model,
                'is_active' => $config->is_active,
                'has_api_key' => $config->api_key !== null,
            ]);

        return Inertia::render('settings/AiProviders', [
            'configs' => $configs,
            'providers' => collect(AiProvider::cases())->map(fn ($provider) => [
                'value' => $provider->value,
                'label' => $provider->label(),
            ])->values(),
        ]);
    }

    public function store(StoreAiProviderConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->boolean('is_active')) {
            $request->user()->aiProviderConfigs()->update(['is_active' => false]);
        }

        $request->user()->aiProviderConfigs()->create([
            'provider' => $data['provider'],
            'name' => $data['name'],
            'base_url' => $data['base_url'] ?? null,
            'api_key' => $data['api_key'] ?? null,
            'default_model' => $data['default_model'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi AI disimpan.']);

        return to_route('ai-providers.edit');
    }

    public function activate(Request $request, AiProviderConfig $aiProviderConfig): RedirectResponse
    {
        abort_unless($aiProviderConfig->user_id === $request->user()->id, 403);

        $request->user()->aiProviderConfigs()->update(['is_active' => false]);
        $aiProviderConfig->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Provider AI diaktifkan.']);

        return to_route('ai-providers.edit');
    }

    public function destroy(Request $request, AiProviderConfig $aiProviderConfig): RedirectResponse
    {
        abort_unless($aiProviderConfig->user_id === $request->user()->id, 403);

        $aiProviderConfig->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi AI dihapus.']);

        return to_route('ai-providers.edit');
    }
}
