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
            <h2>Priser</h2>
            <p class="small muted">Træningssæsonen løber 1. maj – 30. april. Regnskabsåret er kalenderåret. Hver pris har en dato, den gælder fra, så nye priser kan lægges ind på forhånd uden at ændre det, der allerede er opkrævet.</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Pris</th><th class="num">Beløb</th><th>Gælder fra</th></tr></thead>
                <tbody><tr v-for="p in prices" :key="p.code">
                    <td>{{ p.label }}</td><td class="num">{{ p.amount_fmt }}</td>
                    <td class="small">{{ p.valid_from ? dato(p.valid_from) : '–' }}<div v-for="u in p.upcoming" :key="u.valid_from" class="tag info" style="margin-top:4px">{{ u.amount_fmt }} fra {{ dato(u.valid_from) }}</div></td>
                </tr></tbody>
            </table></div>
            <p class="small muted" style="margin-top:8px">Redigering af priser (med virkning fra en valgt dato) kommer i en senere version. Opbevaring og dagsleje har foreløbige priser, der ikke er fastsat af bestyrelsen.</p>
        </div>
    </div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api, toast, dato } from '../api';
const cats = ref([]); const prices = ref([]); const nc = reactive({ name: '', color: '#0f7c8c' });
onMounted(async () => { cats.value = await api('categories'); prices.value = await api('finance/prices'); });
async function save(c) { try { await api(`categories/${c.id}`, { method: 'PUT', body: c }); toast('Gemt'); } catch (e) { toast(e.message, 'error'); } }
async function add() { try { cats.value.push(await api('categories', { method: 'POST', body: nc })); nc.name = ''; toast('Tilføjet'); } catch (e) { toast(e.message, 'error'); } }
</script>
