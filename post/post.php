<?php
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['user'])) {
    header('Location: ../login/login.php');
    exit;
}

if (empty($_SESSION['mfa_verified'])) {
    header('Location: ../login/verify_mfa.php');
    exit;
}

$userID = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'];
$name = $_SESSION['user']['name'];
$role = $_SESSION['user']['role'] ?? 'user';
$isAdmin = in_array($role, ['admin', 'super_admin'], true);

$myAvatarUrl = get_current_user_avatar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FABulous - Dashboard</title>
  <link rel="icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link rel="shortcut icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="post.css"/>
  <script src="giphy.js"></script>
</head>
<body>
  <?php
  $navActive = 'feed';
  $navRoot = '../';
  require __DIR__ . '/../includes/app_nav.php';
  ?>

  <div class="dashboard-body">
    <div class="dashboard-grid">
      <main class="feed">
        <button class="create-post-btn" onclick="openModal()">
          + Share a Project or Update
        </button>

        <div id="postModal" class="modal-overlay" onclick="closeModalOutside(event)">
          <div class="modal-card">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-body">
              <div class="modal-left">
                <h2 class="modal-title">Create Post</h2>
                <?php
                $postError = '';
                $rawErr = $_GET['post_error'] ?? '';
                if ($rawErr === 'invalid_image_type') {
                    $postError = 'Only JPG, JPEG, PNG, and WebP images are accepted.';
                } elseif ($rawErr === 'image_too_large') {
                    $postError = 'Image must be 5 MB or smaller.';
                }
                ?>
                <form action="create_post.php" method="POST" enctype="multipart/form-data" id="postForm">
                  <?php if ($postError): ?>
                  <div class="post-form-error" id="postFormError"><?php echo htmlspecialchars($postError); ?></div>
                  <?php endif; ?>
                  <textarea
                    name="caption"
                    class="caption-input"
                    placeholder="Share your project update..."
                    rows="6"
                    oninput="updatePreview()"
                  ></textarea>
                  <div class="img-attach-row">
                    <label class="file-label" id="imgPickLabel">
                      <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" id="imgInput" onchange="previewImage(this)"/>
                      &#128247; Attach Image (optional)
                    </label>
                    <div class="img-attached-chip" id="imgAttachedChip" style="display:none;">
                      <span class="img-attached-name" id="imgAttachedName"></span>
                      <button type="button" class="img-remove-btn" onclick="removeImage()" aria-label="Remove image">&times;</button>
                    </div>
                  </div>
                  <div class="modal-actions">
                    <button type="button" class="modal-btn-discard" onclick="closeModal()">Discard</button>
                    <button type="submit" class="modal-btn-upload">Upload</button>
                  </div>
                </form>
              </div>
              <div class="modal-right">
                <h3 class="preview-heading">Post Preview</h3>
                <div class="preview-card">
                  <div class="preview-header">
                    <div class="preview-avatar">
                      <?php if ($myAvatarUrl): ?>
                        <img src="<?php echo htmlspecialchars($myAvatarUrl); ?>" alt="Profile" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"/>
                      <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="32" height="32">
                          <circle cx="50" cy="35" r="22" fill="#4E7A5E"/>
                          <ellipse cx="50" cy="85" rx="35" ry="25" fill="#4E7A5E"/>
                        </svg>
                      <?php endif; ?>
                    </div>
                    <p class="preview-author"><?php echo htmlspecialchars($username); ?></p>
                  </div>
                  <img id="previewImg" class="preview-img" style="display:none;" alt=""/>
                  <p id="previewCaption" class="preview-caption"></p>
                  <div class="preview-actions"><span>&#10084; 0</span><span>&#128172; 0</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div id="feedContainer">
          <p style="padding: 20px; text-align: center; color: #888;">Loading feed...</p>
        </div>
      </main>

      <aside class="right-rail">
        <section class="side-card notif-card">
          <div class="side-card-header">
            <div>
              <p class="side-card-kicker">Updates</p>
              <h3 class="side-card-title notif-heading">
                Notifications
                <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
              </h3>
            </div>
            <button class="notif-mark-read" onclick="markAllRead()" title="Mark all read">&#10003;</button>
          </div>
          <div id="notifList" class="notif-list">
            <p class="notif-empty">Loading...</p>
          </div>
        </section>

        <section class="side-card friends-card">
          <div class="side-card-header friends-card-header">
            <div>
              <p class="side-card-kicker">Community</p>
              <h3 class="side-card-title">Friends</h3>
            </div>
            <span class="friend-count-pill" id="friendCountPill">0</span>
          </div>

          <label class="friend-search-shell" for="friendSearchInput">
            <span class="friend-search-icon">&#128269;</span>
            <input id="friendSearchInput" class="friend-search-input" type="search" placeholder="Search people" autocomplete="off"/>
          </label>

          <div class="friend-section">
            <div class="friend-section-heading">Pending Requests</div>
            <div class="friend-stack" id="friendRequestsList">
              <p class="friend-empty">No pending requests.</p>
            </div>
          </div>

          <div class="friend-section">
            <div class="friend-section-heading">Your Friends</div>
            <div class="friend-stack" id="friendsList">
              <p class="friend-empty">No friends yet.</p>
            </div>
          </div>

          <div class="friend-section">
            <div class="friend-section-heading">Find People</div>
            <div class="friend-stack" id="friendResultsList">
              <p class="friend-empty friend-empty-alone">Search by name or username to connect.</p>
            </div>
          </div>
        </section>
      </aside>
    </div>
  </div>

