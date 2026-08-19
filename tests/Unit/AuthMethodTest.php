<?php

declare(strict_types=1);

use App\Enums\AuthMethod;

it('derives Entra from an @nattura.is address', function (string $email) {
    expect(AuthMethod::fromEmail($email))->toBe(AuthMethod::Entra);
})->with([
    'jon.jonsson@nattura.is',
    'JON.JONSSON@NATTURA.IS',
    '  jon@nattura.is  ',
]);

it('derives Password from any other address, or none', function (?string $email) {
    expect(AuthMethod::fromEmail($email))->toBe(AuthMethod::Password);
})->with([
    'jon@gmail.com',
    'jon@nattura.is.example.com',
    'jon@natturais.com',
    '',
    null,
]);

it('knows which login paths each method allows', function () {
    expect(AuthMethod::Entra->canUseEntra())->toBeTrue()
        ->and(AuthMethod::Entra->canUsePassword())->toBeFalse()
        ->and(AuthMethod::Password->canUsePassword())->toBeTrue()
        ->and(AuthMethod::Password->canUseEntra())->toBeFalse()
        ->and(AuthMethod::Both->canUseEntra())->toBeTrue()
        ->and(AuthMethod::Both->canUsePassword())->toBeTrue();
});
