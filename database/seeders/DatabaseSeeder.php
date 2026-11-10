<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\MembershipType;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ClubMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Realistiske demodata til bestyrelsens afprøvning.
 * Alle demo-brugere har adgangskoden "demo1234".
 */
class DatabaseSeeder extends Seeder
{
    private array $acc = [];
    private $pw;
    private $now;

    public function run(): void
    {
        mt_srand(42);
        $this->now = now();
        $this->pw = Hash::make('demo1234');
        $year = $this->now->year;

        // Kontoplan
        foreach ([
            ['1000', 'Kontingent', 'income'], ['1100', 'Aktivitetsindtægter', 'income'], ['1200', 'Udstyrsleje', 'income'],
            ['1300', 'Opbevaring', 'income'], ['1400', 'Tilskud', 'income'], ['1500', 'Sponsorer', 'income'],
            ['2000', 'Indkøb af udstyr', 'expense'], ['2100', 'Vedligehold', 'expense'], ['2200', 'Forsikring', 'expense'],
            ['2300', 'Kurser og uddannelse', 'expense'], ['2400', 'Trænerudgifter', 'expense'], ['2500', 'Administration', 'expense'],
            ['2600', 'Gebyrer', 'expense'], ['2700', 'Arrangementer', 'expense'], ['9000', 'Egenkapital primo', 'equity'],
        ] as [$n, $name, $type]) {
            $this->acc[$n] = Account::create(['number' => $n, 'name' => $name, 'type' => $type])->id;
        }

        $types = collect([
            ['adult', 'Voksen', 120000, null], ['youth', 'Barn/ung (under 18)', 60000, 17],
            ['family', 'Familie', 220000, null], ['trainer', 'Træner', 30000, null], ['passive', 'Passiv/støtte', 25000, null],
        ])->mapWithKeys(fn ($t) => [$t[0] => MembershipType::create(['code' => $t[0], 'name' => $t[1], 'annual_fee' => $t[2], 'max_age' => $t[3]])]);

        $cats = collect([
            ['Windsurf', '#0e7490'], ['Kite', '#b45309'], ['SUP', '#15803d'], ['Wingfoil', '#7c3aed'], ['Klubliv og teori', '#be185d'],
        ])->mapWithKeys(fn ($c) => [$c[0] => Category::create(['name' => $c[0], 'color' => $c[1]])]);

        // --- Demo-brugere ---
        $mk = function (array $a, string $type, ?string $hh = null, ?User $payer = null) use ($types) {
            $u = User::create($a + [
                'password' => isset($a['email']) ? $this->pw : null, 'status' => $a['status'] ?? 'active',
                'joined_on' => $a['joined_on'] ?? $this->now->copy()->subYears(mt_rand(1, 8))->subDays(mt_rand(0, 300)),
                'membership_type_id' => $types[$type]->id, 'consent_at' => $this->now->copy()->subYear(),
                'city' => $a['city'] ?? 'Aarhus', 'postal_code' => $a['postal_code'] ?? '8000',
            ]);
            if ($payer) {
                $u->update(['household_id' => $payer->household_id]);
            } else {
                $h = Household::create(['name' => $hh ?? 'Husstand '.$u->name, 'payer_id' => $u->id]);
                $u->update(['household_id' => $h->id]);
            }

            return $u;
        };

        $sofie = $mk(['name' => 'Sofie Mikkelsen', 'email' => 'medlem@demo.dk', 'phone' => '20 45 67 89', 'address' => 'Havnevej 12', 'birthdate' => '1984-05-14', 'emergency_name' => 'Peter Mikkelsen', 'emergency_phone' => '30 12 34 56'], 'family', 'Familien Mikkelsen');
        $emil = $mk(['name' => 'Emil Mikkelsen', 'birthdate' => $this->now->copy()->subYears(11)->subMonths(3)->toDateString(), 'address' => 'Havnevej 12', 'emergency_name' => 'Sofie Mikkelsen', 'emergency_phone' => '20 45 67 89', 'notify_email' => false], 'family', null, $sofie);
        $freja = $mk(['name' => 'Freja Mikkelsen', 'birthdate' => $this->now->copy()->subYears(14)->subMonths(7)->toDateString(), 'address' => 'Havnevej 12', 'emergency_name' => 'Sofie Mikkelsen', 'emergency_phone' => '20 45 67 89', 'notify_email' => false], 'family', null, $sofie);

        $lars = $mk(['name' => 'Lars Holm', 'email' => 'traener@demo.dk', 'phone' => '22 33 44 55', 'address' => 'Strandvejen 88', 'birthdate' => '1979-09-02'], 'trainer');
        $mette = $mk(['name' => 'Mette Kjær', 'email' => 'formand@demo.dk', 'phone' => '40 11 22 33', 'address' => 'Kystvej 3', 'birthdate' => '1975-02-21'], 'adult');
        $henrik = $mk(['name' => 'Henrik Dahl', 'email' => 'kasserer@demo.dk', 'phone' => '26 77 88 99', 'address' => 'Mølleparken 7', 'birthdate' => '1968-11-30'], 'adult');
        $birgitte = $mk(['name' => 'Birgitte Lund', 'email' => 'revisor@demo.dk', 'phone' => '51 23 45 67', 'address' => 'Skovgade 19', 'birthdate' => '1962-07-08'], 'passive');
        $rasmus = $mk(['name' => 'Rasmus Krogh', 'email' => 'enkelt@demo.dk', 'phone' => '31 42 53 64', 'address' => 'Østergade 14', 'birthdate' => '1990-10-03', 'joined_on' => Carbon::create($this->now->year, 9, 2)], 'adult');
        $anders = $mk(['name' => 'Anders Vestergaard', 'email' => 'admin@demo.dk', 'phone' => '28 90 12 34', 'address' => 'Bakkevej 41', 'birthdate' => '1988-03-17'], 'adult');

        $trainers = collect([$lars]);
        foreach ([['Jonas Bech', '1991-06-11'], ['Camilla Friis', '1993-12-04'], ['Kasper Nygaard', '1986-08-25'], ['Ida Thomsen', '1997-04-29']] as $i => [$n, $b]) {
            $trainers->push($mk(['name' => $n, 'email' => strtolower(explode(' ', $n)[0]).'@demo.dk', 'phone' => '6'.mt_rand(1000000, 9999999), 'address' => 'Sejlervej '.($i + 2), 'birthdate' => $b], 'trainer'));
        }
        $trainers->push($mette);

        $start = $this->now->copy()->subYears(3)->startOfYear();
        $role = fn ($u, $r, $from, $to = null) => $u->roleAssignments()->create(['role' => $r, 'starts_on' => $from, 'ends_on' => $to, 'granted_by' => $anders->id]);
        foreach ($trainers as $t) $role($t, 'trainer', $start->copy()->addDays(mt_rand(0, 400)));
        $role($mette, 'board', $start); $role($henrik, 'board', $start); $role($henrik, 'treasurer', $start);
        $role($anders, 'admin', $start); $role($anders, 'board', $start->copy()->addYear());
        $role($birgitte, 'auditor', $start);
        $role($sofie, 'board', $start, $this->now->copy()->subYear()->startOfYear()->addMonths(2)); // tidligere bestyrelsesmedlem

        // --- Øvrige medlemmer ---
        $first = ['Mads', 'Line', 'Rasmus', 'Julie', 'Mikkel', 'Anne', 'Nikolaj', 'Signe', 'Frederik', 'Maja', 'Christian', 'Louise', 'Søren', 'Katrine', 'Martin', 'Pernille', 'Jakob', 'Rikke', 'Simon', 'Helle', 'Thomas', 'Nanna', 'Peter', 'Cecilie'];
        $last = ['Jensen', 'Nielsen', 'Hansen', 'Pedersen', 'Andersen', 'Christensen', 'Larsen', 'Sørensen', 'Rasmussen', 'Jørgensen', 'Madsen', 'Kristensen', 'Olsen', 'Poulsen', 'Knudsen'];
        $kidNames = ['Oliver', 'Alma', 'William', 'Ella', 'Noah', 'Clara', 'Lucas', 'Agnes', 'Malthe', 'Karla'];
        $members = collect([$sofie, $emil, $freja, $lars, $mette, $henrik, $anders, $rasmus])->merge($trainers)->unique('id');
        $streets = ['Havnegade', 'Bøgevej', 'Lærkevej', 'Engvej', 'Fjordvej', 'Klitvej', 'Rønnebærvej'];
        for ($i = 0; $i < 26; $i++) {
            $n = $first[$i % count($first)].' '.$last[mt_rand(0, count($last) - 1)];
            $family = $i % 6 === 0;
            $status = match (true) { $i === 3 => 'pending', $i === 17 => 'resigned', $i % 9 === 4 => 'passive', default => 'active' };
            $u = $mk([
                'name' => $n, 'email' => 'medlem'.($i + 1).'@demo.dk', 'phone' => mt_rand(20, 61).' '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99),
                'address' => $streets[$i % 7].' '.mt_rand(1, 80), 'birthdate' => $this->now->copy()->subYears(mt_rand(19, 64))->subDays(mt_rand(0, 360))->toDateString(),
                'status' => $status, 'joined_on' => $status === 'pending' ? null : ($i % 5 === 1 ? Carbon::create($this->now->year, mt_rand(4, 7), mt_rand(1, 28)) : null),
                'resigned_on' => $status === 'resigned' ? $this->now->copy()->subMonths(4) : null,
            ], $status === 'passive' ? 'passive' : ($family ? 'family' : 'adult'));
            if ($status === 'pending') $u->update(['joined_on' => null]);
            $members->push($u);
            if ($family) {
                foreach (range(1, mt_rand(1, 2)) as $k) {
                    $members->push($mk(['name' => $kidNames[($i + $k) % 10].' '.explode(' ', $n)[1], 'birthdate' => $this->now->copy()->subYears(mt_rand(8, 16))->subDays(mt_rand(0, 300))->toDateString(), 'address' => $u->address, 'notify_email' => false, 'emergency_name' => $n, 'emergency_phone' => $u->phone], 'family', null, $u));
                }
            }
        }
        foreach (range(1, 3) as $k) { // unge medlemmer med eget kontingent
            $p = $members->where('status', 'active')->whereNotNull('email')->values()[$k + 10];
            $members->push($mk(['name' => $kidNames[$k + 5].' '.explode(' ', $p->name)[1], 'birthdate' => $this->now->copy()->subYears(15 + $k)->toDateString(), 'notify_email' => false, 'emergency_name' => $p->name, 'emergency_phone' => $p->phone], 'youth', null, $p));
        }
        $birgitte->refresh();
        $members->push($birgitte);

