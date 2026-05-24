<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/AdminRepository.php';
require_once __DIR__ . '/../post/CommissionRepository.php'; // For commission actions

// ── Defeat browser Back-Forward Cache (bfcache) ───────────────────────────────
// bfcache can restore an exact in-memory snapshot of this page — including the
// DOM state — when the admin navigates Back after Google OAuth.  That snapshot
// may have been captured before the new session was fully written, meaning
// $_SESSION['user']['id'] was still 0.  Sending no-store forces the browser to
// always fetch a fresh copy from the server, guaranteeing a live session read.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// ─────────────────────────────────────────────────────────────────────────────

// RBAC: admin and super_admin only
// Also verify that id > 0 so a bfcache-replayed or partially-written session
// (e.g. from a Back-button during Google OAuth) cannot produce admin_id = 0
// in the audit log.
$role = $_SESSION['user']['role'] ?? '';
if (
    !isset($_SESSION['user'])
    || !in_array($role, ['admin', 'super_admin'], true)
    || (int)($_SESSION['user']['id'] ?? 0) <= 0
    || empty($_SESSION['user']['username'])
) {
    // Destroy the malformed session so the login form starts clean.
    session_unset();
    session_destroy();
    header('Location: ../admin/admin_login.php?error=session_invalid');
    exit;
}

if (empty($_SESSION['mfa_verified'])) {
    header('Location: ../login/verify_mfa.php');
    exit;
}

$adminID       = (int)$_SESSION['user']['id'];
$adminUsername = (string)$_SESSION['user']['username'];

// Always re-read the role from the database so a just-promoted/demoted admin
// sees the correct UI without needing to log out and back in.
$_connForRoleRefresh = db_connect('accounts');
$_roleRefreshStmt    = $_connForRoleRefresh->prepare("SELECT role FROM accounts WHERE id = ? LIMIT 1");
$_roleRefreshStmt->bind_param('i', $adminID);
$_roleRefreshStmt->execute();
$_roleRefreshRow = $_roleRefreshStmt->get_result()->fetch_assoc();
$_roleRefreshStmt->close();
if ($_roleRefreshRow && $_roleRefreshRow['role'] !== ($_SESSION['user']['role'] ?? '')) {
    $_SESSION['user']['role'] = $_roleRefreshRow['role'];
}
$role         = $_SESSION['user']['role'] ?? '';
$isSuperAdmin = ($role === 'super_admin');

$adminRepo = new AdminRepository('db_connect');
$commissionRepo = new CommissionRepository('db_connect'); // Instantiate CommissionRepository

// ── Handle POST Actions ──────────────────────────────────────────
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $targetID = (int)($_POST['target_id'] ?? 0);

    if ($action === 'ban_user' && $targetID) {
        $banReason = trim($_POST['ban_reason'] ?? '');
        $actionMsg = $adminRepo->processBanUser($targetID, $adminID, $adminUsername, $isSuperAdmin, $banReason);
    } elseif ($action === 'unban_user' && $targetID) {
        $actionMsg = $adminRepo->processUnbanUser($targetID, $adminID, $adminUsername, $isSuperAdmin);
    } elseif ($action === 'delete_user' && $targetID) {
        $deletionReason = $_POST['deletion_reason'] ?? '';
        $adminRole      = $isSuperAdmin ? 'super_admin' : 'admin';
        $result    = $adminRepo->processDeleteUser($targetID, $deletionReason, $adminID, $adminUsername, $adminRole);
        $actionMsg = $result['success'] ? $result['message'] : $result['error'];
    } elseif ($action === 'delete_post' && $targetID) {
        $removalReason = trim($_POST['removal_reason'] ?? '');
        $actionMsg = $adminRepo->processDeletePost($targetID, $removalReason, $adminID, $adminUsername, $isSuperAdmin);
    } elseif ($action === 'promote_to_admin' && $targetID && $isSuperAdmin) {
        $actionMsg = $adminRepo->processPromoteToAdmin($targetID, $adminID, $adminUsername);
    } elseif ($action === 'demote_to_user' && $targetID && $isSuperAdmin) {
        $actionMsg = $adminRepo->processDemoteToUser($targetID, $adminID, $adminUsername); // Corrected call
    } elseif ($action === 'delete_commission' && $targetID) {
        $deletionReason = trim($_POST['deletion_reason'] ?? '');
        $result = $adminRepo->processDeleteCommission($targetID, $deletionReason, $adminID, $adminUsername, $isSuperAdmin ? 'super_admin' : 'admin');
        $actionMsg = $result['success'] ? $result['message'] : $result['error'];
    } elseif ($action === 'update_commission' && $targetID) {
        $newStatus = $_POST['commission_status'] ?? '';
        $adminNote = $_POST['admin_note'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        // Delegate commission updates to CommissionRepository
        $result = $commissionRepo->processUpdateCommission($targetID, $newStatus, $adminNote, $amount, $adminID, $adminUsername, ['Pending', 'Accepted', 'Ongoing', 'Delayed', 'Completed', 'Cancelled'], $isSuperAdmin ? 'super_admin' : 'admin');
        $actionMsg = $result['success'] ? $result['message'] ?? "Commission #{$targetID} updated." : $result['error'];
    }

    header('Location: admin.php?msg=' . urlencode($actionMsg));
    exit;
}
if (isset($_GET['msg'])) $actionMsg = htmlspecialchars($_GET['msg']);

// ── Dashboard Metrics ────────────────────────────────────────────
$metrics = $adminRepo->getDashboardMetrics();
$activeProjects = $metrics['activeProjects'];
$totalUsers     = $metrics['totalUsers'];
$engagementRate = $metrics['engagementRate'];
$revenueSales   = $metrics['revenueSales'];

// ── Order Pipeline ───────────────────────────────────────────────
$pipeline = $adminRepo->getOrderPipeline();

