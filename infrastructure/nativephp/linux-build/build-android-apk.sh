#!/usr/bin/env bash
# Kinevo — NativePHP Android build on headless Linux (repro of 2026-08-27 run).
#
# Replaces the macOS-only `native:run` path with the documented pipeline:
#   container: native:run prep (bundle copy/prune, view cache) using a CLI-zip
#              polyfill for Alpine's ABI-broken ext-zip;
#   host:      laravel_bundle.zip via zip(1), then gradlew assembleDebug.
#
# Prereqs: docker compose stack up (app svc), host JDK 17 + Android SDK with
# platforms;android-35 ndk;27.0.12077973 cmake;3.22.1 build-tools, zip/unzip.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
APP="$ROOT/server/nativephp/android"
SDK="${ANDROID_HOME:-$HOME/.android-sdk}"

dc() { docker compose -f "$ROOT/infrastructure/docker-compose.yml" "$@"; }

docker cp "$(dirname "$0")/ziparchive_cli_shim.php" "$(dc ps -q app)":/tmp/ziparchive_cli_shim.php
dc exec -T app sh -c 'apk add --no-cache rsync p7zip >/dev/null 2>&1 || true'

# token substitution + sdk dir (idempotent)
sed -i 's/REPLACE_COMPILE_SDK/35/; s/REPLACE_TARGET_SDK/35/; s/REPLACE_MIN_SDK/24/' "$APP/app/build.gradle.kts"
sed -i 's/"REPLACE_APP_ID"/"com.developer.lightglowrapid"/' "$APP/app/build.gradle.kts"
sed -i 's/REPLACE_MINIFY_ENABLED/false/; s/REPLACE_SHRINK_RESOURCES/false/' "$APP/app/build.gradle.kts"
sed -i 's/debugSymbolLevel = "REPLACE_DEBUG_SYMBOLS"/debugSymbolLevel = "none"/g' "$APP/app/build.gradle.kts"
[ -f "$APP/app/proguard-rules.pro" ] && sed -i \
  's/^REPLACE_KEEP_LINE_NUMBERS$/# disabled/; s/^REPLACE_KEEP_SOURCE_FILE$/# disabled/; s/^REPLACE_OBFUSCATION_CONTROL$/-dontobfuscate/; s/^REPLACE_CUSTOM_PROGUARD_RULES$//' \
  "$APP/app/proguard-rules.pro"
grep -rlE 'val statusBarStyle = "REPLACE' "$APP/app/src/main/java" 2>/dev/null | xargs -r \
  sed -i 's/val statusBarStyle = "[^"]*"/val statusBarStyle = "auto"/' || true
printf 'sdk.dir=%s\n' "$SDK" > "$APP/local.properties"

# embedded PHP runtime static libs (arm64-v8a) — pinned to PHP 8.5 (PoC parity;
# the 8.4.24 prebuilt element extension silently drops <native:text_input>
# nodes). Install runs only when the libs are missing or the wrong version.
# The install briefly flips the lock to 8.5 and restores the host pin (8.4.24)
# afterwards so the prep below never takes the confirm-reinstall (wipe) path.
LIBPHP="$APP/app/src/main/staticLibs/arm64-v8a/libphp.a"
libphp_version="$(strings -a "$LIBPHP" 2>/dev/null | grep -m1 '^8\.[0-9]*\.[0-9]*$' || true)"
if [ "$libphp_version" != "8.5.9" ]; then
  python3 - "$APP/../.." <<'PY'
import json, sys, pathlib
lock = pathlib.Path(sys.argv[1]) / 'nativephp.lock'
lock.write_text(json.dumps({'php': {'version': '8.5', 'icu': False}}, indent=4) + '\n')
PY
  rm -rf "$APP/app/src/main/staticLibs"
  dc exec -T app sh -c 'cd /var/www/html && php -d auto_prepend_file=/tmp/ziparchive_cli_shim.php artisan native:install android -F --no-interaction' > "$ROOT/server/storage/app/native-install.log" 2>&1 || true
  dc exec -T app sh -c "chown -R $(id -u):$(id -g) /var/www/html/nativephp" >/dev/null 2>&1 || true
fi
python3 - "$APP/../.." <<'PY'
import json, sys, pathlib
lock = pathlib.Path(sys.argv[1]) / 'nativephp.lock'
lock.write_text(json.dumps({'php': {'version': '8.4.24', 'icu': False}}, indent=4) + '\n')
PY
libphp_version="$(strings -a "$LIBPHP" 2>/dev/null | grep -m1 '^8\.[0-9]*\.[0-9]*$' || true)"
if [ "$libphp_version" != "8.5.9" ]; then
  echo "FATAL: embedded runtime is PHP '${libphp_version:-unknown}', expected 8.5.9 — refusing to build" >&2
  tail -5 "$ROOT/server/storage/app/native-install.log" >&2 || true
  exit 1
