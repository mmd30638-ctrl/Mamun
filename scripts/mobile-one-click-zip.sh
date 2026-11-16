#!/usr/bin/env bash
# Pack the sanitized one-click ZIP and publish a tap-to-download URL.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION.txt")"
ZIP_NAME="Aponshop_${VERSION}_FULL_STACK.zip"
UA="Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/126.0.0.0 Mobile Safari/537.36 mobile-one-click-zip"

bash "$ROOT/tools/build_release.sh"
ZIP_PATH="$ROOT/deliverables/${ZIP_NAME}"
test -f "$ZIP_PATH"

BYTES="$(wc -c < "$ZIP_PATH" | tr -d ' ')"
SHA="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
echo "Packed ${ZIP_NAME} (${BYTES} bytes)"
echo "SHA256: ${SHA}"

python3 - "$ZIP_PATH" "$ZIP_NAME" "$BYTES" "$SHA" <<'PY'
import json, re, subprocess, sys, urllib.request

zip_path, zip_name, packed, sha = sys.argv[1:5]
ua = "Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/126.0.0.0 Mobile Safari/537.36"

def curl(args, timeout=90):
    cmd = ["curl", "-sS", "-A", ua, "--max-time", str(timeout)] + args
    try:
        p = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout + 5)
    except subprocess.TimeoutExpired:
        return 1, "", "timeout"
    return p.returncode, (p.stdout or "").strip(), (p.stderr or "").strip()

def verify(url):
    req = urllib.request.Request(url, method="HEAD", headers={"User-Agent": ua})
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            status = res.status
            ctype = res.headers.get("Content-Type", "")
            clen = res.headers.get("Content-Length", "")
    except Exception as e:
        print(f"HEAD fail {url}: {e}", file=sys.stderr)
        return None
    req2 = urllib.request.Request(url, headers={"User-Agent": ua, "Range": "bytes=0-3"})
    try:
        with urllib.request.urlopen(req2, timeout=30) as res:
            magic = res.read(4)
    except Exception as e:
        print(f"GET fail {url}: {e}", file=sys.stderr)
        return None
    pk = magic[:2] == b"PK"
    print(f"HEAD {status}  content-type={ctype}  content-length={clen}")
    print("ZIP magic PK:", pk)
    if status not in (200, 206) or not pk:
        return None
    kb = int(packed) / 1024
    size_bn = f"{kb/1024:.1f} MB" if kb >= 1024 else f"{kb:.0f} KB"
    return {
        "url": url,
        "status": 200,
        "ctype": ctype,
        "size": size_bn,
        "bytes": packed,
        "sha256": sha,
        "filename": zip_name,
        "pk": True,
    }

hosts = [
    (["-F", "reqtype=fileupload", "-F", "time=72h",
      f"fileToUpload=@{zip_path};type=application/zip;filename={zip_name}",
      "https://litterbox.catbox.moe/resources/internals/api.php"], "litterbox", "এই লিংক প্রায় ৭২ ঘণ্টা কাজ করবে।"),
    (["-F", "reqtype=fileupload",
      f"fileToUpload=@{zip_path};type=application/zip;filename={zip_name}",
      "https://catbox.moe/user/api.php"], "catbox", "এই লিংক স্থায়ী (catbox)।"),
    (["-F", f"file=@{zip_path};filename={zip_name};type=application/zip", "https://0x0.st"], "0x0", "এই লিংক 0x0.st-এ হোস্ট; বড় ফাইল কিছুদিন থাকে।"),
]

for args, name, ttl in hosts:
    code, out, err = curl(args)
    print(f"{name}:", out or err, file=sys.stderr)
    if out.startswith("https://"):
        hosted = out.split()[-1]
        payload = verify(hosted)
        if payload:
            payload["ttl"] = ttl
            print("URL:", hosted)
            print("TTL:", ttl)
            print("---USER---")
            print(json.dumps(payload, ensure_ascii=False))
            sys.exit(0)

print("Upload failed", file=sys.stderr)
sys.exit(1)
PY
