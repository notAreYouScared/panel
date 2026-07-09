<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::privateChannel('server.{id}.stats', function (User $user, int $id) {
    $server = Server::findOrFail($id);

    return $user->can('view', $server);
});
