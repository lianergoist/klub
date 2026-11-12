<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\EquipmentMoveRequest;
use App\Notifications\ClubMessage;
use Illuminate\Support\Facades\Notification;

/**
 * Påmindelser, der køres hvert kvarter (routes/console.php). Hver påmindelse sendes kun én gang.
 *  - Anmodning om at flytte en booking: påmindelse til den, der har booket, 3 timer før fristen,
 *    og besked til træneren, når fristen er overskredet uden svar.
 *  - Aktivitet, der afventer: besked til træneren dagen før start og til bestyrelsen 3 timer før start.
 */
class Reminders
{
    public const BOOKER_HOURS = 3;   // påmindelse så mange timer før svarfristen
    public const TRAINER_HOURS = 24; // "afventer stadig" så mange timer før start
    public const BOARD_HOURS = 3;    // besked til bestyrelsen så mange timer før start

    public static function run(): array
    {
        return ['moves' => self::moves(), 'pending' => self::pending()];
    }

    public static function moves(): int
    {
        $n = 0;
        $open = fn () => EquipmentMoveRequest::with('booking.user', 'booking.items', 'activity.trainers', 'activity.creator', 'requester')
            ->where('status', 'pending')->whereHas('booking', fn ($q) => $q->where('status', 'confirmed'))
            ->whereHas('activity', fn ($q) => $q->whereNull('cancelled_at')->where('ends_at', '>', now()));

        // Påmindelse til den, der har booket (kun hvis der var mere end 3 timer til fristen, da anmodningen blev sendt)
        foreach ($open()->whereNull('reminded_at')->where('deadline_at', '>', now())->where('deadline_at', '<=', now()->addHours(self::BOOKER_HOURS))->get() as $m) {
            $m->update(['reminded_at' => now()]);
            if ($m->created_at->diffInHours($m->deadline_at) <= self::BOOKER_HOURS) continue;
            $m->booking->user->notify(new ClubMessage('Påmindelse: kan du flytte din booking?',
                ($m->requester?->name ?? 'Træneren').' venter stadig på svar om din booking '.Equipment::when($m->booking).' ("'.$m->activity->title.'"). '
                .'Flyt eller annullér bookingen – eller svar nej – senest '.Equipment::fmt($m->deadline_at).'. Ellers kan bookingen blive annulleret.',
                '/udstyr?vis=bookinger'));
            $n++;
        }

        // Fristen er overskredet: fortæl træneren/den, der bad om det, at bookingen kan annulleres
        foreach ($open()->whereNull('overdue_notified_at')->where('deadline_at', '<=', now())->get() as $m) {
            $m->update(['overdue_notified_at' => now()]);
            $to = $m->activity->contacts()->push($m->requester)->filter()->unique('id')
                ->filter(fn ($u) => $u->may('equipment.cancel') || ($u->may('equipment.cancel_overdue')
                    && ($u->id === $m->requested_by || \App\Http\Controllers\Api\EquipmentController::mayDecide($u, $m->activity))));
            if ($to->isEmpty()) {
                // Ingen af dem må annullere: send til bestyrelsen
                $to = \App\Models\User::whereIn('id', Permissions::userIds('equipment.cancel'))->get();
            }
            Notification::send($to, new ClubMessage('Intet svar om booking: '.$m->activity->title,
                $m->booking->user->name.' har ikke svaret på anmodningen om at flytte sin booking '.Equipment::when($m->booking)
                .' inden fristen. Bookingen kan nu annulleres på aktivitetens side.', '/aktiviteter/'.$m->activity_id));
            $n++;
        }

        return $n;
    }

    public static function pending(): int
    {
        $n = 0;
        $q = fn () => Activity::with('trainers', 'creator')->where('status', 'pending')->whereNull('cancelled_at')->where('starts_at', '>', now());

        foreach ($q()->whereNull('pending_reminded_at')->where('starts_at', '<=', now()->addHours(self::TRAINER_HOURS))->get() as $a) {
            $a->update(['pending_reminded_at' => now()]);
            if ($a->created_at->diffInHours($a->starts_at) <= self::TRAINER_HOURS) continue; // oprettet med kort varsel: de ved det godt
            Notification::send($a->contacts(), new ClubMessage('Afventer stadig: '.$a->title,
                'Aktiviteten '.Equipment::fmt($a->starts_at).' afventer stadig og er ikke åben for tilmelding. '
                .'Opret den på aktivitetens side, når udstyret er afklaret – eller aflys den.', '/aktiviteter/'.$a->id));
            $n++;
        }

        foreach ($q()->whereNull('pending_board_notified_at')->where('starts_at', '<=', now()->addHours(self::BOARD_HOURS))->get() as $a) {
            $a->update(['pending_board_notified_at' => now()]);
            $board = \App\Models\RoleAssignment::where('role', 'board')->where('starts_on', '<=', now()->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))->pluck('user_id');
            $to = \App\Models\User::whereIn('id', $board)->get()->merge($a->contacts())->unique('id');
            Notification::send($to, new ClubMessage('Aktivitet afventer stadig: '.$a->title,
                'Aktiviteten starter '.Equipment::fmt($a->starts_at).', men afventer stadig, fordi udstyret er booket af andre. '
                .'Medlemmerne kan ikke tilmelde sig. Opret eller aflys den.', '/aktiviteter/'.$a->id));
            $n++;
        }

        return $n;
    }
}
