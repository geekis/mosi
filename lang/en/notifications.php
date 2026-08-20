<?php

declare(strict_types=1);

return [

    'mention' => [
        'subject' => 'You were mentioned on Mosi',
        'body' => ':name mentioned you.',
        'action' => 'View on Mosi',
    ],

    'comment' => [
        'subject' => 'New comment on your post',
        'body' => ':name commented on your post.',
        'action' => 'View on Mosi',
    ],

    'new_follower' => [
        'body' => ':name started following you.',
    ],

    'digest' => [
        'subject' => 'Your Mosi digest',
        'greeting' => 'Hi :name,',
        'intro' => 'Here is what you missed on Mosi:',
        'unread' => ':count unread notification|:count unread notifications',
        'action' => 'Open Mosi',
    ],

];