<!-- Edit Post Modal -->
<div id="editPostModal" class="modal-overlay" onclick="closeEditModalOutside(event)" style="display:none;">
  <div class="modal-card" style="max-width:520px;">
    <button class="modal-close" onclick="closeEditPost()">&times;</button>
    <div class="modal-body" style="flex-direction:column;gap:16px;">
      <h2 class="modal-title">Edit Post</h2>
      <form id="editPostForm" onsubmit="submitEditPost(event)">
        <input type="hidden" id="editPostId" name="post_id"/>
        <textarea id="editPostCaption" name="caption" class="caption-input" rows="6" maxlength="2000" required></textarea>
        <div class="modal-actions" style="margin-top:12px;">
          <button type="button" class="modal-btn-discard" onclick="closeEditPost()">Cancel</button>
          <button type="submit" class="modal-btn-upload">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const burgerBtn = document.getElementById('burgerBtn');
const navDrawer = document.getElementById('navDrawer');
const drawerOverlay = document.getElementById('drawerOverlay');
const friendSearchInput = document.getElementById('friendSearchInput');
const friendRequestsList = document.getElementById('friendRequestsList');
const friendsList = document.getElementById('friendsList');
const friendResultsList = document.getElementById('friendResultsList');
const friendCountPill = document.getElementById('friendCountPill');
const myUserID = <?php echo (int)$userID; ?>;
let friendDirectory = [];

// ── Giphy state: maps postID → selected gif URL ───────────────────────────────
const selectedCommentGifs = {};
Giphy.init('<?= htmlspecialchars(GIPHY_API_KEY, ENT_QUOTES) ?>');

function toggleDrawer(forceState) {
  const shouldOpen = typeof forceState === 'boolean'
    ? forceState
    : !navDrawer.classList.contains('open');

  navDrawer.classList.toggle('open', shouldOpen);
  drawerOverlay.classList.toggle('show', shouldOpen);
  document.body.classList.toggle('menu-open', shouldOpen);
  burgerBtn?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
}

function closeDrawer() {
  toggleDrawer(false);
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeDrawer();
});

function openModal() {
  document.getElementById('postModal').classList.add('show');
}

function closeModal() {
  document.getElementById('postModal').classList.remove('show');
}

function closeModalOutside(event) {
  if (event.target.id === 'postModal') closeModal();
}

function previewImage(input) {
  const img       = document.getElementById('previewImg');
  const label     = document.getElementById('imgPickLabel');
  const chip      = document.getElementById('imgAttachedChip');
  const chipName  = document.getElementById('imgAttachedName');

  if (input.files && input.files[0]) {
    img.src = URL.createObjectURL(input.files[0]);
    img.style.display = 'block';
    chipName.textContent = input.files[0].name;
    label.style.display = 'none';
    chip.style.display  = 'inline-flex';
  }
}

function removeImage() {
  const imgInput  = document.getElementById('imgInput');
  const img       = document.getElementById('previewImg');
  const label     = document.getElementById('imgPickLabel');
  const chip      = document.getElementById('imgAttachedChip');

  // Reset the file input by replacing it with a clone (most reliable cross-browser)
  const newInput = imgInput.cloneNode(true);
  newInput.addEventListener('change', () => previewImage(newInput));
  imgInput.replaceWith(newInput);

  img.src           = '';
  img.style.display = 'none';
  chip.style.display  = 'none';
  label.style.display = 'inline-flex';
}

function updatePreview() {
  document.getElementById('previewCaption').textContent =
    document.querySelector('.caption-input').value;
}

function esc(value) {
  const div = document.createElement('div');
  div.textContent = String(value ?? '');
  return div.innerHTML;
}

