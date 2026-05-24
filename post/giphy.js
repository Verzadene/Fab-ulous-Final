/**
 * giphy.js — FABulous Giphy GIF Picker
 * =====================================
 * Shared module used by both post.php (comments) and messages.php.
 *
 * Public API (all attached to window.Giphy):
 *   Giphy.init(apiKey)           — call once with GIPHY_API_KEY from PHP
 *   Giphy.open(context, onPick)  — open the modal; onPick(gifUrl) fires on selection
 *   Giphy.close()                — close the modal programmatically
 *
 * Usage example:
 *   Giphy.init('<?= GIPHY_API_KEY ?>');
 *   Giphy.open('comments', url => { selectedGifUrl = url; });
 */

(function (window) {
    'use strict';

    // ── State ─────────────────────────────────────────────────────────────────
    let _apiKey    = '';
    let _onPick    = null;
    let _debounce  = null;
    let _modal     = null;
    let _grid      = null;
    let _input     = null;
    let _offset    = 0;
    const LIMIT    = 12;

    // ── Build DOM (once) ──────────────────────────────────────────────────────
    function _buildModal() {
        if (_modal) return;

        _modal = document.createElement('div');
        _modal.id = 'giphyModal';
        _modal.setAttribute('aria-modal', 'true');
        _modal.setAttribute('role', 'dialog');
        _modal.setAttribute('aria-label', 'Pick a GIF');
        _modal.innerHTML = `
<div class="giphy-backdrop"></div>
<div class="giphy-dialog">
  <div class="giphy-header">
    <span class="giphy-logo">
      <!-- Giphy attribution logo (required by Giphy TOS) -->
      <svg height="18" viewBox="0 0 68 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="GIPHY">
        <path d="M2 2h4v24H2V2zm8 0h16v4H10V2zm0 10h12v4H10v-4zm0 10h16v4H10v-4zm20-20h4v24h-4V2zm8 0h4v10h-4V2zm0 14h4v10h-4v-10zm8-14h4v4h-4V2zm0 10h4v4h-4v-4zm0 10h4v4h-4v-4z" fill="url(#giphyGrad)"/>
        <defs>
          <linearGradient id="giphyGrad" x1="0" y1="0" x2="68" y2="28" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#00ff99"/>
            <stop offset="50%" stop-color="#9933ff"/>
            <stop offset="100%" stop-color="#ff6666"/>
          </linearGradient>
        </defs>
      </svg>
    </span>
    <button class="giphy-close" aria-label="Close GIF picker">&times;</button>
  </div>
  <div class="giphy-search-wrap">
    <input class="giphy-search" type="search" placeholder="Search GIFs…" autocomplete="off" maxlength="80"/>
  </div>
  <div class="giphy-grid" role="list"></div>
  <div class="giphy-load-more">
    <button class="giphy-load-btn">Load more</button>
  </div>
  <p class="giphy-attribution">Powered by GIPHY</p>
</div>`;

        document.body.appendChild(_modal);
        _injectStyles();

        _grid   = _modal.querySelector('.giphy-grid');
        _input  = _modal.querySelector('.giphy-search');

        _modal.querySelector('.giphy-close').addEventListener('click', Giphy.close);
        _modal.querySelector('.giphy-backdrop').addEventListener('click', Giphy.close);
        _modal.querySelector('.giphy-load-btn').addEventListener('click', () => _fetch(false));

        _input.addEventListener('input', () => {
            clearTimeout(_debounce);
            _debounce = setTimeout(() => _fetch(true), 400);
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && _modal.classList.contains('giphy-open')) Giphy.close();
        });
    }

    // ── Styles (injected once) ────────────────────────────────────────────────
    function _injectStyles() {
        if (document.getElementById('giphyStyles')) return;
        const s = document.createElement('style');
        s.id = 'giphyStyles';
        s.textContent = `
#giphyModal {
  display:none; position:fixed;
  left:0; top:0; width:100vw; height:100vh;
  z-index:9999;
}
#giphyModal.giphy-open { display:flex; align-items:center; justify-content:center; }
.giphy-backdrop { position:absolute; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.55); }
.giphy-dialog {
  position:relative; z-index:1; background:#18181b; border-radius:12px;
  width:min(520px,96vw); max-height:80vh; display:flex; flex-direction:column;
  overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,.6);
  box-sizing:border-box;
}
.giphy-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 14px 0;
}
.giphy-close {
  background:none; border:none; color:#aaa; font-size:1.5rem; cursor:pointer;
  line-height:1; padding:0 4px;
}
.giphy-close:hover { color:#fff; }
.giphy-search-wrap { padding:8px 14px; }
.giphy-search {
  width:100%; padding:8px 12px; border-radius:8px; border:1px solid #333;
  background:#27272a; color:#fff; font-size:.95rem; outline:none;
}
.giphy-search:focus { border-color:#9333ff; }
.giphy-grid {
  flex:1; min-height:0; overflow-y:auto; overflow-x:hidden;
  padding:8px 14px; box-sizing:border-box;
  display:flex; flex-wrap:wrap; gap:8px; align-content:flex-start;
}
.giphy-grid-item {
  border-radius:6px; overflow:hidden; cursor:pointer;
  background:#27272a; flex:0 0 calc(33.333% - 6px);
  height:120px;
}
.giphy-grid-item img {
  width:100%; height:100%; object-fit:cover; display:block;
  transition:opacity .15s;
}
.giphy-grid-item:hover img { opacity:.8; }
.giphy-grid-item:focus { outline:2px solid #9333ff; }
.giphy-load-more { padding:8px 14px; text-align:center; }
.giphy-load-btn {
  background:#27272a; color:#aaa; border:1px solid #333; border-radius:8px;
  padding:6px 20px; cursor:pointer; font-size:.9rem;
}
.giphy-load-btn:hover { background:#333; color:#fff; }
.giphy-attribution { text-align:center; color:#555; font-size:.75rem; padding:4px 0 10px; margin:0; }
.giphy-status { color:#888; text-align:center; padding:24px; grid-column:1/-1; }

/* GIF preview badge on comment / message input */
.giphy-preview-wrap {
  display:flex; align-items:center; gap:8px;
  padding:4px 0;
}
.giphy-preview-thumb {
  height:64px; border-radius:6px; border:1px solid #333;
}
.giphy-preview-remove {
  background:none; border:none; color:#e55; cursor:pointer; font-size:1.2rem;
  line-height:1; padding:0;
}
`;
        document.head.appendChild(s);
    }

    // ── Fetch GIFs from Giphy ─────────────────────────────────────────────────
    async function _fetch(reset) {
        if (reset) _offset = 0;

        const q        = (_input.value || '').trim();
        const endpoint = q
            ? `https://api.giphy.com/v1/gifs/search?api_key=${_apiKey}&q=${encodeURIComponent(q)}&limit=${LIMIT}&offset=${_offset}&rating=g`
            : `https://api.giphy.com/v1/gifs/trending?api_key=${_apiKey}&limit=${LIMIT}&offset=${_offset}&rating=g`;

        if (reset) {
            _grid.innerHTML = `<p class="giphy-status">Loading…</p>`;
        }

        try {
            const res  = await fetch(endpoint);
            const data = await res.json();

            if (reset) _grid.innerHTML = '';

            if (!data.data || !data.data.length) {
                if (reset) _grid.innerHTML = `<p class="giphy-status">No GIFs found.</p>`;
                return;
            }

            data.data.forEach(gif => {
                const img   = gif.images.fixed_height_small || gif.images.fixed_width || gif.images.original;
                const item  = document.createElement('div');
                item.className = 'giphy-grid-item';
                item.setAttribute('role', 'listitem');
                item.setAttribute('tabindex', '0');
                item.setAttribute('aria-label', gif.title || 'GIF');
                item.innerHTML = `<img src="${img.url}" alt="${gif.title || ''}" loading="lazy"/>`;
                item.addEventListener('click',  () => _pick(img.url));
                item.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') _pick(img.url); });
                _grid.appendChild(item);
            });

            _offset += LIMIT;
        } catch (err) {
            if (reset) _grid.innerHTML = `<p class="giphy-status">Could not load GIFs.</p>`;
            console.error('[Giphy] fetch error:', err);
        }
    }

    function _pick(url) {
        if (typeof _onPick === 'function') _onPick(url);
        Giphy.close();
    }

    // ── Public API ────────────────────────────────────────────────────────────
    const Giphy = {
        init(apiKey) {
            _apiKey = apiKey;
            _buildModal();
        },

        open(context, onPick) {
            _onPick = onPick;
            _offset = 0;
            _input.value = '';
            _modal.classList.add('giphy-open');
            _fetch(true);
            setTimeout(() => _input.focus(), 80);
        },

        close() {
            _modal && _modal.classList.remove('giphy-open');
            _onPick = null;
        },
    };

    window.Giphy = Giphy;
}(window));