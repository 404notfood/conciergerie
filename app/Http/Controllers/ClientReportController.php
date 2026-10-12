<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\ClientReportPdf;
use App\Actions\Concierge\WorkspaceAccess;
use App\Actions\Seo\SeoManager;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Incident;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ClientReportController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private SeoManager $seo) {}

    public function report(Request $request, int $report, ClientReportPdf $pdf): Response
    {
        $member = $this->access->membership($request);
        $record = Report::where('concierge_id', $member->concierge_id)->where('status', 'validated')->with(['mission.property.client', 'photos', 'author', 'reviewer'])->findOrFail($report);
        abort_unless(in_array($member->role, ['manager', 'client'], true), 403);
        abort_if($member->role === 'client' && $record->mission->property->client_id !== $member->client_id, 404);
        $sections = [['heading' => 'Observations validées', 'text' => $record->notes, 'photos' => $record->photos]];
        $subtitle = $record->mission->property->name."\n".$record->mission->property->address."\nIntervention du ".$record->mission->scheduled_at->copy()->timezone('Europe/Paris')->format('d/m/Y à H:i')."\nIntervenant : ".$record->author->name."\nCompte rendu validé le ".$record->reviewed_at?->copy()->timezone('Europe/Paris')->format('d/m/Y à H:i').($record->reviewer ? ' par '.$record->reviewer->name : '');
        $bytes = $pdf->render($this->seo->name().' / '.Concierge::findOrFail($member->concierge_id)->name, $record->mission->title, $subtitle, $sections);

        return $this->download($bytes, 'compte-rendu-'.$record->id.'.pdf');
    }

    public function monthly(Request $request, int $client, string $month, ClientReportPdf $pdf): Response
    {
        $member = $this->access->membership($request);
        abort_unless(in_array($member->role, ['manager', 'client'], true), 403);
        abort_if($member->role === 'client' && $client !== $member->client_id, 404);
        $record = Client::where('concierge_id', $member->concierge_id)->findOrFail($client);
        abort_unless(preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $month) && (int) substr($month, 0, 4) >= 2000 && (int) substr($month, 0, 4) <= 2100, 422, 'Mois invalide.');
        $start = Carbon::parse($month.'-01', 'Europe/Paris')->startOfDay();
        $end = $start->copy()->addMonth();
        $reports = Report::where('concierge_id', $member->concierge_id)->where('status', 'validated')->whereHas('mission', fn ($query) => $query->where('scheduled_at', '>=', $start->copy()->utc())->where('scheduled_at', '<', $end->copy()->utc())->whereHas('property', fn ($property) => $property->where('client_id', $client)))->with('mission.property')->orderBy('id')->get();
        $sections = [];
        foreach ($reports as $report) {
            $sections[] = ['heading' => $report->mission->property->name.' - '.$report->mission->scheduled_at->copy()->timezone('Europe/Paris')->format('d/m/Y'), 'text' => $report->mission->title."\n".$report->notes, 'photos' => []];
        }
        $incidents = Incident::where('concierge_id', $member->concierge_id)->whereNotNull('shared_at')->where('shared_at', '>=', $start->copy()->utc())->where('shared_at', '<', $end->copy()->utc())->whereHas('mission.property', fn ($query) => $query->where('client_id', $client))->with('mission.property')->get();
        foreach ($incidents as $incident) {
            $sections[] = ['heading' => 'Incident partagé - '.$incident->mission->property->name, 'text' => ($incident->client_message ?? '')."\nÉtat partagé : ".match ($incident->shared_status ?? $incident->status) {
                'resolved' => 'Résolu', 'in_progress' => 'En traitement', default => 'À traiter'
            }, 'photos' => []];
        }
        if ($sections === []) {
            $sections[] = ['heading' => 'Aucun élément publié pour ce mois', 'text' => 'Les comptes rendus validés et les incidents partagés apparaîtront dans ce bilan.', 'photos' => []];
        }
        $bytes = $pdf->render($this->seo->name().' / '.Concierge::findOrFail($member->concierge_id)->name, 'Bilan mensuel', $record->name.' - '.$start->translatedFormat('F Y')."\n".$reports->count().($reports->count() === 1 ? ' compte rendu validé' : ' comptes rendus validés').' / '.$incidents->count().($incidents->count() === 1 ? ' incident partagé' : ' incidents partagés'), $sections);

        return $this->download($bytes, 'bilan-client-'.$record->id.'-'.$month.'.pdf');
    }

    private function download(string $bytes, string $filename): Response
    {
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