function normalizeFriendRecord(record) {
  return {
    id: Number(record.id),
    name: String(record.name ?? '').trim() || String(record.username ?? ''),
    username: String(record.username ?? ''),
    bio: String(record.bio ?? ''),
    profile_pic: record.profile_pic ? String(record.profile_pic) : null,
    friend_status: String(record.friend_status ?? 'none'),
    friendship_id: record.friendship_id ? Number(record.friendship_id) : 0,
    friend_requester: record.friend_requester ? Number(record.friend_requester) : 0
  };
}

function updateFriendRecord(authorID, patch) {
  friendDirectory = friendDirectory.map(record => {
    const normalized = normalizeFriendRecord(record);
    if (normalized.id !== Number(authorID)) return normalized;
    return { ...normalized, ...patch };
  });
  renderFriendsPanel();
  syncDiscoverFriendAction(authorID);
}

function updateFriendRecordByFriendship(friendshipID, patch) {
  let targetID = null;
  friendDirectory = friendDirectory.map(record => {
    const normalized = normalizeFriendRecord(record);
    if (normalized.friendship_id !== Number(friendshipID)) return normalized;
    targetID = normalized.id;
    return { ...normalized, ...patch };
  });
  renderFriendsPanel();
  if (targetID !== null) {
    syncDiscoverFriendAction(targetID);
  }
}

function renderDiscoverFriendButton(record) {
  const authorID = Number(record.id);
  if (record.friend_status === 'accepted') {
    return '<span class="friend-badge">&#10003; Friends</span>';
  }
  if (record.friend_status === 'pending' && record.friend_requester === myUserID) {
    return `<button class="btn-friend-pending" onclick="cancelFriendRequest(${record.friendship_id}, ${authorID})">Cancel</button>`;
  }
  if (record.friend_status === 'pending' && record.friend_requester !== myUserID) {
    return `<button class="btn-accept-friend" onclick="acceptFriendRequest(${record.friendship_id}, ${authorID})">Accept</button>`;
  }
  return `<button class="btn-add-friend" onclick="sendFriendRequest(${authorID})">+ Add Friend</button>`;
}

function syncDiscoverFriendAction(authorID) {
  const slot = document.getElementById('fa-' + authorID);
  if (!slot) return;
  const record = friendDirectory.map(normalizeFriendRecord).find(item => item.id === Number(authorID));
  if (!record) return;
  slot.innerHTML = renderDiscoverFriendButton(record);
}

async function loadFriendDirectory() {
  try {
    const response = await fetch('friends.php?action=list');
    const data = await response.json();
    if (data.success) {
      friendDirectory = data.directory || [];
      renderFriendsPanel();
    }
  } catch (error) {
    console.error('Error loading friend directory:', error);
  }
}

async function loadFeed() {
  const filter = localStorage.getItem('feedFilter') || 'friends';
  try {
    const response = await fetch('feed_api.php?filter=' + filter);
    const result = await response.json();
    if (result.status === 'success') {
      renderFeed(result.data.posts, filter);
    }
  } catch (error) {
    console.error('Error loading feed:', error);
  }
}

function setFeedFilter(filter) {
  localStorage.setItem('feedFilter', filter);
  updateFilterButtons(filter);
  loadFeed();
}

function updateFilterButtons(filter) {
  const friendsBtn = document.getElementById('filterFriends');
  const publicBtn  = document.getElementById('filterPublic');
  if (!friendsBtn || !publicBtn) return;
  friendsBtn.classList.toggle('active', filter === 'friends');
  publicBtn.classList.toggle('active',  filter === 'public');
}

