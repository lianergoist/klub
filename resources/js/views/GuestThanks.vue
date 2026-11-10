<template>
    <div class="guest">
        <div v-if="!s" class="spinner"></div>
        <template v-else>
            <div class="card" style="text-align:center;padding:28px 20px">
                <div class="okdot"><Icon name="check" style="width:30px" /></div>
                <h1>Du er tilmeldt, {{ s.name.split(' ')[0] }}!</h1>
                <ul class="list" style="text-align:left;margin:14px 0">
                    <li v-for="(b, i) in s.bookings" :key="i"><strong>{{ b.title }}</strong><div class="small muted" style="text-transform:capitalize">{{ datoLang(b.starts_at) }} kl. {{ klok(b.starts_at) }}<span v-if="b.location"> · {{ b.location }}</span></div></li>
                </ul>
                <p class="small muted">Du har fået en bekræftelse på {{ s.email }}.</p>
            </div>
            <div class="card">
                <h2>Vil du være medlem?</h2>
                <p>Melder du dig ind inden for et år, trækkes de <strong>{{ s.credit_fmt }}</strong>, du har betalt, fra kontingentet.</p>
                <RouterLink to="/bliv-medlem" class="btn primary block">Meld dig ind</RouterLink>
            </div>
            <p class="small" style="text-align:center"><RouterLink to="/prov">Book mere</RouterLink></p>
        </template>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import Icon from '../components/Icon.vue';
import { api, datoLang, klok } from '../api';
const s = ref(null);
onMounted(async () => { s.value = await api('guest/status'); });
</script>
<style scoped>
.guest { max-width: 560px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px; }
.okdot { width: 56px; height: 56px; border-radius: 50%; background: var(--ok-soft); color: var(--ok); display: grid; place-items: center; margin: 0 auto 12px; }
</style>
