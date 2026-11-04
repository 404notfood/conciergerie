<?php

namespace App\Actions\Concierge;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class FetchCalendar
{
    public const MAX_BYTES = 2097152;

    public function validateUrl(string $url): string
    {
        $url = preg_replace('/^webcal:\/\//i', 'https://', trim($url)) ?? '';
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw ValidationException::withMessages(['url' => 'Utilisez un lien iCal HTTPS public, sans identifiants ni port personnalisé.']);
        }

        return $url;
    }

    public function handle(string $url): string
    {
        $url = $this->validateUrl($url);
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $addresses = $this->addresses(trim($host, '[]'));
            if ($addresses === []) {
                throw new RuntimeException('Le serveur du calendrier est introuvable.');
            }
            foreach ($addresses as $address) {
                if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || str_contains($address, ':') && ! str_starts_with(strtolower($address), '2') && ! str_starts_with(strtolower($address), '3')) {
                    throw new RuntimeException('Le calendrier doit être hébergé sur une adresse publique.');
                }
            }
            $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
            $response = Http::accept('text/calendar')->withHeaders(['Accept-Encoding' => 'identity'])
                ->connectTimeout(3)->timeout(6)
                ->withOptions([
                    'allow_redirects' => false,
                    'decode_content' => false,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$address], CURLOPT_PROXY => ''],
                    'on_headers' => function (ResponseInterface $response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new RuntimeException('Calendrier trop volumineux.');
                        }
                    },
                    'progress' => function (float $total, float $downloaded): void {
                        if ($total > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('Calendrier trop volumineux.');
                        }
                    },
                ])->get($url);
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if ($location === '') {
                    throw new RuntimeException('Redirection du calendrier invalide.');
                }
                $url = $this->validateUrl((string) UriResolver::resolve(new Uri($url), new Uri($location)));

                continue;
            }
            if ($response->status() !== 200 || strlen($response->body()) > self::MAX_BYTES) {
                throw new RuntimeException('Le serveur du calendrier ne fournit pas un fichier valide.');
            }

            return $response->body();
        }

        throw new RuntimeException('Trop de redirections du calendrier.');
    }

    /** @return list<string> */
    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