function renderFeed(posts, filter) {
  const container = document.getElementById('feedContainer');
  if (!posts || posts.length === 0) {
    const emptyMsg = filter === 'public'
      ? '<p>No posts yet.</p><p class="empty-sub">Be the first to share a project!</p>'
      : '<p>No posts from friends yet.</p><p class="empty-sub">Add friends from the right panel to start building your feed.</p>';
    container.innerHTML = `<div class="empty-feed">${emptyMsg}</div>`;
    return;
  }
  
  container.innerHTML = posts.map(post => {
    const isOwnPost = Number(post.authorID) === myUserID;
    const avatar = post.author_pic 
      ? `<img src="../uploads/profile_pics/${encodeURIComponent(post.author_pic)}" class="post-avatar-img" alt=""/>`
      : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="42" height="42"><circle cx="50" cy="35" r="22" fill="#4E7A5E"/><ellipse cx="50" cy="85" rx="35" ry="25" fill="#4E7A5E"/></svg>`;
    
    const dateObj = new Date(post.created_at.replace(/-/g, '/'));
    const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
                    ' at ' + 
                    dateObj.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });

    let ownActions = isOwnPost ? `
        <div class="post-own-actions">
          <button class="post-own-btn" onclick="openEditPost(${post.postID}, decodeURIComponent('${encodeURIComponent(post.caption || '')}'))" title="Edit">&#9998;</button>
          <button class="post-own-btn post-own-delete" onclick="deletePost(${post.postID})" title="Delete">&#128465;</button>
        </div>` : '';

    const captionHtml = post.caption ? `<p class="post-caption">${esc(post.caption).replace(/\\n/g, '<br>')}</p>` : '';
    const imgHtml = post.image_url ? `<img src="${esc(post.image_url)}" class="post-image" alt="Post image"/>` : '';
    const bioHtml = post.author_bio ? `<span class="post-bio" style="display:block; font-size:0.85em; opacity:0.8; margin-bottom:2px;">${esc(post.author_bio)}</span>` : '';

    return `
      <div class="post-card" id="post-${post.postID}">
        <div class="post-header">
          <div class="post-avatar">${avatar}</div>
          <div class="post-meta">
            <span class="post-author">${esc(post.author)}</span>
            ${bioHtml}
            <span class="post-time">${dateStr}</span>
          </div>
          ${ownActions}
        </div>
        ${captionHtml}
        ${imgHtml}
        <div class="post-actions">
          <div class="like-btn-wrap" onmouseenter="showLikePopup(this,${post.postID})" onmouseleave="hideLikePopup(this)">
            <button class="post-action-btn like-btn ${post.user_liked ? 'liked' : ''}" onclick="toggleLike(${post.postID}, this)">
              <span class="heart-icon">&#10084;</span>
              <span class="like-count">${post.like_count}</span>
            </button>
            <div class="like-popup" id="like-popup-${post.postID}"></div>
          </div>
          <button class="post-action-btn comment-toggle-btn" onclick="toggleComments(${post.postID})">
            <span>&#128172;</span>
            <span>${post.comment_count} Comment${Number(post.comment_count) !== 1 ? 's' : ''}</span>
          </button>
        </div>
        <div class="comments-section" id="comments-${post.postID}" style="display:none;">
          <div class="comments-list" id="clist-${post.postID}"></div>
          <form class="comment-form" onsubmit="submitComment(event, ${post.postID})">
            <input type="text" class="comment-input" placeholder="Write a comment..." maxlength="500"/>
            <button type="button" class="comment-gif-btn" onclick="openCommentGifPicker(${post.postID})" title="Add a GIF">GIF</button>
            <button type="submit" class="comment-submit">Post</button>
          </form>
          <div class="comment-gif-preview" id="cgif-${post.postID}" style="display:none;">
            <img class="comment-gif-thumb" src="" alt="Selected GIF"/>
            <button type="button" class="comment-gif-remove" onclick="removeCommentGif(${post.postID})" title="Remove GIF">&times;</button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

function buildFriendRow(record, actionMarkup) {
  const safeName = esc(record.name);
  const safeUsername = esc(record.username);
  const initial = safeUsername.charAt(0).toUpperCase() || 'U';
  const avatarHtml = record.profile_pic
    ? `<img src="../uploads/profile_pics/${encodeURIComponent(record.profile_pic)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" alt=""/>`
    : initial;

  const bioHtml = record.bio ? `<div class="friend-bio" style="font-size:0.8em; opacity:0.7; margin-top:2px; line-height:1.2; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(record.bio)}</div>` : '';

  return `
    <div class="friend-row">
      <div class="friend-person">
        <div class="friend-avatar">${avatarHtml}</div>
        <div class="friend-copy">
          <div class="friend-name">${safeName}</div>
          <div class="friend-handle">@${safeUsername}</div>
          ${bioHtml}
        </div>
      </div>
      <div class="friend-row-actions">${actionMarkup}</div>
    </div>
  `;
}

function getFilteredFriendMatches() {
  const query = (friendSearchInput?.value || '').trim().toLowerCase();
  if (!query) return [];

  return friendDirectory
    .map(normalizeFriendRecord)
    .filter(record => {
      const name = record.name.toLowerCase();
      const username = record.username.toLowerCase();
      return name.includes(query) || username.includes(query);
    });
}

function renderFriendsPanel() {
  const records = friendDirectory.map(normalizeFriendRecord);
  const incoming = records.filter(record => record.friend_status === 'pending' && record.friend_requester !== myUserID);
  const accepted = records.filter(record => record.friend_status === 'accepted');
  const matches = getFilteredFriendMatches();

  friendCountPill.textContent = String(accepted.length);

  friendRequestsList.innerHTML = incoming.length
    ? incoming.map(record => buildFriendRow(record, `
        <button class="friend-mini-btn accept" onclick="acceptFriendRequest(${record.friendship_id}, ${record.id})">Accept</button>
        <button class="friend-mini-btn remove" onclick="removeFriend(${record.friendship_id}, ${record.id}, 'reject')">Decline</button>
      `)).join('')
    : '<p class="friend-empty">No pending requests.</p>';

  friendsList.innerHTML = accepted.length
    ? accepted.map(record => buildFriendRow(record, `
        <button class="friend-mini-btn remove" onclick="removeFriend(${record.friendship_id}, ${record.id}, 'remove')">Remove</button>
      `)).join('')
    : '<p class="friend-empty">No friends yet. Start adding people below.</p>';

  if (!friendSearchInput?.value.trim()) {
    friendResultsList.innerHTML = '<p class="friend-empty friend-empty-alone">Search by name or username to connect.</p>';
    return;
  }

  friendResultsList.innerHTML = matches.length
    ? matches.map(record => {
        let actionMarkup = '';

        if (record.friend_status === 'accepted') {
          actionMarkup = `<button class="friend-mini-btn remove" onclick="removeFriend(${record.friendship_id}, ${record.id}, 'remove')">Remove</button>`;
        } else if (record.friend_status === 'pending' && record.friend_requester === myUserID) {
          actionMarkup = `<button class="friend-mini-btn pending" onclick="cancelFriendRequest(${record.friendship_id}, ${record.id})">Cancel</button>`;
        } else if (record.friend_status === 'pending') {
          actionMarkup = `
            <button class="friend-mini-btn accept" onclick="acceptFriendRequest(${record.friendship_id}, ${record.id})">Accept</button>
            <button class="friend-mini-btn remove" onclick="removeFriend(${record.friendship_id}, ${record.id}, 'reject')">Decline</button>
          `;
        } else {
          actionMarkup = `<button class="friend-mini-btn add" onclick="sendFriendRequest(${record.id})">Add</button>`;
        }

        return buildFriendRow(record, actionMarkup);
      }).join('')
    : '<p class="friend-empty friend-empty-alone">No people matched your search.</p>';
}

async function toggleLike(postID, btn) {
  try {
    const response = await fetch('like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'post_id=' + postID
    });
    const data = await response.json();
    if (data.status === 'success') {
      btn.querySelector('.like-count').textContent = data.data.like_count;
      btn.classList.toggle('liked', data.data.liked);
      // Invalidate liker cache so popup re-fetches on next hover
      delete likerCache[postID];
    }
  } catch (error) {
    console.error('Like error:', error);
  }
}

function toggleComments(postID) {
  const section = document.getElementById('comments-' + postID);
  const open = section.style.display === 'none';
  section.style.display = open ? 'block' : 'none';
  if (open) loadComments(postID);
}

async function loadComments(postID) {
  const list = document.getElementById('clist-' + postID);
  list.innerHTML = '<p class="loading-comments">Loading...</p>';
  try {
    const response = await fetch('comment.php?action=get&post_id=' + postID);
    const data = await response.json();
    list.innerHTML = '';

    if (data.status === 'success' && data.data.comments && data.data.comments.length) {
      data.data.comments.forEach(comment => {
        const item = document.createElement('div');
        item.className = 'comment-item';
          
          const isMine = Number(comment.userID) === myUserID;
          let actionsHtml = '';
          if (isMine) {
            const safeContent = encodeURIComponent(comment.content || '');
            actionsHtml = `
              <div class="comment-actions" style="font-size:0.8em; margin-top:2px;">
                <button type="button" onclick="editComment(${comment.commentID}, ${postID}, decodeURIComponent('${safeContent}'))" style="background:none;border:none;color:#888;cursor:pointer;padding:0;margin-right:8px;text-decoration:underline;">Edit</button>
                <button type="button" onclick="deleteComment(${comment.commentID}, ${postID})" style="background:none;border:none;color:#888;cursor:pointer;padding:0;text-decoration:underline;">Delete</button>
              </div>
            `;
          }
          item.innerHTML = `<div><span class="comment-author">${esc(comment.username)}</span> ${comment.content ? esc(comment.content) : ''}${comment.gif_url ? `<br><img src="${esc(comment.gif_url)}" alt="GIF" class="comment-gif-img" loading="lazy"/>` : ''}</div>${actionsHtml}`;
        list.appendChild(item);
      });
    } else {
      list.innerHTML = '<p class="no-comments">No comments yet. Be the first!</p>';
    }
  } catch (error) {
    list.innerHTML = '<p class="no-comments">Could not load comments.</p>';
  }
}

async function submitComment(event, postID) {
  event.preventDefault();
  const form    = event.target;
  const input   = form.querySelector('.comment-input');
  const content = input.value.trim();
  const gifUrl  = selectedCommentGifs[postID] || '';

  // Need at least text or a GIF
  if (!content && !gifUrl) return;

  try {
    const response = await fetch('comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=add&post_id=' + postID
          + '&content=' + encodeURIComponent(content)
          + '&gif_url='  + encodeURIComponent(gifUrl)
    });
    const data = await response.json();
    if (data.status === 'success') {
      input.value = '';
      removeCommentGif(postID);
      loadComments(postID);
    }
  } catch (error) {
    console.error('Comment error:', error);
  }
}

function openCommentGifPicker(postID) {
  Giphy.open('comments', gifUrl => {
    selectedCommentGifs[postID] = gifUrl;
    const preview = document.getElementById('cgif-' + postID);
    if (preview) {
      preview.style.display = 'flex';
      preview.querySelector('.comment-gif-thumb').src = gifUrl;
    }
    // A GIF alone is enough — remove the required attribute
    const form = preview?.previousElementSibling;
    if (form) form.querySelector('.comment-input').removeAttribute('required');
  });
}

function removeCommentGif(postID) {
  delete selectedCommentGifs[postID];
  const preview = document.getElementById('cgif-' + postID);
  if (preview) {
    preview.style.display = 'none';
    preview.querySelector('.comment-gif-thumb').src = '';
  }
}

async function editComment(commentID, postID, oldContent) {
  const newContent = prompt('Edit your comment:', oldContent);
  if (newContent === null) return;
  const content = newContent.trim();
  if (!content) return;

  try {
    const response = await fetch('comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=edit&comment_id=' + commentID + '&content=' + encodeURIComponent(content)
    });
    const data = await response.json();
    if (data.status === 'success') {
      loadComments(postID);
    }
  } catch (error) {
    console.error('Edit comment error:', error);
  }
}