        // --- Kontingent indeværende år (de fleste betalt i januar–marts) ---
        $unpaidPayers = [];
        foreach ($members->unique('id') as $m) {
            $m->refresh();
            if (! in_array($m->status, ['active', 'passive']) || ! $m->membership_type_id) continue;
            $type = $m->membershipType;
            $payer = $m->payer();
            if ($type->code === 'family' && $payer->id !== $m->id) continue;
            $paidDate = Carbon::create($year, mt_rand(1, 3), mt_rand(1, 28));
            if ($m->joined_on && $m->joined_on->year === $year) $paidDate = $m->joined_on->copy()->addDays(3);
            $unpaid = count($unpaidPayers) < 4 && $m->id % 7 === 3 && ! in_array($m->email, array_keys(config('app.demo_accounts')));
            if ($unpaid) $unpaidPayers[] = $payer->id;
            $this->invoice($payer, $type->code === 'family' ? null : $m, 'membership', "Kontingent $year – ".$type->name.($type->code === 'family' ? ' ('.$m->household->name.')' : ' – '.$m->name), $type->annual_fee, '1000', $paidDate, $unpaid ? null : $paidDate, (string) $year, method: mt_rand(0, 9) ? 'mobilepay' : 'bank');
        }

        // Indmeldt men ikke betalt
        foreach ($members->where('status', 'pending') as $p) {
            \App\Support\Memberships::signupInvoice($p->fresh());
        }

