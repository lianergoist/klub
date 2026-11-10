<template>
    <div>
        <h1 style="margin-bottom:14px">Mere</h1>
        <div class="card" style="padding:4px 16px">
            <ul class="list">
                <li v-if="can.members"><RouterLink to="/medlemmer" class="row"><Icon name="users" style="width:20px" /> Medlemmer</RouterLink></li>
                <li v-if="can.finance_read"><RouterLink to="/okonomi" class="row"><Icon name="money" style="width:20px" /> Økonomi <span class="small muted">(bedst på computer)</span></RouterLink></li>
                <li v-if="can.board"><RouterLink to="/indstillinger" class="row"><Icon name="gear" style="width:20px" /> Indstillinger</RouterLink></li>
            </ul>
        </div>
        <div class="card"><ThemeSwitch /></div>
        <p class="small muted">Logget ind som {{ store.user.name }} ({{ store.user.roles.map(r => roleLabel[r]).join(', ') }})</p>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import Icon from '../components/Icon.vue';
import ThemeSwitch from '../components/ThemeSwitch.vue';
import { store, roleLabel } from '../api';
const can = computed(() => store.user.can);
</script>
