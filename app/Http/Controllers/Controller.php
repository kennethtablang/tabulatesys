<?php

namespace App\Http\Controllers;

use App\Models\Event;

abstract class Controller
{
    /** Tabulators generated for one event may not open another event's screens. */
    protected function authorizeEvent(Event $event): void
    {
        abort_unless(auth()->user()?->canAccessEvent($event), 403, 'Your account is not assigned to this event.');
    }
}
