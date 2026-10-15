<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\QuotaGate;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\MissionRecurrence;
use App\Models\Property;
use App\Models\ServiceTemplate;
use App\Models\Stay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DirectoryManagementController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private QuotaGate $quotas) {}

    public function update(Request $request, string $resource, int $record): RedirectResponse
    {
        $this->access->manager($request);
        $entry = $this->entry($request, $resource, $record);
        $rules = match ($resource) {
            'clients' => ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', Rule::unique('clients')->where('concierge_id', $entry->concierge_id)->ignore($entry->id)], 'phone' => ['nullable', 'string', 'max:40'], 'kind' => ['required', Rule::in(['individual', 'company'])]],
            'properties' => ['name' => ['required', 'string', 'max:120'], 'address' => ['required', 'string', 'max:255'], 'kind' => ['required', Rule::in(['rental', 'residence', 'business', 'other'])], 'instructions' => ['nullable', 'string', 'max:5000'], 'access_code' => ['nullable', 'string', 'max:500'], 'clear_access_code' => ['boolean'], 'offline_access' => ['required', 'boolean']],
            'templates' => ['name' => ['required', 'string', 'max:120'], 'checklist' => ['required', 'array', 'min:1', 'max:50'], 'checklist.*' => ['required', 'string', 'max:255']],
            'stays' => ['title' => ['required', 'string', 'max:120'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'], 'kind' => ['required', Rule::in(['stay', 'blocked', 'unclassified'])]],
            default => abort(404),
        };
        $data = $request->validate($rules);
        if ($resource === 'properties') {
            if ($data['offline_access']) {
                $this->quotas->feature($request, 'offline');
            }
            if ($data['clear_access_code'] ?? false) {
                $data['access_code'] = null;
            } elseif (! $request->filled('access_code')) {
                unset($data['access_code']);
            }
            unset($data['clear_access_code']);
        }
        if ($resource === 'templates') {
            $this->quotas->feature($request, 'custom_templates');
        }
        DB::transaction(function () use ($entry, $data, $request) {
            $entry->update($data);
            $this->access->log($request, 'Fiche modifiée : '.($entry instanceof Stay ? $entry->title : $entry->name));
        });

        return back()->with('success', 'Modifications enregistrées.');
    }

    public function archive(Request $request, string $resource, int $record): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $resource, $record, $member, $data) {
            Concierge::whereKey($member->concierge_id)->lockForUpdate()->firstOrFail();
            $entry = $this->entry($request, $resource, $record);
            if ($data['archived']) {
                if ($resource === 'clients' && Property::where('client_id', $entry->id)->where('concierge_id', $member->concierge_id)->whereNull('archived_at')->exists()) {
                    throw ValidationException::withMessages(['archive' => 'Archivez d’abord les lieux de ce client.']);
                }
                $column = match ($resource) {
                    'properties' => 'property_id', 'templates' => 'template_id', default => null
                };
                if ($column && MissionRecurrence::where('concierge_id', $member->concierge_id)->where($column, $entry->id)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['archive' => 'Suspendez d’abord les récurrences associées à cette fiche.']);
                }
            } elseif ($entry instanceof Property && Client::whereKey($entry->client_id)->whereNotNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['archive' => 'Restaurez d’abord le client de ce lieu.']);
            }
            $entry->forceFill(['archived_at' => $data['archived'] ? now() : null])->save();
            $this->access->log($request, ($data['archived'] ? 'Fiche archivée : ' : 'Fiche restaurée : ').($entry instanceof Stay ? $entry->title : $entry->name));
        });

        return back()->with('success', $data['archived'] ? 'Fiche archivée. L’historique est conservé.' : 'Fiche restaurée.');
    }

    private function entry(Request $request, string $resource, int $record): Client|Property|ServiceTemplate|Stay
    {
        $model = match ($resource) {
            'clients' => Client::class,
            'properties' => Property::class,
            'templates' => ServiceTemplate::class,
            'stays' => Stay::class,
            default => abort(404),
        };

        return $this->access->record($request, $model, $record);
    }
}
