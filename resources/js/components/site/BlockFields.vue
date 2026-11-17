<template>
    <div class="bf">
        <!-- Topbillede -->
        <template v-if="b.type === 'hero'">
            <div class="fld"><div class="lbl">Baggrundsbillede</div><ImagePick v-model="b.image" /></div>
            <label class="field"><span>Lille tekst over overskriften</span><textarea v-model="b.kicker" rows="2" maxlength="300"></textarea></label>
            <label class="field"><span>Overskrift</span><input v-model="b.heading" maxlength="160"></label>
            <ButtonFields v-model="b.button" :options="options" :blocks="linkBlocks" optional />
            <details v-if="admin" class="adm" open>
                <summary>Layout (kun admin)</summary>
                <label class="field"><span>Højde</span><select v-model="b.height"><option value="screen">Hele skærmen</option><option value="medium">Lav</option></select></label>
                <label class="field"><span>Logo</span><select v-model="b.logo"><option :value="null">Intet logo</option><option value="farve">Logo i farver</option><option value="hvid">Hvidt logo</option></select></label>
                <label class="field"><span>Overskriftens farve</span><select v-model="b.heading_color"><option value="sand">Sand (lys)</option><option value="orange">Orange</option></select></label>
                <label class="field"><span>Overskriftens størrelse</span><select v-model="b.heading_size"><option value="h1">Stor</option><option value="h2">Mindre</option></select></label>
            </details>
        </template>

        <!-- En eller to kolonner -->
        <template v-else-if="b.type === 'columns'">
            <details v-if="admin" class="adm">
                <summary>Layout (kun admin)</summary>
                <label class="field"><span>Kolonner</span>
                    <select :value="b.layout" @change="setLayout($event.target.value)"><option value="one">Én kolonne</option><option value="two">To kolonner</option></select></label>
                <label v-if="b.layout === 'two'" class="field"><span>Bredder</span>
                    <select :value="b.widths.join(':')" @change="b.widths = $event.target.value.split(':').map(Number)">
                        <option v-for="w in WIDTHS" :key="w[0]" :value="w[0]">{{ w[1] }}</option>
                    </select></label>
                <label class="field"><span>Baggrund</span><select v-model="b.bg"><option value="sand">Sand</option><option value="orange">Orange</option></select></label>
                <label class="field"><span>Tekstfarve</span><select v-model="b.tone"><option value="lilla">Lilla</option><option value="pink">Pink</option></select></label>
                <label class="field"><span>Placering af tekst</span><select v-model="b.align"><option value="left">Venstre</option><option value="center">Midten</option></select></label>
            </details>
            <p v-else class="small muted" style="margin:0">{{ b.layout === 'two' ? 'To kolonner' : 'Én kolonne' }}. Fjerner du alt fra den ene kolonne, breder resten sig over hele bredden.</p>

            <section v-for="(col, ci) in b.cols" :key="ci" class="col">
                <h4>{{ b.layout === 'two' ? (ci === 0 ? 'Venstre kolonne' : 'Højre kolonne') + ' (på mobil: ' + (ci === 0 ? 'øverst' : 'nederst') + ')' : 'Indhold' }}</h4>
                <p v-if="!col.length" class="small" style="margin:0 0 6px;color:var(--warn)">Kolonnen er tom og vises derfor ikke – resten af blokken fylder hele bredden på hjemmesiden. Tilføj tekst, et billede eller en knap herunder for at vise den.</p>
                <div v-for="(el, ei) in col" :key="ei" class="el">
                    <div class="el-head">
                        <strong>{{ EL[el.t] }}</strong>
                        <span class="row" style="gap:2px">
                            <button type="button" class="ib" :disabled="ei === 0" title="Flyt op" @click="move(col, ei, -1)">↑</button>
                            <button type="button" class="ib" :disabled="ei === col.length - 1" title="Flyt ned" @click="move(col, ei, 1)">↓</button>
                            <button type="button" class="ib danger" :title="'Fjern ' + EL[el.t].toLowerCase()" @click="col.splice(ei, 1)">×</button>
                        </span>
                    </div>
                    <template v-if="el.t === 'image'">
                        <ImagePick v-model="el.src" v-model:alt="el.alt" with-alt />
                        <div class="fld" style="margin-top:8px"><div class="lbl">Når man klikker på billedet</div>
                            <LinkPick v-model="el.link" :options="options" :blocks="linkBlocks" allow-empty empty-label="Sker der intet" /></div>
                    </template>
                    <RichText v-else-if="el.t === 'text' || el.t === 'box'" v-model="el.html" :options="options" :prices="options.prices" :label="EL[el.t]" />
                    <ButtonFields v-else-if="el.t === 'button'" :model-value="el" :options="options" :blocks="linkBlocks" inline @update:model-value="Object.assign(el, $event)" />
                    <label v-else-if="el.t === 'map'" class="field"><span>Adresse</span><input v-model="el.address" maxlength="120" :disabled="!admin"></label>
                    <p v-else-if="el.t === 'rule'" class="small muted" style="margin:0">En tynd streg under teksten ovenover.</p>
                </div>
                <div class="add">
                    <span class="small muted">Tilføj:</span>
                    <button type="button" class="btn small ghost" @click="col.push({ t: 'text', html: '<p>Skriv teksten her.</p>' })">+ Tekst</button>
                    <button type="button" class="btn small ghost" @click="col.push({ t: 'image', src: null, alt: '' })">+ Billede</button>
                    <button type="button" class="btn small ghost" @click="col.push({ t: 'button', label: 'Læs mere', link: 'page:/priser-og-medlemskab' })">+ Knap</button>
                    <template v-if="admin">
                        <button type="button" class="btn small ghost" @click="col.push({ t: 'box', html: '<h4>Tekst i en ramme</h4>' })">+ Ramme</button>
                        <button type="button" class="btn small ghost" @click="col.push({ t: 'rule' })">+ Streg</button>
                        <button type="button" class="btn small ghost" @click="col.push({ t: 'map', address: 'Husoddevej 85, 8700 Horsens' })">+ Kort</button>
                    </template>
                </div>
            </section>
        </template>

        <!-- Priskort -->
        <template v-else-if="b.type === 'card'">
            <div class="fld"><div class="lbl">Billede (til venstre)</div>
                <ImagePick v-model="b.image" v-model:alt="b.alt" with-alt removable remove-label="Uden billede" @remove="b.image = null; b.alt = ''" /></div>
            <label class="field"><span>Lille tekst over overskriften</span><input v-model="b.label" maxlength="80" placeholder="Fx: 1 dags kursus"></label>
            <label class="field"><span>Overskrift</span><input v-model="b.heading" maxlength="160"></label>
            <div class="fld"><div class="lbl">Tekst</div><RichText v-model="b.html" :options="options" :prices="options.prices" label="Tekst" /></div>
            <label class="check"><input v-model="b.prices" type="checkbox"> Vis prislisten (fra Medlemskaber og tilkøb)</label>
            <ButtonFields v-model="b.button" :options="options" :blocks="linkBlocks" optional />
            <details v-if="admin" class="adm">
                <summary>Layout (kun admin)</summary>
                <label class="field"><span>Bredder (billede : boks)</span>
                    <select :value="b.widths.join(':')" @change="b.widths = $event.target.value.split(':').map(Number)">
                        <option v-for="w in CARD_WIDTHS" :key="w[0]" :value="w[0]">{{ w[1] }}</option>
                    </select></label>
            </details>
        </template>

        <!-- Spørgsmål og svar -->
        <template v-else-if="b.type === 'faq'">
            <label class="field"><span>Overskrift</span><input v-model="b.label" maxlength="80"></label>
            <div v-for="(it, i) in b.items" :key="i" class="el">
                <div class="el-head">
                    <strong>Spørgsmål {{ i + 1 }}</strong>
                    <span class="row" style="gap:2px">
                        <button type="button" class="ib" :disabled="i === 0" title="Flyt op" @click="move(b.items, i, -1)">↑</button>
                        <button type="button" class="ib" :disabled="i === b.items.length - 1" title="Flyt ned" @click="move(b.items, i, 1)">↓</button>
                        <button type="button" class="ib danger" title="Fjern spørgsmålet" @click="b.items.splice(i, 1)">×</button>
                    </span>
                </div>
                <input v-model="it.q" maxlength="200" placeholder="Spørgsmålet" aria-label="Spørgsmålet" style="margin-bottom:6px">
                <RichText v-model="it.a" :options="options" label="Svaret" />
            </div>
            <button type="button" class="btn small ghost" @click="b.items.push({ q: '', a: '' })">+ Tilføj spørgsmål</button>
        </template>

        <!-- Kalender -->
        <template v-else-if="b.type === 'calendar'">
            <p class="small" style="margin:0">Kalenderen viser kommende aktiviteter med fluebenet <strong>Vis i kalenderen på hjemmesiden</strong>. Ret dem under Aktiviteter.</p>
        </template>
    </div>
