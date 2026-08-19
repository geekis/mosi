<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Staðfestingarskilaboð (íslenska)
|--------------------------------------------------------------------------
|
| Íslensk þýðing á sjálfgefnum villuskilaboðum staðfestingar (validation).
| :attribute er nafn reitsins og er þýtt í 'attributes' fylkinu neðst.
|
*/

return [

    'accepted' => 'Samþykkja verður :attribute.',
    'accepted_if' => 'Samþykkja verður :attribute þegar :other er :value.',
    'active_url' => ':attribute verður að vera gild vefslóð.',
    'after' => ':attribute verður að vera dagsetning eftir :date.',
    'after_or_equal' => ':attribute verður að vera dagsetning eftir eða jöfn :date.',
    'alpha' => ':attribute má aðeins innihalda bókstafi.',
    'alpha_dash' => ':attribute má aðeins innihalda bókstafi, tölustafi, bandstrik og undirstrik.',
    'alpha_num' => ':attribute má aðeins innihalda bókstafi og tölustafi.',
    'any_of' => ':attribute er ógilt.',
    'array' => ':attribute verður að vera fylki.',
    'array_keys' => ':attribute má aðeins innihalda eftirfarandi lykla: :values.',
    'ascii' => ':attribute má aðeins innihalda einsbæta bók- og tölustafi og tákn.',
    'base64' => ':attribute verður að vera gildur Base64 strengur.',
    'before' => ':attribute verður að vera dagsetning fyrir :date.',
    'before_or_equal' => ':attribute verður að vera dagsetning fyrir eða jöfn :date.',
    'between' => [
        'array' => ':attribute verður að innihalda á milli :min og :max atriði.',
        'file' => ':attribute verður að vera á milli :min og :max kílóbæti.',
        'numeric' => ':attribute verður að vera á milli :min og :max.',
        'string' => ':attribute verður að vera á milli :min og :max stafir.',
    ],
    'boolean' => ':attribute verður að vera satt eða ósatt.',
    'can' => ':attribute inniheldur óheimilt gildi.',
    'confirmed' => 'Staðfesting á :attribute passar ekki.',
    'contains' => ':attribute vantar áskilið gildi.',
    'current_password' => 'Lykilorðið er rangt.',
    'date' => ':attribute verður að vera gild dagsetning.',
    'date_equals' => ':attribute verður að vera dagsetning jöfn :date.',
    'date_format' => ':attribute verður að fylgja sniðinu :format.',
    'decimal' => ':attribute verður að hafa :decimal aukastafi.',
    'declined' => 'Hafna verður :attribute.',
    'declined_if' => 'Hafna verður :attribute þegar :other er :value.',
    'different' => ':attribute og :other verða að vera ólík.',
    'digits' => ':attribute verður að vera :digits tölustafir.',
    'digits_between' => ':attribute verður að vera á milli :min og :max tölustafir.',
    'dimensions' => ':attribute hefur ógildar myndastærðir.',
    'distinct' => ':attribute hefur endurtekið gildi.',
    'doesnt_contain' => ':attribute má ekki innihalda neitt af eftirfarandi: :values.',
    'doesnt_end_with' => ':attribute má ekki enda á einu af eftirfarandi: :values.',
    'doesnt_start_with' => ':attribute má ekki byrja á einu af eftirfarandi: :values.',
    'email' => ':attribute verður að vera gilt netfang.',
    'encoding' => ':attribute verður að vera kóðað í :encoding.',
    'ends_with' => ':attribute verður að enda á einu af eftirfarandi: :values.',
    'enum' => 'Valið :attribute er ógilt.',
    'exists' => 'Valið :attribute er ógilt.',
    'extensions' => ':attribute verður að hafa eina af eftirfarandi skráarendingum: :values.',
    'file' => ':attribute verður að vera skrá.',
    'filled' => ':attribute verður að hafa gildi.',
    'gt' => [
        'array' => ':attribute verður að innihalda fleiri en :value atriði.',
        'file' => ':attribute verður að vera stærri en :value kílóbæti.',
        'numeric' => ':attribute verður að vera stærri en :value.',
        'string' => ':attribute verður að vera lengri en :value stafir.',
    ],
    'gte' => [
        'array' => ':attribute verður að innihalda :value atriði eða fleiri.',
        'file' => ':attribute verður að vera stærri en eða jöfn :value kílóbæti.',
        'numeric' => ':attribute verður að vera stærri en eða jöfn :value.',
        'string' => ':attribute verður að vera lengri en eða jöfn :value stafir.',
    ],
    'hex_color' => ':attribute verður að vera gildur hexadesímal litur.',
    'image' => ':attribute verður að vera mynd.',
    'in' => 'Valið :attribute er ógilt.',
    'in_array' => ':attribute verður að vera til í :other.',
    'in_array_keys' => ':attribute verður að innihalda að minnsta kosti einn af eftirfarandi lyklum: :values.',
    'integer' => ':attribute verður að vera heiltala.',
    'ip' => ':attribute verður að vera gild IP-tala.',
    'ipv4' => ':attribute verður að vera gild IPv4-tala.',
    'ipv6' => ':attribute verður að vera gild IPv6-tala.',
    'json' => ':attribute verður að vera gildur JSON strengur.',
    'list' => ':attribute verður að vera listi.',
    'lowercase' => ':attribute verður að vera lágstafir.',
    'lt' => [
        'array' => ':attribute verður að innihalda færri en :value atriði.',
        'file' => ':attribute verður að vera minni en :value kílóbæti.',
        'numeric' => ':attribute verður að vera minni en :value.',
        'string' => ':attribute verður að vera styttri en :value stafir.',
    ],
    'lte' => [
        'array' => ':attribute má ekki innihalda fleiri en :value atriði.',
        'file' => ':attribute verður að vera minni en eða jöfn :value kílóbæti.',
        'numeric' => ':attribute verður að vera minni en eða jöfn :value.',
        'string' => ':attribute verður að vera styttri en eða jöfn :value stafir.',
    ],
    'mac_address' => ':attribute verður að vera gilt MAC-vistfang.',
    'max' => [
        'array' => ':attribute má ekki innihalda fleiri en :max atriði.',
        'file' => ':attribute má ekki vera stærri en :max kílóbæti.',
        'numeric' => ':attribute má ekki vera stærri en :max.',
        'string' => ':attribute má ekki vera lengri en :max stafir.',
    ],
    'max_digits' => ':attribute má ekki hafa fleiri en :max tölustafi.',
    'mimes' => ':attribute verður að vera skrá af gerðinni: :values.',
    'mimetypes' => ':attribute verður að vera skrá af gerðinni: :values.',
    'min' => [
        'array' => ':attribute verður að innihalda að minnsta kosti :min atriði.',
        'file' => ':attribute verður að vera að minnsta kosti :min kílóbæti.',
        'numeric' => ':attribute verður að vera að minnsta kosti :min.',
        'string' => ':attribute verður að vera að minnsta kosti :min stafir.',
    ],
    'min_digits' => ':attribute verður að hafa að minnsta kosti :min tölustafi.',
    'missing' => ':attribute verður að vanta.',
    'missing_if' => ':attribute verður að vanta þegar :other er :value.',
    'missing_unless' => ':attribute verður að vanta nema :other sé :value.',
    'missing_with' => ':attribute verður að vanta þegar :values er til staðar.',
    'missing_with_all' => ':attribute verður að vanta þegar :values eru til staðar.',
    'multiple_of' => ':attribute verður að vera margfeldi af :value.',
    'not_in' => 'Valið :attribute er ógilt.',
    'not_regex' => 'Snið á :attribute er ógilt.',
    'numeric' => ':attribute verður að vera tala.',
    'password' => [
        'letters' => ':attribute verður að innihalda að minnsta kosti einn bókstaf.',
        'mixed' => ':attribute verður að innihalda að minnsta kosti einn hástaf og einn lágstaf.',
        'numbers' => ':attribute verður að innihalda að minnsta kosti einn tölustaf.',
        'symbols' => ':attribute verður að innihalda að minnsta kosti eitt tákn.',
        'uncompromised' => 'Uppgefið :attribute hefur birst í gagnaleka. Vinsamlegast veldu annað :attribute.',
    ],
    'present' => ':attribute verður að vera til staðar.',
    'present_if' => ':attribute verður að vera til staðar þegar :other er :value.',
    'present_unless' => ':attribute verður að vera til staðar nema :other sé :value.',
    'present_with' => ':attribute verður að vera til staðar þegar :values er til staðar.',
    'present_with_all' => ':attribute verður að vera til staðar þegar :values eru til staðar.',
    'prohibited' => ':attribute er óheimilt.',
    'prohibited_if' => ':attribute er óheimilt þegar :other er :value.',
    'prohibited_if_accepted' => ':attribute er óheimilt þegar :other er samþykkt.',
    'prohibited_if_declined' => ':attribute er óheimilt þegar :other er hafnað.',
    'prohibited_unless' => ':attribute er óheimilt nema :other sé í :values.',
    'prohibits' => ':attribute kemur í veg fyrir að :other sé til staðar.',
    'regex' => 'Snið á :attribute er ógilt.',
    'required' => ':attribute er nauðsynlegt.',
    'required_array_keys' => ':attribute verður að innihalda færslur fyrir: :values.',
    'required_if' => ':attribute er nauðsynlegt þegar :other er :value.',
    'required_if_accepted' => ':attribute er nauðsynlegt þegar :other er samþykkt.',
    'required_if_declined' => ':attribute er nauðsynlegt þegar :other er hafnað.',
    'required_unless' => ':attribute er nauðsynlegt nema :other sé í :values.',
    'required_with' => ':attribute er nauðsynlegt þegar :values er til staðar.',
    'required_with_all' => ':attribute er nauðsynlegt þegar :values eru til staðar.',
    'required_without' => ':attribute er nauðsynlegt þegar :values er ekki til staðar.',
    'required_without_all' => ':attribute er nauðsynlegt þegar ekkert af :values er til staðar.',
    'same' => ':attribute verður að passa við :other.',
    'size' => [
        'array' => ':attribute verður að innihalda :size atriði.',
        'file' => ':attribute verður að vera :size kílóbæti.',
        'numeric' => ':attribute verður að vera :size.',
        'string' => ':attribute verður að vera :size stafir.',
    ],
    'starts_with' => ':attribute verður að byrja á einu af eftirfarandi: :values.',
    'string' => ':attribute verður að vera strengur.',
    'timezone' => ':attribute verður að vera gilt tímabelti.',
    'unique' => ':attribute er þegar í notkun.',
    'uploaded' => 'Ekki tókst að hlaða upp :attribute.',
    'uppercase' => ':attribute verður að vera hástafir.',
    'url' => ':attribute verður að vera gild vefslóð.',
    'ulid' => ':attribute verður að vera gilt ULID.',
    'uuid' => ':attribute verður að vera gilt UUID.',

    /*
    |--------------------------------------------------------------------------
    | Sérsniðin staðfestingarskilaboð
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nöfn reita
    |--------------------------------------------------------------------------
    |
    | Notað til að skipta :attribute út fyrir læsilegra heiti, t.d. "netfang"
    | í stað "email". Fyllt út eftir því sem reitir bætast við í síðari fösum.
    |
    */

    'attributes' => [],

];