async function deleteComment(commentID, postID) {
  if (!confirm('Delete this comment?')) return;
  try {
    const response = await fetch('comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=delete&comment_id=' + commentID
    });
    const data = await response.json();
    if (data.status === 'success') {
      loadComments(postID);
    }
  } catch (error) {
    console.error('Delete comment error:', error);
  }
}

async function sendFriendRequest(authorID) {
  const btn = document.querySelector(`#fa-${authorID} button`);
  if (btn) {
    btn.disabled = true;
    btn.textContent = '...';
  }

  try {
    const response = await fetch('friends.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=send&receiver_id=' + authorID
    });
    const data = await response.json();

    if (data.success) {
      updateFriendRecord(authorID, {
        friend_status: 'pending',
        friendship_id: Number(data.friendshipID),
        friend_requester: myUserID
      });
    } else if (btn) {
      btn.disabled = false;
      btn.textContent = '+ Add Friend';
    }
  } catch (error) {
    console.error(error);
  }
}

async function cancelFriendRequest(friendshipID, authorID) {
  try {
    const response = await fetch('friends.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=cancel&friendship_id=' + friendshipID
    });
    const data = await response.json();

    if (data.success) {
      updateFriendRecord(authorID, {
        friend_status: 'none',
        friendship_id: 0,
        friend_requester: 0
      });
      loadNotifications();
    }
  } catch (error) {
    console.error(error);
  }
}

