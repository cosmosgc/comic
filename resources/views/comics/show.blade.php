<!DOCTYPE html>
<html>
<head>
    <title>{{ $comic->title }} - Comic Reader</title>
    <meta property="og:title" content="{{$comic->title}}" />
    <meta property="og:description" content="{{$comic->author}}" />
    <meta property="og:image" content="{{ asset('storage/' . $comic->image_path) }}" />
    <meta property="og:image:width" content="630">
    <meta property="og:image:height" content="1200">

    <meta property="twitter:title" content="{{$comic->title}}" />
    <meta property="twitter:description" content="{{$comic->author}}" />
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:image:src" content="{{ asset('storage/' . $comic->image_path) }}" />
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
            color: #fff;
            font-family: sans-serif;
        }
        .comic-header {
            padding: 20px;
            background-color: #2a2a2a;
            text-align: center;
        }
        .comic-header h1 {
            margin: 0;
            font-size: 2em;
        }
        .comic-meta {
            margin-top: 10px;
            font-size: 0.9em;
        }
        h5.card-title {
            color: white;
        }
        .tags, .collections {
            margin: 10px 0;
        }
        .tag, .collection {
            display: inline-block;
            background-color: #444;
            color: #fff;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 5px;
            font-size: 0.8em;
        }
        .comic-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .comic-container img {
            max-width: 100%;
            height: auto;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .progress-indicator {
        position: fixed;
        top: 10px;
        right: 10px;
        background-color: rgba(50, 50, 50, 0.8);
        padding: 8px 12px;
        border-radius: 5px;
        font-size: 0.9em;
        z-index: 1000;
        }
        .back-button {
            position: fixed;
            top: 10px;
            left: 10px;
            background-color: rgba(50, 50, 50, 0.8);
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.9em;
            color: #fff;
            text-decoration: none;
            z-index: 1000;
        }
        .back-button:hover {
            background-color: rgba(80, 80, 80, 0.8);
        }
        .back-to-top-button {
            position: fixed;
            background-color: #444;
            color: white;
            border: none;
            
            top: 10px;
            left: 100px;
            background-color: rgba(50, 50, 50, 0.8);
            padding: 6px 12px;
            margin-left: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .back-to-top-button:hover {
            background-color: #666;
        }

        .magnified {
            width: 100vw;
            height: auto;
            max-height: none;
            object-fit: contain;
            cursor: zoom-out;
            transition: width 0.3s ease, transform 0.3s ease;
            z-index: 1000;
            position: relative;
        }

        .engage-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            align-items: center;
            margin-top: 12px;
        }
        .engage-btn {
            background-color: rgba(50, 50, 50, 0.9);
            color: #fff;
            border: 1px solid #555;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .engage-btn:hover {
            background-color: rgba(80, 80, 80, 0.9);
        }
        .engage-btn.active {
            border-color: #e11d48;
            color: #fda4af;
        }
        .engage-btn.fav-active {
            border-color: #f59e0b;
            color: #fcd34d;
        }
        .comments-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: rgba(50, 50, 50, 0.92);
            color: #fff;
            border: 1px solid #555;
            padding: 10px 16px;
            border-radius: 24px;
            font-size: 0.9em;
            cursor: pointer;
            z-index: 1000;
        }
        .comments-fab:hover {
            background-color: rgba(80, 80, 80, 0.95);
        }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-backdrop.hidden {
            display: none;
        }
        .modal-panel {
            background-color: #2a2a2a;
            color: #fff;
            border-radius: 12px;
            max-width: 640px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            padding: 20px;
        }
        .modal-panel textarea {
            width: 100%;
            box-sizing: border-box;
            background-color: #1a1a1a;
            color: #fff;
            border: 1px solid #555;
            border-radius: 8px;
            padding: 8px;
            font-family: sans-serif;
        }
        .comment-item {
            border-top: 1px solid #444;
            padding: 10px 0;
        }
        .comment-item .meta {
            font-size: 0.8em;
            color: #bbb;
            margin-bottom: 4px;
        }
        .comment-item .actions {
            margin-top: 6px;
            display: flex;
            gap: 12px;
            font-size: 0.8em;
        }
        .comment-item .actions button {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 0;
        }
        .comment-item .actions button:hover {
            color: #fff;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-panel {
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background-color: #2a2a2a;
            border: 1px solid #555;
            border-radius: 8px;
            min-width: 220px;
            max-height: 260px;
            overflow-y: auto;
            z-index: 2001;
            padding: 6px;
            text-align: left;
        }
        .dropdown-panel.hidden {
            display: none;
        }
        .dropdown-panel label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            font-size: 0.85em;
            cursor: pointer;
            border-radius: 6px;
        }
        .dropdown-panel label:hover {
            background-color: #3a3a3a;
        }

    </style>
    
