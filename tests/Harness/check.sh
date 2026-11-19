#!/bin/bash
# Walkie live checks against the Craft 6 harness (~/Sites/plugin-testing-craft6).
#
# Broadcasts go through the harness-only `walkie-capture` connection: Laravel's real Pusher
# broadcaster for channel auth (HMAC signatures a Reverb/Pusher server would verify), with
# broadcasts appended to storage/logs/walkie-broadcasts.jsonl instead of sent. Needs
# tests/Harness/WalkieCaptureBroadcaster.php.fixture installed as app/Broadcasting/ and
# registered in the harness AppServiceProvider, and pusher/pusher-php-server in the harness.
#
# Fixtures (sections, entries, users, GraphQL schemas, the walkiefr site) are all prefixed
# `walkie` and removed at the end. Multisite checks run with WALKIE_MULTISITE=1 (adds and
# removes the walkiefr site under bin/harness-lock).
set -uo pipefail
exec < /dev/null
H=/Users/jholt/Sites/plugin-testing-craft6
SRC=$(cd "$(dirname "$0")" && pwd)
U=https://plugin-testing-craft6.ddev.site
LOG=$H/storage/logs/walkie-broadcasts.jsonl
SECRET=walkie-secret
cd "$H" || exit 2
mkdir -p resources/views/walkie
cp "$SRC/templates/channels.twig" "$SRC/templates/channels-blade.blade.php" resources/views/walkie/
cp "$SRC"/fixtures.php storage/walkie-fixtures.php
cp "$SRC"/cleanup.php storage/walkie-cleanup.php
cp "$SRC"/gql-fixtures.php storage/walkie-gql-fixtures.php
cp "$SRC"/multisite-fixtures.php storage/walkie-multisite-fixtures.php
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
tk() { ddev artisan tinker --execute "$1" 2>/dev/null | grep -v WARNING | tail -1; }
# Walkie's settings for the checks live in config/craft/walkie.php (written under the harness lock):
# other sessions write project config concurrently, which can revert settings saved there.
# settings "['key' => value, …]" merges into it.
STATE=$(mktemp)
echo '{}' > "$STATE"
settings() {
  python3 -I - "$STATE" "$1" <<'PY' || return 1
import json,sys,re
state=json.load(open(sys.argv[1]))
php=sys.argv[2].strip()[1:-1]
for k,v in re.findall(r"'(\w+)'\s*=>\s*(\[[^\]]*\]|'[^']*'|null|true|false)", php):
    if v.startswith('['): state[k]=[x for x in re.findall(r"'([^']*)'", v)]
    elif v.startswith("'"): state[k]=v[1:-1]
    else: state[k]={'null':None,'true':True,'false':False}[v]
json.dump(state,open(sys.argv[1],'w'))
PY
  python3 -I - "$STATE" "$H/config/craft/walkie.php.tmp" <<'PY'
import json,sys
state=json.load(open(sys.argv[1]))
def php(v):
    if v is None: return 'null'
    if v is True: return 'true'
    if v is False: return 'false'
    if isinstance(v,list): return '['+', '.join(php(x) for x in v)+']'
    return "'"+str(v).replace("'","\\'")+"'"
open(sys.argv[2],'w').write("<?php\n\n// Written by craft-walkie/tests/Harness/check.sh; removed when it finishes.\nreturn [\n"+''.join(f"    '{k}' => {php(v)},\n" for k,v in state.items())+"];\n")
PY
  "$H"/bin/harness-lock mv "$H/config/craft/walkie.php.tmp" "$H/config/craft/walkie.php" || return 1
  sleep 2 # PHP-FPM's opcache revalidates files every 2 s
  echo 1
}
# Validation goes through Craft's own settings save (project config).
validate() { tk "\$p=CraftCms\\Cms\\Support\\Facades\\Plugins::getPlugin('walkie'); echo CraftCms\\Cms\\Support\\Facades\\Plugins::savePluginSettings(\$p, $1) ? 1 : json_encode(\$p->getSettings()->errors()->all(), JSON_UNESCAPED_UNICODE);"; }
E='CraftCms\Cms\Entry\Elements\Entry'
SAVE='CraftCms\Cms\Support\Facades\Elements::saveElement($e)'
# entry <slug> <php run on $e> → saves it
entry() { tk "\$e=$E::find()->slug('$1')->status(null)->one(); $2 echo $SAVE ? 'saved' : json_encode(\$e->errors()->all());"; }
reset_log() { : > "$LOG"; }
# cap <python expression over `m` (list of {event, channels, payload})>
cap() { python3 -I -c "
import json,sys
m=[json.loads(l) for l in open('$LOG') if l.strip()] if __import__('os').path.exists('$LOG') else []
# Other sessions save entries in the shared harness too: keep only this run's fixtures.
ids={int(i) for i in '${IDS:-}'.split() if i}
m=[x for x in m if (x['payload'].get('section') in ('walkieNews', 'walkieSecret') or x['payload'].get('volume') == 'walkieFiles') and x['payload'].get('id') in ids]
def j(*a): return '/'.join(str(x) for x in a)
print($1)"; }
summary='" ".join(x["event"]+"@"+",".join(x["channels"]) for x in m)'

# ---- Setup ----
ok "use the capture connection, immediate delivery" "$(settings "['connection' => 'walkie-capture', 'queueBroadcasts' => false, 'queue' => null, 'presence' => false, 'publicSections' => [], 'broadcastSaves' => true, 'broadcastDeletes' => true, 'broadcastStatusChanges' => true, 'registerAuthRoute' => true]")" "1"
# run <fixture file> under the harness lock, retrying while other sessions change project config.
run() { local out i; for i in 1 2 3 4 5 6; do out=$("$H"/bin/harness-lock ddev artisan tinker --execute "require 'storage/$1';" 2>/dev/null | grep -v WARNING | tail -1); case "$out" in *Stale*|'') sleep 3;; *) break;; esac; done; echo "$out"; }
FX=$(run walkie-fixtures.php)
id() { echo "$FX" | python3 -I -c "import json,sys; print(json.load(sys.stdin)['entries']['$1'])"; }
NEWS=$(echo "$FX" | python3 -I -c "import json,sys; print(json.load(sys.stdin)['news'])")
LIVE=$(id walkie-live); OFF=$(id walkie-off); FUT=$(id walkie-future); OWN=$(id walkie-own); SEC=$(id walkie-secret)
ASSET=$(echo "$FX" | python3 -I -c "import json,sys; print(json.load(sys.stdin)['asset'])")
IDS="$LIVE $OFF $FUT $OWN $SEC $ASSET"
tk 'echo Illuminate\Support\Facades\DB::table("jobs")->where("queue","walkie")->delete();' >/dev/null
ok "fixtures" "$([ -n "$LIVE" ] && [ -n "$SEC" ] && echo yes)" "yes" || { echo "$FX"; exit 1; }
ok "public sections: walkieNews" "$(settings "['publicSections' => ['$NEWS']]")" "1"
ok "an unknown public section is refused" "$(validate "['publicSections' => ['00000000-0000-0000-0000-000000000000']]" | grep -c 'Unknown section')" "1"
ok "an unknown broadcast connection is refused" "$(validate "['connection' => 'nope']" | grep -c 'no “nope” broadcast connection')" "1"

