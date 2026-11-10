<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostImage;
use App\Models\PostLike;
use App\Models\PostReport;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Opslagstavle: aktive medlemmer kan dele tekst og billeder, synes godt om og kommentere.
 * Trænere og bestyrelse moderer (skjul/vis). Alle kan anmelde et opslag.
 */
class FeedController extends Controller
{
    private function member(Request $r): User
    {
        $u = $r->user();
        abort_unless(in_array($u->status, ['active', 'passive']) || $u->isTrainer(), 403, 'Opslagstavlen er for aktive medlemmer. Betal dit kontingent for at få adgang.');

        return $u;
    }

    private function mod(Request $r): User
    {
        $u = $this->member($r);
        abort_unless($u->isTrainer(), 403, 'Kun trænere og bestyrelse kan moderere.');

        return $u;
    }

    /** filter: all | reported | hidden (de to sidste kun for moderatorer) */
    public function index(Request $r)
    {
        $u = $this->member($r);
        $isMod = $u->isTrainer();
        $filter = $isMod ? $r->query('filter', 'all') : 'all';

        $q = Post::with(['user', 'images', 'comments.user:id,name'])
            ->withCount(['likes', 'reports as open_reports_count' => fn ($q) => $q->whereNull('resolved_at')])
            ->latest('id');
        match ($filter) {
            'reported' => $q->whereHas('reports', fn ($q) => $q->whereNull('resolved_at')),
            'hidden' => $q->whereNotNull('hidden_at'),
            default => $isMod ? $q : $q->where(fn ($q) => $q->whereNull('hidden_at')->orWhere('user_id', $u->id)),
        };
        if ($before = $r->query('before')) $q->where('id', '<', $before);
        $posts = $q->limit(15)->get();

        $liked = PostLike::where('user_id', $u->id)->whereIn('post_id', $posts->pluck('id'))->pluck('post_id')->flip();
        $reported = PostReport::where('user_id', $u->id)->whereIn('post_id', $posts->pluck('id'))->pluck('post_id')->flip();

        return [
            'posts' => $posts->map(fn ($p) => $this->row($p, $u, $isMod, isset($liked[$p->id]), isset($reported[$p->id]))),
            'more' => $posts->count() === 15,
            'is_mod' => $isMod,
            'open_reports' => $isMod ? Post::whereHas('reports', fn ($q) => $q->whereNull('resolved_at'))->count() : 0,
        ];
    }

    private function row(Post $p, User $u, bool $isMod, bool $liked, bool $reported): array
    {
        return [
            'id' => $p->id,
            'author' => $p->user->name,
            'author_id' => $p->user_id,
            'can_message' => $p->user_id !== $u->id && $u->cannotMessage($p->user) === null,
            'body' => $p->body,
            'created_at' => $p->created_at->toIso8601String(),
            'images' => $p->images->map(fn ($i) => [
                'url' => URL::temporarySignedRoute('feed.image', now()->addHours(12), ['image' => $i->id]),
                'w' => $i->width, 'h' => $i->height,
            ]),
            'likes' => $p->likes_count,
            'liked' => $liked,
            'reported_by_me' => $reported,
            'mine' => $p->user_id === $u->id,
            'hidden' => (bool) $p->hidden_at,
            'hidden_reason' => ($isMod || $p->user_id === $u->id) ? $p->hidden_reason : null,
            'open_reports' => $isMod ? $p->open_reports_count : null,
            'report_reasons' => $isMod && $p->open_reports_count ? $p->reports()->whereNull('resolved_at')->with('user:id,name')->get()->map(fn ($x) => $x->user->name.': '.$x->reason) : [],
            'comments' => $p->comments->filter(fn ($c) => ! $c->hidden_at || $isMod || $c->user_id === $u->id)->map(fn ($c) => [
                'id' => $c->id, 'author' => $c->user->name, 'body' => $c->body, 'created_at' => $c->created_at->toIso8601String(),
                'mine' => $c->user_id === $u->id, 'hidden' => (bool) $c->hidden_at,
            ])->values(),
        ];
    }

    public function store(Request $r)
    {
        $u = $this->member($r);
        $r->validate([
            'body' => 'nullable|string|max:3000|required_without:images',
            'images' => 'array|max:4',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:15360',
        ], [
            'body.required_without' => 'Skriv noget eller vælg et billede.',
            'images.max' => 'Du kan højst vedhæfte 4 billeder.',
        ]);
        abort_if($r->hasFile('images') && ! $r->boolean('consent'), 422, 'Bekræft, at personerne på billederne er okay med at blive vist.');

        $post = DB::transaction(function () use ($r, $u) {
            $post = Post::create(['user_id' => $u->id, 'body' => trim((string) $r->input('body')) ?: null]);
            foreach (array_values($r->file('images', [])) as $i => $file) {
                $post->images()->create(Images::store($file) + ['position' => $i]);
            }

            return $post;
        });
        Audit::log('post.created', $post, ['images' => $post->images()->count()]);

        return ['id' => $post->id];
    }