async function acceptFriendRequest(friendshipID, authorID) {
  try {
    const response = await fetch('friends.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=accept&friendship_id=' + friendshipID
    });
    const data = await response.json();

    if (data.success) {
      updateFriendRecord(authorID, {
        friend_status: 'accepted',
        friendship_id: Number(friendshipID),
        friend_requester: 0
      });
      loadNotifications();
    }
  } catch (error) {
    console.error(error);
  }
}

async function removeFriend(friendshipID, authorID, action = 'remove') {
  try {
    const response = await fetch('friends.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=' + encodeURIComponent(action) + '&friendship_id=' + friendshipID
    });
    const data = await response.json();

    if (data.success) {
      updateFriendRecord(authorID, {
        friend_status: 'none',
        friendship_id: 0,
        friend_requester: 0
      });
      loadNotifications();
    }
  } catch (error) {
    console.error(error);
  }
}

async function loadNotifications() {
  try {
    const response = await fetch('notifications.php?action=list');
    const data = await response.json();
    const list = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');

    if (!data.success || !data.notifications.length) {
      list.innerHTML = '<p class="notif-empty">No notifications yet.</p>';
      badge.style.display = 'none';
      return;
    }

    const unread = data.notifications.filter(item => !parseInt(item.is_read, 10)).length;
    badge.textContent = unread;
    badge.style.display = unread > 0 ? 'inline-flex' : 'none';
    list.innerHTML = '';

    data.notifications.forEach(notification => {
      const item = document.createElement('div');
      item.className = 'notif-item' + (parseInt(notification.is_read, 10) ? '' : ' unread');
      item.dataset.id = notification.notifID;

      let actionHTML = '';
      if (notification.type === 'friend_request' && notification.ref_id) {
        actionHTML = `
          <div class="notif-actions">
            <button onclick="acceptFromNotif(${notification.ref_id}, ${notification.notifID})" class="btn-notif-accept">Accept</button>
            <button onclick="rejectFromNotif(${notification.ref_id}, ${notification.notifID})" class="btn-notif-reject">Decline</button>
          </div>
        `;
      }

      item.innerHTML = `
        <p class="notif-msg">${esc(notification.message)}</p>
        <span class="notif-time">${esc(String(notification.created_at).substring(0, 16).replace('T', ' '))}</span>
        ${actionHTML}
      `;

      item.addEventListener('click', function(event) {
        if (event.target.tagName === 'BUTTON') return;
        const type = notification.type;
        if (type === 'friend_request') return; // handled by Accept/Decline buttons
        markRead(notification.notifID).then(() => {
          if (type === 'message' && notification.actor_id) {
            window.location.href = 'messages.php?friend=' + notification.actor_id;
          } else if (type === 'commission_submitted' || type === 'commission_approved' ||
                     type === 'commission_updated'   || type === 'commission_paid') {
            window.location.href = 'commissions.php';
          } else {
            loadNotifications();
          }
        });
      });

      list.appendChild(item);
    });
  } catch (error) {
    console.error('Notification error:', error);
  }
}

