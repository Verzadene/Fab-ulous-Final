<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/CommissionRepository.php';

if (empty($_SESSION['user'])) {
    header('Location: ../login/login.php');
    exit;
}

if (empty($_SESSION['mfa_verified'])) {
    header('Location: ../login/verify_mfa.php');
    exit;
}

$commissionRepo = new CommissionRepository('db_connect');
$userId   = (int) $_SESSION['user']['id'];
$username = $_SESSION['user']['username'];

$myAvatarUrl = get_current_user_avatar();

$pageMsg = '';
$pageMsgIsError = false;

if (isset($_GET['payment'])) {
    $paymentState = $_GET['payment'];
    if ($paymentState === 'success') {
        // Mark the payment paid immediately on return from PayMongo checkout.
        // This handles the public/test API where webhooks are not delivered.
        // If the webhook arrives later, processWebhookPayment() will skip the
        // already-paid row due to its idempotency guard.
        require_once __DIR__ . '/PaymentRepository.php';
        $returnCommissionId = (int)($_GET['commission_id'] ?? 0);
        if ($returnCommissionId > 0) {
            $payRepo = new PaymentRepository('db_connect');
            $marked  = $payRepo->markPaymentPaidOnReturn($returnCommissionId, $userId, $username);
            $pageMsg = $marked
                ? 'Payment completed! Your commission payment status has been updated.'
                : 'Payment checkout completed. Your payment status will be reflected shortly.';
        } else {
            $pageMsg = 'Payment checkout completed. Your payment status will be reflected shortly.';
        }
    } elseif ($paymentState === 'cancelled') {
        $pageMsg = 'Payment checkout was cancelled.';
        $pageMsgIsError = true;
    } elseif ($paymentState === 'error') {
        $pageMsg = $_GET['message'] ?? 'Payment could not be started.';
        $pageMsgIsError = true;
    }
}

// ── GET: list THIS user's commissions only (JSON API) ─────────────
// Always passes isAdmin=false so only the requester's own rows are returned.
// Admins who want to manage platform-wide commissions must use admin/admin.php.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    header('Content-Type: application/json');
    $data = $commissionRepo->getCommissionsWithStats(false, $userId);
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// ── GET: fetch a single commission for editing (must be pending + owner) ─
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'fetch_for_edit') {
    header('Content-Type: application/json');
    $editId = (int)($_GET['commission_id'] ?? 0);
    if ($editId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid commission ID.']);
        exit;
    }
    $row = $commissionRepo->getCommissionForEdit($editId, $userId);
    if ($row === null) {
        // Intentionally vague — don't reveal whether the commission exists
        // but belongs to someone else vs. simply isn't pending.
        echo json_encode(['success' => false, 'error' => 'This commission cannot be edited. It may no longer be pending or does not belong to your account.']);
        exit;
    }
    echo json_encode(['success' => true, 'commission' => $row]);
    exit;
}

// ── POST: edit existing pending commission (owner, pending status only) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_commission') {
    header('Content-Type: application/json');
    $editId = (int)($_POST['commission_id'] ?? 0);
    $title       = $_POST['title']       ?? '';
    $description = $_POST['description'] ?? '';
    $file        = $_FILES['attachment']  ?? null;

    if ($editId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid commission ID.']);
        exit;
    }

    $result = $commissionRepo->processEditCommission(
        $editId,
        $userId,
        $title,
        $description,
        ($file && $file['size'] > 0) ? $file : null,
        $username
    );
    echo json_encode($result);
    exit;
}

// ── POST: submit new commission (any authenticated user, any role) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_commission') {
    header('Content-Type: application/json');
    $title       = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $file        = $_FILES['attachment'] ?? null;

    $firstName = $_SESSION['user']['first_name'] ?? '';
    $lastName  = $_SESSION['user']['last_name']  ?? '';

    $result = $commissionRepo->processSubmitCommission($userId, $title, $description, $file, $username, $firstName, $lastName);

    echo json_encode($result);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FABulous - Commissions</title>
  <link rel="icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link rel="shortcut icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="post.css"/>
  <link rel="stylesheet" href="commissions.css"/>
