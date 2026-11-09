#!/bin/bash
# Run with the site served locally:  php -S 127.0.0.1:8100 -t app/public_html
# then: bash app/tests/test_login.sh   (needs the test admin "admin" / "TrustFeed-Test-2026")
B=${1:-http://127.0.0.1:8100/admin}
J=$(mktemp)
rm -f "$J"
C() { curl -s --noproxy '*' -c "$J" -b "$J" "$@"; }
sid() { grep trustfeed_admin "$J" | awk '{print $7}'; }
echo "1. Dashboard without logging in:"; C -o /dev/null -w "   HTTP %{http_code} -> %{redirect_url}\n" $B/dashboard.php
echo "2. Wrong password:"; C -d "username=admin&password=wrong-password" $B/login.php | grep -o 'Wrong username or password[^<]*' | sed 's/^/   /'
C -o /dev/null $B/login.php; BEFORE=$(sid)
echo "3. Right password:"; C -D - -o /dev/null -d "username=admin&password=TrustFeed-Test-2026" $B/login.php | grep -i -E "^location|^set-cookie" | sed -E 's/trustfeed_admin=[^;]*/trustfeed_admin=(id)/; s/^/   /'
AFTER=$(sid); [ -n "$BEFORE" ] && [ "$BEFORE" != "$AFTER" ] && echo "   session ID changed at login: yes" || echo "   session ID changed at login: NO ($BEFORE / $AFTER)"
echo "4. Dashboard after login:"; C $B/dashboard.php | grep -o -E 'You are logged in[^<]*' | sed 's/^/   /'
echo "5. Logout by plain link (GET):"; C -o /dev/null -w "   HTTP %{http_code}\n" $B/logout.php
echo "6. Logout POST with a forged token:"; C -o /dev/null -w "   HTTP %{http_code}\n" -d "csrf_token=forged" $B/logout.php
TOKEN=$(C $B/dashboard.php | grep -o 'name="csrf-token" content="[^"]*"' | sed 's/.*content="//;s/"//')
echo "7. Logout with the real token:"; C -o /dev/null -w "   HTTP %{http_code} -> %{redirect_url}\n" -d "csrf_token=$TOKEN" $B/logout.php
echo "8. Dashboard after logout:"; C -o /dev/null -w "   HTTP %{http_code} -> %{redirect_url}\n" $B/dashboard.php
echo "9. Idle timeout:"; C -o /dev/null -d "username=admin&password=TrustFeed-Test-2026" $B/login.php
F=$(php -r 'echo session_save_path() ?: sys_get_temp_dir();')/sess_$(sid)
OLD=$(( $(date +%s) - 31*60 )); sed -i -E "s/last_activity\|i:[0-9]+/last_activity|i:$OLD/" "$F"
C -o /dev/null -w "   after 31 idle minutes: HTTP %{http_code} -> %{redirect_url}\n" $B/dashboard.php
echo "10. Message shown:"; C $B/login.php?expired=1 | grep -o 'You were logged out after[^<]*' | sed 's/^/   /'
echo "11. Session cookie value never appears in the page HTML:"; C -o /dev/null -d "username=admin&password=TrustFeed-Test-2026" $B/login.php; C $B/dashboard.php | grep -c "$(sid)" | sed 's/^/   matches: /'