</template>
<script setup>
import { defineComponent, h, computed } from 'vue';
import ImagePick from './ImagePick.vue';
import LinkPick from './LinkPick.vue';
import RichText from './RichText.vue';

const props = defineProps({ block: Object, admin: Boolean, options: Object, linkBlocks: Array });
const b = computed(() => props.block);
const EL = { image: 'Billede', text: 'Tekst', button: 'Knap', box: 'Tekst i ramme', rule: 'Streg', map: 'Kort' };
const WIDTHS = [['1:1', 'Lige brede'], ['2:3', 'Smal + bred'], ['3:2', 'Bred + smal'], ['1:2', 'Meget smal + bred'], ['2:1', 'Bred + meget smal']];
const CARD_WIDTHS = [['3:5', 'Standard'], ['1:1', 'Lige brede'], ['1:2', 'Mindre billede'], ['2:3', 'Lidt større billede']];

function move(arr, i, d) { const [x] = arr.splice(i, 1); arr.splice(i + d, 0, x); }
function setLayout(v) {
    const blk = b.value;
    if (v === 'one') blk.cols = [blk.cols.flat()];
    // Den nye kolonne får en tekst at starte med – en tom kolonne vises ikke på hjemmesiden
    else if (blk.cols.length < 2 || !blk.cols[1].length) blk.cols = [blk.cols[0] || [], [{ t: 'text', html: '<p>Skriv teksten her.</p>' }]];
    blk.layout = v;
}

