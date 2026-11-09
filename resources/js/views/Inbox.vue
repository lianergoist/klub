<template>
    <div>
        <h1 style="margin-bottom:14px">Indbakke</h1>
        <div v-if="list === null" class="spinner"></div>
        <div v-else-if="!list.length" class="card empty">Ingen beskeder.</div>
        <div v-else class="card" style="padding:4px 16px">
            <ul class="list">
                <li v-for="n in list" :key="n.id">
                    <component :is="n.link ? 'RouterLink' : 'div'" :to="n.link" style="color:inherit;display:block">
                        <div class="row between"><strong>{{ n.title }}</strong><span v-if="!n.read" class="tag info">Ny</span></div>
                        <div class="small" style="color:var(--ink-2)">{{ n.body }}</div>
                        <div class="small muted">{{ tidspunkt(n.created_at) }}</div>
                    </component>
                </li>
            </ul>
        </div>
        <p class="small muted">Beskeder sendes også på e-mail, hvis du har valgt det under Profil → Beskeder. Push-beskeder på telefonen kommer i næste fase.</p>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { api, store, tidspunkt } from '../api';
const list = ref(null);
onMounted(async () => { list.value = await api('notifications'); if (store.user) store.user.unread = 0; });
</script>
