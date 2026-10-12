<?php

namespace App\Actions\Concierge;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class ClientReportPdf
{
    /** @var list<string> */
    private array $pages = [];

    private string $stream = '';

    private int $y = 90;

    /** @var array<string, array{bytes: string, width: int, height: int}> */
    private array $images = [];

    private string $brand = '';

    /** @param list<array{heading: string, text: string, photos: iterable<int,Photo>}> $sections */
    public function render(string $brand, string $title, string $subtitle, array $sections): string
    {
        $this->pages = [];
        $this->images = [];
        $this->stream = '';
        $this->brand = $brand;
        $this->newPage();
        $this->paragraph($title, 21, true);
        $this->paragraph($subtitle, 11);
        $this->y += 16;
        foreach ($sections as $section) {
            $this->room(75);
            $this->paragraph($section['heading'], 14, true);
            $this->paragraph($section['text'], 10);
            foreach ($section['photos'] as $photo) {
                $this->photo($photo);
            }
            $this->y += 16;
        }
        $this->pages[] = $this->stream;

        return $this->document();
    }

    private function newPage(): void
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
        }
        $this->stream = "0.06 0.46 0.43 rg 42 789 18 18 re f\n";
        $this->y = 90;
        foreach (array_slice($this->wrap($this->brand, 67), 0, 2) as $index => $line) {
            $this->draw($line, 73, 37 + $index * 15, 12, true);
        }
        $this->stream .= "0.85 0.9 0.91 RG 42 777 m 553 777 l S\n";
        $this->draw('Compte rendu de conciergerie', 42, 812, 8);
        $this->draw('Document généré le '.now('Europe/Paris')->format('d/m/Y'), 370, 812, 8);
        $this->draw('Page '.(count($this->pages) + 1), 42, 825, 8);
    }

    private function room(int $height): void
    {
        if ($this->y + $height > 780) {
            $this->newPage();
        }
    }

    private function draw(string $text, int $x, int $y, int $size, bool $bold = false): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: '';
        $safe = str_replace(['\\', '(', ')', "\r", "\n", "\0"], ['\\\\', '\\(', '\\)', ' ', ' ', ''], $encoded);
        $font = $bold ? 'F2' : 'F1';
        $this->stream .= '0.07 0.15 0.23 rg BT /'.$font.' '.$size.' Tf '.$x.' '.(842 - $y).' Td ('.$safe.") Tj ET\n";
    }

    private function paragraph(string $text, int $size, bool $bold = false): void
    {
        $limit = max(20, (int) floor(485 / ($size * 0.57)));
        $paragraphs = explode("\n", str_replace("\r", '', $text));
        foreach ($paragraphs as $paragraph) {
            foreach ($this->wrap($paragraph, $limit) as $line) {
                $this->room($size + 9);
                $this->draw($line, 42, $this->y, $size, $bold);
                $this->y += $size + 7;
            }
        }
        $this->y += 8;
    }

    /** @return list<string> */
    private function wrap(string $text, int $limit): array
    {
        $lines = [];
        $line = '';
        $width = 0.0;
        foreach (mb_str_split($text) as $character) {
            $cell = str_contains('MWmw@%', $character) ? 2.0 : (preg_match('/\p{Lu}/u', $character) ? 1.4 : 1.0);
            if ($width + $cell > $limit) {
                $space = mb_strrpos($line, ' ');
                if ($space !== false) {
                    $lines[] = mb_substr($line, 0, $space);
                    $line = mb_substr($line, $space + 1);
                    $width = 0.0;
                    foreach (mb_str_split($line) as $tail) {
                        $width += str_contains('MWmw@%', $tail) ? 2.0 : (preg_match('/\p{Lu}/u', $tail) ? 1.4 : 1.0);
                    }
                } else {
                    $lines[] = $line;
                    $line = '';
                    $width = 0.0;
                }
            }
            $line .= $character;
            $width += $cell;
        }
        $lines[] = $line;

        return $lines;
    }

    private function photo(Photo $photo): void
    {
        if (! Storage::disk('local')->exists($photo->path)) {
            $this->paragraph('Photo #'.$photo->id.' non disponible dans le stockage.', 9);

            return;
        }
        $bytes = Storage::disk('local')->get($photo->path);
        $dimensions = @getimagesizefromstring($bytes);
        if (! $dimensions || $dimensions[0] * $dimensions[1] > 20000000) {
            $this->paragraph('Photo #'.$photo->id.' disponible dans l’espace client.', 9);

            return;
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return;
        }
        if ($dimensions['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data(Storage::disk('local')->path($photo->path));
            $orientation = $exif['Orientation'] ?? 1;
            $rotation = match ($orientation) {
                3 => 180, 5, 6, 7 => 270, 8 => 90, default => 0
            };
            if ($rotation) {
                $rotated = imagerotate($source, $rotation, 0xFFFFFF);
                if ($rotated) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }
            if (in_array($orientation, [2, 5], true)) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            } elseif (in_array($orientation, [4, 7], true)) {
                imageflip($source, IMG_FLIP_VERTICAL);
            }
            $dimensions[0] = imagesx($source);
            $dimensions[1] = imagesy($source);
        }
        $ratio = min(1, 1200 / max($dimensions[0], $dimensions[1]));
        $width = max(1, (int) round($dimensions[0] * $ratio));
        $height = max(1, (int) round($dimensions[1] * $ratio));
        $target = imagecreatetruecolor($width, $height);
        imagefill($target, 0, 0, 0xFFFFFF);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
        ob_start();
        imagejpeg($target, null, 88);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        $key = 'Im'.(count($this->images) + 1);
        $this->images[$key] = ['bytes' => $jpeg, 'width' => $width, 'height' => $height];
        $scale = min(485 / $width, 370 / $height);
        $displayWidth = (int) round($width * $scale);
        $displayHeight = (int) round($height * $scale);
        $this->room($displayHeight + 50);
        $label = ['before' => 'Avant', 'after' => 'Après', 'observation' => 'Observation'][$photo->stage] ?? 'Photo';
        $this->paragraph($label.' - photo #'.$photo->id, 9);
        $this->stream .= 'q '.$displayWidth.' 0 0 '.$displayHeight.' 42 '.(842 - $this->y - $displayHeight).' cm /'.$key." Do Q\n";
        $this->y += $displayHeight + 22;
    }

    private function document(): string
    {
        $objects = [1 => '', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $imageIds = [];
        foreach ($this->images as $key => $image) {
            $id = count($objects) + 1;
            $imageIds[$key] = $id;
            $objects[$id] = '<< /Type /XObject /Subtype /Image /Width '.$image['width'].' /Height '.$image['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($image['bytes'])." >>\nstream\n".$image['bytes']."\nendstream";
        }
        $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >> /XObject << '.implode(' ', array_map(fn (string $key, int $id) => '/'.$key.' '.$id.' 0 R', array_keys($imageIds), $imageIds)).' >> >>';
        $kids = [];
        foreach ($this->pages as $stream) {
            $contentId = count($objects) + 1;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
            $pageId = count($objects) + 1;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources '.$resources.' /Contents '.$contentId.' 0 R >>';
            $kids[] = $pageId.' 0 R';
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R /Lang (fr-FR) >>';
        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
