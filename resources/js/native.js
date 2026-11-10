// Kun i mobilappen: kobling til telefonens funktioner via Capacitor
import { App } from '@capacitor/app';
import { StatusBar, Style } from '@capacitor/status-bar';
import { SplashScreen } from '@capacitor/splash-screen';
import { Capacitor } from '@capacitor/core';

export function initNative(router) {
    document.documentElement.classList.add('native', `native-${Capacitor.getPlatform()}`);

    StatusBar.setStyle({ style: Style.Dark }).catch(() => {});
    if (Capacitor.getPlatform() === 'android') StatusBar.setBackgroundColor({ color: '#7e5a8c' }).catch(() => {});
    router.isReady().then(() => SplashScreen.hide().catch(() => {}));

    // Androids tilbageknap: tilbage i appen, eller luk appen på forsiden
    App.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack && router.currentRoute.value.path !== '/aktiviteter') router.back();
        else App.exitApp();
    });

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
