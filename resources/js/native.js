// Kun i mobilappen: kobling til telefonens funktioner via Capacitor
import { App } from '@capacitor/app';
import { StatusBar, Style } from '@capacitor/status-bar';
import { SplashScreen } from '@capacitor/splash-screen';
import { Capacitor } from '@capacitor/core';
import { FirebaseMessaging } from '@capacitor-firebase/messaging';
import { watch } from 'vue';
import { api, store, toast, refreshMe } from './api';

let pushToken = null;

/** Registrér telefonen til push, når nogen er logget ind (beder om tilladelse første gang). */
async function registerPush() {
    try {
        if (Capacitor.getPlatform() === 'android') {
            await FirebaseMessaging.createChannel({ id: 'klub', name: 'Klubben', description: 'Aktiviteter, beskeder og betalinger', importance: 4, visibility: 1, vibration: true });
        }
        let perm = await FirebaseMessaging.checkPermissions();
        if (perm.receive === 'prompt' || perm.receive === 'prompt-with-rationale') perm = await FirebaseMessaging.requestPermissions();
        if (perm.receive !== 'granted') return;
        const { token } = await FirebaseMessaging.getToken();
        if (token && token !== pushToken) {
            pushToken = token;
            await api('devices', { method: 'POST', body: { token, platform: Capacitor.getPlatform() } });
        }
    } catch (e) {
        // Fx Firebase ikke sat op i denne build (mangler google-services.json / GoogleService-Info.plist)
        console.warn('Push ikke tilgængelig:', e?.message || e);
    }
}

/** Ved logud: telefonen skal ikke længere modtage beskeder til denne bruger. */
export async function unregisterPush() {
    if (!pushToken) return;
    try { await api('devices', { method: 'DELETE', body: { token: pushToken } }); } catch {}
    pushToken = null;
}

export function initNative(router) {
    document.documentElement.classList.add('native', `native-${Capacitor.getPlatform()}`);

    router.isReady().then(() => SplashScreen.hide().catch(() => {}));

    // Androids tilbageknap: tilbage i appen, eller luk appen på forsiden
    App.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack && router.currentRoute.value.path !== '/aktiviteter') router.back();
        else App.exitApp();
    });

    // Push: registrér når en bruger er logget ind
    watch(() => store.user?.id, (id) => { if (id) registerPush(); }, { immediate: true });
    FirebaseMessaging.addListener('tokenReceived', ({ token }) => {
        if (store.user && token !== pushToken) { pushToken = token; api('devices', { method: 'POST', body: { token, platform: Capacitor.getPlatform() } }).catch(() => {}); }
    }).catch(() => {});
    // Tryk på en notifikation: åbn det rigtige sted i appen
    FirebaseMessaging.addListener('notificationActionPerformed', ({ notification }) => {
        const link = notification?.data?.link;
        if (link) router.push(link);
    }).catch(() => {});
    // Besked mens appen er åben: vis den kort og opdatér tællerne
    FirebaseMessaging.addListener('notificationReceived', ({ notification }) => {
        if (notification?.title) toast(notification.title);
        refreshMe().catch(() => {});
    }).catch(() => {});

    // Links ind i appen (fx fra mails: https://klub.rpix.dk/beskeder/12) – kræver universal/app links i næste trin
    App.addListener('appUrlOpen', ({ url }) => {
        try { const u = new URL(url); router.push(u.pathname + u.search); } catch {}
    });
}

/** Åbn en side på websitet i telefonens browser (fx økonomi, som ikke er i appen). */
export async function openWeb(path) {
    const { Browser } = await import('@capacitor/browser');
    const { WEB_URL } = await import('./api');
    await Browser.open({ url: WEB_URL + path });
}

/** Filer i appen (CSV, bilag, dataudtræk): WebView'en kan ikke downloade, så filen gemmes midlertidigt og deles via telefonens Del-menu. */
export async function shareFile(blob, filename) {
    const { Filesystem, Directory } = await import('@capacitor/filesystem');
    const { Share } = await import('@capacitor/share');
    const data = await new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(String(r.result).split(',')[1]); r.onerror = rej; r.readAsDataURL(blob); });
    const { uri } = await Filesystem.writeFile({ path: filename.replace(/[\\/:*?"<>|]/g, '_'), data, directory: Directory.Cache });
    try { await Share.share({ title: filename, files: [uri] }); } catch { /* brugeren lukkede Del-menuen */ }
}

/** Statuslinjen: lyse ikoner på mørke temaer (Style.Dark), mørke ikoner på lyse temaer (Style.Light). Kaldes fra theme.js. */
export function setStatusBarDark(isDark) {
    StatusBar.setStyle({ style: isDark ? Style.Dark : Style.Light }).catch(() => {});
    try { window.KlubNative?.setNavigationBarDark?.(isDark); } catch {} // Android: navigationsknapperne nederst
    // Ældre Android (før 15) tegner ikke siden bag statuslinjen: giv den sidens baggrundsfarve
    const bg = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim();
    if (Capacitor.getPlatform() === 'android' && bg) StatusBar.setBackgroundColor({ color: bg }).catch(() => {});
}
