<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\AuthMethod;
use App\Enums\PersonType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @var array<string, int> Tracks nattura.is local-parts already issued, for disambiguation. */
    private static array $natturaEmailCounts = [];

    /** @var list<string> */
    private const array MALE_NAMES = [
        'Jón', 'Sigurður', 'Guðmundur', 'Gunnar', 'Ólafur', 'Einar', 'Kristján', 'Magnús',
        'Stefán', 'Björn', 'Arnar', 'Aron', 'Daníel', 'Kári', 'Baldur', 'Þór', 'Hafþór',
        'Ingólfur', 'Sveinn', 'Bjarki', 'Hilmar', 'Snorri', 'Þorsteinn', 'Ragnar',
    ];

    /** @var list<string> */
    private const array FEMALE_NAMES = [
        'Guðrún', 'Anna', 'Kristín', 'Margrét', 'Sigríður', 'Helga', 'Jóhanna', 'María',
        'Katrín', 'Elín', 'Ragnheiður', 'Hildur', 'Þórunn', 'Freyja', 'Sara', 'Halldóra',
        'Ásta', 'Bryndís', 'Erla', 'Íris', 'Lilja', 'Sunna', 'Þórdís', 'Unnur',
    ];

    /** @var list<string> Genitive forms used to build patronymics. */
    private const array PATRONYM_ROOTS = [
        'Jóns', 'Sigurðar', 'Guðmundar', 'Gunnars', 'Ólafs', 'Einars', 'Kristjáns', 'Magnúsar',
        'Stefáns', 'Björns', 'Arnars', 'Þórs', 'Baldurs', 'Kára', 'Sveins', 'Ragnars',
        'Hilmars', 'Snorra', 'Þorsteins', 'Ingólfs',
    ];

    /** @var list<array{string, string}> Real NVS organisations: [organizationId, name]. */
    private const array ORGANISATIONS = [
        ['39163', 'NVS Vestursvæði'],
        ['39164', 'NVS Norðurland'],
        ['39165', 'NVS Suðurland'],
        ['39166', 'NVS Austurland'],
        ['39167', 'NVS Vesturland'],
        ['39168', 'NVS Suðvesturland'],
        ['39169', 'Þjóðgarðurinn á Þingvöllum'],
        ['39170', 'Vatnajökulsþjóðgarður'],
        ['39171', 'Skrifstofa forstjóra'],
    ];

    /** @var list<string> */
    private const array JOB_TITLES = [
        'Landvörður', 'Sérfræðingur', 'Verkefnastjóri', 'Þjóðgarðsvörður', 'Sviðsstjóri',
        'Náttúrufræðingur', 'Skrifstofustjóri', 'Deildarstjóri', 'Ráðgjafi', 'Landfræðingur',
        'Umsjónarmaður', 'Sérfræðingur í náttúruvernd',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $female = fake()->boolean();
        $given = fake()->randomElement($female ? self::FEMALE_NAMES : self::MALE_NAMES);
        $patronym = fake()->randomElement(self::PATRONYM_ROOTS).($female ? 'dóttir' : 'son');
        $displayName = "{$given} {$patronym}";

        // The default is a password (invitation) account so tests can authenticate with
        // "password". The realistic ~75% Entra / ~25% personal split is composed in the
        // seeder via the entra() and passwordAuth() states.
        $email = $this->personalEmail($displayName);

        [$organizationId, $organizationName] = fake()->randomElement(self::ORGANISATIONS);

        return [
            'external_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'display_name' => $displayName,
            'email' => $email,
            'phone' => '+354'.fake()->randomElement([6, 7, 8]).fake()->numerify('######'),
            'employee_number' => (string) fake()->unique()->numberBetween(1000, 9999),
            'person_type' => PersonType::Employee->value,
            'job_title' => fake()->randomElement(self::JOB_TITLES),
            'job_id' => (string) fake()->numberBetween(200, 899),
            'organization_id' => $organizationId,
            'organization_name' => $organizationName,
            'parent_organization_id' => '39162',
            'assignment_status' => AssignmentStatus::Active->value,
            'assignment_starts_at' => fake()->dateTimeBetween('-8 years', '-1 month'),
            'assignment_ends_at' => null,
            'assignment_percent' => fake()->randomElement([100, 100, 100, 80, 50]),
            'is_manager' => false,
            'location_name' => $organizationName,
            'auth_method' => AuthMethod::Password->value,
            'locale' => 'is',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->kennitala_hash === null) {
                $user->kennitala = $this->generateKennitala();
            }
        });
    }

    /**
     * A staff member with a work account (Entra, no password).
     */
    public function entra(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => $this->natturaEmail($attributes['display_name']),
            'auth_method' => AuthMethod::Entra->value,
            'password' => null,
        ]);
    }

    /**
     * A staff member onboarded by invitation (personal email, password login).
     */
    public function passwordAuth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => $this->personalEmail($attributes['display_name']),
            'auth_method' => AuthMethod::Password->value,
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_manager' => true,
        ]);
    }

    /**
     * A staff member with no contact details at all — the hardest onboarding case.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => null,
            'phone' => null,
            'auth_method' => AuthMethod::Password->value,
            'password' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function natturaEmail(string $displayName): string
    {
        $slug = (string) Str::of($displayName)->ascii()->lower()->replaceMatches('/[^a-z ]/', '')->squish()->replace(' ', '.');

        // Disambiguate repeated names the way a real intranet does: the first
        // jon.jonsson is bare, the next is jon.jonsson2, and so on.
        $n = self::$natturaEmailCounts[$slug] = (self::$natturaEmailCounts[$slug] ?? 0) + 1;

        return ($n === 1 ? $slug : $slug.$n).'@nattura.is';
    }

    private function personalEmail(string $displayName): string
    {
        $given = (string) Str::of($displayName)->ascii()->lower()->before(' ')->replaceMatches('/[^a-z]/', '');
        $domain = fake()->randomElement(['gmail.com', 'hotmail.com', 'outlook.com', 'live.com']);

        return $given.fake()->unique()->numberBetween(1, 999999).'@'.$domain;
    }

    /**
     * Generate a format- and checksum-valid Icelandic kennitala (DDMMYY-NNCY).
     */
    private function generateKennitala(): string
    {
        while (true) {
            $year = fake()->numberBetween(1955, 2004);
            $date = sprintf('%02d%02d%02d', fake()->numberBetween(1, 28), fake()->numberBetween(1, 12), $year % 100);
            $birthNumber = (string) fake()->numberBetween(20, 99);

            $first8 = $date.$birthNumber;
            $weights = [3, 2, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < 8; $i++) {
                $sum += (int) $first8[$i] * $weights[$i];
            }

            $check = 11 - ($sum % 11);
            if ($check === 11) {
                $check = 0;
            }
            if ($check === 10) {
                continue; // Not a valid kennitala — try again.
            }

            $century = $year >= 2000 ? '0' : '9';

            return $first8.$check.$century;
        }
    }
}
