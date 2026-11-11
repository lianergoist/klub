<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    public const ROLE_LABELS = [
        'member' => 'Medlem',
        'trainer' => 'Træner',
        'board' => 'Bestyrelse',
        'treasurer' => 'Kasserer',
        'auditor' => 'Revisor',
        'admin' => 'Administrator',
    ];

    public const STATUS_LABELS = [
        'pending' => 'Afventer betaling',
        'active' => 'Aktiv',
        'passive' => 'Passiv',
        'resigned' => 'Udmeldt',
        'guest' => 'Gæst (ikke medlem)',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'joined_on' => 'date',
            'resigned_on' => 'date',
            'consent_at' => 'datetime',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'allow_messages' => 'boolean',
        ];
    }

    public function household() { return $this->belongsTo(Household::class); }
    public function membershipType() { return $this->belongsTo(MembershipType::class); }
    public function roleAssignments() { return $this->hasMany(RoleAssignment::class); }
    public function registrations() { return $this->hasMany(Registration::class); }
    public function unavailabilities() { return $this->hasMany(Unavailability::class); }
    public function categorySettings() { return $this->belongsToMany(Category::class)->withPivot('show', 'notify'); }

    /** Aktive roller i dag (medlem er altid med). */
    public function roles(): array
    {
        $today = now()->toDateString();
        $roles = $this->roleAssignments
            ->filter(fn ($r) => $r->starts_on->toDateString() <= $today && (! $r->ends_on || $r->ends_on->toDateString() >= $today))
            ->pluck('role')->unique()->values()->all();

        return array_values(array_unique(array_merge(['member'], $roles)));
    }

    public function hasRole(string ...$roles): bool
    {
        return (bool) array_intersect($roles, $this->roles());
    }

    public function isTrainer(): bool { return $this->hasRole('trainer', 'board', 'admin'); }
    public function isBoard(): bool { return $this->hasRole('board', 'admin'); }
    public function canReadFinance(): bool { return $this->hasRole('treasurer', 'auditor', 'board'); }
    public function canWriteFinance(): bool { return $this->hasRole('treasurer'); }
    public function canEditPrices(): bool { return $this->hasRole('board', 'treasurer', 'admin'); }
    public function canSeeMembers(): bool { return $this->hasRole('board', 'treasurer', 'admin'); }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    /** Medlemmer denne bruger må handle på vegne af (sig selv + husstand, hvis betaler). */
    public function managedMembers()
    {
        if ($this->household_id && $this->household?->payer_id === $this->id) {
            return User::where('household_id', $this->household_id)->orderByRaw('id = ? desc', [$this->id])->orderBy('birthdate')->get();
        }

        return collect([$this]);
    }

    public function payer(): User
    {
        if ($this->household_id && $this->household?->payer_id && $this->household->payer_id !== $this->id) {
            return $this->household->payer;
        }

        return $this;
    }

    /** Betalt kontingent for træningssæsonen (1. maj – 30. april), fx "2026/27". */
    public function purchases() { return $this->belongsToMany(Purchase::class); }

    /**
     * Gyldigt medlemskab = med i et betalt medlemskab, der dækker dags dato.
     * $access: kun medlemskaber, der giver adgang til aktiviteter (ikke støttemedlem).
     */
    public function hasValidMembership(bool $access = false): bool
    {
        return $this->currentMembership($access) !== null;
    }

    /** Betalt medlemskab, der først begynder senere (fx købt efter sæsonafslutning til næste sæson). */
    public function upcomingMembership(): ?Purchase
    {
        return $this->purchases()->with('package')->whereHas('package', fn ($q) => $q->where('kind', 'membership'))
            ->whereNull('cancelled_at')->whereRaw('COALESCE(access_from, valid_from) > ?', [now()->toDateString()])
            ->where(fn ($q) => $q->whereNull('invoice_id')->orWhereHas('invoice', fn ($q) => $q->where('status', 'paid')))
            ->orderBy('valid_from')->first();
    }

    public function currentMembership(bool $access = false): ?Purchase
    {
        return $this->purchases()->activeOn()->with('package')
            ->whereHas('package', fn ($q) => $q->where('kind', 'membership')->when($access, fn ($q) => $q->where('gives_access', true)))
            ->orderByDesc('valid_to')->first();
    }

    public function isUnavailableOn($date): bool
    {
        $d = \Illuminate\Support\Carbon::parse($date)->toDateString();

        return $this->unavailabilities()->where('from_date', '<=', $d)->where('to_date', '>=', $d)->exists();
    }

    /** Id'er på aktive trænere, bestyrelse og administratorer. */
    public static function staffIds()
    {
        return RoleAssignment::whereIn('role', ['trainer', 'board', 'admin'])->where('starts_on', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()))->select('user_id');
    }

    /**
     * Medlemmer denne bruger har blokeret. Trænere og bestyrelse kan ikke blokeres,
     * så en ældre blokering af en person, der siden er blevet træner, ignoreres.
     */
    public function blockedIds(): array
    {
        return \Illuminate\Support\Facades\DB::table('user_blocks')->where('user_id', $this->id)
            ->whereNotIn('blocked_id', self::staffIds())->pluck('blocked_id')->all();
    }

    /**
     * Hvem kan bruge beskeder? Kun voksne (18+) med eget login og aktivt medlemskab (testudgave – se Afklares i funktionsoversigten).
     */
    public function canUseMessages(): bool
    {
        return $this->email && $this->password && in_array($this->status, ['active', 'passive'])
            && ($this->age() === null || $this->age() >= 18);
    }

    /** Trænere, bestyrelse og administrator kan skrive til alle (også dem, der har fravalgt beskeder). */
    public function isStaff(): bool
    {
        return $this->hasRole('trainer', 'board', 'admin');
    }

    /**
     * Begrundelse hvis $this ikke kan skrive til $to, ellers null.
     *  - blokering (begge veje) stopper altid beskeder
     *  - har man selv fravalgt beskeder, kan man kun skrive med trænere/bestyrelse
     *  - har modtageren fravalgt beskeder, kan kun trænere/bestyrelse skrive
     */
    public function cannotMessage(User $to): ?string
    {
        if ($to->id === $this->id) return 'Du kan ikke skrive til dig selv.';
        if (! $this->canUseMessages()) return 'Beskeder er kun for voksne medlemmer med eget login.';
        if (! $to->canUseMessages()) return $to->name.' kan ikke modtage beskeder.';
        $first = explode(' ', $to->name)[0];
        if (in_array($to->id, $this->blockedIds())) return 'Du har blokeret '.$to->name.'. Fjern blokeringen for at skrive.';
        if (in_array($this->id, $to->blockedIds())) return 'Du kan ikke skrive til '.$to->name.'.'; // (staff er aldrig i blockedIds)
        if (! $this->allow_messages && ! $this->isStaff() && ! $to->isStaff()) {
            return 'Du har slået beskeder fra, så du kan kun skrive med trænere og bestyrelse. Slå beskeder til under Profil → Beskeder.';
        }
        if (! $to->allow_messages && ! $this->isStaff()) return $first.' har slået beskeder fra andre medlemmer fra.';

        return null;
    }

    public function unreadMessages(): int
    {
        return Message::join('conversation_user as cu', function ($j) {
            $j->on('cu.conversation_id', '=', 'messages.conversation_id')->where('cu.user_id', $this->id)->whereNull('cu.left_at');
        })->whereColumn('messages.id', '>', 'cu.last_read_message_id')
            ->where(fn ($q) => $q->where('messages.sender_id', '!=', $this->id)->orWhereNull('messages.sender_id'))
            ->where('messages.system', false)->count();
    }

    /** Medlemmer som $this kan skrive til (til listen "Ny besked"). */
    public function messageableQuery()
    {
        $cut = now()->subYears(18)->toDateString();
        $staffIds = self::staffIds();

        $q = User::where('id', '!=', $this->id)->whereNotNull('email')->whereNotNull('password')
            ->whereIn('status', ['active', 'passive'])
            ->where(fn ($q) => $q->whereNull('birthdate')->orWhere('birthdate', '<=', $cut))
            ->whereNotIn('id', $this->blockedIds())
            ->when(! $this->isStaff(), fn ($q) => $q->whereNotIn('id', \Illuminate\Support\Facades\DB::table('user_blocks')->where('blocked_id', $this->id)->select('user_id')));

        if ($this->isStaff()) return $q;                                  // trænere/bestyrelse: alle
        if (! $this->allow_messages) return $q->whereIn('id', $staffIds);   // selv fravalgt: kun trænere/bestyrelse

        return $q->where(fn ($q) => $q->where('allow_messages', true)->orWhereIn('id', $staffIds));
    }

    public function brief(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