    public function destroy(Request $r, Post $post)
    {
        $u = $this->member($r);
        abort_unless($post->user_id === $u->id, 403, 'Du kan kun slette dine egne opslag.');
        foreach ($post->images as $i) Storage::delete($i->path);
        Audit::log('post.deleted', $post);
        $post->delete();

        return ['ok' => true];
    }

    public function like(Request $r, Post $post)
    {
        $u = $this->member($r);
        $existing = PostLike::where('post_id', $post->id)->where('user_id', $u->id);
        $liked = ! $existing->exists();
        $liked ? PostLike::create(['post_id' => $post->id, 'user_id' => $u->id, 'created_at' => now()]) : $existing->delete();

        return ['likes' => $post->likes()->count(), 'liked' => $liked];
    }

    public function comment(Request $r, Post $post)
    {
        $u = $this->member($r);
        abort_if($post->hidden_at, 422, 'Opslaget er skjult.');
        $c = $post->comments()->create(['user_id' => $u->id, 'body' => $r->validate(['body' => 'required|string|max:1000'])['body']]);
        if ($post->user_id !== $u->id) {
            $post->user->notify(new ClubMessage($u->name.' kommenterede dit opslag', mb_strimwidth($c->body, 0, 120, '…'), '/opslag'));
        }

        return ['id' => $c->id];
    }

    public function deleteComment(Request $r, PostComment $comment)
    {
        $u = $this->member($r);
        abort_unless($comment->user_id === $u->id, 403);
        $comment->delete();

        return ['ok' => true];
    }

    public function report(Request $r, Post $post)
    {
        $u = $this->member($r);
        $reason = $r->validate(['reason' => 'required|string|max:300'])['reason'];
        $first = ! $post->reports()->whereNull('resolved_at')->exists();
        PostReport::updateOrCreate(['post_id' => $post->id, 'user_id' => $u->id], ['reason' => $reason, 'resolved_at' => null]);
        Audit::log('post.reported', $post, ['reason' => $reason]);
        if ($first) {
            User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['trainer', 'board', 'admin'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
                ->where('id', '!=', $post->user_id)->get()
                ->each->notify(new ClubMessage('Opslag anmeldt', 'Et opslag af '.$post->user->name.' er anmeldt: '.$reason, '/opslag?filter=reported'));
        }

        return ['ok' => true];
    }

    /** Moderator: skjul (med begrundelse) eller vis igen. Løser samtidig åbne anmeldelser. */
    public function moderate(Request $r, Post $post)
    {
        $u = $this->mod($r);
        $d = $r->validate(['hide' => 'required|boolean', 'reason' => 'nullable|string|max:300']);
        if ($d['hide']) {
            abort_unless($d['reason'] ?? null, 422, 'Skriv en kort begrundelse til forfatteren.');
            $post->update(['hidden_at' => now(), 'hidden_by' => $u->id, 'hidden_reason' => $d['reason']]);
            if ($post->user_id !== $u->id) {
                $post->user->notify(new ClubMessage('Dit opslag er skjult', 'En moderator har skjult dit opslag. Begrundelse: '.$d['reason'], '/opslag'));
            }
        } else {
            $post->update(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null]);
        }
        $post->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        Audit::log($d['hide'] ? 'post.hidden' : 'post.shown', $post, ['reason' => $d['reason'] ?? null]);

        return ['ok' => true];
    }

    /** Moderator: afvis anmeldelser uden at skjule. */
    public function dismissReports(Request $r, Post $post)
    {
        $this->mod($r);
        $post->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        Audit::log('post.reports_dismissed', $post);

        return ['ok' => true];
    }

    public function moderateComment(Request $r, PostComment $comment)
    {
        $u = $this->mod($r);
        $hide = (bool) $r->validate(['hide' => 'required|boolean'])['hide'];
        $comment->update(['hidden_at' => $hide ? now() : null, 'hidden_by' => $hide ? $u->id : null]);
        Audit::log($hide ? 'comment.hidden' : 'comment.shown', $comment);

        return ['ok' => true];
    }

    /** Billeder hentes via tidsbegrænset signeret link (img-tags kan ikke sende login-token). */
    public function image(PostImage $image)
    {
        abort_unless(Storage::exists($image->path), 404);

        return response(Storage::get($image->path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=43200',
        ]);
    }
}
