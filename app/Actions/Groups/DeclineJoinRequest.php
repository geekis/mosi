<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\GroupJoinRequest;

final class DeclineJoinRequest
{
    public function __invoke(GroupJoinRequest $request): void
    {
        $request->delete();
    }
}