</head>
<body>
    
<a href="{{ url()->previous() }}" class="back-button">← Back</a>
<button onclick="topFunction()" id="backToTopButton" class="back-to-top-button">↑ Top</button>
<div class="progress-indicator" id="progress">Page 1 of {{ $comic->pages->count() }}</div>
<button type="button" id="commentsFab" class="comments-fab" title="Read and write comments">Comments</button>

<div id="infoModal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-label="Comic info and comments">
    <div class="modal-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h2 style="margin:0;">{{ $comic->title }}</h2>
            <button type="button" id="infoModalClose" class="engage-btn" title="Close">✕</button>
        </div>
        <p class="comic-meta">By {{ $comic->author ?? 'Unknown Author' }} · Views: {{ $comic->view_count }}</p>
        @if ($comic->description)
            <p>{{ $comic->description }}</p>
        @endif

        <h3>Comments (<span id="modalCommentCount">{{ $comic->comments_count ?? 0 }}</span>)</h3>
        <div id="commentsList"></div>
        <div style="display:flex;gap:8px;justify-content:center;margin:10px 0;">
            <button type="button" id="commentsPrev" class="engage-btn">← Newer</button>
            <span id="commentsPage" style="align-self:center;font-size:0.85em;"></span>
            <button type="button" id="commentsNext" class="engage-btn">Older →</button>
        </div>

        @auth
            <textarea id="commentBox" rows="3" maxlength="2000" placeholder="Write a comment... (Enter to send, Shift+Enter for a new line)"></textarea>
            <div style="text-align:right;margin-top:8px;">
                <button type="button" id="commentSubmit" class="engage-btn">Post comment</button>
            </div>
        @else
            <p><a href="{{ route('login') }}" style="color:#fff;">Log in</a> to join the discussion.</p>
        @endauth
    </div>
</div>

