<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/MessageRepository.php';

if (empty($_SESSION['user'])) {
    header('Location: ../login/login.php');
    exit;
}

if (empty($_SESSION['mfa_verified'])) {
    header('Location: ../login/verify_mfa.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$username = $_SESSION['user']['username'];
$name = $_SESSION['user']['name'];
$role = $_SESSION['user']['role'] ?? 'user';
$isAdmin = in_array($role, ['admin', 'super_admin'], true);

$connMessages = db_connect('messages');
$hasMessagesTable = (bool) $connMessages->query("SHOW TABLES LIKE 'messages'")->num_rows;
$connFriendships = db_connect('friendships');
$hasFriendships   = (bool) $connFriendships->query("SHOW TABLES LIKE 'friendships'")->num_rows;
$selectedPersonId = (int) ($_GET['friend'] ?? 0);

$myAvatarUrl = get_current_user_avatar();

$msgRepo = new MessageRepository('db_connect');
$contacts = $msgRepo->getContacts($userId, $hasFriendships);

$selectedContact = null;
foreach ($contacts as $contact) {
    if ($selectedPersonId === (int) $contact['id']) {
        $selectedContact = $contact;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FABulous - Messages</title>
  <link rel="icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link rel="shortcut icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="post.css"/>
  <link rel="stylesheet" href="messages.css"/>
  <script src="giphy.js"></script>
</head>
<body>
  <?php
  $navActive = 'messages';
  $navRoot = '../';
  require __DIR__ . '/../includes/app_nav.php';
  ?>

  <div class="dashboard-body messages-dashboard">
    <div class="messages-layout">
      <aside class="messages-friends side-card">
        <div class="messages-card-head">
          <div>
            <p class="side-card-kicker">Community</p>
            <h2 class="messages-title">Messages</h2>
          </div>

        </div>
        <p class="messages-subtitle">Message anyone on FABulous. Friends are listed first.</p>

        <label class="friend-search-shell messages-search-shell" for="friendFilter">
          <span class="friend-search-icon">&#128269;</span>
          <input id="friendFilter" class="friend-search-input" type="search" placeholder="Filter people" autocomplete="off"/>
        </label>

        <div class="message-friend-list" id="friendList">
          <?php if (empty($contacts)): ?>
            <div class="messages-empty">
              <strong>No accounts found</strong>
              <span>There are no other registered accounts yet.</span>
            </div>
          <?php else: ?>
            <?php
              $hasFriendContacts = false;
              foreach ($contacts as $c) {
                  if (($c['friend_status'] ?? 'none') === 'accepted') { $hasFriendContacts = true; break; }
              }
            ?>
            <div class="messages-empty messages-search-empty" id="friendSearchEmpty"
              <?php echo ($hasFriendContacts || $selectedContact) ? 'style="display:none;"' : ''; ?>>
              <strong>Search for someone</strong>
              <span>Type a name or username to show matching people.</span>
            </div>
            <?php foreach ($contacts as $contact): ?>
              <?php
                $contactPic = !empty($contact['profile_pic'])
                    ? '../uploads/profile_pics/' . rawurlencode($contact['profile_pic'])
                    : null;
                $isFriend = ($contact['friend_status'] ?? 'none') === 'accepted';
                // Friends are visible by default; non-friends are hidden until searched
                $isActive  = $selectedPersonId === (int) $contact['id'];
                $showRow   = $isActive || $isFriend;
              ?>
              <a
                href="?friend=<?php echo (int) $contact['id']; ?>"
                class="message-friend-row<?php echo $isActive ? ' active' : ''; ?>"
                data-name="<?php echo htmlspecialchars(strtolower($contact['name'])); ?>"
                data-username="<?php echo htmlspecialchars(strtolower($contact['username'])); ?>"
                data-is-friend="<?php echo $isFriend ? '1' : '0'; ?>"
                style="<?php echo $showRow ? '' : 'display:none;'; ?>"
              >
                <span class="message-friend-avatar">
                  <?php if ($contactPic): ?>
                    <img src="<?php echo htmlspecialchars($contactPic); ?>" class="msg-contact-img" alt=""/>
                  <?php else: ?>
                    <?php echo htmlspecialchars(strtoupper(substr($contact['username'], 0, 1))); ?>
                  <?php endif; ?>
                </span>
                <span class="message-friend-copy">
                  <strong><?php echo htmlspecialchars($contact['name']); ?></strong>
                  <small>@<?php echo htmlspecialchars($contact['username']); ?>
                    <?php if ($isFriend): ?>
                      <span class="msg-friend-tag">&#10003; Friend</span>
                    <?php endif; ?>
                  </small>
                  <?php if (!empty($contact['bio'])): ?>
                    <div style="font-size:0.8em; opacity:0.7; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($contact['bio']); ?></div>
                  <?php endif; ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </aside>

      <main class="messages-thread side-card">
        <?php if (!$hasMessagesTable): ?>
          <div class="messages-empty messages-unavailable">
            <strong>Messages are not available yet</strong>
            <span>The code is ready, but your database still needs a compatible <code>messages</code> table. Run <code>migration_v5.sql</code>.</span>
          </div>
        <?php elseif (!$selectedContact): ?>
          <div class="messages-empty">
            <strong>Select someone to message</strong>
            <span>Choose any account from the left panel to start a conversation.</span>
          </div>
        <?php else: ?>
          <?php
            $selPic = !empty($selectedContact['profile_pic'])
                ? '../uploads/profile_pics/' . rawurlencode($selectedContact['profile_pic'])
                : null;
            $selIsFriend = ($selectedContact['friend_status'] ?? 'none') === 'accepted';
          ?>
          <div class="thread-head">
            <div class="thread-person">
              <span class="message-friend-avatar large">
                <?php if ($selPic): ?>
                  <img src="<?php echo htmlspecialchars($selPic); ?>" class="msg-contact-img large" alt=""/>
                <?php else: ?>
                  <?php echo htmlspecialchars(strtoupper(substr($selectedContact['username'], 0, 1))); ?>
                <?php endif; ?>
              </span>
              <div>
                <h3><?php echo htmlspecialchars($selectedContact['name']); ?></h3>
                <p>@<?php echo htmlspecialchars($selectedContact['username']); ?></p>
                <?php if (!empty($selectedContact['bio'])): ?>
                  <p style="font-size:0.85em; opacity:0.8; margin-top:2px; margin-bottom:0; line-height:1.3; max-width:350px;"><?php echo htmlspecialchars($selectedContact['bio']); ?></p>
                <?php endif; ?>
              </div>
            </div>
            <span class="thread-badge">
              <?php echo $selIsFriend ? '&#10003; Friend' : 'Not a friend'; ?>
            </span>
          </div>

          <div class="thread-stream" id="threadStream">
            <div class="messages-loading">Loading conversation...</div>
          </div>

          <form class="thread-composer" id="messageForm" enctype="multipart/form-data">
            <textarea
              id="messageInput"
              class="thread-input"
              placeholder="Write a message..."
              maxlength="1000"
              rows="3"
            ></textarea>
            <input type="file" id="msgImageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" style="display:none"/>
            <div class="thread-previews">
              <div id="messageImagePreview" style="display:none; align-items:center; gap:8px; padding:4px 0;">
                <img id="messageImageThumb" src="" alt="Selected image" style="height:64px; border-radius:6px; border:1px solid #ccc;"/>
                <button type="button" id="messageImageRemove" style="background:none;border:none;color:#e55;cursor:pointer;font-size:1.3rem;line-height:1;">&times;</button>
              </div>
              <div id="messageGifPreview" style="display:none; align-items:center; gap:8px; padding:4px 0;">
                <img id="messageGifThumb" src="" alt="Selected GIF" style="height:64px; border-radius:6px; border:1px solid #333;"/>
                <button type="button" id="messageGifRemove" style="background:none;border:none;color:#e55;cursor:pointer;font-size:1.3rem;line-height:1;">&times;</button>
              </div>
            </div>
            <div class="thread-actions">
              <p class="thread-helper">Messages refresh automatically every few seconds.</p>
              <div class="thread-action-btns">
                <button type="button" id="msgImageBtn" class="comment-gif-btn" title="Attach an image">&#128247;</button>
                <button type="button" id="messageGifBtn" class="comment-gif-btn" title="Add a GIF">GIF</button>
              </div>
              <button type="submit" class="thread-send">Send Message</button>
            </div>
          </form>
        <?php endif; ?>
      </main>
    </div>
  </div>

  <script>
    const burgerBtn = document.getElementById('burgerBtn');
    const navDrawer = document.getElementById('navDrawer');
    const drawerOverlay = document.getElementById('drawerOverlay');
    const friendFilter = document.getElementById('friendFilter');
    const threadStream = document.getElementById('threadStream');
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    const selectedFriendId = <?php echo (int) $selectedPersonId; ?>;
    const messagesReady = <?php echo $hasMessagesTable ? 'true' : 'false'; ?>;
    let pollHandle = null;
    let selectedMessageGif = null;
    let selectedMessageImage = null; // File object for upload

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

    function esc(value) {
      const div = document.createElement('div');
      div.textContent = String(value ?? '');
      return div.innerHTML;
    }

    function renderMessages(messages) {
      if (!threadStream) return;

      if (!messages.length) {
        threadStream.innerHTML = `
          <div class="messages-empty inline-empty">
            <strong>No messages yet</strong>
            <span>Say hello and start the conversation.</span>
          </div>
        `;
        return;
      }

      threadStream.innerHTML = messages.map(message => `
        <article class="message-bubble ${message.is_mine ? 'mine' : 'theirs'}">
          <div class="message-meta">
            <strong>${esc(message.sender_name)}</strong>
            <span>${esc(message.sent_at)}</span>
          </div>
          ${message.message_text ? `<p>${esc(message.message_text).replace(/\n/g, '<br>')}</p>` : ''}
          ${message.image_url ? `<img src="${esc(message.image_url)}" alt="Image" class="message-img" loading="lazy"/>` : ''}
          ${message.gif_url ? `<img src="${esc(message.gif_url)}" alt="GIF" class="message-gif-img" loading="lazy"/>` : ''}
        </article>
      `).join('');

      threadStream.scrollTop = threadStream.scrollHeight;
    }

    async function loadConversation() {
      if (!messagesReady || !selectedFriendId || !threadStream) return;

      try {
        const response = await fetch(`messages_api.php?action=conversation&friend_id=${selectedFriendId}`);
        const data = await response.json();

        if (!data.success) {
          threadStream.innerHTML = `
            <div class="messages-empty inline-empty">
              <strong>Conversation unavailable</strong>
              <span>${esc(data.error || 'Unable to load messages right now.')}</span>
            </div>
          `;
          return;
        }

        renderMessages(data.messages || []);
      } catch (error) {
        threadStream.innerHTML = `
          <div class="messages-empty inline-empty">
            <strong>Connection issue</strong>
            <span>We could not refresh this conversation.</span>
          </div>
        `;
      }
    }

    async function sendMessage(event) {
      event.preventDefault();
      if (!selectedFriendId || !messageInput) return;

      const message = messageInput.value.trim();
      if (!message && !selectedMessageGif && !selectedMessageImage) return;

      const formData = new FormData();
      formData.append('action', 'send');
      formData.append('friend_id', String(selectedFriendId));
      formData.append('message_text', message);
      formData.append('gif_url', selectedMessageGif || '');
      if (selectedMessageImage) {
        formData.append('image', selectedMessageImage);
      }

      try {
        const response = await fetch('messages_api.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();
        if (data.success) {
          messageInput.value = '';
          selectedMessageGif = null;
          selectedMessageImage = null;
          document.getElementById('messageGifPreview').style.display = 'none';
          document.getElementById('messageImagePreview').style.display = 'none';
          document.getElementById('msgImageInput').value = '';
          await loadConversation();
        } else {
          alert('Could not send message: ' + (data.error || 'An unknown error occurred.'));
        }
      } catch (error) {
        console.error('Message send failed.', error);
        alert('Message send failed. Please check the console for details.');
      }
    }

    friendFilter?.addEventListener('input', event => {
      const query = event.target.value.trim().toLowerCase();
      const list = document.getElementById('friendList');
      const empty = document.getElementById('friendSearchEmpty');
      const rows = Array.from(document.querySelectorAll('.message-friend-row'));

      function matchScore(item) {
        const name = item.dataset.name || '';
        const username = item.dataset.username || '';
        const isFriend = item.dataset.isFriend === '1';
        const haystack = `${name} ${username}`;

        if (!query) {
          // No search: friends visible (score 5), non-friends hidden
          return isFriend ? 5 : Infinity;
        }

        if (!haystack.includes(query)) return Infinity;
        // Exact match
        if (username === query || name === query) return 0;
        // Friend boost: friends ranked above non-friends for same query
        const boost = isFriend ? 0 : 100;
        if (username.startsWith(query)) return boost + 1;
        if (name.startsWith(query)) return boost + 2;
        const usernameIndex = username.indexOf(query);
        const nameIndex = name.indexOf(query);
        return boost + 10 + Math.min(
          usernameIndex >= 0 ? usernameIndex : 999,
          nameIndex >= 0 ? nameIndex : 999
        );
      }

      let visible = 0;
      rows
        .map(item => ({ item, score: matchScore(item) }))
        .sort((a, b) => a.score - b.score)
        .forEach(({ item, score }) => {
          const show = Number.isFinite(score);
          item.style.display = show ? '' : 'none';
          if (show) {
            visible++;
            list?.appendChild(item);
          }
        });

      if (empty) {
        const hasFriends = rows.some(r => r.dataset.isFriend === '1');
        if (!query) {
          // Show empty state only when there are truly no friends
          empty.style.display = hasFriends ? 'none' : '';
          empty.querySelector('strong').textContent = 'Search for someone';
          empty.querySelector('span').textContent = 'Type a name or username to show matching people.';
        } else {
          empty.style.display = visible ? 'none' : '';
          empty.querySelector('strong').textContent = 'No matches found';
          empty.querySelector('span').textContent = 'Try a more specific username or name.';
        }
      }
    });

    messageForm?.addEventListener('submit', sendMessage);

    document.getElementById('messageGifBtn')?.addEventListener('click', () => {
      Giphy.open('messages', gifUrl => {
        selectedMessageGif = gifUrl;
        const preview = document.getElementById('messageGifPreview');
        const thumb   = document.getElementById('messageGifThumb');
        if (preview && thumb) {
          thumb.src = gifUrl;
          preview.style.display = 'flex';
        }
      });
    });

    document.getElementById('messageGifRemove')?.addEventListener('click', () => {
      selectedMessageGif = null;
      const preview = document.getElementById('messageGifPreview');
      if (preview) preview.style.display = 'none';
    });

    document.getElementById('msgImageBtn')?.addEventListener('click', () => {
      document.getElementById('msgImageInput')?.click();
    });

    document.getElementById('msgImageInput')?.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;
      const allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type)) {
        alert('Only JPG, JPEG, PNG, and WebP images are accepted.');
        this.value = '';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        alert('Image must be 5 MB or smaller.');
        this.value = '';
        return;
      }
      selectedMessageImage = file;
      const thumb   = document.getElementById('messageImageThumb');
      const preview = document.getElementById('messageImagePreview');
      if (thumb && preview) {
        thumb.src = URL.createObjectURL(file);
        preview.style.display = 'flex';
      }
    });

    document.getElementById('messageImageRemove')?.addEventListener('click', () => {
      selectedMessageImage = null;
      document.getElementById('msgImageInput').value = '';
      const preview = document.getElementById('messageImagePreview');
      if (preview) preview.style.display = 'none';
    });

    if (messagesReady && selectedFriendId) {
      loadConversation();
      pollHandle = window.setInterval(loadConversation, 4000);
    }

    window.addEventListener('beforeunload', () => {
      if (pollHandle) {
        window.clearInterval(pollHandle);
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

    document.addEventListener('click', function (e) {
      if (e.target.matches('.message-gif-img, .message-img')) {
        openLightbox(e.target.src);
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