# ---- Broadcasting element changes ----
reset_log
ok "save a live public entry" "$(entry walkie-live '$e->title = "Walkie Live 2";')" "saved"
ok "→ private saved on the entry + section feed, public saved on both" "$(cap "$summary")" "saved@private-walkie.default.entry.$LIVE,private-walkie.default.entries.walkieNews saved@walkie.default.entry.$LIVE,walkie.default.entries.walkieNews"
ok "→ private payload: ids, statuses, no content" "$(cap '",".join(sorted(m[0]["payload"].keys()))')/$(cap 'j(m[0]["payload"]["status"], m[0]["payload"]["previousStatus"], m[0]["payload"]["section"], m[0]["payload"]["site"])')" "dateUpdated,event,id,previousStatus,section,site,status,type/live/live/walkieNews/default"
ok "→ public payload: ids only" "$(cap 'json.dumps(m[1]["payload"], sort_keys=True)')" "{\"event\": \"saved\", \"id\": $LIVE, \"section\": \"walkieNews\", \"site\": \"default\", \"type\": \"entry\"}"
ok "→ the title is nowhere in a broadcast" "$(grep -c 'Walkie Live 2' "$LOG")" "0"

reset_log; entry walkie-off '$e->title = "Walkie Off 2";' >/dev/null
ok "a disabled entry: private only" "$(cap "$summary")" "saved@private-walkie.default.entry.$OFF,private-walkie.default.entries.walkieNews"
reset_log; entry walkie-future '$e->title = "Walkie Future 2";' >/dev/null
ok "a pending (future) entry: private only" "$(cap "$summary")/$(cap 'm[0]["payload"]["status"]')" "saved@private-walkie.default.entry.$FUT,private-walkie.default.entries.walkieNews/pending"
reset_log; entry walkie-secret '$e->title = "Walkie Secret 2";' >/dev/null
ok "an entry in a non-public section: private only" "$(cap "$summary")" "saved@private-walkie.default.entry.$SEC,private-walkie.default.entries.walkieSecret"

