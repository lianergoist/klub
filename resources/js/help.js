// Hjælpetekst (resources/help/hjaelp.md) delt op i afsnit. Bruges af HelpPanel og HelpButton.
// Formatet er beskrevet øverst i hjaelp.md. Kun et lille udsnit af Markdown: afsnit, punktlister og **fed**.
import { reactive } from 'vue';
import raw from '../help/hjaelp.md?raw';
import { store } from './api';
import { track } from './usage';

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inline = (s) => esc(s).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

function toHtml(lines) {
    const out = [];
    let para = [], list = [];
    const flushPara = () => { if (para.length) out.push(`<p>${inline(para.join(' '))}</p>`); para = []; };
    const flushList = () => { if (list.length) out.push(`<ul>${list.map((l) => `<li>${inline(l)}</li>`).join('')}</ul>`); list = []; };
    for (const line of lines) {
        const t = line.trim();
        if (!t) { flushPara(); flushList(); continue; }
        if (t.startsWith('- ')) { flushPara(); list.push(t.slice(2)); } else { flushList(); para.push(t); }
    }
    flushPara(); flushList();
    return out.join('');
}

function parse(md) {
    const sections = [];
    let group = '', cur = null;
    const body = md.replace(/<!--[\s\S]*?-->/g, '');
    for (const line of body.split('\n')) {
        const h1 = line.match(/^# (.+)/);
        const h2 = line.match(/^## (.+?)\s*\{(.+)\}\s*$/);
        if (h1) { group = h1[1].trim(); cur = null; continue; }
        if (h2) {
            const attrs = h2[2].trim().split(/\s+/);
            const get = (k) => attrs.find((a) => a.startsWith(k + ':'))?.slice(k.length + 1);
            cur = {
                id: attrs.find((a) => a.startsWith('#'))?.slice(1),
                title: h2[1].trim(), group,
                requires: get('kræver') || null,
                pages: (get('side') || '').split(',').filter(Boolean),
                lines: [],
            };
            sections.push(cur);
            continue;
        }
        if (cur) cur.lines.push(line);
    }
    return sections.map(({ lines, ...s }) => ({ ...s, text: lines.join(' ').replace(/\*\*/g, '').replace(/\s+/g, ' ').trim(), html: toHtml(lines) }));
}

export const sections = parse(raw);

/** AI-svar til HTML: teksten escapes først, derefter kun **fed** og lister ("1." eller "- "). */
export function answerHtml(text) {
    const out = [];
    let list = null, para = [];
    const flushPara = () => { if (para.length) out.push(`<p>${inline(para.join(' '))}</p>`); para = []; };
    const flushList = () => { if (list) out.push(`<${list.tag}>${list.items.map((i) => `<li>${inline(i)}</li>`).join('')}</${list.tag}>`); list = null; };
    for (const line of String(text).split('\n')) {
        const t = line.trim();
        const m = t.match(/^(\d+)[.)]\s+(.*)$/) || t.match(/^([-*•])\s+(.*)$/);
        if (!t) { flushPara(); flushList(); continue; }
        if (m) {
            const tag = /\d/.test(m[1]) ? 'ol' : 'ul';
            flushPara(); if (list && list.tag !== tag) flushList();
            (list ||= { tag, items: [] }).items.push(m[2]);
        } else { flushList(); para.push(t); }
    }
    flushPara(); flushList();
    return out.join('');
}

/** Afsnit, brugeren har adgang til (samme rettigheder som menuen). */
export function visibleSections() {
    const can = store.user?.can || {};
    return sections.filter((s) => !s.requires || can[s.requires]);
}

export const help = reactive({ open: false, topic: null });

export function openHelp(topic = null) {
    help.topic = topic; help.open = true;
    track('hjaelp', 'aabnet', topic ? { topic } : null);
}