/** Knap: tekst + hvor den fører hen. optional: kan fjernes/tilføjes. inline: felterne ligger direkte på elementet. */
const ButtonFields = defineComponent({
    props: { modelValue: Object, options: Object, blocks: Array, optional: Boolean, inline: Boolean },
    emits: ['update:modelValue'],
    setup(p, { emit }) {
        const set = (k, v) => emit('update:modelValue', { ...(p.modelValue || {}), [k]: v });
        return () => {
            if (!p.modelValue) {
                return h('div', { class: 'fld' }, [h('div', { class: 'lbl' }, 'Knap'),
                    h('button', { type: 'button', class: 'btn small ghost', onClick: () => emit('update:modelValue', { label: 'Læs mere', link: 'next' }) }, '+ Tilføj en knap')]);
            }
            return h('div', { class: 'fld btnf' }, [
                p.inline ? null : h('div', { class: 'lbl row between' }, ['Knap', p.optional ? h('button', { type: 'button', class: 'btn small ghost danger', onClick: () => emit('update:modelValue', null) }, 'Fjern knappen') : null]),
                h('label', { class: 'field' }, [h('span', 'Tekst på knappen'), h('input', { value: p.modelValue.label, maxlength: 80, onInput: (e) => set('label', e.target.value) })]),
                h('div', { class: 'field' }, [h('span', 'Fører til'), h(LinkPick, { modelValue: p.modelValue.link, options: p.options, blocks: p.blocks, 'onUpdate:modelValue': (v) => set('link', v) })]),
            ]);
        };
    },
});
</script>
<style scoped>
.bf { display: flex; flex-direction: column; gap: 12px; }
.bf :deep(.field) { display: flex; flex-direction: column; gap: 4px; }
.bf :deep(.field > span), .bf :deep(.lbl) { font-size: .82rem; font-weight: 600; }
.bf :deep(.lbl) { margin-bottom: 6px; }
.bf :deep(.btnf) { display: flex; flex-direction: column; gap: 8px; }
.adm { border: 1px dashed var(--line); border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; gap: 8px; }
.adm[open] { padding-bottom: 12px; }
.adm summary { cursor: pointer; font-weight: 600; font-size: .85rem; }
.adm > label { margin-top: 8px; }
.col { border-top: 1px solid var(--line); padding-top: 10px; }
.col h4 { margin: 0 0 8px; font-size: .9rem; }
.el { border: 1px solid var(--line); border-radius: 10px; padding: 10px; margin-bottom: 8px; background: var(--surface-2); }
.el-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: .85rem; }
.ib { border: 1px solid var(--line); background: var(--surface); width: 28px; height: 28px; border-radius: 6px; cursor: pointer; font: inherit; line-height: 1; color: var(--ink); }
.ib:disabled { opacity: .35; cursor: default; }
.ib.danger { color: var(--bad); }
.add { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.check { display: flex; gap: 8px; align-items: center; font-size: .88rem; }
.check input { width: auto; }
</style>