reset_log; entry walkie-live '$e->enabled = false;' >/dev/null
ok "disabling a live entry: private saved (live → disabled), public removed" "$(cap 'j(m[0]["event"], m[0]["payload"]["previousStatus"], m[0]["payload"]["status"], m[1]["event"], ",".join(m[1]["channels"]))')" "saved/live/disabled/removed/walkie.default.entry.$LIVE,walkie.default.entries.walkieNews"
reset_log; entry walkie-live '$e->enabled = true;' >/dev/null
ok "re-enabling it: public saved again" "$(cap 'j(m[0]["payload"]["previousStatus"], m[0]["payload"]["status"], m[1]["event"])')" "disabled/live/saved"

reset_log
NEW=$(tk "\$s=CraftCms\\Cms\\Support\\Facades\\Sections::getSectionByHandle('walkieNews'); \$e=new $E; \$e->sectionId=\$s->id; \$e->typeId=\$s->getEntryTypes()[0]->id; \$e->title='Walkie New'; \$e->slug='walkie-new'; $SAVE; echo \$e->id;")
IDS="$IDS $NEW"
ok "creating an entry: private created, public saved" "$(cap 'j(m[0]["event"], "previousStatus" in m[0]["payload"], m[1]["event"])')" "created/False/saved"
reset_log
ok "delete it" "$(tk "\$e=$E::find()->id($NEW)->one(); echo CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(\$e) ? 'deleted' : 'failed';")" "deleted"
ok "→ private deleted (was live), public removed" "$(cap 'j(m[0]["event"], m[0]["payload"]["previousStatus"], "status" in m[0]["payload"], m[1]["event"])')" "deleted/live/False/removed"
reset_log
ok "restore it" "$(tk "\$e=$E::find()->id($NEW)->trashed()->one(); echo CraftCms\\Cms\\Support\\Facades\\Elements::restoreElement(\$e) ? 'restored' : 'failed';")" "restored"
ok "→ private restored, public saved" "$(cap 'j(m[0]["event"], m[1]["event"])')" "restored/saved"
tk "\$e=$E::find()->id($NEW)->one(); CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(\$e, true); echo 1;" >/dev/null

reset_log
tk "\$c=$E::find()->id($LIVE)->one(); \$e=CraftCms\\Cms\\Support\\Facades\\Drafts::createDraft(\$c, 1); \$e->title='Draft title'; $SAVE; echo 1;" >/dev/null
ok "drafts are never broadcast" "$(cap 'len(m)')" "0"
reset_log
tk "\$e=$E::find()->id($LIVE)->one(); \$e->resaving=true; $SAVE; echo 1;" >/dev/null
ok "bulk resaves are never broadcast" "$(cap 'len(m)')" "0"

