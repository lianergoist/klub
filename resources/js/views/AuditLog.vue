<template>
    <div>
        <RouterLink to="/indstillinger" class="small">‹ Indstillinger</RouterLink>
        <h1 style="margin:4px 0 6px">Ændringslog</h1>
        <AuditTable endpoint="settings/audit" intro="Alle handlinger i systemet: medlemmer, roller og rettigheder, aktiviteter, opslag, udstyr, priser og regnskab – med hvem og hvornår. Viser de seneste 300." />
    </div>
</template>
<script setup>
import AuditTable from '../components/AuditTable.vue';
</script>
