<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Events\BuildEventIcs;
use App\Models\Event;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EventIcsController extends Controller
{
    public function __invoke(Event $event, BuildEventIcs $buildEventIcs): Response
    {
        Gate::authorize('view', $event);

        return response($buildEventIcs($event), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }
}
