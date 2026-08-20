<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A conversation is either a one-to-one direct message or a named group chat.
 */
enum ConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';
}
