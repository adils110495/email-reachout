<?php

namespace App\Http\Controllers\Sequencer;

use App\Http\Controllers\Controller;
use App\Models\User;

/** Sequences, like the rest of the app, are shared by every signed-in team member. */
abstract class SequencerController extends Controller
{
    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    protected function perPage(): int
    {
        return 25;
    }
}