        // --- Fravær ---
        $trainers[1]->unavailabilities()->create(['from_date' => $this->now->copy()->addDays(3), 'to_date' => $this->now->copy()->addDays(12), 'reason' => 'Ferie']);
        $trainers[2]->unavailabilities()->create(['from_date' => $this->now->copy()->addDays(15), 'to_date' => $this->now->copy()->addDays(18), 'reason' => 'Arbejde']);
        $lars->unavailabilities()->create(['from_date' => $this->now->copy()->addDays(20), 'to_date' => $this->now->copy()->addDays(24), 'reason' => 'Konkurrence i Hvide Sande']);
        $trainers[3]->unavailabilities()->create(['from_date' => $this->now->copy()->subDays(2), 'to_date' => $this->now->copy()->addDays(30), 'reason' => 'Skade (knæ)']);

        // --- Aktiviteter: afholdte (sommersæson) ---
        $past = [
            ['Begynderkursus windsurf, dag 1', ['Windsurf'], 'Strandpladsen', 12, 45000],
            ['Begynderkursus windsurf, dag 2', ['Windsurf'], 'Strandpladsen', 12, 0],
            ['Onsdagstræning windsurf', ['Windsurf'], 'Klubhuset', 20, 0],
            ['SUP-tur langs kysten', ['SUP'], 'Slipwayen', 15, 0],
            ['Wingfoil intro', ['Wingfoil'], 'Strandpladsen', 8, 35000],
            ['Kite-sikkerhed og selvredning', ['Kite', 'Klubliv og teori'], 'Klubhuset', 16, 0],
            ['Juniortræning', ['Windsurf', 'SUP'], 'Lagunen', 14, 0],
            ['Sankthans-SUP og bål', ['SUP', 'Klubliv og teori'], 'Klubhuset', 40, 0],
            ['Freestyle-workshop', ['Windsurf'], 'Strandpladsen', 10, 20000],
            ['Wingfoil træning', ['Wingfoil'], 'Strandpladsen', 10, 0],
            ['Klubmesterskab', ['Windsurf', 'Wingfoil', 'Kite'], 'Bugten', 50, 10000],
            ['Arbejdsdag: klargøring af udstyr', ['Klubliv og teori'], 'Klubhuset', null, 0],
        ];
        $allActs = collect();
        $d = Carbon::create($year, 5, 20, 18, 0);
        $fam = [$sofie->id, $emil->id, $freja->id];
        $adults = $members->filter(fn ($m) => $m->status === 'active' && ! in_array($m->id, $fam))->unique('id')->values();
        for ($i = 0; $i < 28; $i++) {
            $tpl = $past[$i % count($past)];
            $recurring = ['Onsdagstræning windsurf', 'Juniortræning', 'Wingfoil træning', 'SUP-tur langs kysten'];
            if ($i >= count($past) && ! in_array($tpl[0], $recurring)) {
                $tpl = collect($past)->firstWhere(0, $recurring[$i % 4]);
            }
            $d = $d->copy()->addDays(mt_rand(3, 6))->setTime([10, 17, 18][mt_rand(0, 2)], 0);
            if ($d->gte($this->now->copy()->subWeeks(3))) break;
            $a = $this->activity($tpl, $d, $cats, $trainers->random(mt_rand(1, 2))->pluck('id')->all(), $mette);
            if ($i % 5 === 2) $this->trainerSwap($a, $trainers, $lars);
            $n = min($tpl[3] ?? 25, mt_rand(5, 16));
            $pick = $adults->random(min($n, $adults->count()));
            if ($tpl[0] === 'Juniortræning') $pick = $pick->push($emil)->push($freja);
            if (in_array($tpl[0], ['SUP-tur langs kysten', 'Sankthans-SUP og bål', 'Klubmesterskab', 'Onsdagstræning windsurf']) || ($tpl[0] === 'Wingfoil intro' && $i < 12)) $pick = $pick->push($sofie);
            if ($tpl[0] === 'Begynderkursus windsurf, dag 1' && $i === 0) $pick = $pick->push($emil);
            $pick = $pick->unique('id');
            foreach ($pick as $m) {
                $at = $d->copy()->subDays(mt_rand(2, 10));
                $reg = Registration::create(['activity_id' => $a->id, 'user_id' => $m->id, 'status' => 'signed_up', 'attended' => mt_rand(0, 9) > 0, 'created_at' => $at, 'updated_at' => $at]);
                $this->ev($reg, 'signup', $m->payer(), $at);
                if ($tpl[4]) {
                    $inv = $this->invoice($m->payer(), $m, 'activity', $tpl[0].' – '.$m->name, $tpl[4], '1100', $at, $at, null, $a->id);
                    $reg->update(['invoice_id' => $inv->id]);
                    $this->ev($reg, 'paid', $m->payer(), $at->copy()->addMinutes(2));
                }
                $this->ev($reg, $reg->attended ? 'attended' : 'absent', $a->trainers->first(), $a->ends_at->copy()->addHours(1));
            }
            $allActs->push($a);
        }
        // Afmeldt og tilmeldt igen, for at vise historikken
        $demoReg = Registration::where('user_id', $sofie->id)->first();
        if ($demoReg) {
            $t = $demoReg->created_at->copy()->addDay();
            $this->ev($demoReg, 'cancel', $sofie, $t);
            $this->ev($demoReg, 'resignup', $sofie, $t->copy()->addHours(5));
        }

