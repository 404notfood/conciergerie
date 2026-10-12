<?php
namespace App\Actions\Concierge;
use App\Models\Stay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ImportCalendar {
    public function handle(int $conciergeId, int $propertyId, string $source, string $content): int {
        $content=preg_replace('/\r?\n[ \t]/','',$content);
        if (!str_contains($content,'BEGIN:VCALENDAR') || !preg_match_all('/BEGIN:VEVENT\r?\n(.*?)END:VEVENT/s',$content,$matches)) {
            throw ValidationException::withMessages(['calendar'=>'Le fichier doit contenir un calendrier iCal avec des événements.']);
        }
        if (count($matches[1])>1000) { throw ValidationException::withMessages(['calendar'=>'Import limité à 1 000 événements par fichier.']); }
        $events=[];
        foreach ($matches[1] as $event) {
            $fields=[];
            foreach (preg_split('/\r?\n/',$event) as $line) {
                if (preg_match('/^([A-Z]+)(?:;[^:]*)?:(.*)$/',$line,$m)) { $fields[$m[1]]=trim($m[2]); }
            }
            if (($fields['STATUS']??'')==='CANCELLED') { continue; }
            try {
                $start=$this->date($fields['DTSTART']??''); $end=$this->date($fields['DTEND']??'');
            } catch (\Throwable) { throw ValidationException::withMessages(['calendar'=>'Un événement a une date non prise en charge. Aucun élément importé.']); }
            if (empty($fields['UID']) || strlen($fields['UID'])>255 || $end<=$start) { throw ValidationException::withMessages(['calendar'=>'Chaque événement doit avoir un UID et une période valide.']); }
            $events[]=['uid'=>$fields['UID'],'title'=>mb_substr(str_replace(['\\n','\\,','\\;'],[' ', ',', ';'],$fields['SUMMARY']??'Période importée'),0,120),'start'=>$start,'end'=>$end];
        }
        return DB::transaction(function () use ($events,$conciergeId,$propertyId,$source) {
            foreach ($events as $event) {
                Stay::updateOrCreate(['concierge_id'=>$conciergeId,'property_id'=>$propertyId,'source'=>$source,'external_uid'=>$event['uid']],['title'=>$event['title'],'starts_on'=>$event['start'],'ends_on'=>$event['end'],'kind'=>'unclassified']);
            } return count($events);
        });
    }
    private function date(string $date): string {
        if (!preg_match('/^(\d{8})(?:T\d{6}Z?)?$/',$date,$m)) { throw new \InvalidArgumentException; }
        return Carbon::createFromFormat('!Ymd',$m[1])->format('Y-m-d');
    }
}