async function acceptFromNotif(friendshipID, notifID) {
  try {
    const response = await fetch('friends.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=accept&friendship_id=' + friendshipID
    });
    const data = await response.json();

    if (data.success) {
      updateFriendRecordByFriendship(friendshipID, {
        friend_status: 'accepted',
        friendship_id: Number(friendshipID),
        friend_requester: 0
      });
      markRead(notifID);
      loadNotifications();
    }
  } catch (error) {
    console.error(error);
  }
}

async function rejectFromNotif(friendshipID, notifID) {
  try {
    const response = await fetch('friends.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=reject&friendship_id=' + friendshipID
    });
    const data = await response.json();

    if (data.success) {
      updateFriendRecordByFriendship(friendshipID, {
        friend_status: 'none',
        friendship_id: 0,
        friend_requester: 0
      });
      markRead(notifID);
      loadNotifications();
    }
  } catch (error) {
    console.error(error);
  }
}

async function markRead(notifID) {
  return fetch('notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=mark_read&notif_id=' + notifID
  });
}

async function markAllRead() {
  await fetch('notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=mark_read'
  });
  loadNotifications();
}

friendSearchInput?.addEventListener('input', renderFriendsPanel);

// ── Liker popup cache ─────────────────────────────────────────────────────────
const likerCache = {};

async function showLikePopup(wrap, postID) {
  const popup = document.getElementById('like-popup-' + postID);
  if (!popup) return;

  // Skip popup if no likes yet
  const count = parseInt(wrap.querySelector('.like-count')?.textContent || '0', 10);
  if (count < 1) return;

  popup.style.opacity = '1';

  // Serve from cache if fresh
  if (likerCache[postID] !== undefined) {
    popup.textContent = likerCache[postID];
    return;
  }

  popup.textContent = '…';

  try {
    const res  = await fetch('like.php?action=likers&post_id=' + postID);
    const data = await res.json();
    if (data.status === 'success') {
      const likers = data.likers || [];
      const extra  = count - likers.length;
      let text;
      if (likers.length === 0)      text = 'No likes yet';
      else if (extra <= 0)          text = likers.join(', ');
      else                          text = likers.join(', ') + ' and ' + extra + ' more';
      likerCache[postID] = text;
      popup.textContent  = text;
    } else {
      popup.textContent = '';
    }
  } catch (_) {
    popup.textContent = '';
  }
}

function hideLikePopup(wrap) {
  const popup = wrap.querySelector('.like-popup');
  if (popup) popup.style.opacity = '0';
}

