package dk.surfhorsens.klub;

import android.content.res.Configuration;
import android.os.Bundle;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    /**
     * Android-WebView'en melder ikke altid telefonens mørke tilstand videre til siden (prefers-color-scheme).
     * Siden spørger derfor appen direkte: window.KlubNative.isDark(). Skifter telefonen tilstand, mens appen er åben,
     * får siden besked med hændelsen 'nativecolorscheme' (se resources/js/theme.js).
     */
    public class KlubNative {
        @JavascriptInterface
        public boolean isDark() {
            return (getResources().getConfiguration().uiMode & Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES;
        }
    }

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        WebView wv = bridge.getWebView();
        wv.addJavascriptInterface(new KlubNative(), "KlubNative");
        wv.reload(); // så grænsefladen også findes på den side, der allerede er ved at indlæses
    }

    @Override
    public void onConfigurationChanged(Configuration newConfig) {
        super.onConfigurationChanged(newConfig);
        WebView wv = bridge != null ? bridge.getWebView() : null;
        if (wv != null) wv.post(() -> wv.evaluateJavascript("window.dispatchEvent(new Event('nativecolorscheme'))", null));
    }
}
