<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Notes when and from where a person last signed in, for the account record
 * an Admin sees. Written past the model, so "updated at" keeps meaning the
 * last change someone made to the record.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        DB::table('users')->where('id', $event->user->id)->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
    }
}