// ── Smart feed-count polling (live reactions & comments, no full re-render) ───
async function pollFeedCounts() {
  const filter = localStorage.getItem('feedFilter') || 'friends';
  try {
    const res    = await fetch('feed_api.php?filter=' + filter);
    const result = await res.json();
    if (result.status !== 'success') return;

    (result.data.posts || []).forEach(post => {
      const card = document.getElementById('post-' + post.postID);
      if (!card) return;

      // Update like count + liked state
      const likeBtn = card.querySelector('.like-btn');
      if (likeBtn) {
        const countEl = likeBtn.querySelector('.like-count');
        const newCount = String(post.like_count);
        if (countEl && countEl.textContent !== newCount) {
          countEl.textContent = newCount;
          likeBtn.classList.toggle('liked', !!post.user_liked);
          delete likerCache[post.postID]; // stale — re-fetch on next hover
        }
      }

      // Update comment count label
      const commentBtn = card.querySelector('.comment-toggle-btn span:last-child');
      if (commentBtn) {
        const n       = Number(post.comment_count);
        const newText = n + ' Comment' + (n !== 1 ? 's' : '');
        if (commentBtn.textContent !== newText) commentBtn.textContent = newText;
      }
    });
  } catch (_) { /* silent — non-critical background update */ }
}

// ── Smart friend-directory polling (only re-renders if data changed) ──────────
let _lastFriendJSON = '';
const _origLoadFriendDirectory = loadFriendDirectory;
async function loadFriendDirectory() {
  try {
    const response = await fetch('friends.php?action=list');
    const data     = await response.json();
    if (data.success) {
      const newJSON = JSON.stringify(data.directory || []);
      if (newJSON !== _lastFriendJSON) {
        _lastFriendJSON = newJSON;
        friendDirectory = data.directory || [];
        renderFriendsPanel();
      }
    }
  } catch (error) {
    console.error('Error loading friend directory:', error);
  }
}

// ── Unified live polling ──────────────────────────────────────────────────────
loadFriendDirectory();
updateFilterButtons(localStorage.getItem('feedFilter') || 'friends');
loadFeed();
loadNotifications();

// Notifications + friend directory together every 10 s (friend requests show in both)
setInterval(() => { loadNotifications(); loadFriendDirectory(); }, 10000);
// Live reaction/comment counts every 15 s (slightly offset to spread requests)
setTimeout(() => setInterval(pollFeedCounts, 15000), 5000);

function openEditPost(postId, caption) {
  document.getElementById('editPostId').value = postId;
  document.getElementById('editPostCaption').value = caption;
  document.getElementById('editPostModal').style.display = 'flex';
}

function closeEditPost() {
  document.getElementById('editPostModal').style.display = 'none';
}

function closeEditModalOutside(event) {
  if (event.target.id === 'editPostModal') closeEditPost();
}

async function submitEditPost(event) {
  event.preventDefault();
  const postId = document.getElementById('editPostId').value;
  const caption = document.getElementById('editPostCaption').value.trim();
  if (!caption) return;

  try {
    const response = await fetch('edit_post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'post_id=' + encodeURIComponent(postId) + '&caption=' + encodeURIComponent(caption)
    });
    const data = await response.json();
    if (data.success) {
      const card = document.getElementById('post-' + postId);
      if (card) {
        const captionEl = card.querySelector('.post-caption');
        if (captionEl) captionEl.innerHTML = esc(caption).replace(/\n/g, '<br>');
      }
      closeEditPost();
    } else {
      alert(data.error || 'Could not save changes.');
    }
  } catch (error) {
    console.error('Edit post error:', error);
  }
}

async function deletePost(postId) {
  if (!confirm('Delete this post? This cannot be undone.')) return;

  try {
    const response = await fetch('delete_post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'post_id=' + postId
    });
    const data = await response.json();
    if (data.success) {
      const card = document.getElementById('post-' + postId);
      if (card) card.remove();
    } else {
      alert(data.error || 'Could not delete post.');
    }
  } catch (error) {
    console.error('Delete post error:', error);
  }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($postError): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  openModal();
  var el = document.getElementById('postFormError');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
<?php endif; ?>

<!-- Fullscreen image lightbox -->
<div id="imgLightbox" role="dialog" aria-modal="true" aria-label="Fullscreen image">
  <button id="imgLightboxClose" aria-label="Close">&times;</button>
  <img id="imgLightboxImg" src="" alt="Fullscreen image"/>
</div>
<script>
(function () {
  const box   = document.getElementById('imgLightbox');
  const img   = document.getElementById('imgLightboxImg');
  const close = document.getElementById('imgLightboxClose');

  function openLightbox(src) {
    img.src = src;
    box.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLightbox() {
    box.classList.remove('open');
    document.body.style.overflow = '';
    img.src = '';
  }

  // Delegate clicks on post images and comment GIFs
  document.addEventListener('click', function (e) {
    const t = e.target;
    if (t.matches('.post-image, .comment-gif-img')) {
      openLightbox(t.src);
    }
  });

  box.addEventListener('click', function (e) {
    if (e.target !== img) closeLightbox();
  });
  close.addEventListener('click', closeLightbox);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeLightbox();
  });
})();
</script>
</body>
</html>