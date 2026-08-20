<?php

declare(strict_types=1);

return [

    'sync_aborted' => [
        'subject' => 'Mosi: samstilling starfsmanna stöðvuð',
        'body' => 'Samstilling starfsmanna var stöðvuð því vefþjónustan skilaði :current færslum, en var með :previous í síðustu heppnuðu keyrslu — meira en 15% fækkun. Engum aðgangi var breytt.',
        'action' => 'Athugaðu vefþjónustu starfsmanna áður en næsta keyrsla fer af stað.',
    ],

    'invitation' => [
        'subject' => 'Velkomin í Mosa',
        'greeting' => 'Hæ :name,',
        'greeting_generic' => 'Hæ,',
        'intro' => 'Þér hefur verið boðið í Mosa, innri vef starfsfólks NVS. Stilltu lykilorðið þitt til að byrja.',
        'action' => 'Stilla lykilorð',
        'expiry' => 'Þessi hlekkur rennur út eftir :days daga. Ef það gerist skaltu biðja kerfisstjóra um nýjan.',
    ],

];