fi

# bundle prep inside container (fails at gradle by design — no JVM there).
# NOTE: native:run may re-run `native:install --force` (host-PHP-vs-lock confirm
# defaults to yes under --no-interaction), which WIPES + regenerates the whole
# nativephp/android template. Every Kinevo customization below is therefore
# (re-)applied idempotently AFTER this step.
# Keep the lock on the host PHP (8.4) for the prep so the confirm-reinstall
# path is not taken; the 8.5 libs are pinned once at the top and persist.
python3 - "$APP/../.." <<'PY'
import json, sys, pathlib
lock = pathlib.Path(sys.argv[1]) / 'nativephp.lock'
lock.write_text(json.dumps({'php': {'version': '8.4.24', 'icu': False}}, indent=4) + '\n')
PY
dc exec -T app sh -c 'cd /var/www/html && php -d auto_prepend_file=/tmp/ziparchive_cli_shim.php -d memory_limit=1G artisan native:run android emulator-5554 --build debug --no-tty >/dev/null 2>&1 || true'
dc exec -T app sh -c "chown -R $(id -u):$(id -g) /var/www/html/nativephp" >/dev/null 2>&1 || true


# Kinevo renderer customizations (ui-audit UI-021 content subtree) — the vendor
# `native-ui` plugin is absent on this pipeline, so content element renderers are
# registered in-repo. Source of truth lives in infrastructure/nativephp/linux-setup/
# and is copied into the freshly regenerated template on every build.
RENDERER_SRC="$ROOT/infrastructure/nativephp/linux-setup/KinevoRendererRegistration.kt"
RENDERER_DST="$APP/app/src/main/java/com/nativephp/mobile/ui/nativerender/KinevoRendererRegistration.kt"
cp "$RENDERER_SRC" "$RENDERER_DST"
MAINACT="$APP/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt"
if ! grep -q "registerKinevoRenderers()" "$MAINACT"; then
  sed -i 's/^import com.nativephp.mobile.ui.nativerender.registerNativeChromeRenderers$/import com.nativephp.mobile.ui.nativerender.registerKinevoRenderers\nimport com.nativephp.mobile.ui.nativerender.registerNativeChromeRenderers/' "$MAINACT"
  sed -i 's/^            registerPluginRenderers()$/            registerPluginRenderers()\n            registerKinevoRenderers()/' "$MAINACT"
fi

# version bump if the prep recreated the project
if grep -q REPLACEMECODE "$APP/app/build.gradle.kts"; then
  sed -i 's/versionCode = REPLACEMECODE/versionCode = 1/; s/versionName = "REPLACEME"/versionName = "0.27.0-debug"/' "$APP/app/build.gradle.kts"
fi

# bundle_meta.json (BootPlanner NATIVE_DIRECT manifest)
dc exec -T app sh -c "chown -R $(id -u):$(id -g) /var/www/html/nativephp" >/dev/null 2>&1 || true
python3 - "$ROOT/server" <<'PY'
import json, re, sys
src = open(sys.argv[1] + '/routes/native.php').read()
routes = sorted(set(re.findall(r"Route::native\('([^']+)'", src)))
meta = {"version": "0.27.0-debug.%d" % __import__('time').time(), "version_code": __import__('time').time() // 10, "bifrost_app_id": None,
        "runtime_mode": "persistent", "entry_mode": "auto",
        "native_routes": [r if r.startswith('/') else '/' + r for r in routes]}
open(sys.argv[1] + '/nativephp/android/app/src/main/assets/bundle_meta.json', 'w').write(json.dumps(meta, indent=2))
PY

# zip the staged Laravel app from HOST (deterministic, no shim involvement).
# The prep's in-container ZipArchive can produce an empty archive here, so the
# host zip rebuild is the integrity path — always rebuild from laravel/.
ASSETS="$APP/app/src/main/assets"
cd "$APP/laravel"
rm -f "$ASSETS/laravel_bundle.zip"
# `|| true`: find(1) can exit 141 (SIGPIPE) when zip(1) closes stdin after the
# last entry; `unzip -t` below is the real integrity gate for the bundle.
find . -print0 | zip -q -X "$ASSETS/laravel_bundle.zip" -@ || true
unzip -t "$ASSETS/laravel_bundle.zip" >/dev/null

export JAVA_HOME="${JAVA_HOME:-$(dirname "$(dirname "$(readlink -f "$(which java)")")")}"
cd "$APP"
./gradlew assembleDebug --no-daemon -q
ls -la app/build/outputs/apk/debug/app-debug.apk