        // --- Kommende aktiviteter ---
        $upcoming = [
            [3, 18, 'Vinter-SUP i tørdragt', ['SUP'], 'Slipwayen', 10, 0, 'Kun for erfarne. Tørdragt er påkrævet – klubben har 4 til udlån.'],
            [5, 19, 'Teoriaften: vejr, vind og strøm', ['Klubliv og teori'], 'Klubhuset', 30, 0, 'Vi gennemgår vindudsigter, DMI og lokale strømforhold. Kaffe og kage.'],
            [7, 10, 'Wingfoil for let øvede', ['Wingfoil'], 'Strandpladsen', 8, 0, "Afgøres dagen før ud fra vinden.\nMedbring egen wing, hvis du har én."],
            [10, 19, 'Udstyrsværksted: reparation af sejl og boards', ['Windsurf', 'Klubliv og teori'], 'Klubhuset', 15, 0, 'Vi lapper sejl og ser boards efter inden vinteropbevaring.'],
            [12, 17, 'Juniortræning – indendørs balance', ['Windsurf', 'SUP'], 'Hallen', 16, 0, 'Balancetræning på boards og gymnastikbolde. For 8–17 år.'],
            [17, 19, 'Førstehjælp ved vandet', ['Klubliv og teori'], 'Klubhuset', 12, 25000, 'Certificeret kursus med Dansk Førstehjælpsråd. 4 timer.'],
            [21, 11, 'Vinterbad og SUP-hygge', ['SUP', 'Klubliv og teori'], 'Klubhuset', 40, 0, 'Saunaen er varm fra kl. 10.'],
            [26, 18, 'Julefrokost', ['Klubliv og teori'], 'Klubhuset', 60, 15000, 'Klubbens årlige julefrokost. Prisen dækker mad, drikkevarer for egen regning.'],
            [47, 10, 'Nytårs-SUP', ['SUP'], 'Slipwayen', 25, 0, 'Traditionen tro.'],
            [96, 10, 'Instruktørkursus, modul 1', ['Windsurf', 'Klubliv og teori'], 'Klubhuset', 10, 0, 'For kommende trænere.'],
            [140, 18, 'Sæsonstart: standerhejsning', ['Windsurf', 'Kite', 'SUP', 'Wingfoil', 'Klubliv og teori'], 'Klubhuset', null, 0, 'Vi hejser standeren og byder nye medlemmer velkommen.'],
            [155, 10, 'Begynderkursus windsurf (forår)', ['Windsurf'], 'Strandpladsen', 12, 45000, 'Tre lørdage. Udstyr og våddragt er inkluderet.'],
        ];
        foreach ($upcoming as $i => [$days, $h, $title, $c, $loc, $max, $price, $desc]) {
            $start = $this->now->copy()->addDays($days)->setTime($h, 0);
            $tids = match ($i) { 0 => [$trainers[2]->id], 1 => [$mette->id], 2 => [$lars->id, $trainers[1]->id], 4 => [$lars->id], 5 => [], 9 => [$lars->id, $mette->id], default => [$trainers->random()->id] };
            $a = $this->activity([$title, $c, $loc, $max, $price], $start, $cats, $tids, $i % 2 ? $mette : $lars, $desc);
            if ($i === 2) $this->trainerSwap($a, $trainers, $mette);
            $n = $max ? mt_rand(1, (int) ($max * 0.7)) : mt_rand(8, 20);
            if ($i === 0) $n = 9; // næsten fuldt hold
            foreach ($adults->random(min($n, $adults->count())) as $m) {
                if ($m->id === $sofie->id) continue;
                $at = $this->now->copy()->subDays(mt_rand(0, 6))->subHours(mt_rand(0, 20));
                $reg = Registration::create(['activity_id' => $a->id, 'user_id' => $m->id, 'status' => 'signed_up', 'created_at' => $at, 'updated_at' => $at]);
                $this->ev($reg, 'signup', $m->payer(), $at);
                if ($price) {
                    $inv = $this->invoice($m->payer(), $m, 'activity', $title.' – '.$m->name, $price, '1100', $at, $at, null, $a->id);
                    $reg->update(['invoice_id' => $inv->id]);
                }
            }
            if ($i === 4) { // Emil tilmeldt juniortræning
                $at = $this->now->copy()->subDays(2);
                $reg = Registration::create(['activity_id' => $a->id, 'user_id' => $emil->id, 'status' => 'signed_up', 'created_at' => $at, 'updated_at' => $at]);
                $this->ev($reg, 'signup', $sofie, $at);
            }
            if ($i === 1) {
                $at = $this->now->copy()->subDays(1);
                $reg = Registration::create(['activity_id' => $a->id, 'user_id' => $sofie->id, 'status' => 'signed_up', 'created_at' => $at, 'updated_at' => $at]);
                $this->ev($reg, 'signup', $sofie, $at);
            }
        }