reset_log
tk "Illuminate\\Support\\Facades\\DB::transaction(function () { \$e=$E::find()->id($LIVE)->one(); \$e->title='Twice 1'; $SAVE; \$e->title='Twice 2'; $SAVE; }); echo 1;" >/dev/null
ok "two saves in one transaction: one message per channel set, after the commit" "$(cap 'len(m)')" "2"
reset_log
tk "try { Illuminate\\Support\\Facades\\DB::transaction(function () { \$e=$E::find()->id($LIVE)->one(); \$e->title='Rolled back'; $SAVE; throw new RuntimeException('no'); }); } catch (RuntimeException) {} echo 1;" >/dev/null
ok "a rolled-back save broadcasts nothing" "$(cap 'len(m)')" "0"

reset_log
ok "save an asset" "$(tk "\$e=CraftCms\\Cms\\Asset\\Elements\\Asset::find()->id($ASSET)->one(); \$e->title='Walkie asset'; echo $SAVE ? 'saved' : 'failed';")" "saved"
ok "→ private saved on the asset + volume feed, never public" "$(cap "$summary")/$(cap 'j(m[0]["payload"]["volume"], m[0]["payload"]["type"])')" "saved@private-walkie.default.asset.$ASSET,private-walkie.default.assets.walkieFiles/walkieFiles/asset"

settings "['broadcastSaves' => false]" >/dev/null
reset_log; entry walkie-live '$e->title = "Walkie Live 3";' >/dev/null
ok "saves switched off → nothing" "$(cap 'len(m)')" "0"
settings "['broadcastSaves' => true]" >/dev/null

# Scheduled status changes: Craft fires no event when a post date passes.
reset_log
tk "Illuminate\\Support\\Facades\\DB::table('entries')->where('id', $FUT)->update(['postDate' => now()->subMinutes(5)->utc()->format('Y-m-d H:i:s')]); echo 1;" >/dev/null
ok "a post date passing silently broadcasts nothing" "$(cap 'len(m)')" "0"
ddev artisan craft:walkie:scheduled --since="-10 minutes" >/dev/null 2>&1
ok "craft:walkie:scheduled → private status (pending → live), public saved" "$(cap 'j(m[0]["event"], m[0]["payload"]["previousStatus"], m[0]["payload"]["status"], m[1]["event"])')" "status/pending/live/saved"
reset_log; ddev artisan craft:walkie:scheduled >/dev/null 2>&1
ok "the next run doesn't repeat it" "$(cap 'len(m)')" "0"
tk "Illuminate\\Support\\Facades\\DB::table('entries')->where('id', $FUT)->update(['expiryDate' => now()->subMinute()->utc()->format('Y-m-d H:i:s')]); echo 1;" >/dev/null
reset_log; ddev artisan craft:walkie:scheduled --since="-3 minutes" >/dev/null 2>&1
ok "an expiry date passing → status (live → expired), public removed" "$(cap 'j(m[0]["payload"]["previousStatus"], m[0]["payload"]["status"], m[1]["event"])')" "live/expired/removed"
entry walkie-future '$e->postDate = new DateTime("+1 day"); $e->expiryDate = null;' >/dev/null

# Queued delivery
settings "['queueBroadcasts' => true, 'queue' => 'walkie']" >/dev/null
reset_log; entry walkie-live '$e->title = "Walkie Live 4";' >/dev/null
ok "queued: nothing sent until the job runs" "$(cap 'len(m)')/$(tk 'echo Illuminate\Support\Facades\DB::table("jobs")->where("queue","walkie")->where("payload","like","%walkieNews%")->count();')" "0/2"
ddev artisan queue:work --queue=walkie --stop-when-empty >/dev/null 2>&1
ok "queued: sent by the worker" "$(cap "$summary")" "saved@private-walkie.default.entry.$LIVE,private-walkie.default.entries.walkieNews saved@walkie.default.entry.$LIVE,walkie.default.entries.walkieNews"
settings "['queueBroadcasts' => false, 'queue' => null]" >/dev/null

# A broken broadcaster never breaks saving.
settings "['connection' => 'reverb']" >/dev/null
ok "broadcaster misconfigured (reverb without keys) → the save still works" "$(entry walkie-live '$e->title = "Walkie Live 5";')" "saved"
settings "['connection' => 'walkie-capture']" >/dev/null

