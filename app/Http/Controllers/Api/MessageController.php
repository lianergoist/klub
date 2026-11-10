<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Notifications\MessageReceived;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Private beskeder mellem to medlemmer (testudgave).
 * Indholdet er privat: bestyrelsen ser kun en besked, hvis modtageren anmelder den.
 */
class MessageController extends Controller
{
    private function me(Request $r): User
    {
        $u = $r->user();
        abort_unless($u->canUseMessages(), 403, 'Beskeder er kun for voksne medlemmer med eget login.');

        return $u;
    }

    public function index(Request $r)
    {
        $u = $this->me($r);
        $convs = Conversation::for($u)->whereNotNull('last_message_at')->with('userA:id,name', 'userB:id,name', 'latestMessage')
            ->orderByDesc('last_message_at')->limit(100)->get();
        $unread = Message::whereIn('conversation_id', $convs->pluck('id'))->whereNull('read_at')->where('sender_id', '!=', $u->id)
            ->groupBy('conversation_id')->select('conversation_id', DB::raw('count(*) c'))->pluck('c', 'conversation_id');
        $blocked = $u->blockedIds();

        return $convs->map(fn ($c) => [
            'id' => $c->id,
            'with' => $c->other($u)->name,
            'with_id' => $c->otherId($u),
            'last' => mb_strimwidth($c->latestMessage?->body ?? '', 0, 90, '…'),
            'last_mine' => $c->latestMessage?->sender_id === $u->id,
            'at' => $c->last_message_at?->toIso8601String(),
            'unread' => (int) ($unread[$c->id] ?? 0),
            'blocked' => in_array($c->otherId($u), $blocked),
        ]);
    }

    public function unread(Request $r)
    {
        return ['messages' => $r->user()->canUseMessages() ? $r->user()->unreadMessages() : 0, 'notifications' => $r->user()->unreadNotifications()->count()];
    }

    /** Find medlemmer at skrive til (kun navn vises – ingen kontaktoplysninger). */
    public function people(Request $r)
    {
        $u = $this->me($r);
        $q = trim((string) $r->query('q'));
        if (mb_strlen($q) < 2) return [];
        $cut = now()->subYears(18)->toDateString();

        return User::where('id', '!=', $u->id)->where('name', 'like', "%$q%")
            ->whereNotNull('email')->whereNotNull('password')->whereIn('status', ['active', 'passive'])
            ->where('allow_messages', true)->where(fn ($q) => $q->whereNull('birthdate')->orWhere('birthdate', '<=', $cut))
            ->whereNotIn('id', $u->blockedIds())
            ->whereNotIn('id', DB::table('user_blocks')->where('blocked_id', $u->id)->select('user_id'))
            ->orderBy('name')->limit(10)->get(['id', 'name']);
    }

    public function start(Request $r)
    {
        $u = $this->me($r);
        $to = User::findOrFail($r->validate(['user_id' => 'required|integer'])['user_id']);
        if ($why = $u->cannotMessage($to)) abort(422, $why);

        return ['id' => Conversation::between($u, $to)->id];
    }

    public function show(Request $r, Conversation $conversation)
    {
        $u = $this->me($r);
        abort_unless($conversation->involves($u), 404);
        $other = $conversation->other($u);

        $q = $conversation->messages()->orderByDesc('id');
        if ($after = $r->query('after')) $q->where('id', '>', $after);
        if ($before = $r->query('before')) $q->where('id', '<', $before);
        $msgs = $q->limit(50)->get()->reverse()->values();

        $conversation->messages()->where('sender_id', '!=', $u->id)->whereNull('read_at')->update(['read_at' => now()]);

        return [
            'id' => $conversation->id,
            'with' => ['id' => $other->id, 'name' => $other->name],
            'can_send' => $u->cannotMessage($other) === null,
            'cannot_reason' => $u->cannotMessage($other),
            'i_blocked' => in_array($other->id, $u->blockedIds()),
            'more' => ! $after && $msgs->count() === 50,
            'messages' => $msgs->map(fn ($m) => [
                'id' => $m->id, 'body' => $m->body, 'mine' => $m->sender_id === $u->id,
                'at' => $m->created_at->toIso8601String(), 'read' => (bool) $m->read_at,
            ]),
        ];
    }

    public function send(Request $r, Conversation $conversation)
    {
        $u = $this->me($r);
        abort_unless($conversation->involves($u), 404);
        $to = $conversation->other($u);
        if ($why = $u->cannotMessage($to)) abort(422, $why);
        $body = trim($r->validate(['body' => 'required|string|max:4000'])['body']);
        abort_if($body === '', 422, 'Skriv en besked.');

        // Mail kun ved første ulæste besked i samtalen, så modtageren ikke får en mail pr. besked
        $hadUnread = $conversation->messages()->where('sender_id', $u->id)->whereNull('read_at')->exists();
        $m = $conversation->messages()->create(['sender_id' => $u->id, 'body' => $body]);
        $conversation->update(['last_message_at' => now()]);
        if (! $hadUnread) $to->notify(new MessageReceived($u->name, $conversation->id));

        return ['id' => $m->id, 'at' => $m->created_at->toIso8601String()];
    }

    public function block(Request $r, User $user)
    {
        $u = $this->me($r);
        abort_if($user->id === $u->id, 422);
        $block = (bool) $r->validate(['block' => 'required|boolean'])['block'];
        $block ? DB::table('user_blocks')->insertOrIgnore(['user_id' => $u->id, 'blocked_id' => $user->id, 'created_at' => now()])
               : DB::table('user_blocks')->where('user_id', $u->id)->where('blocked_id', $user->id)->delete();
        Audit::log($block ? 'user.blocked' : 'user.unblocked', $user);

        return ['ok' => true];
    }

    /** Modtageren anmelder en besked. Bestyrelsen får beskedens tekst – ellers ser ingen indholdet. */
    public function report(Request $r, Message $message)
    {
        $u = $this->me($r);
        abort_unless($message->conversation->involves($u) && $message->sender_id !== $u->id, 403);
        $reason = $r->validate(['reason' => 'required|string|max:300'])['reason'];
        $message->load('sender');
        DB::table('message_reports')->insert(['message_id' => $message->id, 'user_id' => $u->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('message.reported', $message, ['reason' => $reason]);
        User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['board', 'admin'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->get()->each->notify(new ClubMessage(
                'Besked anmeldt',
                $u->name.' har anmeldt en besked fra '.$message->sender->name.' ('.$reason.'): "'.mb_strimwidth($message->body, 0, 300, '…').'"',
                '/medlemmer/'.$message->sender_id
            ));

        return ['ok' => true];
    }
}
