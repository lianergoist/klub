import { ref } from 'vue';

// Samme grænse som sidemenu/bundmenu i app.css (900 px): under den er vi i "mobil"-layout (også appen).
const mq = window.matchMedia('(max-width: 899.98px)');
export const isMobile = ref(mq.matches);
mq.addEventListener('change', (e) => { isMobile.value = e.matches; });
