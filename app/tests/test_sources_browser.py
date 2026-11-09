# Browser test for admin/sources.php (Playwright + Chromium). Serve app/public_html on 127.0.0.1:8100,
# use the test database "trustfeed_test" with the test admin, then: python3 app/tests/test_sources_browser.py
# It changes the test data (adds, disables and deletes sources).
from playwright.sync_api import sync_playwright
import subprocess
B='http://127.0.0.1:8100/admin/'
def sql(q): return subprocess.run(['mariadb','-N','trustfeed_test','-e',q],capture_output=True,text=True).stdout.strip()
dialogs=[]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path='/opt/pw-browsers/chromium', args=['--no-proxy-server'])
    pg=b.new_page(viewport={'width':1280,'height':900})
    pg.on('dialog', lambda d:(dialogs.append(d.message), d.dismiss()))
    pg.goto(B+'login.php'); pg.fill('#inputUsername','admin'); pg.fill('#inputPassword','TrustFeed-Test-2026'); pg.click('button[type=submit]')
    pg.goto(B+'sources.php')
    def add(name,url):
        pg.evaluate("document.getElementById('sUrl').type='text'; document.querySelectorAll('[required]').forEach(e=>e.removeAttribute('required'))")
        pg.fill('#sName',name); pg.fill('#sUrl',url); pg.click('form [type=submit]:has-text("Add Source")'); pg.wait_for_load_state()
        return pg.inner_text('.alert').strip()
    print('a. not a web address  :', add('Bad','ftp://example.com/feed'))
    print('b. duplicate          :', add('Punch again','https://punchng.com/feed/'))
    print('c. a page, not a feed :', add('Channels home','https://www.channelstv.com/'))
    pg.screenshot(path='/tmp/s_error.png')
    print('d. unreachable        :', add('Nowhere','https://feeds.example.invalid/rss'))
    print('e. valid new feed     :', add('The Babylon Bee','https://babylonbee.com/feed'))
    print('j. attack in the name :', add('<script>alert("x")</script>Bee','https://dailypost.ng/feed/?copy=1'))
    print('   name stored as     :', sql("SELECT website_name FROM rss_sources WHERE rss_url LIKE '%copy=1'"))
    # f. rename the Guardian (its feed is blocked) without changing the address
    g=sql("SELECT source_id FROM rss_sources WHERE website_name='The Guardian Nigeria'")
    pg.click(f'[data-edit][data-id="{g}"]'); pg.wait_for_timeout(500); pg.fill('#eName','Guardian Nigeria'); pg.click('#editModal [type=submit]'); pg.wait_for_load_state()
    print('f. rename blocked feed:', pg.inner_text('.alert').strip())
    # g. disable the Bee
    bee=sql("SELECT source_id FROM rss_sources WHERE website_name='The Babylon Bee'")
    pg.click(f'tr:has(td:text-is("The Babylon Bee")) button:has-text("Disable")'); pg.wait_for_load_state()
    print('g. disable            :', pg.inner_text('.alert').strip(), '| status now', sql(f"SELECT status FROM rss_sources WHERE source_id={bee}"))
    # i. delete a source that has articles
    t=sql("SELECT source_id FROM rss_sources WHERE website_name='Test feed (made-up items)'")
    before=sql(f"SELECT COUNT(*) FROM articles WHERE source_id={t}")
    pg.click(f'[data-delete][data-id="{t}"]'); pg.wait_for_timeout(600)
    print('i. delete pop-up says :', pg.inner_text('#deleteModal .modal-body'))
    pg.screenshot(path='/tmp/s_delete_modal.png')
    pg.click('#deleteModal [type=submit]'); pg.wait_for_load_state()
    print('   after delete       :', pg.inner_text('.alert').strip(), f'| articles before {before}, after', sql(f"SELECT COUNT(*) FROM articles WHERE source_id={t}"), '| source rows', sql(f"SELECT COUNT(*) FROM rss_sources WHERE source_id={t}"))
    x=sql("SELECT source_id FROM rss_sources WHERE rss_url LIKE '%copy=1'")
    pg.click(f'[data-delete][data-id="{x}"]'); pg.wait_for_timeout(500); pg.click('#deleteModal [type=submit]'); pg.wait_for_load_state()
    pg.screenshot(path='/tmp/s_list.png', full_page=True)
    # k. a forged request from another site: no CSRF token
    r=pg.request.post(B+'sources.php', form={'action':'delete','source_id':bee})
    print('k. delete without token: HTTP', r.status, '| Bee still there:', sql(f"SELECT COUNT(*) FROM rss_sources WHERE source_id={bee}"))
    b.close()
print('alerts that ran:', dialogs)
