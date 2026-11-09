# Browser test for admin/articles.php (Playwright + Chromium). Serve app/public_html on 127.0.0.1:8100,
# use the test database "trustfeed_test" with the test admin, then: python3 app/tests/test_articles_browser.py
# It changes the test data (re-flags and deletes one article).
from playwright.sync_api import sync_playwright
import subprocess, re
B='http://127.0.0.1:8100/admin/'
def sql(q): return subprocess.run(['mariadb','-N','trustfeed_test','-e',q],capture_output=True,text=True).stdout.strip()
dialogs=[]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path='/opt/pw-browsers/chromium', args=['--no-proxy-server'])
    pg=b.new_page(viewport={'width':1280,'height':900})
    pg.on('dialog', lambda d:(dialogs.append(d.message), d.dismiss()))
    pg.goto(B+'login.php'); pg.fill('#inputUsername','admin'); pg.fill('#inputPassword','TrustFeed-Test-2026'); pg.click('button[type=submit]')
    pg.goto(B+'articles.php'); pg.wait_for_selector('[data-reflag]')
    print('1. all articles     :', pg.inner_text('#articleCount'), '| DB:', sql('SELECT COUNT(*) FROM articles'), '| rows on page 1:', pg.locator('#articleRows tr').count())
    pg.fill('#articleSearch','naira'); pg.wait_for_timeout(900)
    print('2. live search naira:', pg.inner_text('#articleCount'), 'match(es) | DB:', sql("SELECT COUNT(*) FROM articles a JOIN rss_sources s USING(source_id) WHERE a.title LIKE '%naira%' OR s.website_name LIKE '%naira%'"))
    pg.fill('#articleSearch','Punch'); pg.wait_for_timeout(900)
    print('3. search by source :', pg.inner_text('#articleCount'), '| DB Punch:', sql("SELECT COUNT(*) FROM articles a JOIN rss_sources s USING(source_id) WHERE s.website_name='Punch'"))
    pg.fill('#articleSearch','%'); pg.wait_for_timeout(900)
    print('4. search for "%"   :', pg.inner_text('#articleCount'), '(should be the number of titles containing a real % sign:', sql("SELECT COUNT(*) FROM articles WHERE title LIKE '%\\\\%%'") + ')')
    # pick a Real article with an AI score from the current test data
    title=sql("SELECT title FROM articles WHERE verification_status='Real' AND confidence_score IS NOT NULL ORDER BY article_id LIMIT 1")
    pg.fill('#articleSearch',title[:40]); pg.wait_for_timeout(900)
    aid=pg.get_attribute('[data-reflag]','data-reflag')
    print('5. before re-flag   :', sql(f"SELECT verification_status, confidence_score, LEFT(verification_reason,30), emotional_manipulation, bias_level FROM articles WHERE article_id={aid}"))
    pg.select_option(f'[data-reflag="{aid}"]','Misinformation'); pg.wait_for_timeout(1000)
    print('   message          :', pg.inner_text('#articleMessage'))
    print('   after re-flag    :', sql(f"SELECT verification_status, confidence_score, verification_reason, is_clickbait, emotional_manipulation, bias_level FROM articles WHERE article_id={aid}"))
    pg.screenshot(path='/tmp/ar_reflag.png')
    pub=b.new_page(); pub.goto(f'http://127.0.0.1:8100/article.php?id={aid}')
    print('6. public page shows:', pub.inner_text('.alert').strip(), '|', pub.inner_text('.card-body').split('\n')[0])
    pg.select_option(f'[data-reflag="{aid}"]','Pending Verification'); pg.wait_for_timeout(1000)
    q=sql("SELECT GROUP_CONCAT(article_id) FROM (SELECT article_id FROM articles WHERE verification_status='Pending Verification' AND confidence_score IS NULL ORDER BY (is_clickbait=1) DESC, publication_date DESC LIMIT 200) x")
    print('7. set to Pending   : back in the AI queue:', aid in q.split(','))
    pg.click(f'[data-delete="{aid}"]'); pg.wait_for_timeout(600)
    print('8. delete pop-up    :', pg.inner_text('#deleteModal .modal-body'))
    pg.screenshot(path='/tmp/ar_delete.png')
    pg.click('#confirmDelete'); pg.wait_for_timeout(1000)
    print('   after delete     :', pg.inner_text('#articleMessage'), '| in DB:', sql(f"SELECT COUNT(*) FROM articles WHERE article_id={aid}"))
    other=sql('SELECT MIN(article_id) FROM articles')
    r=pg.request.post(B+'api/article_action.php', form={'action':'delete','id':other}, headers={'X-Requested-With':'fetch'})
    print('9. delete, no token : HTTP', r.status, f'| article {other} still there:', sql(f'SELECT COUNT(*) FROM articles WHERE article_id={other}'))
    pg.fill('#articleSearch',''); pg.wait_for_timeout(900); pg.click('[data-goto-page="2"]'); pg.wait_for_timeout(800)
    print('10. page 2 active   :', pg.inner_text('#articlePager .active'))
    sid=[c['value'] for c in pg.context.cookies() if c['name']=='trustfeed_admin'][0]
    import time; f=subprocess.run(['php','-r','echo session_save_path();'],capture_output=True,text=True).stdout+'/sess_'+sid
    s=open(f).read(); open(f,'w').write(re.sub(r'last_activity\|i:\d+','last_activity|i:%d'%(time.time()-31*60),s))
    pg.fill('#articleSearch','x'); pg.wait_for_timeout(1500)
    print('11. after 31 idle minutes, typing in search goes to:', pg.url)
    b.close()
print('alerts that ran:', dialogs)