// ── Live Audit Log (visibility-filtered, searchable) ─────────────
$auditSearch = trim($_GET['audit_search'] ?? '');
$auditHours  = (int)($_GET['audit_hours'] ?? 8);
if (!in_array($auditHours, [8, 24, 72, 168, 720], true)) {
    $auditHours = 8;
}
$auditLimit = max(0, (int)($_GET['audit_limit'] ?? 30)); // default 30 to prevent performance lag
$auditSort  = ($_GET['audit_sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc'; // default newest-first
// Action filter: supports comma-separated multi-select (e.g. "ban,login").
// '' means no filter (All). Server-side only needs the first value for the
// SQL LIKE pre-filter; client-side JS does the multi-filter visually.
$validActionFilters = ['ban', 'unban', 'delete', 'commission', 'login', 'logout'];
$auditActionRaw     = trim($_GET['audit_action'] ?? '');
$auditActionFilters = array_values(array_filter(
    array_map('trim', explode(',', $auditActionRaw)),
    fn($v) => in_array($v, $validActionFilters, true)
));
// Pass only the first active filter to the SQL layer (secondary filters are
// handled client-side). Passing '' means "no SQL action filter".
$auditActionFilter  = $auditActionFilters[0] ?? '';
$auditLogs = $adminRepo->searchAuditLogs($isSuperAdmin, $auditSearch, $auditHours, $auditLimit, $auditSort, $auditActionFilter);

// ── User List ────────────────────────────────────────────────────
$userLimit = max(0, (int)($_GET['user_limit'] ?? 10)); // default 10
$users = $adminRepo->getAllUsers($userLimit);

// ── All Posts (Feed Moderator) ───────────────────────────────────
$postLimit = max(0, (int)($_GET['post_limit'] ?? 10)); // default 10
$allPosts = $adminRepo->getAllPosts($postLimit);

// ── Commissions ──────────────────────────────────────────────────
$commLimit   = max(0, (int)($_GET['comm_limit'] ?? 10)); // default 10
$commissions = $commissionRepo->getAllCommissions(true, $adminID, $commLimit); // Call CommissionRepository for all commissions
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FABulous – Admin Dashboard</title>
  <link rel="icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link rel="shortcut icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="admin.css"/>
</head>
<body>

<div class="admin-layout">

  <!-- ── SIDEBAR ── -->
  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <img src="../images/Top_Left_Nav_Logo.png" alt="FABulous" class="sidebar-logo-img"/>
    </div>
    <nav class="sidebar-nav">
      <button class="sidebar-link active" onclick="switchTab('dashboard',this)">Dashboard</button>
      <button class="sidebar-link" onclick="switchTab('users',this)">User Management</button>
      <button class="sidebar-link" onclick="switchTab('feed',this)">Feed Moderator</button>
      <button class="sidebar-link" onclick="switchTab('commissions',this)">Commissions</button>
      <a href="../post/post.php" class="sidebar-link">Post Section</a>
      <a href="admin_logout.php" class="sidebar-link logout-link">Logout</a>
    </nav>
    <?php if ($isSuperAdmin): ?>
      <div class="sidebar-role-badge">Super Admin</div>
    <?php endif; ?>
  </aside>

  <!-- ── MAIN ── -->
  <main class="admin-main">

    <div class="admin-header">
      <div>
        <p class="admin-welcome">Welcome back, <?php echo htmlspecialchars($adminUsername); ?>!</p>
        <h1 class="admin-title">Dashboard</h1>
      </div>
      <div class="header-actions">
        <button class="btn-print" onclick="exportCurrentTabCSV()">&#128190; Export CSV</button>
        <img src="../images/Top_Left_Nav_Logo.png" alt="" class="header-logo"/>
      </div>
    </div>

    <div class="action-msg" id="liveActionMsg" <?php echo $actionMsg ? '' : 'style="display:none;"'; ?>>
      <?php echo htmlspecialchars($actionMsg); ?>
    </div>

    <!-- ── DASHBOARD TAB ── -->
    <div id="tab-dashboard" class="tab-content active">

      <div class="metrics-grid">
        <div class="metric-card">
          <p class="metric-title">Active Projects</p>
          <p class="metric-value"><?php echo number_format($activeProjects); ?></p>
          <p class="metric-sub">Total posts on platform</p>
        </div>
        <div class="metric-card">
          <p class="metric-title">Total Users</p>
          <p class="metric-value"><?php echo number_format($totalUsers); ?></p>
          <p class="metric-sub">Registered accounts</p>
        </div>
        <div class="metric-card">
          <p class="metric-title">Engagement Rate</p>
          <p class="metric-value"><?php echo $engagementRate; ?></p>
          <p class="metric-sub">Interactions per post</p>
        </div>
        <div class="metric-card">
          <p class="metric-title">Revenue Sales</p>
          <p class="metric-value">&#8369;<?php echo $revenueSales; ?></p>
          <p class="metric-sub">Completed commissions</p>
        </div>
      </div>

      <div class="bottom-row">
        <div class="audit-card">
          <h2 class="card-heading">Audit Log</h2>

          <!-- ── Audit Filters ── -->
          <div class="audit-filters">
            <div class="audit-search-wrap">
              <svg class="audit-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
              <input
                type="text"
                id="auditSearchInput"
                class="audit-search-input"
                placeholder="Search by username or name…"
                value="<?php echo htmlspecialchars($auditSearch); ?>"
                oninput="filterAuditLog()"
                autocomplete="off"
              >
              <button class="audit-search-clear" id="auditClearBtn" onclick="clearAuditSearch()" title="Clear search" aria-label="Clear search" style="display:<?php echo $auditSearch !== '' ? 'flex' : 'none'; ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            <div class="audit-time-pills" role="group" aria-label="Time window">
              <?php
                $pills = [8 => '8 hrs', 24 => '24 hrs', 72 => '3 days', 168 => '7 days', 720 => '30 days'];
                foreach ($pills as $h => $label):
              ?>
                <button
                  type="button"
                  class="audit-pill<?php echo $auditHours === $h ? ' active' : ''; ?>"
                  data-hours="<?php echo $h; ?>"
                  onclick="setAuditWindow(<?php echo $h; ?>, this)"
                ><?php echo $label; ?></button>
              <?php endforeach; ?>
            </div>
            <!-- ── Limit + Action Filter: same horizontal row ── -->
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px;">
              <label class="audit-limit-wrap" title="Limit the number of entries returned (0 = no limit)" style="margin-left:0;">
                <span class="audit-limit-label">Limit</span>
                <input
                  type="number"
                  id="auditLimitInput"
                  class="audit-search-input audit-limit-input"
                  placeholder="e.g. 30"
                  value="<?php echo $auditLimit > 0 ? $auditLimit : ''; ?>"
                  min="0"
                  max="500"
                  step="1"
                  onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault(); if(event.key==='Enter'){event.preventDefault();applyAuditLimit();}"
                  onchange="applyAuditLimit()"
                  title="Max entries to display (default: 30)"
                />
              </label>
              <div class="audit-time-pills" role="group" aria-label="Filter by action type" style="margin:0;">
              <?php
                $actionPills = [
                  ''           => 'All',
                  'ban'        => 'Ban',
                  'unban'      => 'Unban',
                  'delete'     => 'Delete',
                  'commission' => 'Commission',
                  'login'      => 'Login',
                  'logout'     => 'Logout',
                ];
                foreach ($actionPills as $val => $label):
              ?>
                <?php
                  // Determine active state: All pill active when no specific filters; others when in active set
                  $pillActive = ($val === '')
                    ? (empty($auditActionFilters) ? ' active' : '')
                    : (in_array($val, $auditActionFilters, true) ? ' active' : '');
                  $pillTitle  = ($val === '') ? 'Show all action types' : 'Toggle ' . $label . ' filter';
                ?>
                <button
                  type="button"
                  class="audit-pill<?php echo $pillActive; ?>"
                  data-action-filter="<?php echo htmlspecialchars($val); ?>"
                  onclick="setAuditActionFilter(<?php echo htmlspecialchars(json_encode($val), ENT_QUOTES); ?>, this)"
                  title="<?php echo htmlspecialchars($pillTitle); ?>"
                ><?php echo htmlspecialchars($label); ?></button>
              <?php endforeach; ?>
              </div>
            </div><!-- end Limit+ActionFilter row -->
            <div class="audit-time-pills" role="group" aria-label="Sort order">
              <button
                type="button"
                class="audit-pill<?php echo $auditSort === 'desc' ? ' active' : ''; ?>"
                onclick="setAuditSort('desc', this)"
                title="Newest first"
              >Newest</button>
              <button
                type="button"
                class="audit-pill<?php echo $auditSort === 'asc' ? ' active' : ''; ?>"
                onclick="setAuditSort('asc', this)"
                title="Oldest first"
              >Oldest</button>
            </div>
          </div>

          <!-- ── Audit result count ── -->
          <p class="audit-result-count" id="auditResultCount">
            <?php
              $cnt = count($auditLogs);
              $windowLabel = $pills[$auditHours] ?? "{$auditHours} hrs";
              echo $cnt === 0
                ? 'No entries found.'
                : "{$cnt} " . ($cnt === 1 ? 'entry' : 'entries') . " · last {$windowLabel}";
            ?>
          </p>

          <div class="audit-list" id="auditList">
            <?php if (empty($auditLogs)): ?>
              <p class="audit-empty" id="auditEmpty">No admin actions in this period.</p>
            <?php else: ?>
              <?php foreach ($auditLogs as $log):
                $fullName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
                $searchData = strtolower($log['admin_username'] . ' ' . $fullName . ' ' . ($log['action'] ?? ''));
                // Derive a coarse action-category tag for client-side filtering.
                $actionLower = strtolower($log['action'] ?? '');
                $actionTag = 'other';
                if (str_contains($actionLower, 'unban'))      $actionTag = 'unban';
                elseif (str_contains($actionLower, 'ban'))    $actionTag = 'ban';
                elseif (str_contains($actionLower, 'delete') || str_contains($actionLower, 'deleted') || str_contains($actionLower, 'removed')) $actionTag = 'delete';
                elseif (str_contains($actionLower, 'commission')) $actionTag = 'commission';
                elseif (str_contains($actionLower, 'login'))  $actionTag = 'login';
                elseif (str_contains($actionLower, 'logout')) $actionTag = 'logout';
              ?>
                <div class="audit-entry" data-search="<?php echo htmlspecialchars($searchData); ?>" data-action="<?php echo htmlspecialchars($actionTag); ?>">
                  <span class="audit-admin"><?php echo htmlspecialchars($log['admin_username']); ?></span>
                  <?php if ($fullName): ?>
                    <span class="audit-fullname">(<?php echo htmlspecialchars($fullName); ?>)</span>
                  <?php endif; ?>:
                  <?php echo htmlspecialchars($log['action']); ?>
                  <span class="audit-time"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></span>
                </div>
              <?php endforeach; ?>
              <p class="audit-empty" id="auditEmpty" style="display:none;">No entries match your search.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="pipeline-card">
          <h2 class="card-heading" style="align-self:flex-start;">Order Pipeline</h2>
          <canvas id="pipelineChart" width="190" height="190"></canvas>
          <div class="pipeline-legend">
            <span><span class="legend-dot pending"></span>Pending: <?php echo $pipeline['Pending']; ?></span>
            <span><span class="legend-dot accepted"></span>Accepted: <?php echo $pipeline['Accepted']; ?></span>
            <span><span class="legend-dot ongoing"></span>Ongoing: <?php echo $pipeline['Ongoing']; ?></span>
            <span><span class="legend-dot delayed"></span>Delayed: <?php echo $pipeline['Delayed']; ?></span>
            <span><span class="legend-dot completed"></span>Completed: <?php echo $pipeline['Completed']; ?></span>
            <span><span class="legend-dot cancelled"></span>Cancelled: <?php echo $pipeline['Cancelled']; ?></span>
          </div>
        </div>
      </div>
    </div><!-- end tab-dashboard -->

    <!-- ── USER MANAGEMENT TAB ── -->
    <div id="tab-users" class="tab-content">
      <h2 class="section-heading">User Management</h2>
      
      <div class="admin-filters">
        <input type="text" id="filterUsersText" placeholder="Search by name, username, email..." oninput="filterUsers()" style="flex: 1; min-width: 200px;">
        <input type="date" id="filterUsersStart" onchange="filterUsers()" title="Start Date">
        <input type="date" id="filterUsersEnd" onchange="filterUsers()" title="End Date">
      </div>
      <div class="admin-filters" style="margin-top:8px; flex-wrap:wrap; gap:8px; align-items:center;">
        <span style="font-size:0.78rem; font-weight:600; opacity:0.6; letter-spacing:.04em; text-transform:uppercase;">Filter by:</span>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">
          User ID
          <input type="number" id="filterUsersId" class="audit-search-input" placeholder="e.g. 4" oninput="filterUsers()" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" min="1" style="width:90px;" title="Filter by User ID">
        </label>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;" title="Limit the number of users fetched from the server (0 = no limit)">
          Limit
          <input type="number" id="userLimitInput" class="audit-search-input"
                 placeholder="e.g. 10"
                 min="0" step="1"
                 value="<?php echo $userLimit > 0 ? $userLimit : ''; ?>"
                 style="width:72px;"
                 onkeydown="blockNonInteger(event)"
                 onchange="applyUserLimit()"
                 title="Max users to load (0 = no limit)">
        </label>
        <span style="margin-left:auto; font-size:0.82rem; color:rgba(255,255,255,0.45);">
          Showing <span class="comm-filter-count" id="countUsersVisible"><?php echo count($users); ?></span> of <?php echo count($users); ?> users
        </span>
      </div>

      <div class="table-wrap">
        <table class="admin-table" id="usersTable">
          <thead>
            <tr>
              <th>ID</th><th>Name</th><th>Username</th><th>Email</th>
              <th>Role</th><th>Status</th><th>Joined</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <?php
                $uRole       = $u['role'];
                $isSelf      = ((int)$u['id'] === $adminID);
                $isSuperTgt  = ($uRole === 'super_admin');
                $isAdminTgt  = ($uRole === 'admin');
                // Admins can unban admin peers so suspended staff accounts can be recovered.
                // Standard admins may only Ban/Unban/Delete regular 'user' accounts.
                // Super Admins may Ban/Unban/Delete any account except super_admins and themselves.
                $canUnban    = !$isSelf && (bool)$u['banned'] && ($isSuperAdmin ? !$isSuperTgt : $uRole === 'user');
                $canBan      = !$isSelf && !$u['banned'] && !$isSuperTgt && ($isSuperAdmin || $uRole === 'user');
                $canPromote  = $isSuperAdmin && $uRole === 'user';
                $canDemote   = $isSuperAdmin && $isAdminTgt && !$isSelf;
                $canDelete   = !$isSelf && !$isSuperTgt && ($isSuperAdmin || $uRole === 'user');
                
                $searchString = htmlspecialchars(strtolower($u['username'] . ' ' . $u['first_name'] . ' ' . $u['last_name'] . ' ' . $u['email']));
                $dateString = date('Y-m-d', strtotime($u['created_at']));
              ?>
              <tr class="<?php echo $u['banned'] ? 'banned-row' : ''; ?>" 
                  data-search="<?php echo $searchString; ?>" 
                  data-date="<?php echo $dateString; ?>"
                  data-id="<?php echo (int)$u['id']; ?>">
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="role-badge <?php echo $uRole; ?>"><?php echo $uRole; ?></span></td>
                <td><?php echo $u['banned']
                    ? '<span class="banned-badge">Banned</span>'
                    : '<span class="active-badge">Active</span>'; ?></td>
                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                <td class="action-cell">
                  <?php if ($canPromote): ?>
                    <button type="button"
                            class="action-btn btn-promote"
                            onclick="openPromoteModal(<?php echo $u['id']; ?>, <?php echo htmlspecialchars(json_encode($u['username']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($u['email']), ENT_QUOTES); ?>)">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;vertical-align:middle;"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                      Promote
                    </button>
                  <?php endif; ?>
                  <?php if ($canDemote): ?>
                    <button type="button"
                            class="action-btn btn-demote"
                            onclick="openDemoteModal(<?php echo $u['id']; ?>, <?php echo htmlspecialchars(json_encode($u['username']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($u['email']), ENT_QUOTES); ?>)">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                      Demote
                    </button>
                  <?php endif; ?>
                  <?php if ($canBan || $canUnban): ?>
                    <?php if ($canUnban): ?>
                      <button type="button"
                              class="action-btn btn-unban"
                              onclick="openUnbanUserModal(<?php echo $u['id']; ?>, <?php echo htmlspecialchars(json_encode($u['username']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($u['email']), ENT_QUOTES); ?>)">
                        Unban
                      </button>
                    <?php else: ?>
                      <button type="button"
                              class="action-btn btn-ban"
                              onclick="openBanUserModal(<?php echo $u['id']; ?>, <?php echo htmlspecialchars(json_encode($u['username']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($u['email']), ENT_QUOTES); ?>)">
                        Ban
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <button type="button"
                            class="action-btn btn-delete"
                            onclick="openDeleteUserModal(<?php echo $u['id']; ?>, <?php echo htmlspecialchars(json_encode($u['username']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($u['email']), ENT_QUOTES); ?>)">
                      Delete
                    </button>
                  <?php endif; ?>
                  <?php if (!$canBan && !$canUnban && !$canPromote && !$canDemote && !$canDelete): ?>
                    <span class="no-action">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div><!-- end tab-users -->

    <!-- ── FEED MODERATOR TAB ── -->
    <div id="tab-feed" class="tab-content">
      <h2 class="section-heading">Feed Moderator</h2>
      
      <div class="admin-filters">
        <input type="text" id="filterFeedText" placeholder="Search by username or caption..." oninput="filterFeed()" style="flex: 1; min-width: 200px;">
        <label><input type="checkbox" id="filterFeedHasCaption" onchange="filterFeed()"> Must have caption</label>
        <input type="date" id="filterFeedStart" onchange="filterFeed()" title="Start Date">
        <input type="date" id="filterFeedEnd" onchange="filterFeed()" title="End Date">
      </div>
      <div class="admin-filters" style="margin-top:8px; flex-wrap:wrap; gap:8px; align-items:center;">
        <span style="font-size:0.78rem; font-weight:600; opacity:0.6; letter-spacing:.04em; text-transform:uppercase;">Filter by:</span>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">
          Caption
          <input type="text" id="filterFeedCaption" class="audit-search-input" placeholder="Caption contains…" oninput="filterFeed()" style="width:200px;" title="Filter by caption text">
          <span class="comm-filter-count" id="countFeedCaption" style="display:none;"></span>
        </label>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;" title="Limit the number of posts fetched from the server (0 = no limit)">
          Limit
          <input type="number" id="postLimitInput" class="audit-search-input"
                 placeholder="e.g. 10"
                 min="0" step="1"
                 value="<?php echo $postLimit > 0 ? $postLimit : ''; ?>"
                 style="width:72px;"
                 onkeydown="blockNonInteger(event)"
                 onchange="applyPostLimit()"
                 title="Max posts to load (0 = no limit)">
        </label>
        <span style="margin-left:auto; font-size:0.82rem; color:rgba(255,255,255,0.45);">
          Showing <span class="comm-filter-count" id="countFeedVisible"><?php echo count($allPosts); ?></span> of <?php echo count($allPosts); ?> posts
        </span>
      </div>

      <div class="table-wrap">
        <table class="admin-table" id="feedTable">
          <thead>
            <tr>
              <th>Post ID</th><th>Author</th><th>Caption</th>
              <th>Likes</th><th>Comments</th><th>Posted</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allPosts)): ?>
              <tr class="empty-row"><td colspan="7" style="text-align:center;padding:28px;color:rgba(255,255,255,0.4);">No posts yet.</td></tr>
            <?php else: ?>
              <?php foreach ($allPosts as $p): ?>
                <?php 
                  $caption = $p['caption'] ?? '';
                  $searchString = htmlspecialchars(strtolower($p['username'] . ' ' . $caption));
                  $dateString = date('Y-m-d', strtotime($p['created_at']));
                  $hasCaption = trim($caption) !== '' ? '1' : '0';
                ?>
                <tr data-search="<?php echo $searchString; ?>" data-date="<?php echo $dateString; ?>" data-has-caption="<?php echo $hasCaption; ?>">
                  <td>#<?php echo $p['postID']; ?></td>
                  <td><?php echo htmlspecialchars($p['username']); ?></td>
                  <td class="caption-cell caption-cell-expanded">
                    <?php
                      $shortCaption = mb_substr($caption, 0, 90);
                      $captionIsLong = mb_strlen($caption) > 90;
                    ?>
                    <?php if ($captionIsLong): ?>
                      <details class="caption-details">
                        <summary><?php echo htmlspecialchars($shortCaption); ?>...</summary>
                        <div class="caption-full"><?php echo nl2br(htmlspecialchars($caption)); ?></div>
                      </details>
                    <?php else: ?>
                      <?php echo htmlspecialchars($caption); ?>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $p['likes']; ?></td>
                  <td><?php echo $p['comments']; ?></td>
                  <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                  <td>
                    <button type="button" class="action-btn btn-ban"
                            onclick="openRemovePostModal(
                              <?php echo (int)$p['postID']; ?>,
                              <?php echo htmlspecialchars(json_encode($p['username']), ENT_QUOTES); ?>,
                              <?php echo htmlspecialchars(json_encode(mb_substr($p['caption'] ?? '', 0, 200)), ENT_QUOTES); ?>
                            )">Remove</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div><!-- end tab-feed -->

    <!-- ── COMMISSIONS TAB ── -->
    <div id="tab-commissions" class="tab-content">
      <h2 class="section-heading">Commission Management</h2>

      <div class="admin-filters">
        <input type="text" id="filterCommText" placeholder="Search requester, title, description, status…" oninput="filterCommissions()" style="flex: 1; min-width: 200px;">
        <input type="date" id="filterCommStart" onchange="filterCommissions()" title="Start Date">
        <input type="date" id="filterCommEnd" onchange="filterCommissions()" title="End Date">
      </div>
      <div class="admin-filters" style="margin-top:8px; flex-wrap:wrap; gap:8px; align-items:center;">
        <span style="font-size:0.78rem; font-weight:600; opacity:0.6; letter-spacing:.04em; text-transform:uppercase;">Filter by:</span>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">
          User
          <input type="text" id="filterCommUser" class="audit-search-input" placeholder="Username…" oninput="filterCommissions()" style="width:130px;" title="Filter by requester username">
          <span class="comm-filter-count" id="countCommUser" style="display:none;"></span>
        </label>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">
          Commission ID
          <input type="text" id="filterCommPostId" class="audit-search-input" placeholder="e.g. 12" oninput="filterCommissions()" style="width:90px;" title="Filter by Commission ID">
          <span class="comm-filter-count" id="countCommId" style="display:none;"></span>
        </label>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;" title="Limit the number of commissions fetched from the server (0 = no limit)">
          Limit
          <input type="number" id="commLimitInput" class="audit-search-input"
                 placeholder="e.g. 10"
                 min="0" step="1"
                 value="<?php echo $commLimit > 0 ? $commLimit : ''; ?>"
                 style="width:72px;"
                 onkeydown="blockNonInteger(event)"
                 onchange="applyCommLimit()"
                 title="Max commissions to load (0 = no limit)">
        </label>
        <span style="margin-left:auto; font-size:0.82rem; color:rgba(255,255,255,0.45);">
          Showing <span class="comm-filter-count" id="countCommVisible"><?php echo count($commissions); ?></span> of <?php echo count($commissions); ?> commissions
        </span>
      </div>

      <div class="table-wrap">
        <table class="admin-table commissions-table" id="commTable">
          <thead>
            <tr>
              <th>S/N</th><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Title</th><th>Description</th>
              <th>Amount</th><th>Status</th><th>File</th><th>Payment</th><th>Submitted</th><th>Admin Note</th><th>Update</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($commissions)): ?>
              <tr class="empty-row"><td colspan="15" style="text-align:center;padding:28px;color:rgba(255,255,255,0.4);">No commissions yet.</td></tr>
            <?php else: ?>
              <?php $commRowNum = 1; foreach ($commissions as $c): ?>
                <?php
                  // Self-approval guard: an admin who submitted this commission cannot modify it.
                  // Only another admin can change status/note/amount for their own requests.
                  $commRequesterId  = (int)($c['userID'] ?? 0);
                  $isSelfCommission = ($commRequesterId === $adminID);

                  $searchString = htmlspecialchars(strtolower(
                    ($c['requester_username'] ?? '') . ' ' .
                    ($c['title'] ?? '') . ' ' .
                    ($c['description'] ?? '') . ' ' .
                    ($c['status'] ?? '')
                  ));
                  $dateString = date('Y-m-d', strtotime($c['created_at']));

                  $statusClass   = 'status-badge status-' . strtolower(str_replace(' ', '-', $c['status']));
                  $amountDisplay = '&#8369;' . number_format((float)($c['amount'] ?? 0), 2);
                  $amountValue   = number_format((float)($c['amount'] ?? 0), 2, '.', '');

                  $fileHtml = '—';
                  if (!empty($c['attachment_url'])) {
                    $fileUrl = htmlspecialchars('../' . $c['attachment_url']);
                    $fileHtml = '<a href="' . $fileUrl . '" target="_blank" class="action-btn btn-view" title="Open file">&#128196; View</a>';
                  }

                  $payStatus = $c['payment_status'] ?? '';
                  if ($payStatus === 'paid') {
                    $payHtml = '<span class="status-badge status-completed">Paid</span>';
                  } elseif ($payStatus) {
                    $payHtml = '<span class="status-badge status-pending">' . htmlspecialchars(ucfirst($payStatus)) . '</span>';
                  } else {
                    $payHtml = '<span class="no-action">—</span>';
                  }
                ?>
                <tr data-search="<?php echo $searchString; ?>" data-date="<?php echo $dateString; ?>" data-username="<?php echo htmlspecialchars(strtolower($c['requester_username'] ?? '')); ?>" data-id="<?php echo (int)$c['commissionID']; ?>">
                  <td class="comm-sn"><?php echo $commRowNum++; ?></td>
                  <td>#<?php echo $c['commissionID']; ?></td>
                  <td>
                    <?php echo htmlspecialchars($c['requester_username'] ?? '—'); ?>
                    <?php if ($isSelfCommission): ?>
                      <br><span class="status-badge" style="font-size:0.65rem;margin-top:4px;background:rgba(230,126,34,0.18);color:#e67e22;">Your request</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($c['requester_name'] ?? '—'); ?></td>
                  <td>
                    <?php if (!empty($c['requester_email'])): ?>
                      <a class="requester-email" href="mailto:<?php echo htmlspecialchars($c['requester_email']); ?>">
                        <?php echo htmlspecialchars($c['requester_email']); ?>
                      </a>
                    <?php else: ?>
                      <span class="no-action">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="caption-cell"><?php echo htmlspecialchars($c['title'] ?? '—'); ?></td>
                  <td class="caption-cell"><?php echo htmlspecialchars(mb_substr($c['description'] ?? '', 0, 60)) . (mb_strlen($c['description'] ?? '') > 60 ? '…' : ''); ?></td>
                  <td id="commission-amount-display-<?php echo $c['commissionID']; ?>"><?php echo $amountDisplay; ?></td>
                  <td>
                    <span class="<?php echo $statusClass; ?>" id="commission-status-<?php echo $c['commissionID']; ?>">
                      <?php echo htmlspecialchars($c['status']); ?>
                    </span>
                  </td>
                  <td><?php echo $fileHtml; ?></td>
                  <td><?php echo $payHtml; ?></td>
                  <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                  <td class="caption-cell" id="commission-note-display-<?php echo $c['commissionID']; ?>">
                    <?php echo htmlspecialchars(($c['admin_note'] ?? '') !== '' ? $c['admin_note'] : 'No note yet.'); ?>
                  </td>
                  <td>
                    <?php if ($isSelfCommission): ?>
                      <!-- Self-approval prevention: this admin submitted this commission.
                           Only another admin can approve or modify it. -->
                      <span class="no-action" title="You cannot modify your own commission request. Another admin must action this.">
                        &#128274; Self-request
                      </span>
                    <?php else: ?>
                      <form method="POST" class="commission-form" data-commission-id="<?php echo $c['commissionID']; ?>">
                        <input type="hidden" name="action"    value="update_commission"/>
                        <input type="hidden" name="target_id" value="<?php echo $c['commissionID']; ?>"/>
                        <select name="commission_status" class="commission-select">
                          <?php foreach (['Pending','Accepted','Ongoing','Delayed','Completed','Cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $c['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                          <?php endforeach; ?>
                        </select>
                        <input type="text" name="admin_note" class="commission-note"
                               value="<?php echo htmlspecialchars($c['admin_note'] ?? ''); ?>"
                               placeholder="Add a note…" maxlength="500"/>
                        <label class="commission-amount-label">
                          Amount (₱)
                          <input type="number" name="amount" class="commission-amount-input"
                                 value="<?php echo htmlspecialchars($amountValue); ?>"
                                 min="0" step="0.01"/>
                        </label>
                        <button type="submit" class="action-btn btn-save">Save</button>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td class="action-cell">
                    <button
                      class="action-btn btn-delete"
                      onclick="openDeleteCommissionModal(<?php echo $c['commissionID']; ?>, <?php echo htmlspecialchars(json_encode($c['title'] ?? 'Commission #'.$c['commissionID']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($c['requester_username'] ?? ''), ENT_QUOTES); ?>)">
                      Delete
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($commissions)): ?>
        <p class="comm-count-row" id="adminCommCount">Showing <strong><?php echo count($commissions); ?></strong> commissions</p>
      <?php endif; ?>
    </div><!-- end tab-commissions -->

  </main>
</div>

<script>
// ── Remove Post Modal ────────────────────────────────────────────
let removePostModal;

function openRemovePostModal(postId, username, caption) {
  const info = document.getElementById('removePostInfo');
  let html = `Post <strong>#${postId}</strong> by <strong>${escapeHtml(username)}</strong>`;
  if (caption && caption.trim() !== '') {
    const preview = caption.length > 120 ? caption.substring(0, 120) + '…' : caption;
    html += `<br><small class="text-muted" style="font-style:italic;">&ldquo;${escapeHtml(preview)}&rdquo;</small>`;
  }
  info.innerHTML = html;

  document.getElementById('removePostId').value = postId;
  document.getElementById('removalReasonTextarea').value = '';
  document.getElementById('removalCharCount').textContent = '0';

  removePostModal = new bootstrap.Modal(document.getElementById('removePostModal'));
  removePostModal.show();
}

function confirmRemovePost() {
  const reason = document.getElementById('removalReasonTextarea').value.trim();
  if (!reason) {
    alert('Please provide a reason for removing this post.');
    return;
  }
  if (!confirm('Are you sure you want to permanently remove this post? The post owner will be notified by email.')) {
    return;
  }
  document.getElementById('removePostForm').submit();
}

function submitRemovePostForm(event) {
  event.preventDefault();
  confirmRemovePost();
}

// Character counter for removal reason — deferred so the modal HTML exists in the DOM
document.addEventListener('DOMContentLoaded', function () {
  const removalTextarea = document.getElementById('removalReasonTextarea');
  if (removalTextarea) {
    removalTextarea.addEventListener('input', function () {
      document.getElementById('removalCharCount').textContent = this.value.length;
    });
  }

  // Auto-activate tab from URL ?tab= parameter (used by applyUserLimit / applyPostLimit)
  const tabParam = new URLSearchParams(window.location.search).get('tab');
  if (tabParam) {
    const targetTab = document.getElementById('tab-' + tabParam);
    const targetBtn = document.querySelector(`.sidebar-link[onclick*="switchTab('${tabParam}'"]`);
    if (targetTab) {
      document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
      targetTab.classList.add('active');
      if (targetBtn) targetBtn.classList.add('active');
    }
  }
});

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function switchTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

// ── CSV Export (respects active tab + current filters) ──────────
function csvEscape(value) {
  const s = (value ?? '').toString().replace(/\s+/g, ' ').trim();
  return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

function rowIsVisible(row) {
  // A row is "filtered out" only when something explicitly hid it (display:none)
  return row.style.display !== 'none';
}

function exportTableCSV(tableId, headers, columnIndexes) {
  const table = document.getElementById(tableId);
  if (!table) return '';
  const lines = [headers.map(csvEscape).join(',')];
  const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
  rows.forEach(row => {
    if (!rowIsVisible(row)) return;
    const cells = row.querySelectorAll('td');
    const values = columnIndexes.map(i => {
      const cell = cells[i];
      if (!cell) return '';
      // Prefer <details> summary over the expanded body so long captions stay one line
      const summary = cell.querySelector('details summary');
      return summary ? summary.textContent : cell.textContent;
    });
    lines.push(values.map(csvEscape).join(','));
  });
  return lines.join('\r\n');
}

function exportDashboardCSV() {
  const metricCards = document.querySelectorAll('#tab-dashboard .metric-card');
  const metricLines = ['Metric,Value'];
  metricCards.forEach(card => {
    const title = card.querySelector('.metric-title')?.textContent || '';
    const value = card.querySelector('.metric-value')?.textContent || '';
    metricLines.push([title, value].map(csvEscape).join(','));
  });

  const auditLines = ['', 'Audit Log', 'Admin,Full Name,Action,Timestamp'];
  document.querySelectorAll('#auditList .audit-entry').forEach(entry => {
    if (!rowIsVisible(entry)) return;
    const admin = entry.querySelector('.audit-admin')?.textContent || '';
    const fullName = (entry.querySelector('.audit-fullname')?.textContent || '').replace(/^\(|\)$/g, '');
    const time = entry.querySelector('.audit-time')?.textContent || '';
    // Action text = entry text with the labelled spans stripped out
    const clone = entry.cloneNode(true);
    clone.querySelectorAll('.audit-admin, .audit-fullname, .audit-time').forEach(n => n.remove());
    const action = clone.textContent.replace(/^[\s:]+/, '').trim();
    auditLines.push([admin, fullName, action, time].map(csvEscape).join(','));
  });

  return metricLines.concat(auditLines).join('\r\n');
}

function downloadCSV(csv, filename) {
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function exportCurrentTabCSV() {
  const active = document.querySelector('.tab-content.active');
  if (!active) return;
  const today = new Date().toISOString().slice(0, 10);
  let csv = '';
  let filename = `fabulous-${today}.csv`;

  switch (active.id) {
    case 'tab-dashboard':
      csv = exportDashboardCSV();
      filename = `fabulous-dashboard-${today}.csv`;
      break;
    case 'tab-users':
      csv = exportTableCSV(
        'usersTable',
        ['ID', 'Name', 'Username', 'Email', 'Role', 'Status', 'Joined'],
        [0, 1, 2, 3, 4, 5, 6]
      );
      filename = `fabulous-users-${today}.csv`;
      break;
    case 'tab-feed':
      csv = exportTableCSV(
        'feedTable',
        ['Post ID', 'Author', 'Caption', 'Likes', 'Comments', 'Posted'],
        [0, 1, 2, 3, 4, 5]
      );
      filename = `fabulous-feed-${today}.csv`;
      break;
    case 'tab-commissions':
      csv = exportTableCSV(
        'commTable',
        ['ID', 'Requester', 'Email', 'Title', 'Description', 'Amount', 'Status', 'Submitted', 'Admin Note'],
        [0, 1, 2, 3, 4, 5, 6, 7, 8]
      );
      filename = `fabulous-commissions-${today}.csv`;
      break;
  }

  if (!csv) {
    showActionMessage('Nothing to export on this tab.', true);
    return;
  }
  downloadCSV(csv, filename);
}

const liveActionMsg = document.getElementById('liveActionMsg');

function showActionMessage(message, isError = false) {
  liveActionMsg.textContent = message;
  liveActionMsg.style.display = 'block';
  liveActionMsg.style.borderColor = isError ? '#e74c3c' : '';
  liveActionMsg.style.color = isError ? '#ff8a8a' : '';
  liveActionMsg.style.background = isError ? 'rgba(231,76,60,0.12)' : '';
}

// Filter Logic
function applyFilters(tableId, textId, startId, endId, extraLogic = null) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
  const text = document.getElementById(textId).value.toLowerCase().trim();
  const start = document.getElementById(startId).value;
  const end = document.getElementById(endId).value;

  rows.forEach(row => {
    const searchData = row.getAttribute('data-search') || '';
    const dateData = row.getAttribute('data-date') || '';

    const matchText = text === '' || searchData.includes(text);
    const matchStart = start === '' || dateData >= start;
    const matchEnd = end === '' || dateData <= end;
    const matchExtra = extraLogic ? extraLogic(row) : true;

    row.style.display = (matchText && matchStart && matchEnd && matchExtra) ? '' : 'none';
  });
}

// ── Limit input helpers ──────────────────────────────────────────
/**
 * Block non-integer keystrokes: e, E, +, -, decimal point.
 * Called via onkeydown on all integer-only number inputs.
 */
function blockNonInteger(event) {
  if (['e', 'E', '+', '-', '.', ','].includes(event.key)) {
    event.preventDefault();
  }
}

function applyUserLimit() {
  const raw   = (document.getElementById('userLimitInput')?.value ?? '').trim();
  const limit = raw === '' ? 0 : Math.max(0, parseInt(raw, 10) || 0);
  const url   = new URL(window.location.href);
  if (limit > 0) url.searchParams.set('user_limit', limit);
  else           url.searchParams.delete('user_limit');
  // Preserve active tab
  url.searchParams.set('tab', 'users');
  window.location.href = url.toString();
}

function applyPostLimit() {
  const raw   = (document.getElementById('postLimitInput')?.value ?? '').trim();
  const limit = raw === '' ? 0 : Math.max(0, parseInt(raw, 10) || 0);
  const url   = new URL(window.location.href);
  if (limit > 0) url.searchParams.set('post_limit', limit);
  else           url.searchParams.delete('post_limit');
  url.searchParams.set('tab', 'feed');
  window.location.href = url.toString();
}

function filterUsers() {
  const idFilter = (document.getElementById('filterUsersId')?.value || '').trim();
  applyFilters('usersTable', 'filterUsersText', 'filterUsersStart', 'filterUsersEnd', (row) => {
    if (idFilter) {
      const rowId = (row.getAttribute('data-id') || '');
      if (!rowId.startsWith(idFilter)) return false;
    }
    return true;
  });
  const countEl = document.getElementById('countUsersVisible');
  if (countEl) {
    const visible = document.querySelectorAll('#usersTable tbody tr:not(.empty-row):not([style*="display: none"]):not([style*="display:none"])').length;
    countEl.textContent = visible;
  }
}

function filterFeed() {
  const hasCaptionObj = document.getElementById('filterFeedHasCaption');
  const captionInput  = document.getElementById('filterFeedCaption');
  const captionQuery  = captionInput ? captionInput.value.toLowerCase().trim() : '';
  applyFilters('feedTable', 'filterFeedText', 'filterFeedStart', 'filterFeedEnd', (row) => {
    if (hasCaptionObj.checked && row.getAttribute('data-has-caption') !== '1') return false;
    if (captionQuery) {
      const captionEl = row.querySelector('.caption-cell');
      const captionText = captionEl ? captionEl.textContent.toLowerCase() : '';
      if (!captionText.includes(captionQuery)) return false;
    }
    return true;
  });
  // Update caption count badge
  const visible = document.querySelectorAll('#feedTable tbody tr:not(.empty-row):not([style*="display: none"]):not([style*="display:none"])').length;
  const countEl = document.getElementById('countFeedCaption');
  if (countEl) {
    if (captionQuery) {
      countEl.textContent = visible;
      countEl.style.display = '';
    } else {
      countEl.style.display = 'none';
    }
  }
  // Update total visible counter
  const countFeedEl = document.getElementById('countFeedVisible');
  if (countFeedEl) countFeedEl.textContent = visible;
}

function filterCommissions() {
  const userFilter   = (document.getElementById('filterCommUser')?.value   || '').toLowerCase().trim();
  const postIdFilter = (document.getElementById('filterCommPostId')?.value || '').trim();

  applyFilters('commTable', 'filterCommText', 'filterCommStart', 'filterCommEnd', (row) => {
    if (userFilter) {
      const rowUser = (row.getAttribute('data-username') || '').toLowerCase();
      if (!rowUser.includes(userFilter)) return false;
    }
    if (postIdFilter) {
      const rowId = (row.getAttribute('data-id') || '');
      if (!rowId.startsWith(postIdFilter)) return false;
    }
    return true;
  });

  const visibleRows = document.querySelectorAll('#commTable tbody tr:not(.empty-row):not([style*="display: none"]):not([style*="display:none"])').length;

  const countUser = document.getElementById('countCommUser');
  if (countUser) { countUser.textContent = visibleRows; countUser.style.display = userFilter ? '' : 'none'; }

  const countId = document.getElementById('countCommId');
  if (countId) { countId.textContent = visibleRows; countId.style.display = postIdFilter ? '' : 'none'; }

  // Always update the main visible counter
  const countVisible = document.getElementById('countCommVisible');
  if (countVisible) countVisible.textContent = visibleRows;
}

function applyCommLimit() {
  const raw   = (document.getElementById('commLimitInput')?.value ?? '').trim();
  const limit = raw === '' ? 0 : Math.max(0, parseInt(raw, 10) || 0);
  const url   = new URL(window.location.href);
  if (limit > 0) url.searchParams.set('comm_limit', limit);
  else           url.searchParams.delete('comm_limit');
  url.searchParams.set('tab', 'commissions');
  window.location.href = url.toString();
}

// ── Audit Log Filter ─────────────────────────────────────────────
function filterAuditLog() {
  const input   = document.getElementById('auditSearchInput');
  const text    = input ? input.value.toLowerCase().trim() : '';
  const entries = document.querySelectorAll('#auditList .audit-entry');
  const empty   = document.getElementById('auditEmpty');
  const counter = document.getElementById('auditResultCount');
  const clearBtn = document.getElementById('auditClearBtn');

  if (clearBtn) clearBtn.style.display = text ? 'flex' : 'none';

  // Collect ALL active action pills into a Set (multi-select support).
  // An empty Set means "All" — no action filtering applied.
  const activePills = document.querySelectorAll('[aria-label="Filter by action type"] .audit-pill.active');
  const activeActions = new Set(
    [...activePills]
      .map(p => p.getAttribute('data-action-filter') || '')
      .filter(v => v !== '')
  );

  let visible = 0;
  entries.forEach(entry => {
    const data   = (entry.getAttribute('data-search') || '').toLowerCase();
    const action = (entry.getAttribute('data-action') || '').toLowerCase();
    const matchText   = text === '' || data.includes(text);
    const matchAction = activeActions.size === 0 || activeActions.has(action);
    const show = matchText && matchAction;
    entry.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  if (empty) empty.style.display = visible === 0 ? '' : 'none';
  if (counter) {
    const total    = entries.length;
    const filtered = text !== '' || activeActions.size > 0;
    if (!filtered) {
      counter.textContent = total === 0
        ? 'No entries found.'
        : `${total} ${total === 1 ? 'entry' : 'entries'} · shown`;
    } else {
      counter.textContent = visible === 0
        ? 'No entries match your filters.'
        : `${visible} of ${total} ${total === 1 ? 'entry' : 'entries'} match`;
    }
  }
}

function clearAuditSearch() {
  const input = document.getElementById('auditSearchInput');
  if (input) { input.value = ''; input.focus(); }
  filterAuditLog();
}

function setAuditWindow(hours, btn) {
  // Update pill active state immediately for snappy feel
  document.querySelectorAll('[aria-label="Time window"] .audit-pill').forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');

  // Re-fetch by navigating; preserve any active search text, limit, sort, and action filter
  const search = (document.getElementById('auditSearchInput')?.value ?? '').trim();
  const limit  = (document.getElementById('auditLimitInput')?.value ?? '').trim();
  const url = new URL(window.location.href);
  url.searchParams.set('audit_hours', hours);
  if (search) url.searchParams.set('audit_search', search);
  else url.searchParams.delete('audit_search');
  if (limit && parseInt(limit) > 0) url.searchParams.set('audit_limit', limit);
  else url.searchParams.delete('audit_limit');
  const activeVals = [...document.querySelectorAll('[aria-label="Filter by action type"] .audit-pill.active')]
    .map(p => p.getAttribute('data-action-filter') || '').filter(v => v !== '');
  if (activeVals.length > 0) url.searchParams.set('audit_action', activeVals.join(','));
  else url.searchParams.delete('audit_action');
  window.location.href = url.toString();
}

function setAuditSort(sortDir, btn) {
  // Update sort pill active state
  document.querySelectorAll('[aria-label="Sort order"] .audit-pill').forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');

  const search = (document.getElementById('auditSearchInput')?.value ?? '').trim();
  const limit  = (document.getElementById('auditLimitInput')?.value ?? '').trim();
  const url = new URL(window.location.href);
  url.searchParams.set('audit_sort', sortDir);
  if (search) url.searchParams.set('audit_search', search);
  else url.searchParams.delete('audit_search');
  if (limit && parseInt(limit) > 0) url.searchParams.set('audit_limit', limit);
  else url.searchParams.delete('audit_limit');
  const activeVals = [...document.querySelectorAll('[aria-label="Filter by action type"] .audit-pill.active')]
    .map(p => p.getAttribute('data-action-filter') || '').filter(v => v !== '');
  if (activeVals.length > 0) url.searchParams.set('audit_action', activeVals.join(','));
  else url.searchParams.delete('audit_action');
  window.location.href = url.toString();
}

function applyAuditLimit() {
  const limit  = (document.getElementById('auditLimitInput')?.value ?? '').trim();
  const search = (document.getElementById('auditSearchInput')?.value ?? '').trim();
  const sort   = new URL(window.location.href).searchParams.get('audit_sort') ?? 'desc';
  const url = new URL(window.location.href);
  if (limit && parseInt(limit) > 0) url.searchParams.set('audit_limit', limit);
  else url.searchParams.delete('audit_limit');
  if (search) url.searchParams.set('audit_search', search);
  else url.searchParams.delete('audit_search');
  url.searchParams.set('audit_sort', sort);
  const activeVals = [...document.querySelectorAll('[aria-label="Filter by action type"] .audit-pill.active')]
    .map(p => p.getAttribute('data-action-filter') || '').filter(v => v !== '');
  if (activeVals.length > 0) url.searchParams.set('audit_action', activeVals.join(','));
  else url.searchParams.delete('audit_action');
  window.location.href = url.toString();
}

function setAuditActionFilter(actionVal, btn) {
  const allPills = document.querySelectorAll('[aria-label="Filter by action type"] .audit-pill');
  const allBtn   = document.querySelector('[aria-label="Filter by action type"] .audit-pill[data-action-filter=""]');

  if (actionVal === '') {
    // "All" pill — clear every specific filter
    allPills.forEach(p => p.classList.remove('active'));
    if (allBtn) allBtn.classList.add('active');
  } else {
    // Toggle the clicked pill on/off
    if (btn) btn.classList.toggle('active');
    // If no specific pill is active, fall back to "All"
    const anySpecificActive = [...allPills].some(
      p => p.getAttribute('data-action-filter') !== '' && p.classList.contains('active')
    );
    if (allBtn) allBtn.classList.toggle('active', !anySpecificActive);
  }

  // Re-filter client-side immediately (no page reload)
  filterAuditLog();

  // Persist active filters in the URL (comma-separated) so time-window /
  // sort / limit navigations carry the selection forward.
  const activeVals = [...document.querySelectorAll('[aria-label="Filter by action type"] .audit-pill.active')]
    .map(p => p.getAttribute('data-action-filter') || '')
    .filter(v => v !== '');
  const url = new URL(window.location.href);
  if (activeVals.length > 0) url.searchParams.set('audit_action', activeVals.join(','));
  else url.searchParams.delete('audit_action');
  window.history.replaceState(null, '', url.toString());
}

function statusClassName(status) {
  return 'status-badge status-' + String(status).toLowerCase().replaceAll(' ', '-');
}

async function saveCommissionForm(form, successMessage) {
  const payload = new FormData(form);

  try {
    const response = await fetch('commission_update.php', {
      method: 'POST',
      body: payload
    });
    const data = await response.json();

    if (!data.success) {
      showActionMessage(data.error || 'Commission update failed.', true);
      return;
    }

    const commissionId = form.dataset.commissionId;
    const statusBadge  = document.getElementById('commission-status-' + commissionId);
    const noteDisplay  = document.getElementById('commission-note-display-' + commissionId);
    const amountDisplay = document.getElementById('commission-amount-display-' + commissionId);
    const noteField    = form.querySelector('.commission-note');

    if (statusBadge) {
      statusBadge.className = statusClassName(data.status);
      statusBadge.textContent = data.status;
    }

    if (noteDisplay && noteField) {
      noteDisplay.textContent = noteField.value.trim() || 'No note yet.';
    }

    if (amountDisplay && data.amount_formatted) {
      amountDisplay.textContent = data.amount_formatted;
    }

    showActionMessage(successMessage || `Commission #${commissionId} updated.`);
  } catch (error) {
    showActionMessage('Commission update failed. Please try again.', true);
  }
}

// Wire up commission forms — only non-self-request rows have .commission-form elements.
// Rows where the admin is the requester render a locked placeholder instead (self-approval prevention).
document.querySelectorAll('.commission-form').forEach(form => {
  const select = form.querySelector('.commission-select');

  form.addEventListener('submit', event => {
    event.preventDefault();
    saveCommissionForm(form, `Commission #${form.dataset.commissionId} saved.`);
  });

  select?.addEventListener('change', () => {
    saveCommissionForm(form, `Commission #${form.dataset.commissionId} status updated.`);
  });
});

new Chart(document.getElementById('pipelineChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: ['Pending','Accepted','Ongoing','Delayed','Completed','Cancelled'],
    datasets: [{
      data: [
        <?php echo $pipeline['Pending']; ?>,
        <?php echo $pipeline['Accepted']; ?>,
        <?php echo $pipeline['Ongoing']; ?>,
        <?php echo $pipeline['Delayed']; ?>,
        <?php echo $pipeline['Completed']; ?>,
        <?php echo $pipeline['Cancelled']; ?>
      ],
      backgroundColor: ['#f39c12','#2ecc71','#3498db','#e67e22','#27ae60','#e74c3c'],
      borderWidth: 0,
      hoverOffset: 8
    }]
  },
  options: {
    responsive: false,
    plugins: { legend: { display: false } },
    cutout: '62%'
  }
});
</script>

<!-- ── Ban User Modal ── -->
<div id="banUserModal" class="modal fade" tabindex="-1" aria-labelledby="banUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header ban-modal-header">
        <h5 class="modal-title ban-modal-title" id="banUserModalLabel">Ban User Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="ban-warning">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
          </svg>
          <h6>Ban Account</h6>
          <p id="banUserInfo" class="delete-user-info"></p>
        </div>
        <form id="banUserForm" method="POST" onsubmit="submitBanUserForm(event)">
          <input type="hidden" name="action" value="ban_user"/>
          <input type="hidden" name="target_id" id="banUserId" value=""/>

          <div class="form-group">
            <label for="banReasonTextarea" class="form-label">Reason for Ban</label>
            <p class="form-text">Provide a reason for banning this account. This will be recorded in the audit log.</p>
            <textarea
              id="banReasonTextarea"
              name="ban_reason"
              class="form-control deletion-reason-textarea"
              placeholder="e.g., Repeated violations of community guidelines, Harassment, Spam activity..."
              rows="5"
              maxlength="1000"></textarea>
            <div class="reason-char-count">
              <span id="banCharCount">0</span>/1000 characters
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn ban-modal-confirm-btn" onclick="confirmBanUser()">Ban Account</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Unban User Modal ── -->
<div id="unbanUserModal" class="modal fade" tabindex="-1" aria-labelledby="unbanUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header unban-modal-header">
        <h5 class="modal-title unban-modal-title" id="unbanUserModalLabel">Unban User Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="unban-warning">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="9 12 11 14 15 10"></polyline>
          </svg>
          <h6>Restore Account Access</h6>
          <p id="unbanUserInfo" class="delete-user-info"></p>
        </div>
        <p class="unban-description">
          Unbanning this account will restore the user's ability to log in and use the platform.
          This action will be recorded in the audit log.
        </p>
        <form id="unbanUserForm" method="POST">
          <input type="hidden" name="action" value="unban_user"/>
          <input type="hidden" name="target_id" id="unbanUserId" value=""/>
        </form>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn unban-modal-confirm-btn" onclick="confirmUnbanUser()">Restore Access</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Remove Post Modal ── -->
<div id="removePostModal" class="modal fade" tabindex="-1" aria-labelledby="removePostModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header delete-modal-header">
        <h5 class="modal-title" id="removePostModalLabel">Remove Post</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="delete-warning">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <h6>Remove Post Permanently</h6>
          <p id="removePostInfo" class="delete-user-info"></p>
        </div>
        <form id="removePostForm" method="POST" onsubmit="submitRemovePostForm(event)">
          <input type="hidden" name="action" value="delete_post"/>
          <input type="hidden" name="target_id" id="removePostId" value=""/>

          <div class="form-group">
            <label for="removalReasonTextarea" class="form-label">Reason for Removal</label>
            <p class="form-text">Inform the post owner why their post is being removed. This message will be sent to their email.</p>
            <textarea
              id="removalReasonTextarea"
              name="removal_reason"
              class="form-control deletion-reason-textarea"
              placeholder="e.g., Violation of community guidelines, Inappropriate content, Spam..."
              rows="5"
              maxlength="1000"
              required></textarea>
            <div class="reason-char-count">
              <span id="removalCharCount">0</span>/1000 characters
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmRemovePost()">Remove Post</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Delete User Modal ── -->
<div id="deleteUserModal" class="modal fade" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header delete-modal-header">
        <h5 class="modal-title" id="deleteUserModalLabel">Delete User Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="delete-warning">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <h6>Permanently Delete Account</h6>
          <p id="deleteUserInfo" class="delete-user-info"></p>
        </div>
        <form id="deleteUserForm" method="POST" onsubmit="submitDeleteUserForm(event)">
          <input type="hidden" name="action" value="delete_user"/>
          <input type="hidden" name="target_id" id="deleteUserId" value=""/>
          
          <div class="form-group">
            <label for="deletionReasonTextarea" class="form-label">Reason for Deletion</label>
            <p class="form-text">Inform the user why their account is being deleted. This message will be sent to their email.</p>
            <textarea 
              id="deletionReasonTextarea"
              name="deletion_reason"
              class="form-control deletion-reason-textarea"
              placeholder="e.g., Violation of community guidelines, Spam activity, User request..."
              rows="5"
              maxlength="1000"
              required></textarea>
            <div class="reason-char-count">
              <span id="charCount">0</span>/1000 characters
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteUser()">Delete Account Permanently</button>
      </div>
    </div>
  </div>
</div>

<script>
let deleteUserModal;

function openDeleteUserModal(userId, username, email) {
  const userInfo = document.getElementById('deleteUserInfo');
  userInfo.innerHTML = `<strong>${username}</strong> (${email})`;
  
  document.getElementById('deleteUserId').value = userId;
  document.getElementById('deletionReasonTextarea').value = '';
  document.getElementById('charCount').textContent = '0';
  
  deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
  deleteUserModal.show();
}

function confirmDeleteUser() {
  const reason = document.getElementById('deletionReasonTextarea').value.trim();
  
  if (!reason) {
    alert('Please provide a reason for the account deletion.');
    return;
  }
  
  if (!confirm('Are you sure? This action cannot be undone. The user account and all associated data will be permanently deleted.')) {
    return;
  }
  
  document.getElementById('deleteUserForm').submit();
}

// Character counter for deletion reason
document.getElementById('deletionReasonTextarea').addEventListener('input', function() {
  document.getElementById('charCount').textContent = this.value.length;
});
</script>

<script>
let banUserModal;

function openBanUserModal(userId, username, email) {
  const userInfo = document.getElementById('banUserInfo');
  userInfo.innerHTML = `<strong>${username}</strong> (${email})`;

  document.getElementById('banUserId').value = userId;
  document.getElementById('banReasonTextarea').value = '';
  document.getElementById('banCharCount').textContent = '0';

  banUserModal = new bootstrap.Modal(document.getElementById('banUserModal'));
  banUserModal.show();
}

function confirmBanUser() {
  const reason = document.getElementById('banReasonTextarea').value.trim();

  if (!reason) {
    alert('Please provide a reason for banning this account.');
    return;
  }

  if (!confirm('Are you sure you want to ban this account? The user will lose access to the platform.')) {
    return;
  }

  document.getElementById('banUserForm').submit();
}

// Character counter for ban reason
document.getElementById('banReasonTextarea').addEventListener('input', function() {
  document.getElementById('banCharCount').textContent = this.value.length;
});
</script>

<script>
let unbanUserModal;

function openUnbanUserModal(userId, username, email) {
  const userInfo = document.getElementById('unbanUserInfo');
  userInfo.innerHTML = `<strong>${username}</strong> (${email})`;

  document.getElementById('unbanUserId').value = userId;

  unbanUserModal = new bootstrap.Modal(document.getElementById('unbanUserModal'));
  unbanUserModal.show();
}

function confirmUnbanUser() {
  if (!confirm('Restore access for this account? The user will be able to log in again.')) {
    return;
  }
  document.getElementById('unbanUserForm').submit();
}
</script>


<!-- ── Promote User Modal ── -->
<div id="promoteUserModal" class="modal fade" tabindex="-1" aria-labelledby="promoteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header promote-modal-header">
        <h5 class="modal-title promote-modal-title" id="promoteUserModalLabel">Promote to Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="promote-banner-box">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="8 12 12 8 16 12"></polyline>
            <line x1="12" y1="16" x2="12" y2="8"></line>
          </svg>
          <h6>Confirm Role Change</h6>
          <p id="promoteUserInfo" class="delete-user-info"></p>
        </div>
        <form id="promoteUserForm" method="POST">
          <input type="hidden" name="action" value="promote_to_admin"/>
          <input type="hidden" name="target_id" id="promoteUserId" value=""/>
        </form>
        <p class="promote-description">
          This user will gain access to the Admin Dashboard, including user management,
          post moderation, and audit log visibility.
        </p>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn promote-modal-confirm-btn" onclick="confirmPromoteUser()">Promote to Admin</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Demote User Modal ── -->
<div id="demoteUserModal" class="modal fade" tabindex="-1" aria-labelledby="demoteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header demote-modal-header">
        <h5 class="modal-title demote-modal-title" id="demoteUserModalLabel">Demote to User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="demote-banner-box">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="16 12 12 16 8 12"></polyline>
            <line x1="12" y1="8" x2="12" y2="16"></line>
          </svg>
          <h6>Confirm Role Change</h6>
          <p id="demoteUserInfo" class="delete-user-info"></p>
        </div>
        <form id="demoteUserForm" method="POST">
          <input type="hidden" name="action" value="demote_to_user"/>
          <input type="hidden" name="target_id" id="demoteUserId" value=""/>
        </form>
        <p class="demote-description">
          This user will lose all administrative privileges but will retain their posts and account data.
        </p>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn demote-modal-confirm-btn" onclick="confirmDemoteUser()">Demote to User</button>
      </div>
    </div>
  </div>
</div>

<script>
let promoteUserModal;

function openPromoteModal(userId, username, email) {
  document.getElementById('promoteUserInfo').innerHTML = `<strong>${username}</strong> (${email})`;
  document.getElementById('promoteUserId').value = userId;
  promoteUserModal = new bootstrap.Modal(document.getElementById('promoteUserModal'));
  promoteUserModal.show();
}

function confirmPromoteUser() {
  if (!confirm('Are you sure you want to promote this user to Admin? They will gain full admin dashboard access.')) return;
  document.getElementById('promoteUserForm').submit();
}
</script>

<script>
let demoteUserModal;

function openDemoteModal(userId, username, email) {
  document.getElementById('demoteUserInfo').innerHTML = `<strong>${username}</strong> (${email})`;
  document.getElementById('demoteUserId').value = userId;
  demoteUserModal = new bootstrap.Modal(document.getElementById('demoteUserModal'));
  demoteUserModal.show();
}

function confirmDemoteUser() {
  if (!confirm('Are you sure you want to demote this admin to a regular User? They will lose all admin access.')) return;
  document.getElementById('demoteUserForm').submit();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- ── Delete Commission Modal ── -->
<div id="deleteCommissionModal" class="modal fade" tabindex="-1" aria-labelledby="deleteCommissionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content delete-modal-content">
      <div class="modal-header delete-modal-header">
        <h5 class="modal-title" id="deleteCommissionModalLabel">Delete Commission</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body delete-modal-body">
        <div class="delete-warning">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <h6>Permanently Delete Commission</h6>
          <p id="deleteCommissionInfo" class="delete-user-info"></p>
        </div>
        <form id="deleteCommissionForm" method="POST" onsubmit="submitDeleteCommissionForm(event)">
          <input type="hidden" name="action" value="delete_commission"/>
          <input type="hidden" name="target_id" id="deleteCommissionId" value=""/>
          <div class="form-group">
            <label for="commissionDeletionReasonTextarea" class="form-label">Reason for Deletion</label>
            <p class="form-text">Provide a reason for removing this commission. This will be recorded in the audit log.</p>
            <textarea
              id="commissionDeletionReasonTextarea"
              name="deletion_reason"
              class="form-control deletion-reason-textarea"
              placeholder="e.g., Duplicate request, Invalid submission, Requester asked for removal…"
              rows="5"
              maxlength="1000"
              required></textarea>
            <div class="reason-char-count">
              <span id="commissionCharCount">0</span>/1000 characters
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer delete-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteCommission()">Delete Commission Permanently</button>
      </div>
    </div>
  </div>
</div>

<script>
let deleteCommissionModal;

function openDeleteCommissionModal(commissionId, title, username) {
  const info = document.getElementById('deleteCommissionInfo');
  info.innerHTML = `<strong>${title}</strong> — requested by <strong>@${username}</strong>`;

  document.getElementById('deleteCommissionId').value = commissionId;
  document.getElementById('commissionDeletionReasonTextarea').value = '';
  document.getElementById('commissionCharCount').textContent = '0';

  deleteCommissionModal = new bootstrap.Modal(document.getElementById('deleteCommissionModal'));
  deleteCommissionModal.show();
}

function confirmDeleteCommission() {
  const reason = document.getElementById('commissionDeletionReasonTextarea').value.trim();

  if (!reason) {
    alert('Please provide a reason for deleting this commission.');
    return;
  }

  if (!confirm('Are you sure? This will permanently delete the commission and cannot be undone.')) {
    return;
  }

  document.getElementById('deleteCommissionForm').submit();
}

function submitDeleteCommissionForm(event) {
  event.preventDefault();
  confirmDeleteCommission();
}

document.getElementById('commissionDeletionReasonTextarea').addEventListener('input', function() {
  document.getElementById('commissionCharCount').textContent = this.value.length;
});
</script>

</body>
</html>