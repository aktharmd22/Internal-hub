<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| These checks are the real security boundary for the chat. Without them any
| authenticated employee could subscribe to any client's private thread — the
| policy on the HTTP route would never see the request.
|
| The rule below is deliberately the same one TaskPolicy::view enforces. If the
| two ever drift apart, that gap is the vulnerability.
|
*/

Broadcast::channel('task.{task}', function (User $user, Task $task) {
    return $user->can('view', $task);
});

/*
 * Presence needs its own name, not `presence-task.{id}`. Laravel strips the
 * `private-` and `presence-` prefixes before matching, so that name would
 * normalise to `task.{id}` and collide with the private channel above —
 * returning a boolean where Pusher expects member data.
 *
 * The client joins this with Echo.join('viewing-task.5').
 */
Broadcast::channel('viewing-task.{task}', function (User $user, Task $task) {
    if (! $user->can('view', $task)) {
        return false;
    }

    // Echoed to every other member of the channel, so it carries nothing
    // beyond what they can already see on the thread.
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
