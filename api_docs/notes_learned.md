# Studio Notes (Herding Cats)

- Ajax lock: always include `lock: true` in client calls; the framework fills a UUID. Without it you get “lock should be a UUID (false)”.
- Request types: `AT_utf8` is not present on this stack; prefer `AT_alphanum` (or another supported type in the scaffold’s constants).
- Stock images: ebg.stock prepends `g_gamethemeurl` to item image paths. Use relative `img/...` or manually prefix once. Avoid double-prefixing.
- Substitution templates: if a log string uses `${player_name}` etc., provide those keys in notification args. Otherwise you’ll see “missing substitution argument”.
- One‑way deploy: `./deploy.sh --watch` syncs from `src/` → `~/BGA_mount` only; changes on the mount are overwritten.
