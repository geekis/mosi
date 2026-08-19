<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Enum labels (English)
|--------------------------------------------------------------------------
|
| Human-readable labels for backed enums. Keyed by the enum's backing value.
| personType / assignmentStatus come straight from HR as Icelandic terms and are
| shown as-is until an English wording is chosen.
|
*/

return [

    'auth_method' => [
        'entra' => 'Work account',
        'password' => 'Password',
        'both' => 'Work account or password',
    ],

    'person_type' => [
        'Starfsmaður' => 'Employee',
    ],

    'assignment_status' => [
        'Virkt starf' => 'Active assignment',
    ],

];