        // --- Produkter og opbevaring ---
        $rack = Product::create(['name' => 'Opbevaring: plads i boardrack', 'description' => 'Plads til ét board og rig i klubhusets rack. Gælder kalenderåret.', 'price' => 60000, 'billing' => 'year', 'needs_resource' => true]);
        foreach (range(1, 16) as $n) $rack->resources()->create(['label' => 'Rack '.chr(65 + intdiv($n - 1, 8)).(($n - 1) % 8 + 1)]);
        $cont = Product::create(['name' => 'Opbevaring: skab i container', 'description' => 'Aflåst skab til våddragt og småudstyr.', 'price' => 40000, 'billing' => 'year', 'needs_resource' => true]);
        foreach (range(1, 10) as $n) $cont->resources()->create(['label' => 'Skab '.$n]);
        $season = Product::create(['name' => 'Sæsonleje af klubudstyr', 'description' => 'Fri brug af klubbens boards og sejl i hele sæsonen.', 'price' => 80000, 'billing' => 'season']);
        $day = Product::create(['name' => 'Dagsleje af board og sejl', 'description' => 'Til medlemmer uden sæsonleje.', 'price' => 10000, 'guest_price' => 20000, 'billing' => 'once']);
        $key = Product::create(['name' => 'Nøglebrik til klubhuset (depositum)', 'description' => 'Depositum refunderes ved aflevering.', 'price' => 20000, 'billing' => 'once']);

