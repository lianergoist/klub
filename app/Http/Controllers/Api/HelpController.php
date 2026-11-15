<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\HelpAssistant;
use App\Support\HelpAssistantUnavailable;
use App\Support\Usage;
use Illuminate\Http\Request;

/** AI-svar i hjælpens Spørg-felt. Selve spørgsmålet gemmes ikke (afventer bestyrelsen, se Funktionsoversigt.md). */
class HelpController extends Controller
{
    public function ask(Request $r)
    {
        $data = $r->validate(['question' => 'required|string|min:3|max:300', 'page' => 'nullable|string|max:191'], [
            'question.min' => 'Skriv lidt mere i dit spørgsmål.',
            'question.max' => 'Spørgsmålet må højst være 300 tegn.',
        ]);
        abort_unless(HelpAssistant::enabled(), 503, 'AI-svar er ikke slået til endnu. Kig i emnerne nedenfor, eller kontakt bestyrelsen.');

        $ctx = Usage::context($r);
        try {
            $res = HelpAssistant::answer(trim($data['question']), $r->user());
        } catch (HelpAssistantUnavailable $e) {
            Usage::log($ctx + ['type' => 'track', 'path' => 'api/help/ask', 'target' => 'spoerg.utilgaengelig']);
            abort(503, $e->getMessage());
        }

        // Til brugsloggen: om der kunne svares, hvilke emner, og forbruget (til at holde øje med prisen)
        Usage::log($ctx + [
            'type' => 'track', 'path' => 'api/help/ask',
            'target' => $res['answered'] ? 'spoerg.besvaret' : 'spoerg.ubesvaret',
            'meta' => ['topics' => $res['topics'], 'page' => $data['page'] ?? null] + $res['usage'],
        ]);

        return ['answered' => $res['answered'], 'answer' => $res['answer'], 'topics' => $res['topics']];
    }
}
