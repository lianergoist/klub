<template>
    <div>
        <h1 style="margin-bottom:14px">Indstillinger</h1>
        <div class="card">
            <h2>Kategorier</h2>
            <p class="small muted">Bestyrelsen kan selv tilføje og omdøbe kategorier. Medlemmer bruger dem til at filtrere og vælge beskeder.</p>
            <ul class="list">
                <li v-for="c in cats" :key="c.id" class="row" style="flex-wrap:nowrap">
                    <input type="color" v-model="c.color" style="width:44px;padding:2px">
                    <input v-model="c.name">
                    <button class="btn small" @click="save(c)">Gem</button>
                </li>
            </ul>
            <form class="row" style="margin-top:12px;flex-wrap:nowrap" @submit.prevent="add">
                <input type="color" v-model="nc.color" style="width:44px;padding:2px">
                <input v-model="nc.name" placeholder="Ny kategori, fx Surfski" required>
                <button class="btn primary small">Tilføj</button>
            </form>
        </div>
        <div class="card">
            <h2>Kontingentsatser</h2>
            <table><tbody><tr v-for="t in types" :key="t.id"><td>{{ t.name }}</td><td class="num">{{ t.fee_fmt }}</td></tr></tbody></table>
            <p class="small muted" style="margin-top:8px">Satserne kan redigeres i næste fase.</p>
        </div>
    </div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api, toast } from '../api';
const cats = ref([]); const types = ref([]); const nc = reactive({ name: '', color: '#0f7c8c' });
onMounted(async () => { cats.value = await api('categories'); types.value = await api('membership-types'); });
async function save(c) { try { await api(`categories/${c.id}`, { method: 'PUT', body: c }); toast('Gemt'); } catch (e) { toast(e.message, 'error'); } }
async function add() { try { cats.value.push(await api('categories', { method: 'POST', body: nc })); nc.name = ''; toast('Tilføjet'); } catch (e) { toast(e.message, 'error'); } }
</script>
