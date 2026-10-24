package id.naraya.sales;

import android.content.Intent;
import android.content.pm.PackageInfo;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import androidx.core.content.FileProvider;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/**
 * Updates the app without leaving it (resources/js/app-update.js on the server): downloads the new APK into the app's
 * cache with progress events, then hands it to Android's installer — the one "Pasang" tap Android always asks for
 * outside the Play Store. Only an https APK from example.com, and only one of this very app (same package name), is
 * installed; Android itself refuses an APK signed with another key.
 */
@CapacitorPlugin(name = "AppUpdater")
public class AppUpdaterPlugin extends Plugin {

    private final ExecutorService io = Executors.newSingleThreadExecutor();

    private File apk() {
        File dir = new File(getContext().getCacheDir(), "updates");
        dir.mkdirs();
        return new File(dir, "naraya-update.apk");
    }

    /** download({ url }): progress events { percent }, resolves once the APK is complete and checked. */
    @PluginMethod
    public void download(PluginCall call) {
        String url = call.getString("url", "");
        Uri u = Uri.parse(url);
        if (!"https".equals(u.getScheme()) || u.getHost() == null || !u.getHost().endsWith("example.com")) {
            call.reject("Alamat unduhan tidak dikenal.");
            return;
        }
        io.execute(() -> {
            File out = apk();
            HttpURLConnection c = null;
            try {
                c = (HttpURLConnection) new URL(url).openConnection();
                c.setConnectTimeout(15000);
                c.setReadTimeout(30000);
                if (c.getResponseCode() != 200) throw new Exception("HTTP " + c.getResponseCode());
                long total = c.getContentLengthLong();
                try (InputStream in = c.getInputStream(); OutputStream os = new FileOutputStream(out)) {
                    byte[] buf = new byte[64 * 1024];
                    long done = 0;
                    int last = -1;
                    for (int n; (n = in.read(buf)) > 0; ) {
                        os.write(buf, 0, n);
                        done += n;
                        int percent = total > 0 ? (int) (done * 100 / total) : -1;
                        if (percent != last) {
                            last = percent;
                            JSObject p = new JSObject();
                            p.put("percent", percent);
                            notifyListeners("progress", p);
                        }
                    }
                }
                PackageInfo info = getContext().getPackageManager().getPackageArchiveInfo(out.getAbsolutePath(), 0);
                if (info == null || !getContext().getPackageName().equals(info.packageName)) {
                    out.delete();
                    call.reject("File unduhan bukan aplikasi ini.");
                    return;
                }
                call.resolve();
            } catch (Exception e) {
                out.delete();
                call.reject("Unduhan gagal. Periksa sinyal lalu coba lagi.", e);
            } finally {
                if (c != null) c.disconnect();
            }
        });
    }

    /**
     * install(): opens Android's installer for the downloaded APK. Resolves { needsPermission: true } after opening the
     * "Instal aplikasi tak dikenal" setting instead, when this app may not install yet — the page asks to tap again.
     */
    @PluginMethod
    public void install(PluginCall call) {
        File f = apk();
        if (!f.exists()) {
            call.reject("Unduh pembaruan dulu.");
            return;
        }
        // first, so a wrong FileProvider setup shows up as an error here and not only after the permission is granted
        Uri uri = FileProvider.getUriForFile(getContext(), getContext().getPackageName() + ".fileprovider", f);
        JSObject result = new JSObject();
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !getContext().getPackageManager().canRequestPackageInstalls()) {
            Intent settings = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:" + getContext().getPackageName()));
            settings.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(settings);
            result.put("needsPermission", true);
            call.resolve(result);
            return;
        }
        Intent intent = new Intent(Intent.ACTION_VIEW);
        intent.setDataAndType(uri, "application/vnd.android.package-archive");
        intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
        getContext().startActivity(intent);
        result.put("needsPermission", false);
        call.resolve(result);
    }
}
