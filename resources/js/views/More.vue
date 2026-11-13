<template>
    <div>
        <h1 style="margin-bottom:14px">Mere</h1>
        <div class="card" style="padding:2px 16px">
            <ul class="list">
                <li v-for="t in tools" :key="t.to"><RouterLink :to="t.to" class="mrow">
                    <Icon :name="t.icon" class="mi" />
                    <span class="mtext"><strong>{{ t.title }}</strong><span class="small muted">{{ t.hint }}</span></span>
                    <span class="chev" aria-hidden="true">›</span>
                </RouterLink></li>
            </ul>
        </div>
        <p class="small muted" style="margin:4px 4px 0">Farvetema og Log ud finder du under <RouterLink to="/profil">Profil</RouterLink>.</p>
    </div>
</template>
<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import { store } from '../api';
const can = computed(() => store.user.can);
const router = useRouter();
const tools = computed(() => [
    can.value.portal && { to: '/traener', icon: 'whistle', title: 'Trænerportal', hint: 'Mangler træner og tilgængelighed' },
    can.value.equipment && { to: '/udstyr', icon: 'board', title: 'Udstyr', hint: 'Udstyrslisten og booking' },
    can.value.members_view && { to: '/medlemmer', icon: 'users', title: 'Medlemmer', hint: 'Kartotek, roller og betalinger' },
    can.value.finance_read && { to: '/okonomi', icon: 'money', title: 'Økonomi', hint: 'Regnskab, betalinger og udlæg' },
    can.value.settings && { to: '/indstillinger', icon: 'gear', title: 'Indstillinger', hint: 'Kategorier, medlemskaber, priser og rettigheder' },
].filter(Boolean));
// Ingen værktøjer (fx et gammelt link): send videre til Profil
onMounted(() => { if (!tools.value.length) router.replace('/profil'); });
</script>
<style scoped>
.list > li { padding: 0; }
.mrow { display: flex; align-items: center; gap: 12px; padding: 12px 0; color: var(--ink); min-height: 56px; }
.mi { width: 22px; height: 22px; color: var(--accent); flex-shrink: 0; }
.mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mtext .small { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { color: var(--ink-3); font-size: 1.4rem; line-height: 1; }
</style>
