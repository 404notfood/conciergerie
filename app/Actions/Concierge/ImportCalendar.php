<?php

namespace App\Actions\Concierge;

use App\Models\Stay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportCalendar
{
    public function handle(int $conciergeId, int $propertyId, string $source, string $content): int
    {
        $events = $this->parse($content);

        return DB::transaction(function () use ($events, $conciergeId, $propertyId, $source) {
            foreach ($events as $event) {
                $stay = Stay::firstOrNew(['concierge_id' => $conciergeId, 'property_id' => $propertyId, 'source' => $source, 'external_uid' => $event['uid'], 'calendar_feed_id' => null]);
                $stay->fill(['title' => $event['title'], 'starts_on' => $event['start'], 'ends_on' => $event['end']]);
                if (! $stay->exists) {
                    $stay->kind = 'unclassified';
                }
                $stay->save();
            }

            return count($events);
        });
    }

    /** @return list<array{uid: string, title: string, start: string, end: string}> */
    public function parse(string $content, bool $allowEmpty = false): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content, "\xEF\xBB\xBF \t\r\n"));
        $content = preg_replace('/\n[ \t]/', '', $content) ?? '';
        if (! str_starts_with($content, "BEGIN:VCALENDAR\n") || ! str_ends_with($content, 'END:VCALENDAR') || substr_count($content, 'BEGIN:VCALENDAR') !== 1) {
            $this->invalid('Le contenu doit être un calendrier iCal complet.');
        }
        preg_match_all('/^BEGIN:VEVENT\n(.*?)^END:VEVENT$/ms', $content, $matches);
        if (count($matches[1]) !== substr_count($content, 'BEGIN:VEVENT') || count($matches[1]) !== substr_count($content, 'END:VEVENT')) {
            $this->invalid('Le calendrier contient un événement incomplet.');
        }
        if (count($matches[1]) > 1000 || (! $allowEmpty && count($matches[1]) === 0)) {
            $this->invalid('Le calendrier doit contenir entre 1 et 1 000 événements (un flux synchronisé peut être vide).');
        }
        $events = [];
        $seen = [];
        foreach ($matches[1] as $event) {
            $fields = [];
            foreach (explode("\n", $event) as $line) {
                if (preg_match('/^([A-Z-]+)(?:;[^:]*)?:(.*)$/', $line, $m)) {
                    $fields[$m[1]] = trim($m[2]);
                }
            }
            $uid = $fields['UID'] ?? '';
            if ($uid === '' || strlen($uid) > 255 || isset($seen[$uid])) {
                $this->invalid('Chaque événement doit avoir un identifiant unique valide.');
            }
            $seen[$uid] = true;
            if (isset($fields['RRULE']) || isset($fields['RDATE']) || isset($fields['RECURRENCE-ID'])) {
                $this->invalid('Les événements récurrents doivent être exportés sous forme de périodes individuelles.');
            }
            if (($fields['STATUS'] ?? '') === 'CANCELLED') {
                continue;
            }
            $start = $this->date($fields['DTSTART'] ?? '');
            $end = $this->date($fields['DTEND'] ?? '');
            if ($end <= $start) {
                $this->invalid('Chaque événement doit avoir une date de fin après sa date de début.');
            }
            $events[] = ['uid' => $uid, 'title' => mb_substr(str_replace(['\\n', '\\N', '\\,', '\\;'], [' ', ' ', ',', ';'], $fields['SUMMARY'] ?? 'Période importée'), 0, 120), 'start' => $start, 'end' => $end];
        }

        return $events;
    }

    private function date(string $date): string
    {
        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(?:T([01]\d|2[0-3])([0-5]\d)([0-5]\d)Z?)?$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $this->invalid('Un événement contient une date non prise en charge. Aucune période modifiée.');
        }

        return $m[1].'-'.$m[2].'-'.$m[3];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['calendar' => $message]);
    }
}
