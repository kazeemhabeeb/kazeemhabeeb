<?php
/*
 * Manage articles (FR-3.3): search, manually re-flag, or permanently delete ingested articles.
 * The table is loaded and changed with AJAX (js/articles.js -> api/articles.php, api/article_action.php).
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/public.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/admin.php';

send_security_headers();
require_admin();

echo admin_header('Articles', 'articles');
?>
                        <div id="articleMessage" aria-live="polite"></div>
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-newspaper me-1"></i> Ingested Articles <span class="text-muted small">(<span id="articleCount">0</span>)</span></div>
                            <div class="card-body">
                                <div class="row mb-3"><div class="col-md-6">
                                    <form class="input-group" id="articleSearchForm" role="search">
                                        <input class="form-control" id="articleSearch" type="search" maxlength="100" placeholder="Search by title or source..." aria-label="Search articles" />
                                        <button class="btn btn-primary" type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
                                    </form>
                                </div></div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead><tr><th>ID</th><th>Title</th><th>Source</th><th>Publication Date</th><th>Verification Status</th><th class="text-end">Views</th><th>Re-flag</th><th>Delete</th></tr></thead>
                                        <tbody id="articleRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                                    </table>
                                </div>
                                <div class="mt-3" id="articlePager"></div>
                                <div class="small text-muted mt-2">Re-flagging replaces the AI's verdict, so its score, reason and flags are cleared. Choosing <em>Pending Verification</em> sends the article back to the AI in the next hourly run.</div>
                            </div>
                        </div>

                        <!-- Delete confirmation -->
                        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                            <div class="modal-dialog"><div class="modal-content">
                                <div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                <div class="modal-body">Permanently delete article #<span id="deleteId"></span> (<em id="deleteTitle"></em>)? This cannot be undone.</div>
                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDelete">Yes, delete</button></div>
                            </div></div>
                        </div>
<?= admin_footer(['js/articles.js']) ?>
