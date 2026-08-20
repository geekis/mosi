<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Gdpr\ExportUserData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * GDPR self-service: a user downloads their own data as JSON. Scoped to the
 * authenticated user only — you can never export someone else's record.
 */
class DataExportController extends Controller
{
    public function __invoke(ExportUserData $exportUserData): JsonResponse
    {
        $data = $exportUserData(Auth::user());

        return response()
            ->json($data, 200, [
                'Content-Disposition' => 'attachment; filename="mosi-data-export.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