# ---- Channel authorisation over /broadcasting/auth (Pusher protocol) ----
login() { # login <username> → cookie jar. Craft allows 5 logins a minute per IP, shared with other sessions: wait it out.
  local j x code tries=0; j=$(mktemp)
  while :; do
    curl -sk -c "$j" -o /dev/null "$U/admin/login"
    x=$(awk '$6=="XSRF-TOKEN"{print $7}' "$j" | tail -1 | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')
    code=$(curl -sk -b "$j" -c "$j" -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H "X-XSRF-TOKEN: $x" -X POST "$U/actions/users/login" --data-urlencode "loginName=$1" --data-urlencode "password=${2:-walkiepassword}")
    [ "$code" = 429 ] && [ $tries -lt 8 ] || break
    tries=$((tries+1)); sleep 10
  done
  [ "$code" = 200 ] || echo "login $1 failed: $code" >&2
  echo "$j"
}
xsrf() { awk '$6=="XSRF-TOKEN"{print $7}' "$1" | tail -1 | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))'; }
# auth <jar|-> <channel> → "<code> <valid-signature?>"
auth() {
  local jar=$1 ch=$2 out code
  if [ "$jar" = - ]; then jar=$(mktemp); curl -sk -c "$jar" -o /dev/null "$U/admin/login"; fi
  out=$(curl -sk -b "$jar" -c "$jar" -w '\n%{http_code}' -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf "$jar")" -X POST "$U/broadcasting/auth" --data-urlencode "socket_id=1234.5678" --data-urlencode "channel_name=$ch")
  code=$(echo "$out" | tail -1)
  if [ "$code" = 200 ]; then
    echo "$out" | sed '$d' | python3 -I -c "
import json,sys,hmac,hashlib
r=json.load(sys.stdin); ch='$ch'
msg='1234.5678:'+ch+((':'+r['channel_data']) if 'channel_data' in r else '')
want='walkie-key:'+hmac.new(b'$SECRET', msg.encode(), hashlib.sha256).hexdigest()
print('200', 'signed' if r.get('auth')==want else 'BAD-SIGNATURE', json.loads(r['channel_data'])['user_info']['name'] if 'channel_data' in r else '')" | sed 's/ $//'
  else echo "$code"; fi
}
VIEWER=$(login walkie_viewer); OWNJ=$(login walkie_own); NONE=$(login walkie_none); ASSETS=$(login walkie_assets)
P=private-walkie.default
ok "guest → private entry channel refused" "$(auth - $P.entry.$LIVE)" "403"
ok "guest → public channel names need no auth (and get none)" "$(auth - walkie.default.entry.$LIVE)" "403"
ok "viewer → live entry (signed for this socket + channel)" "$(auth "$VIEWER" $P.entry.$LIVE)" "200 signed"
ok "viewer → disabled entry (CP viewers see disabled entries)" "$(auth "$VIEWER" $P.entry.$OFF)" "200 signed"
ok "viewer → section feed" "$(auth "$VIEWER" $P.entries.walkieNews)" "200 signed"
ok "viewer → entry in a section without permission" "$(auth "$VIEWER" $P.entry.$SEC)" "403"
ok "viewer → feed of a section without permission" "$(auth "$VIEWER" $P.entries.walkieSecret)" "403"
ok "own-entries user → own entry" "$(auth "$OWNJ" $P.entry.$OWN)" "200 signed"
ok "own-entries user → someone else's entry (no viewPeerEntries)" "$(auth "$OWNJ" $P.entry.$LIVE)" "403"
ok "own-entries user → section feed (shows everyone's entries)" "$(auth "$OWNJ" $P.entries.walkieNews)" "403"
ok "member without permissions → live public entry's private channel" "$(auth "$NONE" $P.entry.$LIVE)" "403"
ok "unknown site handle" "$(auth "$VIEWER" private-walkie.nope.entry.$LIVE)" "403"
ok "malformed id" "$(auth "$VIEWER" $P.entry.1e3)/$(auth "$VIEWER" $P.entry.0$LIVE)" "403/403"
ok "unknown section" "$(auth "$VIEWER" $P.entries.nope)" "403"
DRAFT=$(tk "echo $E::find()->draftOf($LIVE)->status(null)->one()?->id;")
ok "a draft's id" "$(auth "$VIEWER" $P.entry.$DRAFT)" "403"
ok "asset viewer → volume feed" "$(auth "$ASSETS" $P.assets.walkieFiles)" "200 signed"
ok "entry viewer → volume feed" "$(auth "$VIEWER" $P.assets.walkieFiles)" "403"
ok "asset viewer → an asset" "$(auth "$ASSETS" $P.asset.$ASSET)/$(auth "$VIEWER" $P.asset.$ASSET)" "200 signed/403"
ok "presence while switched off" "$(auth "$VIEWER" presence-walkie.default.entry.$LIVE)" "403"
settings "['presence' => true]" >/dev/null
ok "presence: who's here (name only)" "$(auth "$VIEWER" presence-walkie.default.entry.$LIVE)" "200 signed Viewer"
ok "presence: still needs view permission" "$(auth "$OWNJ" presence-walkie.default.entry.$LIVE)" "403"
ok "presence on a section feed" "$(auth "$VIEWER" presence-walkie.default.entries.walkieNews)" "403"
ok "presence payload has no email" "$(curl -sk -b "$VIEWER" -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf "$VIEWER")" -X POST "$U/broadcasting/auth" --data-urlencode socket_id=1.2 --data-urlencode "channel_name=presence-walkie.default.entry.$LIVE" | grep -c '@walkie.test')" "0"
ok "presence off again" "$(settings "['presence' => false]")" "1"
ok "a channel that isn't Walkie's (no app channels registered)" "$(auth "$VIEWER" private-App.Models.User.1)" "403"
ok "no CSRF token → refused" "$(curl -sk -b "$VIEWER" -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -X POST "$U/broadcasting/auth" --data-urlencode socket_id=1.2 --data-urlencode "channel_name=$P.entry.$LIVE")" "419"
ok "no socket id → refused" "$(curl -sk -b "$VIEWER" -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf "$VIEWER")" -X POST "$U/broadcasting/auth" --data-urlencode "channel_name=$P.entry.$LIVE")" "403"
ok "GET isn't an auth endpoint" "$(curl -sk -b "$VIEWER" -o /dev/null -w '%{http_code}' "$U/broadcasting/auth?socket_id=1.2&channel_name=$P.entry.$LIVE" | grep -c 200)" "0"
tk "\$u=CraftCms\\Cms\\User\\Elements\\User::find()->username('walkie_viewer')->one(); CraftCms\\Cms\\Support\\Facades\\UserPermissions::saveUserPermissions(\$u->id, []); echo 1;" >/dev/null
ok "permission revoked → refused on the next subscribe" "$(auth "$VIEWER" $P.entry.$LIVE)" "403"
run walkie-fixtures.php >/dev/null

