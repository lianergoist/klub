<template>
    <div>
        <h1 style="margin-bottom:14px">Mere</h1>
        <div v-if="can.trainer || can.members || can.finance_read || can.board" class="card" style="padding:4px 16px">
            <ul class="list">
                <li v-if="can.trainer"><RouterLink to="/traener" class="row"><Icon name="whistle" style="width:20px" /> Trænerportal</RouterLink></li>
                <li v-if="can.members"><RouterLink to="/medlemmer" class="row"><Icon name="users" style="width:20px" /> Medlemmer</RouterLink></li>
                <li v-if="can.finance_read"><RouterLink to="/okonomi" class="row"><Icon name="money" style="width:20px" /> Økonomi <span class="small muted">(bedst på computer)</span></RouterLink></li>
                <li v-if="can.board"><RouterLink to="/indstillinger" class="row"><Icon name="gear" style="width:20px" /> Indstillinger</RouterLink></li>
            </ul>
        </div>
        <div class="card"><ThemeSwitch /></div>
        <button class="btn danger block" @click="logout">Log ud</button>
        <p class="small muted" style="margin-top:12px">Logget ind som {{ store.user.name }} ({{ store.user.roles.map(r => roleLabel[r]).join(', ') }})</p>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import Icon from '../components/Icon.vue';
import ThemeSwitch from '../components/ThemeSwitch.vue';
import { useRouter } from 'vue-router';
import { api, store, roleLabel, setSession } from '../api';
const can = computed(() => store.user.can);
const router = useRouter();
async function logout() { try { await api('logout', { method: 'POST' }); } catch {} setSession(null, null); router.push('/login'); }
</script>
