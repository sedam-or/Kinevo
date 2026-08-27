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

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
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
  sed -i 's/val statusBarStyle = "[^"]*"/val statusBarStyle = "auto"/'
printf 'sdk.dir=%s\n' "$SDK" > "$APP/local.properties"

# embedded PHP runtime static libs (arm64-v8a)
if [ ! -f "$APP/app/src/main/staticLibs/arm64-v8a/libphp.a" ]; then
  dc exec -T app sh -c 'cd /var/www/html && php -d auto_prepend_file=/tmp/ziparchive_cli_shim.php artisan native:install android -F --no-interaction'
fi

# bundle prep inside container (fails at gradle by design — no JVM there)
dc exec -T app sh -c 'cd /var/www/html && php -d auto_prepend_file=/tmp/ziparchive_cli_shim.php -d memory_limit=1G artisan native:run android emulator-5554 --build debug --no-tty >/dev/null 2>&1 || true'

# version bump if the prep recreated the project
if grep -q REPLACEMECODE "$APP/app/build.gradle.kts"; then
  sed -i 's/versionCode = REPLACEMECODE/versionCode = 1/; s/versionName = "REPLACEME"/versionName = "0.27.0-debug"/' "$APP/app/build.gradle.kts"
fi

# bundle_meta.json (BootPlanner NATIVE_DIRECT manifest)
python3 - "$ROOT/server" <<'PY'
import json, re, sys
src = open(sys.argv[1] + '/routes/native.php').read()
routes = sorted(set(re.findall(r"Route::native\('([^']+)'", src)))
meta = {"version": "0.27.0-debug", "version_code": 1, "bifrost_app_id": None,
        "runtime_mode": "persistent", "entry_mode": "auto",
        "native_routes": [r if r.startswith('/') else '/' + r for r in routes]}
open(sys.argv[1] + '/nativephp/android/app/src/main/assets/bundle_meta.json', 'w').write(json.dumps(meta, indent=2))
PY

# zip the staged Laravel app from HOST (deterministic, no shim involvement)
cd "$APP/laravel"
rm -f "$APP/app/src/main/assets/laravel_bundle.zip"
find . -print0 | zip -q -X "$APP/app/src/main/assets/laravel_bundle.zip" -@
unzip -t "$APP/app/src/main/assets/laravel_bundle.zip" >/dev/null

export JAVA_HOME="${JAVA_HOME:-$(dirname "$(dirname "$(readlink -f "$(which java)")")")}"
cd "$APP"
./gradlew assembleDebug --no-daemon -q
ls -la app/build/outputs/apk/debug/app-debug.apk