# ---- Twig + Blade ----
pg() { curl -sk ${3:+-b "$3"} "$U/walkie/$1?$2"; }
field() { grep -o "<p id=\"$1\">[^<]*" | sed "s/<p id=\"$1\">//"; }
for t in channels channels-blade; do
  ok "$t: public entry channel" "$(pg $t slug=walkie-live | field public)/$(pg $t slug=walkie-live | field private)" "walkie.default.entry.$LIVE/walkie.default.entry.$LIVE"
  ok "$t: no public channel for a non-public section" "$(pg $t slug=walkie-secret | field public)/$(pg $t slug=walkie-secret | field private)" "/walkie.default.entry.$SEC"
  ok "$t: section feeds (public only when switched on)" "$(pg $t slug=walkie-live | field feed)/$(pg $t slug=walkie-live | field privateFeed)/$(pg $t slug=walkie-live | field secretFeed)" "walkie.default.entries.walkieNews/walkie.default.entries.walkieNews/"
  ok "$t: presence only when switched on" "$(pg $t slug=walkie-live | field presence)" ""
  ok "$t: volume feed" "$(pg $t slug=walkie-live | field volume)" "walkie.default.assets.walkieFiles"
  ok "$t: unknown site → nothing" "$(pg $t 'slug=walkie-live&site=nope' | field feed)$(pg $t 'slug=walkie-live&site=nope' | field config)$(pg $t 'slug=walkie-live&site=nope' | grep -c walkieConfig)" "0"
  ok "$t: guest config has no CSRF token, no secret" "$(pg $t slug=walkie-live | field config | sed 's/&quot;/"/g' | python3 -I -c 'import json,sys; c=json.load(sys.stdin); print(c["site"], c["csrfToken"], c["signedIn"], c["echo"])')/$(pg $t slug=walkie-live | grep -c "$SECRET")" "default None False None/0"
  ok "$t: signed-in config carries a CSRF token" "$(pg $t slug=walkie-live "$VIEWER" | field config | sed 's/&quot;/"/g' | python3 -I -c 'import json,sys; c=json.load(sys.stdin); print(c["signedIn"], len(c["csrfToken"] or "") > 20)')" "True True"
  ok "$t: script() loads the client" "$(pg $t slug=walkie-live | grep -o 'src="[^"]*walkie.js?v=[a-f0-9]*"' | grep -c 'vendor/justinholtweb/craft-walkie')" "1"
done
ok "Twig and Blade render the same" "$(pg channels slug=walkie-live | grep '<p id' | md5)" "$(pg channels-blade slug=walkie-live | grep '<p id' | md5)"
ok "the client is published" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/vendor/justinholtweb/craft-walkie/walkie.js")" "200"

# ---- GraphQL ----
run walkie-gql-fixtures.php >/dev/null
gq() { curl -sk -H "Authorization: Bearer $(printf 'walkie%s' "$1" | awk '{ s=$0; while (length(s) < 32) s=s "0"; print s }')" -H 'Content-Type: application/json' \
  -X POST "$U/actions/graphql/api" -d "$(python3 -I -c 'import json,sys; print(json.dumps({"query": sys.argv[1]}))' "$2")" | python3 -I -c "
import json,sys
r=json.load(sys.stdin)
def j(*a): return '/'.join(str(x) for x in a)
if r.get('errors'): print('ERR:'+r['errors'][0]['message'])
else:
  d=r['data']; print($3)"; }
ok "gql: public section feeds the schema can read" "$(gq full '{ walkieChannels { site sections { section channel } } }' 'json.dumps(d["walkieChannels"], separators=(",",":"))')" '{"site":"default","sections":[{"section":"walkieNews","channel":"walkie.default.entries.walkieNews"}]}'
ok "gql: entry channel for a live public entry" "$(gq full "{ walkieChannels { entryChannel(id: $LIVE) } }" 'd["walkieChannels"]["entryChannel"]')" "walkie.default.entry.$LIVE"
ok "gql: no channel for disabled, pending or non-public entries" "$(gq full "{ a: walkieChannels { entryChannel(id: $OFF) } b: walkieChannels { entryChannel(id: $FUT) } c: walkieChannels { entryChannel(id: $SEC) } }" 'j(d["a"]["entryChannel"], d["b"]["entryChannel"], d["c"]["entryChannel"])')" "None/None/None"
ok "gql: no Echo config for a server-only driver" "$(gq full '{ walkieChannels { echo { key } } }' 'd["walkieChannels"]["echo"]')" "None"
ok "gql: schema without the section sees no feed and no entry channel" "$(gq nosection "{ walkieChannels { sections { section } entryChannel(id: $LIVE) } }" 'j(len(d["walkieChannels"]["sections"]), d["walkieChannels"]["entryChannel"])')" "0/None"
ok "gql: schema without Walkie has no walkieChannels" "$(gq nowalkie '{ walkieChannels { site } }' 'd')" 'ERR:Cannot query field "walkieChannels" on type "Query".'
ok "gql: a site the schema can't read" "$(gq full '{ walkieChannels(site: "nope") { site } }' 'd')" "ERR:This schema can’t read the “nope” site."
ok "gql: no private channel or secret in the schema" "$(gq full '{ __type(name: "WalkieEcho") { fields { name } } }' '",".join(f["name"] for f in d["__type"]["fields"])')" "broadcaster,key,cluster,wsHost,wsPort,wssPort,forceTLS"

# ---- CP + console ----
CP=$(login admin claudepassword)
ok "CP: settings page" "$(curl -sk -b "$CP" -o /dev/null -w '%{http_code}' "$U/admin/settings/plugins/walkie")" "200"
ok "console: status shows the connection and the auth route, no secret" "$(ddev artisan craft:walkie:status 2>/dev/null | grep -cE 'walkie-capture|Walkie')/$(ddev artisan craft:walkie:status 2>/dev/null | grep -c "$SECRET")" "2/0"

# ---- Multisite (WALKIE_MULTISITE=1: adds the walkiefr site, removes it after) ----
if [ "${WALKIE_MULTISITE:-0}" = 1 ]; then
  ok "multisite: add walkiefr" "$(run walkie-multisite-fixtures.php)" "ok"
  ALL=$(login walkie_all)
  reset_log; entry walkie-live '$e->title = "Walkie Live 6";' >/dev/null
  ok "multisite: one save → a message per site, each on its own site's channels" "$(cap '" ".join(sorted(set(c for x in m for c in x["channels"] if c.startswith("walkie."))))')" "walkie.default.entries.walkieNews walkie.default.entry.$LIVE walkie.walkiefr.entries.walkieNews walkie.walkiefr.entry.$LIVE"
  reset_log
  tk "\$e=$E::find()->id($LIVE)->site('walkiefr')->status(null)->one(); \$e->enabledForSite=false; echo $SAVE ? 'saved' : 'failed';" >/dev/null
  ok "multisite: disabling for one site → removed on that site, still live on the other" "$(cap '" ".join(sorted(x["event"]+"@"+x["channels"][0] for x in m if not x["channels"][0].startswith("private-")))')" "removed@walkie.walkiefr.entry.$LIVE saved@walkie.default.entry.$LIVE"
  tk "\$e=$E::find()->id($LIVE)->site('walkiefr')->status(null)->one(); \$e->enabledForSite=true; echo $SAVE ? 'saved' : 'failed';" >/dev/null
  ok "multisite: viewer without walkiefr access → its channels refused" "$(auth "$VIEWER" private-walkie.walkiefr.entry.$LIVE)/$(auth "$VIEWER" private-walkie.walkiefr.entries.walkieNews)" "403/403"
  ok "multisite: viewer with walkiefr access" "$(auth "$ALL" private-walkie.walkiefr.entry.$LIVE)/$(auth "$ALL" private-walkie.walkiefr.entries.walkieNews)" "200 signed/200 signed"
  ok "multisite: templates per site" "$(pg channels 'slug=walkie-live&site=walkiefr' | field public)/$(pg channels-blade 'slug=walkie-live&site=walkiefr' | field feed)" "walkie.walkiefr.entry.$LIVE/walkie.walkiefr.entries.walkieNews"
  run walkie-gql-fixtures.php >/dev/null
  ok "multisite: gql per site" "$(gq full '{ walkieChannels(site: "walkiefr") { sections { channel } } }' 'd["walkieChannels"]["sections"][0]["channel"]')" "walkie.walkiefr.entries.walkieNews"
  ok "multisite: French translations" "$(tk "app()->setLocale('fr'); echo CraftCms\\Cms\\t('Public sections', category: 'walkie');")" "Sections publiques"
fi

# ---- Cleanup ----
ok "cleanup" "$(run walkie-cleanup.php)" "ok"
# Leave Walkie quiet in the shared harness: other sessions' saves shouldn't queue broadcasts.
"$H"/bin/harness-lock rm -f "$H/config/craft/walkie.php"
validate "['connection' => null, 'queueBroadcasts' => false, 'publicSections' => [], 'presence' => false, 'broadcastSaves' => false, 'broadcastDeletes' => false, 'broadcastStatusChanges' => false]" >/dev/null
rm -f "$LOG" storage/walkie-*.php resources/views/walkie/channels.twig resources/views/walkie/channels-blade.blade.php
rmdir resources/views/walkie 2>/dev/null
ok "no walkie fixtures left" "$(tk 'echo CraftCms\Cms\User\Elements\User::find()->username("walkie_*")->status(null)->count() + (CraftCms\Cms\Support\Facades\Sections::getSectionByHandle("walkieNews") ? 1 : 0) + (CraftCms\Cms\Support\Facades\Sites::getSiteByHandle("walkiefr", true) ? 1 : 0);')" "0"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