        $payers = $adults->filter(fn ($m) => $m->payer()->id === $m->id)->values();
        foreach ($payers->random(18)->values() as $k => $m) {
            $prod = $k < 11 ? $rack : ($k < 15 ? $cont : $season);
            $res = $prod->needs_resource ? $prod->resources->values()[$k < 11 ? $k : $k - 11] : null;
            $at = Carbon::create($year, mt_rand(3, 5), mt_rand(1, 28));
            $inv = $this->invoice($m, $m, 'product', $prod->name.($res ? ' ('.$res->label.')' : '').' – '.$m->name, $prod->price, $prod->needs_resource ? '1300' : '1200', $at, $k === 7 ? null : $at, (string) $year, productId: $prod->id);
            ProductOrder::create(['user_id' => $m->id, 'product_id' => $prod->id, 'resource_id' => $res?->id, 'invoice_id' => $inv->id, 'valid_from' => $at, 'valid_to' => Carbon::create($year)->endOfYear()]);
        }
        $order = ProductOrder::create(['user_id' => $sofie->id, 'product_id' => $rack->id, 'resource_id' => $rack->resources->last()->id, 'valid_from' => Carbon::create($year, 4, 2), 'valid_to' => Carbon::create($year)->endOfYear()]);
        $order->update(['invoice_id' => $this->invoice($sofie, $sofie, 'product', $rack->name.' ('.$rack->resources->last()->label.') – Sofie Mikkelsen', 60000, '1300', Carbon::create($year, 4, 2), Carbon::create($year, 4, 2), (string) $year, productId: $rack->id)->id]);
        $keyInv = $this->invoice($sofie, $freja, 'product', $key->name.' – Freja Mikkelsen', $key->price, '1200', $this->now->copy()->subDays(3), null, (string) $year, productId: $key->id);
        ProductOrder::create(['user_id' => $freja->id, 'product_id' => $key->id, 'invoice_id' => $keyInv->id, 'valid_from' => $this->now->copy()->subDays(3)]);
        foreach ($payers->random(12) as $m) {
            $at = Carbon::create($year, mt_rand(5, 9), mt_rand(1, 28));
            $this->invoice($m, $m, 'product', $day->name.' – '.$m->name, $day->price, '1200', $at, $at, null, productId: $day->id);
        }

        // --- Manuelle posteringer ---
        Entry::create(['entry_date' => Carbon::create($year, 1, 1), 'text' => 'Primosaldo (overført fra '.($year - 1).')', 'account_id' => $this->acc['9000'], 'amount' => 8650000, 'created_by' => $henrik->id]);
        $manual = [
            ['01-15', 'Ansvars- og ulykkesforsikring', '2200', -842000], ['01-20', 'Domæne og hjemmeside', '2500', -149900],
            ['02-10', 'Kommunalt aktivitetstilskud', '1400', 1500000], ['03-01', 'Sponsorat: Havnens Bådservice', '1500', 1000000],
            ['03-12', 'To nye begyndersejl 4,5 m²', '2000', -1890000], ['03-28', 'Wingfoil-boards (2 stk.)', '2000', -2450000],
            ['04-05', 'Reparation af slæbejolle', '2100', -385000], ['04-22', 'Trænerkursus, 2 deltagere', '2300', -680000],
            ['05-03', 'Standerhejsning: pølser og sodavand', '2700', -124550], ['06-23', 'Sankthans: brænde og forplejning', '2700', -98000],
            ['06-30', 'MobilePay-gebyrer, 1. halvår', '2600', -215400], ['07-15', 'Træneropmuntring: sæsontrøjer', '2400', -312000],
            ['08-20', 'Ny våddragter til juniorer (6 stk.)', '2000', -1170000], ['09-02', 'Reparation af rack i klubhus', '2100', -164500],
            ['09-18', 'Tilskud fra forbund: juniorarbejde', '1400', 500000], ['10-05', 'Klubmesterskab: præmier', '2700', -87500],
            ['10-31', 'MobilePay-gebyrer, 3. kvartal', '2600', -96300],
        ];
        $last = null;
        foreach ($manual as [$md, $text, $acc, $amount]) {
            $date = Carbon::parse("$year-$md");
            if ($date->gt($this->now)) continue;
            $e = Entry::create(['entry_date' => $date, 'text' => $text, 'account_id' => $this->acc[$acc], 'amount' => $amount, 'created_by' => $henrik->id,
                'attachment_path' => 'bilag/demo-bilag.pdf', 'attachment_name' => 'bilag-'.$md.'.pdf', 'created_at' => $date->copy()->addHours(20), 'updated_at' => $date]);
            AuditLog::create(['user_id' => $henrik->id, 'action' => 'entry.created', 'subject_type' => 'Entry', 'subject_id' => $e->id, 'changes' => ['amount' => $amount, 'text' => $text], 'created_at' => $date->copy()->addHours(20)]);
            if ($acc === '2100' && ! $last) $last = $e;
        }
        // Eksempel på rettelse ved modpostering
        $wrong = Entry::create(['entry_date' => Carbon::create($year, 4, 6), 'text' => 'Reparation af slæbejolle', 'account_id' => $this->acc['2100'], 'amount' => -385000, 'created_by' => $henrik->id]);
        $rev = Entry::create(['entry_date' => Carbon::create($year, 4, 8), 'text' => 'Modpostering af #'.$wrong->id.': Indtastet to gange', 'account_id' => $this->acc['2100'], 'amount' => 385000, 'reverses_id' => $wrong->id, 'created_by' => $henrik->id]);
        AuditLog::create(['user_id' => $henrik->id, 'action' => 'entry.reversed', 'subject_type' => 'Entry', 'subject_id' => $wrong->id, 'changes' => ['reversal_id' => $rev->id, 'reason' => 'Indtastet to gange'], 'created_at' => Carbon::create($year, 4, 8, 9)]);

