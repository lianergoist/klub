<template>
    <div>
        <div class="topbar noprint">
            <div><h1>Økonomi</h1>
                <p class="small muted" style="margin:0">{{ store.user.can.finance_write ? 'Du kan indtaste og rette (kasserer).' : 'Du har læseadgang.' }}</p></div>
            <select v-model="fy.year" style="width:auto"><option v-for="y in years" :key="y" :value="y">Regnskabsår {{ y }}</option></select>
        </div>
        <nav class="tabs noprint">
            <RouterLink to="/okonomi">Overblik</RouterLink>
            <RouterLink to="/okonomi/posteringer">Posteringer</RouterLink>
            <RouterLink to="/okonomi/betalinger">Betalinger</RouterLink>
            <RouterLink to="/okonomi/udlaeg">Udlæg</RouterLink>
            <RouterLink to="/okonomi/budget">Budget</RouterLink>
            <RouterLink to="/okonomi/mobilepay">MobilePay</RouterLink>
            <RouterLink to="/okonomi/bank">Bankafstemning</RouterLink>
            <RouterLink to="/okonomi/aarsregnskab">Årsregnskab</RouterLink>
            <RouterLink to="/okonomi/log">Ændringslog</RouterLink>
        </nav>
        <RouterView :key="fy.year" />
    </div>
</template>
<script setup>
import { provide, reactive } from 'vue';
import { store } from '../../api';
const now = new Date().getFullYear();
const years = [now, now - 1];
const fy = reactive({ year: now });
provide('fy', fy);
</script>