<div class="comic-header">
    <h1>{{ $comic->title }}</h1>
    <div class="comic-meta">
        <p>By {{ $comic->author ?? 'Unknown Author' }}</p>
        @if ($comic->user)
            <a href="{{ route('profile.public.show.username', ['username' => $comic->user->name]) }}">

                <div class="uploader-info">
                    <h5 class="card-title">{{ $comic->user->name }}</h5>
                    <img 
                        src="{{ $comic->user->avatar_image_path ? asset($comic->user->avatar_image_path) : asset('default-avatar.png') }}" 
                        alt="{{ $comic->user->name }}'s avatar" 
                        class="avatar-image"
                        style="max-width: 100px; border-radius: 50%;"
                    >
                </div>
            </a>
        @else
            <p>Uploaded By Unknown Uploader</p>
        @endif
        
        <p>{{ $comic->description }}</p>
        <p>Views: {{ $comic->view_count }}</p>
    </div>
    @if($comic->tags->count())
        <div class="tags">
            <strong>Tags:</strong>
            @foreach($comic->tags as $tag)
                <span class="tag">{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif
    @php($visibleCollections = $visibleCollections ?? $comic->collections)
    @if($visibleCollections->count())
        <div class="collections">
            <strong>Collections:</strong>
            @foreach($visibleCollections as $collection)
                <span class="collection">{{ $collection->name }}</span>
            @endforeach
        </div>
    @endif

    <div class="engage-row">
        @auth
            <button type="button" id="likeBtn" class="engage-btn {{ ($liked ?? false) ? 'active' : '' }}" title="Like this comic">
                <span id="likeHeart">{{ ($liked ?? false) ? '♥' : '♡' }}</span>
                <span id="likeCount">{{ $comic->liked_by_users_count ?? 0 }}</span>
            </button>
        @else
            <a class="engage-btn" href="{{ route('login') }}" title="Log in to like">♡ <span>{{ $comic->liked_by_users_count ?? 0 }}</span></a>
        @endauth
        @auth
            <button type="button" id="favBtn" class="engage-btn" title="Toggle Favorites">★ Favorite</button>
            <div class="dropdown">
                <button type="button" id="collectionsBtn" class="engage-btn" title="Add to a collection">＋ Collections</button>
                <div id="collectionsPanel" class="dropdown-panel hidden"></div>
            </div>
        @endauth
        <button type="button" id="commentsBtn" class="engage-btn" title="Read and write comments">
            Comments (<span id="headerCommentCount">{{ $comic->comments_count ?? 0 }}</span>)
        </button>
    </div>
</div>

<div class="comic-container">
    @foreach ($comic->pages as $page)
        <a id="page-{{ $page->page_number }}"></a>
        <img 
            src="{{ asset('storage/' . $page->image_path) }}" 
            data-page="{{ $page->page_number }}"
            id="page-{{ $page->page_number }}"
            alt="Page {{ $page->page_number }}"
            loading="lazy"
        >
    @endforeach
</div>


<script>
        let pages = 0;
        let totalPages = 1;
        let progress = document.getElementById('progress');
        var comicId = '{{ $comic->id }}'; // Get comic ID from the backend
        var storedPage = getCookie("comic_" + comicId + "_page");



    document.addEventListener('DOMContentLoaded', () => {
         pages = Array.from(document.querySelectorAll('.comic-container img'));
         totalPages = pages.length;
         progress = document.getElementById('progress');
         scrollToPage(storedPage);

        
    });
    function topFunction() {
     window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }

        // Function to get a cookie
        function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        // Function to erase a cookie
        function eraseCookie(name) {
            document.cookie = name + '=; Max-Age=-99999999;';
        }

    function updateProgress(currentPage) {
            setCookie("comic_" + comicId + "_page", currentPage, 30);
            progress.textContent = `Page ${currentPage} of ${totalPages}`;
        }

        function scrollToPage(pageNumber) {
            const anchor = document.getElementById(`page-${pageNumber}`);
            if (anchor) {
                window.location.hash = `page-${pageNumber}`;
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                updateProgress(pageNumber);
            }
        }

        function getCurrentPage() {
            let closest = {page: 1, offset: Infinity};
            const scrollY = window.scrollY;
            pages.forEach(img => {
                const rect = img.getBoundingClientRect();
                const offset = Math.abs(rect.top);
                const page = parseInt(img.dataset.page) || 1;
                if (offset < closest.offset) {
                    closest = {page, offset};
                }
            });
            return closest.page;
        }

        // Initial load
        const hash = window.location.hash;
        if (hash.startsWith('#page-')) {
            const pageNumber = parseInt(hash.replace('#page-', ''));
            if (!isNaN(pageNumber)) {
                scrollToPage(pageNumber);
            }
        }

        // Update progress on scroll
        window.addEventListener('scroll', () => {
            const currentPage = getCurrentPage();
            updateProgress(currentPage);
        });

        // Keyboard navigation
let scrollInterval = null;
const scrollSpeed = 15; // pixels per frame (adjust for desired speed)
let isMagnified = false;
let magnifiedImg = null;

function getCurrentImageElement() {
    let closest = {page: null, offset: Infinity, element: null};
    pages.forEach(img => {
        const rect = img.getBoundingClientRect();
        const offset = Math.abs(rect.top);
        const page = parseInt(img.dataset.page);
        if (offset < closest.offset) {
            closest = {page, offset, element: img};
        }
    });
    return closest.element;
}

function toggleMagnify(img) {
    console.log(img);
    if (!img) return;
    if (isMagnified) {
        img.classList.remove('magnified');
        isMagnified = false;
        magnifiedImg = null;
    } else {
        img.classList.add('magnified');
        isMagnified = true;
        magnifiedImg = img;
        img.scrollIntoView({ behavior: "smooth", block: "center" });
    }
}

document.querySelectorAll('.comic-container img').forEach(img => {
    img.addEventListener('click', () => {
        if (isMagnified && magnifiedImg === img) {
            toggleMagnify(img);
        } else {
            if (isMagnified && magnifiedImg) {
                toggleMagnify(magnifiedImg);
            }
            toggleMagnify(img);
        }
    });
});


function startScroll(direction) {
    if (scrollInterval) return; // prevent multiple intervals
    scrollInterval = setInterval(() => {
        window.scrollBy(0, direction * scrollSpeed);
    }, 16); // roughly 60fps
}

function stopScroll() {
    if (scrollInterval) {
        clearInterval(scrollInterval);
        scrollInterval = null;
    }
}

document.addEventListener('keydown', (e) => {
    // Comments modal owns all keys while open (except its own text inputs,
    // which the check below already spares). Escape closes it.
    if (window.__commentsOpen) {
        if (e.key === 'Escape' && typeof closeCommentsModal === 'function') closeCommentsModal();
        return;
    }
    if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
    const key = e.key.toLowerCase();
    const currentPage = getCurrentPage();

    if (e.key === 'ArrowRight' || e.key.toLowerCase() === 'd') {
        // Next page
        if (currentPage < totalPages) {
            scrollToPage(currentPage + 1);
        }
    }

    if (e.key === 'ArrowLeft' || e.key.toLowerCase() === 'a') {
        // Previous page
        if (currentPage > 1) {
            scrollToPage(currentPage - 1);
        }
    }
    if (key === 'w') {
        startScroll(-1);
    }
    if (key === 's') {
        startScroll(1);
    }
    if (key === 'f') {
        const currentImg = getCurrentImageElement();
        if (isMagnified && magnifiedImg) {
            toggleMagnify(magnifiedImg);
        } else {
            toggleMagnify(currentImg);
        }
    }
    const navKeys = ['w', 'a', 's', 'd', 'arrowright', 'arrowleft', 'arrowup', 'arrowdown'];
    if (navKeys.includes(e.key.toLowerCase())) {
        if (isMagnified && magnifiedImg) {
            toggleMagnify(magnifiedImg);
        }
    }

});

document.addEventListener('keyup', (e) => {
    const key = e.key.toLowerCase();
    if (key === 'w' || key === 's') {
        stopScroll();
    }
});

</script>
<script>
(function () {
    // Engagement layer: info modal + comments + likes + collections.
    // Reader keybinds above stay untouched (modal sets window.__commentsOpen).
    const CSRF = '{{ csrf_token() }}';
    const COMIC_ID = '{{ $comic->id }}';
    const AUTH_ID = {{ auth()->check() ? auth()->id() : 'null' }};
    const CAN_MODERATE = {{ (auth()->check() && (int) auth()->user()->admin_level >= 1) ? 'true' : 'false' }};
    const URLS = {
        // App base URL (respects subpath installs like /comic/public).
        assetBase: '{{ url('/') }}',
        comments: '{{ route('comments.index', $comic) }}',
        commentBase: '{{ url('/comments') }}',
        like: '{{ route('comics.like', $comic) }}',
        favorite: '{{ route('collections.favorite', $comic) }}',
        mine: '{{ route('collections.mine') }}',
        addComic: '{{ route('collections.addComic', ['collection' => '__CID__', 'comic' => $comic->id]) }}',
        createCollection: '{{ route('collections.store') }}',
    };
    const JSON_HEADERS = { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' };

    function esc(text) {
        return String(text ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }
    function fmtDate(iso) {
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return iso;
        }
    }
    async function api(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: JSON_HEADERS,
            body: body === undefined ? null : JSON.stringify(body),
        });
        let data = null;
        try {
            data = await res.json();
        } catch (e) { /* non-JSON error page */ }
        if (!res.ok) {
            const detail = data && data.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data && data.message) || ('HTTP ' + res.status);
            throw new Error(detail);
        }
        return data;
    }

    // ---- Info modal ----
    const modal = document.getElementById('infoModal');
    function openCommentsModal() {
        modal.classList.remove('hidden');
        window.__commentsOpen = true;
        loadComments(1);
    }
    window.closeCommentsModal = function () {
        modal.classList.add('hidden');
        window.__commentsOpen = false;
    };
    document.getElementById('commentsBtn').addEventListener('click', openCommentsModal);
    document.getElementById('commentsFab').addEventListener('click', openCommentsModal);
    document.getElementById('infoModalClose').addEventListener('click', window.closeCommentsModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) window.closeCommentsModal();
    });

    // ---- Comments list (paginated) ----
    const listEl = document.getElementById('commentsList');
    const pageEl = document.getElementById('commentsPage');
    let commentPage = 1;
    let commentLastPage = 1;

    function commentNode(comment) {
        const mine = AUTH_ID !== null && comment.user_id === AUTH_ID;
        const canEdit = mine || CAN_MODERATE;
        const name = esc(comment.user ? comment.user.name : 'Deleted user');
        const avatar = URLS.assetBase + '/' + (comment.user && comment.user.avatar_image_path
            ? comment.user.avatar_image_path.replace(/^\/+/, '')
            : 'default-avatar.png');
        const div = document.createElement('div');
        div.className = 'comment-item';
        div.dataset.commentId = comment.id;
        div.innerHTML =
            '<div style="display:flex;gap:10px;">' +
            '<img src="' + esc(avatar) + '" alt="" style="width:44px;height:44px;border-radius:50%;flex-shrink:0;object-fit:cover;">' +
            '<div style="flex:1;min-width:0;">' +
            '<div class="meta"><strong>' + name + '</strong> · <span title="' + esc(comment.created_at) + '">' + esc(fmtDate(comment.created_at)) + '</span></div>' +
            '<div class="comment-body"></div>' +
            (canEdit ? '<div class="actions"><button type="button" data-act="edit">Edit</button><button type="button" data-act="delete">Delete</button></div>' : '') +
            '</div></div>';
        div.querySelector('.comment-body').textContent = comment.body;
        if (canEdit) {
            div.querySelector('[data-act="edit"]').addEventListener('click', () => startEdit(div, comment));
            div.querySelector('[data-act="delete"]').addEventListener('click', () => removeComment(div, comment));
        }
        return div;
    }

    function setCounts(total) {
        document.getElementById('headerCommentCount').textContent = total;
        document.getElementById('modalCommentCount').textContent = total;
    }

    async function loadComments(page) {
        listEl.innerHTML = '<p style="color:#999;">Loading…</p>';
        try {
            const data = await api(URLS.comments + '?page=' + page, 'GET');
            commentPage = data.current_page;
            commentLastPage = data.last_page;
            setCounts(data.total);
            pageEl.textContent = 'Page ' + data.current_page + ' of ' + data.last_page;
            document.getElementById('commentsPrev').disabled = data.current_page <= 1;
            document.getElementById('commentsNext').disabled = data.current_page >= data.last_page;
            listEl.innerHTML = '';
            if (data.data.length === 0) {
                listEl.innerHTML = '<p style="color:#999;">No comments yet. Be the first!</p>';
                return;
            }
            data.data.forEach((comment) => listEl.appendChild(commentNode(comment)));
        } catch (e) {
            listEl.innerHTML = '<p style="color:#f87171;">Could not load comments: ' + esc(e.message) + '</p>';
        }
    }
    document.getElementById('commentsPrev').addEventListener('click', () => {
        if (commentPage > 1) loadComments(commentPage - 1);
    });
    document.getElementById('commentsNext').addEventListener('click', () => {
        if (commentPage < commentLastPage) loadComments(commentPage + 1);
    });

    // ---- Post a comment (Enter sends, Shift+Enter breaks the line) ----
    const box = document.getElementById('commentBox');
    const submitBtn = document.getElementById('commentSubmit');
    async function postComment() {
        const body = box.value.trim();
        if (!body) return;
        submitBtn.disabled = true;
        try {
            await api(URLS.comments, 'POST', { body });
            box.value = '';
            loadComments(1);
        } catch (e) {
            alert('Could not post comment: ' + e.message);
        } finally {
            submitBtn.disabled = false;
        }
    }
    if (submitBtn) {
        submitBtn.addEventListener('click', postComment);
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                postComment();
            }
        });
    }

    // ---- Edit inline ----
    async function startEdit(div, comment) {
        const bodyEl = div.querySelector('.comment-body');
        const original = comment.body;
        bodyEl.innerHTML = '';
        const area = document.createElement('textarea');
        area.rows = 3;
        area.maxLength = 2000;
        area.value = original;
        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'engage-btn';
        save.textContent = 'Save';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'engage-btn';
        cancel.textContent = 'Cancel';
        bodyEl.append(area, save, cancel);
        cancel.addEventListener('click', () => { bodyEl.textContent = original; });
        save.addEventListener('click', async () => {
            try {
                const updated = await api(URLS.commentBase + '/' + comment.id, 'PUT', { body: area.value });
                comment.body = updated.body;
                bodyEl.textContent = updated.body;
            } catch (e) {
                alert('Could not save: ' + e.message);
            }
        });
    }

    async function removeComment(div, comment) {
        if (!confirm('Delete this comment?')) return;
        try {
            await api(URLS.commentBase + '/' + comment.id, 'DELETE');
            div.remove();
            loadComments(commentPage);
        } catch (e) {
            alert('Could not delete: ' + e.message);
        }
    }

    // ---- Like button ----
    const likeBtn = document.getElementById('likeBtn');
    if (likeBtn) {
        likeBtn.addEventListener('click', async () => {
            try {
                const data = await api(URLS.like, 'POST');
                document.getElementById('likeCount').textContent = data.count;
                document.getElementById('likeHeart').textContent = data.liked ? '♥' : '♡';
                likeBtn.classList.toggle('active', !!data.liked);
            } catch (e) {
                alert('Could not like: ' + e.message);
            }
        });
    }

    // ---- Favorite shortcut ----
    const favBtn = document.getElementById('favBtn');
    if (favBtn) {
        // Initial state comes from the server-rendered collections list.
        favBtn.addEventListener('click', async () => {
            try {
                const data = await api(URLS.favorite, 'POST');
                favBtn.classList.toggle('fav-active', !!data.favorited);
            } catch (e) {
                alert('Could not update favorites: ' + e.message);
            }
        });
    }

    // ---- Collections quick-add dropdown (mine only, server-filtered) ----
    const collectionsBtn = document.getElementById('collectionsBtn');
    const collectionsPanel = document.getElementById('collectionsPanel');
    if (collectionsBtn && collectionsPanel) {
        collectionsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            collectionsPanel.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!collectionsPanel.classList.contains('hidden') && !e.target.closest('.dropdown')) {
                collectionsPanel.classList.add('hidden');
            }
        });
        loadCollectionsPanel();
    }
    async function loadCollectionsPanel() {
        try {
            const data = await api(URLS.mine, 'GET');
            const items = data.data || data;
            collectionsPanel.innerHTML = '';
            items.forEach((collection) => {
                const label = document.createElement('label');
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = (collection.comics || []).some((c) => String(c.id) === String(COMIC_ID));
                box.addEventListener('change', () => toggleCollection(collection.id, box.checked, box));
                label.append(box, document.createTextNode(' ' + collection.name));
                collectionsPanel.appendChild(label);
            });
            const form = document.createElement('div');
            form.style.cssText = 'display:flex;gap:6px;padding:6px 8px;';
            const input = document.createElement('input');
            input.placeholder = 'New collection…';
            input.maxLength = 100;
            input.style.cssText = 'flex:1;background:#1a1a1a;color:#fff;border:1px solid #555;border-radius:6px;padding:4px 8px;font-size:0.85em;';
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'engage-btn';
            add.textContent = '＋';
            add.addEventListener('click', async () => {
                if (!input.value.trim()) return;
                try {
                    const created = await api(URLS.createCollection, 'POST', { name: input.value.trim() });
                    await toggleCollection(created.id, true, null);
                    loadCollectionsPanel();
                } catch (e) {
                    alert('Could not create collection: ' + e.message);
                }
            });
            form.append(input, add);
            collectionsPanel.appendChild(form);
        } catch (e) {
            collectionsPanel.innerHTML = '<p style="padding:8px;font-size:0.85em;">Could not load collections.</p>';
        }
    }
    async function toggleCollection(collectionId, add, box) {
        const base = URLS.addComic.replace('__CID__', collectionId);
        try {
            if (add) {
                await api(base, 'POST');
            } else {
                await api(base, 'DELETE');
            }
        } catch (e) {
            if (box) box.checked = !box.checked;
            alert('Could not update collection: ' + e.message);
        }
    }
})();
</script>
</body>
</html>
