<?php
/*
 * Manage sources (FR-3.2): add, edit, enable, disable or delete the RSS feeds and news APIs
 * the hourly script reads. Every change is a POST form with a CSRF token (decision 18).
 * A new or changed address is test-read first, so only working RSS feeds or news APIs are saved.
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/public.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/admin.php';
require __DIR__ . '/../../includes/feed.php';
require __DIR__ . '/../../includes/sanitize.php';

send_security_headers();
require_admin();
$db = db_connect();

// Check the name and address typed by the admin. Returns [name, url, error].
// The address is test-read only when it is new or changed ($mustRead), so a source that is failing
// today (e.g. one that blocks bots) can still be renamed or disabled.
function validate_source($db, $name, $url, $ignoreId, $mustRead)
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    $url  = trim($url);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        return [$name, $url, 'Enter a website name of up to 100 characters.'];
    }
    if (clean_url($url) === '') {
        return [$name, $url, 'The feed URL must be a full web address starting with http:// or https:// (up to 500 characters).'];
    }

    // The same address may only be listed once (unique rss_url, §3.6.2).
    $stmt = mysqli_prepare($db, 'SELECT website_name FROM rss_sources WHERE rss_url = ? AND source_id <> ?');
    mysqli_stmt_bind_param($stmt, 'si', $url, $ignoreId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($existing) {
        return [$name, $url, 'That feed URL is already in the list as "' . $existing['website_name'] . '".'];
    }

    if (!$mustRead) {
        return [$name, $url, ''];
    }

    // Read the address once now, the same way the hourly script will (Objective 1: RSS or API).
    $download = fetch_url(prepare_source_url($url));
    if (!$download['ok']) {
        return [$name, $url, 'Could not open that address (' . $download['error'] . '). Check it in a browser and try again.'];
    }
    if (parse_source($download['body']) === null) {
        return [$name, $url, 'That address opened, but it is not an RSS feed or a WordPress news API (…/wp-json/wp/v2/posts).'];
    }
    return [$name, $url, ''];
}

// ---------------------------------------------------------------------------
// Handle a submitted form, then redirect back to this page.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) filter_input(INPUT_POST, 'action');
    $id     = (int) filter_input(INPUT_POST, 'source_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status') === 'Inactive' ? 'Inactive' : 'Active';

    if ($action === 'add' || $action === 'edit') {
        $typedUrl = trim((string) filter_input(INPUT_POST, 'rss_url'));
        $mustRead = true;
        if ($action === 'edit') {
            $stmt = mysqli_prepare($db, 'SELECT rss_url FROM rss_sources WHERE source_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $mustRead = !$current || $current['rss_url'] !== $typedUrl;
        }
        list($name, $url, $error) = validate_source($db, (string) filter_input(INPUT_POST, 'website_name'),
                                                    $typedUrl, $action === 'edit' ? $id : 0, $mustRead);
        if ($error !== '') {
            set_flash('danger', $error);
            $_SESSION['form'] = ['action' => $action, 'source_id' => $id, 'website_name' => $name, 'rss_url' => $url, 'status' => $status];
        } elseif ($action === 'add') {
            $stmt = mysqli_prepare($db, 'INSERT INTO rss_sources (website_name, rss_url, status) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sss', $name, $url, $status);
            mysqli_stmt_execute($stmt);
            set_flash('success', "$name was added. Its articles will be collected in the next hourly run.");
        } else {
            $stmt = mysqli_prepare($db, 'UPDATE rss_sources SET website_name = ?, rss_url = ?, status = ? WHERE source_id = ?');
            mysqli_stmt_bind_param($stmt, 'sssi', $name, $url, $status, $id);
            mysqli_stmt_execute($stmt);
            set_flash('success', "$name was updated.");
        }
    } elseif ($action === 'toggle') {
        $stmt = mysqli_prepare($db,
            "UPDATE rss_sources SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE source_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'The source was ' . ($status === 'Active' ? 'disabled' : 'enabled') . '.');
    } elseif ($action === 'delete') {
        // ON DELETE CASCADE removes the source's articles too (decision 11).
        $stmt = mysqli_prepare($db, 'DELETE FROM rss_sources WHERE source_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        set_flash('success', mysqli_stmt_affected_rows($stmt) ? 'The source and its articles were deleted.' : 'That source no longer exists.');
    }
    header('Location: sources.php');
    exit;
}

// ---------------------------------------------------------------------------
// Show the page.
// ---------------------------------------------------------------------------
$sources = mysqli_fetch_all(mysqli_query($db,
    'SELECT s.source_id, s.website_name, s.rss_url, s.status, COUNT(a.article_id) AS articles
       FROM rss_sources s LEFT JOIN articles a ON a.source_id = s.source_id
      GROUP BY s.source_id ORDER BY s.website_name'), MYSQLI_ASSOC);
$active = count(array_filter($sources, function ($s) { return $s['status'] === 'Active'; }));

// After a failed add/edit, put back what the admin typed.
$form = isset($_SESSION['form']) ? $_SESSION['form'] : null;
unset($_SESSION['form']);
$add = ($form && $form['action'] === 'add') ? $form : ['website_name' => '', 'rss_url' => '', 'status' => 'Active'];

echo admin_header('RSS Sources', 'sources');
echo show_flash();
?>
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-plus me-1"></i> Add New Source</div>
                            <div class="card-body">
                                <form class="row g-3 align-items-end" method="post" action="sources.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add" />
                                    <div class="col-md-3"><label class="form-label" for="sName">Website name</label><input class="form-control" id="sName" name="website_name" maxlength="100" required placeholder="e.g. Punch" value="<?= e($add['website_name']) ?>" /></div>
                                    <div class="col-md-5"><label class="form-label" for="sUrl">Feed URL (RSS or API)</label><input class="form-control" id="sUrl" name="rss_url" type="url" maxlength="500" required placeholder="https://example.com/feed or https://example.com/wp-json/wp/v2/posts" value="<?= e($add['rss_url']) ?>" /></div>
                                    <div class="col-md-2"><label class="form-label" for="sStatus">Status</label><select class="form-select" id="sStatus" name="status"><option<?= $add['status'] === 'Active' ? ' selected' : '' ?>>Active</option><option<?= $add['status'] === 'Inactive' ? ' selected' : '' ?>>Inactive</option></select></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Add Source</button></div>
                                </form>
                                <div class="small text-muted mt-2">The address is checked before it is saved: it must be a working RSS feed or a WordPress news API.</div>
                            </div>
                        </div>
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-rss me-1"></i> RSS Sources <span class="text-muted small">(<?= $active ?> active of <?= count($sources) ?>)</span></div>
                            <div class="card-body table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0">
                                    <thead><tr><th>ID</th><th>Website Name</th><th>Feed URL (RSS or API)</th><th>Status</th><th class="text-end">Articles</th><th>Actions</th></tr></thead>
                                    <tbody>
<?php foreach ($sources as $s): ?>
                                        <tr>
                                            <td><?= (int) $s['source_id'] ?></td>
                                            <td><?= e($s['website_name']) ?></td>
                                            <td class="text-break"><?= e($s['rss_url']) ?></td>
                                            <td><span class="badge <?= $s['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($s['status']) ?></span></td>
                                            <td class="text-end"><?= (int) $s['articles'] ?></td>
                                            <td class="text-nowrap">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-edit
                                                        data-id="<?= (int) $s['source_id'] ?>" data-name="<?= e($s['website_name']) ?>"
                                                        data-url="<?= e($s['rss_url']) ?>" data-status="<?= e($s['status']) ?>"><i class="fas fa-edit"></i> Edit</button>
                                                <form class="d-inline" method="post" action="sources.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle" />
                                                    <input type="hidden" name="source_id" value="<?= (int) $s['source_id'] ?>" />
                                                    <input type="hidden" name="status" value="<?= e($s['status']) ?>" />
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $s['status'] === 'Active' ? 'Disable' : 'Enable' ?></button>
                                                </form>
                                                <button class="btn btn-sm btn-outline-danger" type="button" data-delete
                                                        data-id="<?= (int) $s['source_id'] ?>" data-name="<?= e($s['website_name']) ?>"
                                                        data-articles="<?= (int) $s['articles'] ?>"><i class="fas fa-trash"></i> Delete</button>
                                            </td>
                                        </tr>
<?php endforeach; ?>
<?php if (!$sources): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No sources yet. Add the first one above.</td></tr>
<?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Edit source -->
                        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                            <div class="modal-dialog"><div class="modal-content">
                                <form method="post" action="sources.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="edit" />
                                    <input type="hidden" name="source_id" id="eId" value="<?= $form && $form['action'] === 'edit' ? (int) $form['source_id'] : '' ?>" />
                                    <div class="modal-header"><h5 class="modal-title" id="editModalLabel">Edit Source</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-3"><label class="form-label" for="eName">Website name</label><input class="form-control" id="eName" name="website_name" maxlength="100" required value="<?= $form && $form['action'] === 'edit' ? e($form['website_name']) : '' ?>" /></div>
                                        <div class="mb-3"><label class="form-label" for="eUrl">Feed URL (RSS or API)</label><input class="form-control" id="eUrl" name="rss_url" type="url" maxlength="500" required value="<?= $form && $form['action'] === 'edit' ? e($form['rss_url']) : '' ?>" /></div>
                                        <div><label class="form-label" for="eStatus">Status</label><select class="form-select" id="eStatus" name="status"><option>Active</option><option<?= $form && $form['action'] === 'edit' && $form['status'] === 'Inactive' ? ' selected' : '' ?>>Inactive</option></select></div>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
                                </form>
                            </div></div>
                        </div>

                        <!-- Delete confirmation -->
                        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                            <div class="modal-dialog"><div class="modal-content">
                                <form method="post" action="sources.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete" />
                                    <input type="hidden" name="source_id" id="dId" />
                                    <div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                    <div class="modal-body">Delete <strong id="dName"></strong>? This will also delete its <strong id="dArticles"></strong> article(s). This cannot be undone.</div>
                                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete source and articles</button></div>
                                </form>
                            </div></div>
                        </div>
<?= admin_footer(['js/sources.js']) ?>