</head>
<body>
  <?php
  $navActive = 'commissions';
  $navRoot = '../';
  require __DIR__ . '/../includes/app_nav.php';
  ?>

  <div class="dashboard-body commissions-dashboard">
    <div class="commissions-layout">

      <!-- ── Overview Card ── -->
      <section class="commission-hero side-card">
        <div class="commission-hero-copy">
          <p class="side-card-kicker">Orders</p>
          <h1>Commission Overview</h1>
          <p>Track the status of your FABulous service requests in one place.</p>
        </div>
        <button class="comm-submit-toggle" id="submitToggle" onclick="toggleSubmitForm()">
          + Submit a Commission
        </button>
      </section>

      <!-- ── Payment / submission messages ── -->
      <?php if ($pageMsg): ?>
        <div id="commissionPageMsg" class="commission-page-msg <?php echo $pageMsgIsError ? 'commission-msg-error' : 'commission-msg-ok'; ?>">
          <?php echo htmlspecialchars($pageMsg); ?>
        </div>
      <?php else: ?>
        <div id="commissionPageMsg" class="commission-page-msg" style="display:none;"></div>
      <?php endif; ?>

      <!-- ── Stats Row ── -->
      <section class="commission-stats">
        <article class="commission-stat">
          <span>Total Requests</span>
          <strong id="statTotal">—</strong>
        </article>
        <article class="commission-stat">
          <span>Pending</span>
          <strong id="statPending">—</strong>
        </article>
        <article class="commission-stat">
          <span>In Progress</span>
          <strong id="statActive">—</strong>
        </article>
        <article class="commission-stat">
          <span>Completed</span>
          <strong id="statCompleted">—</strong>
        </article>
        <article class="commission-stat">
          <span>Total Value</span>
          <strong id="statSpent">—</strong>
        </article>
      </section>

      <!-- ── Submit Form (collapsible, available to every role) ── -->
      <section class="commission-submit-card side-card" id="submitFormCard" style="display:none;">
        <div class="commission-table-head">
          <div>
            <p class="side-card-kicker">New Request</p>
            <h2>Submit a Commission</h2>
          </div>
          <button class="comm-close-btn" onclick="toggleSubmitForm()" title="Close form">&times;</button>
        </div>
        <form id="submitCommissionForm" enctype="multipart/form-data" class="commission-submit-form" onsubmit="submitCommission(event)">
          <div class="commission-field">
            <label class="commission-label">Title <span class="commission-required">*</span></label>
            <input type="text" name="title" class="commission-input" placeholder="Short title for your request" maxlength="255" required/>
          </div>
          <div class="commission-field">
            <label class="commission-label">Description <span class="commission-required">*</span></label>
            <textarea name="description" class="commission-textarea" placeholder="Describe what you need — dimensions, material, quantity, deadline…" rows="5" maxlength="2000" required></textarea>
          </div>
          <div class="commission-field">
            <label class="commission-label">Attachment <span class="commission-required">* (PDF, STL or DWG, max 10 MB)</span></label>
            <input type="file" name="attachment" id="commissionAttachment" class="commission-file-input" accept=".pdf,.stl,.dwg"/>
          </div>
          <button type="submit" class="thread-send" style="align-self:flex-start;">Submit Request</button>
        </form>
      </section>

      <!-- ── Commission Table (requester's own commissions only) ── -->
      <section class="commission-table-card side-card">
        <div class="commission-table-head">
          <div class="comm-head-left">
            <p class="side-card-kicker">Updates</p>
            <div class="comm-head-title-row">
              <h2>My Commissions</h2>
              <span class="thread-badge" id="completedBadge">0 Completed</span>
            </div>
          </div>
          <div class="comm-search-wrap">
            <svg class="comm-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="commSearch" class="comm-search-input" placeholder="Search by title, status…" oninput="filterTable(this.value)"/>
          </div>
        </div>

        <div class="messages-empty commission-empty" id="emptyState" style="display:none;">
          <strong>No commission requests yet</strong>
          <span id="emptyStateMsg">Use the button above to submit your first commission request.</span>
        </div>

        <div class="commission-table-wrap" id="tableWrap" style="display:none;">
          <table class="commission-table" id="commTable">
            <thead>
              <tr>
                <th>S/N</th>
                <th>Title</th><th>Description</th><th>Status</th>
                <th>Amount</th><th>Payment</th><th>Submitted</th><th>Admin Note</th><th>File</th><th>Actions</th>
              </tr>
            </thead>
            <tbody id="commissionsTableBody">
            </tbody>
          </table>
        </div>

        <div class="comm-count-row" id="commCountRow" style="display:none;">
          Showing <strong id="commCountShown">0</strong> of <strong id="commCountTotal">0</strong> commissions
        </div>
      </section>

    </div>
  </div>

  <!-- ── Edit Commission Modal (pending-only) ── -->
  <div id="editCommissionModal" style="display:none; position:fixed; inset:0; z-index:1050; background:rgba(0,0,0,.55); align-items:center; justify-content:center;">
    <div style="background:var(--card-bg, #1e1e2e); border-radius:12px; padding:2rem; width:min(560px,94vw); max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 8px 40px rgba(0,0,0,.45);">
      <button onclick="closeEditModal()" title="Close" style="position:absolute;top:.9rem;right:1rem;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted,#aaa);">&times;</button>
      <p class="side-card-kicker" style="margin-bottom:.25rem;">Edit Request</p>
      <h2 style="margin:0 0 1.25rem;">Edit Commission</h2>
      <div id="editModalError" class="commission-page-msg commission-msg-error" style="display:none; margin-bottom:1rem;"></div>
      <form id="editCommissionForm" enctype="multipart/form-data" onsubmit="submitEditCommission(event)">
        <input type="hidden" name="action" value="edit_commission"/>
        <input type="hidden" name="commission_id" id="editCommissionId"/>
        <div class="commission-field">
          <label class="commission-label">Title <span class="commission-required">*</span></label>
          <input type="text" name="title" id="editTitle" class="commission-input" maxlength="255" required/>
        </div>
        <div class="commission-field">
          <label class="commission-label">Description <span class="commission-required">*</span></label>
          <textarea name="description" id="editDescription" class="commission-textarea" rows="5" maxlength="2000" required></textarea>
        </div>
        <div class="commission-field">
          <label class="commission-label">Replace Attachment <span style="font-weight:400;opacity:.7;">(optional — PDF, STL or DWG, max 10 MB)</span></label>
          <input type="file" name="attachment" id="editAttachment" class="commission-file-input" accept=".pdf,.stl,.dwg"/>
        </div>
        <button type="submit" class="thread-send" style="align-self:flex-start; margin-top:.5rem;">Save Changes</button>
      </form>
    </div>
  </div>
    <script>
    const burgerBtn     = document.getElementById('burgerBtn');
    const navDrawer     = document.getElementById('navDrawer');
    const drawerOverlay = document.getElementById('drawerOverlay');

    function toggleDrawer(forceState) {
      const shouldOpen = typeof forceState === 'boolean'
        ? forceState : !navDrawer.classList.contains('open');
      navDrawer.classList.toggle('open', shouldOpen);
      drawerOverlay.classList.toggle('show', shouldOpen);
      document.body.classList.toggle('menu-open', shouldOpen);
      burgerBtn?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }

    function closeDrawer() { toggleDrawer(false); }

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeDrawer();
    });

    let allCommissions = [];

    function esc(val) {
      const div = document.createElement('div');
      div.textContent = String(val ?? '');
      return div.innerHTML;
    }

    function formatDate(dateStr) {
      const d = new Date(dateStr.replace(/-/g, '/'));
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatMoney(amount) {
      return '₱' + new Intl.NumberFormat('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(amount));
    }

    function toggleSubmitForm() {
      const card = document.getElementById('submitFormCard');
      const btn  = document.getElementById('submitToggle');
      if (!card) return;
      const isOpen = card.style.display !== 'none';
      card.style.display = isOpen ? 'none' : 'block';
      if (btn) btn.classList.toggle('active', !isOpen);
    }

    async function loadCommissions() {
      try {
        const response = await fetch('commissions.php?action=list');
        const data = await response.json();
        if (data.success) {
          allCommissions = data.commissions || [];
          updateStats(data.stats);
          renderCommissions(allCommissions);
        }
      } catch (error) {
        console.error('Error loading commissions:', error);
      }
    }

    function updateStats(stats) {
      document.getElementById('statTotal').textContent     = new Intl.NumberFormat().format(stats.total);
      document.getElementById('statPending').textContent   = new Intl.NumberFormat().format(stats.pending);
      document.getElementById('statActive').textContent    = new Intl.NumberFormat().format(stats.active);
      document.getElementById('statCompleted').textContent = new Intl.NumberFormat().format(stats.completed);
      document.getElementById('statSpent').innerHTML       = '&#8369;' + new Intl.NumberFormat('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(stats.spent);
      const badge = document.getElementById('completedBadge');
      if (badge) badge.textContent = new Intl.NumberFormat().format(stats.completed) + ' Completed';
    }

    function filterTable(query) {
      if (!query) { renderCommissions(allCommissions); return; }
      const q = query.toLowerCase();
      const filtered = allCommissions.filter(c =>
        (c.title || '').toLowerCase().includes(q)
        || (c.description || '').toLowerCase().includes(q)
        || (c.status || '').toLowerCase().includes(q)
        || String(c.commissionID).includes(q)
      );
      renderCommissions(filtered);
    }

    function renderCommissions(commissions) {
      const tbody      = document.getElementById('commissionsTableBody');
      const emptyState = document.getElementById('emptyState');
      const tableWrap  = document.getElementById('tableWrap');
      const countRow   = document.getElementById('commCountRow');

      if (!commissions || commissions.length === 0) {
        emptyState.style.display = 'flex';
        tableWrap.style.display  = 'none';
        if (countRow) countRow.style.display = 'none';
        return;
      }

      emptyState.style.display = 'none';
      tableWrap.style.display  = 'block';

      if (countRow) {
        countRow.style.display = 'block';
        document.getElementById('commCountShown').textContent = commissions.length;
        document.getElementById('commCountTotal').textContent = allCommissions.length;
      }

      tbody.innerHTML = commissions.map((c, idx) => {
        const sn          = idx + 1;
        const title       = esc(c.title || 'Untitled');
        const desc        = esc(c.description || '').substring(0, 96);
        const statusClass = 'status-' + (c.status || '').toLowerCase().replace(/ /g, '-');
        const amountNum   = Number(c.amount || 0);

        let paymentHtml = '';
        if (c.payment_status === 'paid') {
          paymentHtml = '<span class="payment-badge paid">Paid</span>';
        } else if (c.payment_status) {
          paymentHtml = `<span class="payment-badge pending">${esc(c.payment_status.charAt(0).toUpperCase() + c.payment_status.slice(1))}</span>`;
        }

        let fileHtml = '<span class="comm-empty-cell">—</span>';
        if (c.attachment_url) {
          fileHtml = `<a href="../${esc(c.attachment_url)}" target="_blank" class="commission-file-link">&#128196; View</a>`;
        }

        let payCellHtml = `<span class="comm-amount-text">${esc(formatMoney(amountNum))}</span>`;
        if (amountNum > 0 && c.payment_status !== 'paid') {
          payCellHtml += `
            <form method="POST" action="paymongo_checkout.php">
              <input type="hidden" name="commission_id" value="${c.commissionID}"/>
              <button type="submit" class="commission-pay-btn">Pay</button>
            </form>`;
        }
        if (!paymentHtml) {
          paymentHtml = amountNum > 0
            ? '<span class="payment-badge pending">Unpaid</span>'
            : '<span class="comm-empty-cell">Awaiting amount</span>';
        }

        return `
          <tr>
            <td class="comm-sn">${sn}</td>
            <td class="comm-title-cell">${title}</td>
            <td class="commission-description">${desc}</td>
            <td><span class="status-badge ${statusClass}">${esc(c.status)}</span></td>
            <td><div class="commission-pay-cell">${payCellHtml}</div></td>
            <td>${paymentHtml}</td>
            <td class="comm-date-cell">${formatDate(c.created_at)}</td>
            <td class="comm-note-cell">${esc(c.admin_note || 'No update yet.')}</td>
            <td>${fileHtml}</td>
            <td>${
              (c.status || '').toLowerCase() === 'pending'
                ? `<button class="commission-pay-btn" style="white-space:nowrap;" onclick="openEditModal(${c.commissionID})">&#9998; Edit</button>`
                : '<span class="comm-empty-cell" title="Editing is locked once a commission has been actioned.">Locked</span>'
            }</td>
          </tr>`;
      }).join('');
    }

    async function submitCommission(event) {
      event.preventDefault();
      const form = event.target;

      // Client-side validation for file (browser required attr doesn't fire on file inputs in all cases)
      const fileInput = document.getElementById('commissionAttachment');
      const msgBox = document.getElementById('commissionPageMsg');
      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        msgBox.style.display = 'block';
        msgBox.className = 'commission-page-msg commission-msg-error';
        msgBox.textContent = 'An attachment (PDF, STL, or DWG) is required.';
        fileInput.focus();
        return;
      }

      const formData = new FormData(form);
      formData.append('action', 'submit_commission');

      try {
        const response = await fetch('commissions.php', { method: 'POST', body: formData });
        const data = await response.json();

        msgBox.style.display = 'block';

        if (data.success) {
          msgBox.className = 'commission-page-msg commission-msg-ok';
          msgBox.textContent = data.message;
          form.reset();
          toggleSubmitForm();
          loadCommissions();
        } else {
          msgBox.className = 'commission-page-msg commission-msg-error';
          msgBox.textContent = data.error || 'Submission failed.';
        }
      } catch (error) {
        console.error('Error submitting commission:', error);
      }
    }

    // ── Edit Commission Modal ────────────────────────────────────────────────
    async function openEditModal(commissionId) {
      const modal    = document.getElementById('editCommissionModal');
      const errBox   = document.getElementById('editModalError');
      errBox.style.display = 'none';
      errBox.textContent   = '';
      document.getElementById('editCommissionForm').reset();

      // Fetch the commission from the server — server re-validates ownership + pending status
      try {
        const resp = await fetch(`commissions.php?action=fetch_for_edit&commission_id=${commissionId}`);
        const data = await resp.json();
        if (!data.success) {
          alert(data.error || 'This commission cannot be edited.');
          return;
        }
        const c = data.commission;
        document.getElementById('editCommissionId').value  = c.commissionID;
        document.getElementById('editTitle').value         = c.title        || '';
        document.getElementById('editDescription').value   = c.description  || '';
      } catch (err) {
        alert('Could not load commission details. Please try again.');
        return;
      }

      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
      const modal = document.getElementById('editCommissionModal');
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }

    // Close modal on backdrop click
    document.getElementById('editCommissionModal').addEventListener('click', function(e) {
      if (e.target === this) closeEditModal();
    });

    async function submitEditCommission(event) {
      event.preventDefault();
      const form   = event.target;
      const errBox = document.getElementById('editModalError');
      errBox.style.display = 'none';

      const formData = new FormData(form);

      try {
        const resp = await fetch('commissions.php', { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.success) {
          closeEditModal();
          const msgBox = document.getElementById('commissionPageMsg');
          msgBox.style.display = 'block';
          msgBox.className = 'commission-page-msg commission-msg-ok';
          msgBox.textContent = data.message;
          loadCommissions();
        } else {
          errBox.style.display = 'block';
          errBox.textContent = data.error || 'Update failed.';
        }
      } catch (err) {
        errBox.style.display = 'block';
        errBox.textContent = 'A network error occurred. Please try again.';
      }
    }

    loadCommissions();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>