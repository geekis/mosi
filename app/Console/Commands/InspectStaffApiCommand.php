<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Print the STRUCTURE of a staff-API sample — keys and value types only, never the
 * values — so the payload shape can be verified without exposing kennitölur or names.
 */
#[Signature('app:inspect-staff-api {--rows=1 : How many records to request}')]
#[Description('Print the staff API payload structure (keys and types only, no values).')]
class InspectStaffApiCommand extends Command
{
    public function handle(): int
    {
        /** @var array<string, mixed> $config */
        $config = config('services.staff_api');

        if (blank($config['url'] ?? null)) {
            $this->error('STAFF_API_URL is not set.');

            return self::FAILURE;
        }

        $parts = parse_url((string) $config['url']);
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').($parts['path'] ?? '');
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        $request = Http::acceptJson();

        if (filled($config['username'] ?? null)) {
            $request = $request->withBasicAuth((string) $config['username'], (string) ($config['password'] ?? ''));
        }

        $url = $base.'?'.http_build_query(array_merge($query, [
            'row_offset' => 0,
            'fetch_size' => (int) $this->option('rows'),
        ]));

        $response = $request->get($url);

        if ($response->failed()) {
            $this->error("Request failed: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $body = $response->json();

        if (! is_array($body)) {
            $this->error('Response was not a JSON object.');

            return self::FAILURE;
        }

        $this->info('Envelope keys: '.implode(', ', array_keys($body)));

        $items = $body['items'] ?? $body['rows'] ?? [];
        $first = is_array($items) ? ($items[0] ?? null) : null;

        if ($first === null) {
            $this->warn('No records found to describe.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('First record structure:');
        $this->line($this->describe($first));

        return self::SUCCESS;
    }

    /**
     * Render a value as its shape: object/array keys with their value types, recursing
     * into nested arrays (showing the first element only). Scalars become their type
     * name so no actual value — kennitala, name, email — is ever printed.
     */
    private function describe(mixed $value, int $depth = 0): string
    {
        $pad = str_repeat('  ', $depth);

        if (! is_array($value)) {
            return get_debug_type($value);
        }

        if (array_is_list($value)) {
            $first = $value[0] ?? null;

            return 'array['.count($value).'] of '.($first === null ? 'mixed' : $this->describe($first, $depth));
        }

        $lines = ['{'];
        foreach ($value as $key => $item) {
            $lines[] = "{$pad}  {$key}: ".$this->describe($item, $depth + 1);
        }
        $lines[] = "{$pad}}";

        return implode("\n", $lines);
    }
}