        // --- Budget ---
        foreach (['1000' => 4000000, '1100' => 3000000, '1200' => 900000, '1300' => 800000, '1400' => 1800000, '1500' => 1000000,
            '2000' => 5000000, '2100' => 800000, '2200' => 850000, '2300' => 900000, '2400' => 400000, '2500' => 250000, '2600' => 350000, '2700' => 400000] as $n => $amt) {
            Budget::create(['year' => $year, 'account_id' => $this->acc[$n], 'amount' => $amt]);
        }

        // Demo-bilag
        @mkdir(storage_path('app/private/bilag'), 0775, true);
        file_put_contents(storage_path('app/private/bilag/demo-bilag.pdf'), $this->demoPdf());

        $this->seedFeed($members->unique('id')->values(), compact('sofie', 'freja', 'lars', 'mette', 'rasmus') + ['camilla' => $trainers[2], 'ida' => $trainers[4], 'jonas' => $trainers[1]]);

        // Indbakke til demo-medlemmet
        $sofie->notify(new ClubMessage('Ny aktivitet: Vinter-SUP i tørdragt', 'Om 3 dage kl. 18, Slipwayen.', '/aktiviteter/'.Activity::where('title', 'Vinter-SUP i tørdragt')->value('id')));
        $sofie->notify(new ClubMessage('Tilmeldt: Juniortræning – indendørs balance', 'Emil Mikkelsen er tilmeldt.', '/aktiviteter/'.Activity::where('title', 'like', 'Juniortræning – indendørs%')->value('id')));
    }

    private function seedFeed($members, array $p): void
    {
        $others = $members->filter(fn ($m) => $m->email && $m->status === 'active')->values();
        $img = function (string $name) {
            $path = 'feed/demo/'.$name.'.jpg';
            \Illuminate\Support\Facades\Storage::put($path, file_get_contents(database_path('seeders/demo-images/'.$name.'.jpg')));
            [$w, $h] = getimagesize(database_path('seeders/demo-images/'.$name.'.jpg'));

            return ['path' => $path, 'width' => $w, 'height' => $h];
        };
        $make = function (User $author, string $body, $ago, array $images = [], int $likes = 0, array $comments = []) use ($others, $img) {
            $at = $this->now->copy()->sub($ago);
            $post = \App\Models\Post::create(['user_id' => $author->id, 'body' => $body, 'created_at' => $at, 'updated_at' => $at]);
            foreach ($images as $i => $n) $post->images()->create($img($n) + ['position' => $i]);
            foreach ($others->where('id', '!=', $author->id)->random(min($likes, $others->count() - 1)) as $u) {
                \App\Models\PostLike::create(['post_id' => $post->id, 'user_id' => $u->id, 'created_at' => $at->copy()->addMinutes(mt_rand(5, 600))]);
            }
            foreach ($comments as $k => [$who, $text]) {
                $t = $at->copy()->addMinutes(20 + $k * 47);
                $post->comments()->create(['user_id' => $who->id, 'body' => $text, 'created_at' => $t, 'updated_at' => $t]);
            }

            return $post;
        };
        $x = fn ($i) => $others[$i % $others->count()];

        $make($p['mette'], "Tak til alle der hjalp på arbejdsdagen! Racket i klubhuset er repareret, og sejlene er lagt i vinterhi. 🙌\nNæste gang: udstyrsværksted d. 19. – kom og lær at lappe et sejl.", '3 days', [], 9, [[$x(3), 'Det var en god dag! Tak for kaffen ☕'], [$p['lars'], 'Jeg tager lappegrej med til værkstedet.']]);
        $make($p['camilla'], "Gem datoen! Camp i uge 27 i Ringkøbing 🏄‍♀️ Tilmelding åbner i marts – sig til, hvis I vil hjælpe med at planlægge.", '1 day', ['camp'], 22, [[$p['sofie'], 'Vi er med! Begge børn har allerede spurgt 😄'], [$x(5), 'Bliver der også wingfoil?'], [$p['camilla'], 'Ja, vi har to instruktører på wing i år.']]);
        $make($p['sofie'], "Freja stod op på boardet for første gang i weekenden 🥹 Kæmpe tak til Ida for tålmodigheden!", '2 days', ['strand'], 18, [[$p['ida'], 'Hun var SÅ sej! 💪'], [$p['mette'], 'Fedt! Velkommen til sporten, Freja.']]);
        $make($x(7), "Nogen der har et 5,3 m² sejl til salg? Gerne camber-free, til en let øvet.", '4 days', [], 2, [[$p['lars'], 'Prøv at spørge i værkstedet d. 19. – der plejer at være noget brugt.']]);
        $make($p['rasmus'], "Ny i klubben – hej allesammen! 👋 Glæder mig til teoriaftenen på lørdag.", '5 days', [], 6, [[$p['mette'], 'Velkommen, Rasmus!']]);
        $make($p['lars'], "Sikke en aften på fjorden i går 🌅 Blankt vand inde ved Husodde og 10–12 m/s fra vest. Hvem tager med næste gang?", '6 hours', ['aften'], 14, [[$p['jonas'], 'Ærgerligt jeg missede den!'], [$x(9), 'Jeg er klar 🙋']]);

        // Eksempel til moderering: et anmeldt opslag
        $spam = $make($x(11), "Sælger billige sejl og boards – skriv privat, kun kontant 😉 Se mere på billigsejl-xyz.dk", '10 hours', [], 0);
        foreach ([$x(2), $x(4)] as $u) \App\Models\PostReport::create(['post_id' => $spam->id, 'user_id' => $u->id, 'reason' => 'Ligner reklame/spam']);

        // Og et opslag, en moderator har skjult
        $hidden = $make($x(13), 'Billeder fra juniortræningen i dag!', '8 days', [], 1);
        $hidden->update(['hidden_at' => $this->now->copy()->subDays(7), 'hidden_by' => $p['mette']->id, 'hidden_reason' => 'Billeder af børn kræver samtykke fra forældrene – del dem gerne igen uden genkendelige ansigter.']);
    }

    private function activity(array $tpl, Carbon $start, $cats, array $trainerIds, User $creator, ?string $desc = null): Activity
    {
        [$title, $c, $loc, $max, $price] = $tpl;
        $created = $start->isPast() ? $start->copy()->subDays(14) : $this->now->copy()->subDays(mt_rand(7, 14));
        $a = Activity::create([
            'title' => $title, 'description' => $desc ?? 'Mødested ved '.strtolower($loc).'. Husk våddragt og godt humør.',
            'starts_at' => $start, 'ends_at' => $start->copy()->addHours(str_contains($title, 'kursus') ? 4 : 2),
            'location' => $loc, 'max_participants' => $max, 'signup_deadline' => $start->copy()->subDay()->setTime(20, 0),
            'cancel_hours' => 24, 'price' => $price, 'created_by' => $creator->id, 'created_at' => $created, 'updated_at' => $created,
        ]);
        $a->categories()->sync($cats->only($c)->pluck('id'));
        foreach ($trainerIds as $id) {
            $a->trainerChanges()->create(['action' => 'added', 'trainer_id' => $id, 'changed_by' => $creator->id, 'created_at' => $created]);
        }
        $a->trainers()->sync($trainerIds);

        return $a;
    }

    private function trainerSwap(Activity $a, $trainers, User $by): void
    {
        $old = $a->trainers->first() ?? $a->trainers()->first();
        $new = $trainers->firstWhere(fn ($t) => ! $a->trainers->contains('id', $t->id) && $t->id !== $old?->id);
        if (! $old || ! $new) return;
        $at = $a->created_at->copy()->addDays(3);
        $a->trainerChanges()->create(['action' => 'removed', 'trainer_id' => $old->id, 'changed_by' => $by->id, 'created_at' => $at]);
        $a->trainerChanges()->create(['action' => 'added', 'trainer_id' => $new->id, 'changed_by' => $by->id, 'created_at' => $at]);
        $a->trainers()->detach($old->id);
        $a->trainers()->attach($new->id);
    }

    private function ev(Registration $reg, string $action, ?User $by, Carbon $at): void
    {
        $reg->events()->create(['action' => $action, 'by_user_id' => $by?->id, 'created_at' => $at]);
    }

    private function invoice(User $payer, ?User $member, string $kind, string $desc, int $amount, string $acc, Carbon $created, ?Carbon $paid, ?string $period = null, ?int $activityId = null, ?int $productId = null, string $method = 'mobilepay'): Invoice
    {
        $inv = Invoice::create([
            'payer_id' => $payer->id, 'member_id' => $member?->id, 'kind' => $kind, 'description' => $desc, 'amount' => $amount,
            'status' => $paid ? 'paid' : 'open', 'due_on' => $created->copy()->addDays(14), 'period' => $period,
            'activity_id' => $activityId, 'product_id' => $productId, 'account_id' => $this->acc[$acc], 'paid_at' => $paid,
            'created_at' => $created, 'updated_at' => $paid ?? $created,
        ]);
        if ($paid) {
            $p = $inv->payments()->create(['amount' => $amount, 'method' => $method, 'reference' => strtoupper($method).'-'.$paid->format('ymd').'-'.$inv->id, 'created_at' => $paid, 'updated_at' => $paid]);
            Entry::create(['entry_date' => $paid, 'text' => $desc.($member && $member->id !== $payer->id ? ' (betalt af '.$payer->name.')' : ''), 'account_id' => $this->acc[$acc], 'amount' => $amount, 'activity_id' => $activityId, 'payment_id' => $p->id, 'created_at' => $paid, 'updated_at' => $paid]);
        }

        return $inv;
    }

    private function demoPdf(): string
    {
        $text = 'Demo-bilag (kvittering)';
        $stream = "BT /F1 18 Tf 72 720 Td ($text) Tj ET";
        $objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream", '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $pdf = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $i => $o) { $off[] = strlen($pdf); $pdf .= ($i + 1)." 0 obj\n$o\nendobj\n"; }
        $x = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objs) + 1)."\n0000000000 65535 f \n";
        foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);

        return $pdf."trailer\n<< /Size ".(count($objs) + 1)." /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
    }
}
