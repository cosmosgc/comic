<?php

namespace App\Http\Controllers;

use App\Models\ForumBan;
use App\Models\ForumCategory;
use App\Models\ForumModerator;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Models\ForumSetting;
use App\Models\ForumThread;
use App\Models\User;
use App\Support\ForumNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin → Fórum: category tree, moderation queue, moderators, bans and
 * the tunable forum settings. No console access needed.
 */
class AdminForumController extends Controller
{
    public function dashboard()
    {
        $totals = [
            'categories' => ForumCategory::count(),
            'threads' => ForumThread::count(),
            'posts' => ForumPost::count(),
            'members' => User::count(),
            'pending_reports' => Schema::hasTable('forum_reports') ? ForumReport::pending()->count() : 0,
            'pending_posts' => ForumPost::where('is_approved', false)->count(),
        ];

        // 30-day activity: threads + posts per day.
        $since = now()->subDays(29)->startOfDay();
        $threadsPerDay = ForumThread::query()->where('created_at', '>=', $since)
            ->get(['created_at'])->countBy(fn ($t) => $t->created_at->toDateString());
        $postsPerDay = ForumPost::query()->where('created_at', '>=', $since)
            ->get(['created_at'])->countBy(fn ($p) => $p->created_at->toDateString());

        $activity = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $activity[$day] = [
                'threads' => (int) ($threadsPerDay[$day] ?? 0),
                'posts' => (int) ($postsPerDay[$day] ?? 0),
            ];
        }

        return view('admin.forum.dashboard', [
            'totals' => $totals,
            'activity' => $activity,
            'latestThreads' => ForumThread::with(['author', 'category'])->latest()->limit(8)->get(),
        ]);
    }

    public function categories()
    {
        $sections = ForumCategory::query()
            ->sections()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->withCount('threads')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.forum.categories', [
            'sections' => $sections,
            'boards' => ForumCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function categoryCreate()
    {
        return view('admin.forum.category-form', [
            'category' => new ForumCategory(['is_visible' => true, 'min_role' => 'member']),
            'sections' => ForumCategory::sections()->orderBy('sort_order')->get(),
            'roles' => ForumCategory::roles(),
        ]);
    }

    public function categoryStore(Request $request)
    {
        $data = $this->validateCategory($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        ForumCategory::create($data);

        return redirect()->route('admin.forum.categories')->with('success', 'Categoria criada.');
    }

    public function categoryEdit(int $id)
    {
        return view('admin.forum.category-form', [
            'category' => ForumCategory::findOrFail($id),
            'sections' => ForumCategory::sections()->where('id', '!=', $id)->orderBy('sort_order')->get(),
            'roles' => ForumCategory::roles(),
        ]);
    }

    public function categoryUpdate(Request $request, int $id)
    {
        $category = ForumCategory::findOrFail($id);
        $data = $this->validateCategory($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name'], $id);

        // A section cannot become its own child.
        if ($data['parent_id'] !== null && (int) $data['parent_id'] === $id) {
            $data['parent_id'] = null;
        }

        $category->update($data);

        return redirect()->route('admin.forum.categories')->with('success', 'Categoria atualizada.');
    }

    public function categoryDestroy(int $id)
    {
        $category = ForumCategory::findOrFail($id);
        $category->delete();

        return redirect()->route('admin.forum.categories')->with('success', 'Categoria removida.');
    }

    public function categoryToggle(Request $request, int $id)
    {
        $category = ForumCategory::findOrFail($id);
        $field = $request->input('field', 'is_visible');
        if (! in_array($field, ['is_visible', 'is_locked'], true)) {
            $field = 'is_visible';
        }
        $category->update([$field => ! $category->{$field}]);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function threads(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer',
        ]);
        $query = trim((string) ($validated['q'] ?? ''));

        $threads = ForumThread::with(['author', 'category'])
            ->withCount('posts')
            ->when($query !== '', fn ($q) => $q->where('title', 'like', '%'.$query.'%'))
            ->when(! empty($validated['category']), fn ($q) => $q->where('forum_category_id', $validated['category']))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.forum.threads', [
            'threads' => $threads,
            'query' => $query,
            'category' => $validated['category'] ?? null,
            'categories' => ForumCategory::orderBy('name')->get(),
        ]);
    }

    public function moderation(Request $request)
    {
        $validated = $request->validate(['status' => 'nullable|in:pending,reviewed,dismissed']);
        $status = $validated['status'] ?? 'pending';

        $reports = Schema::hasTable('forum_reports')
            ? ForumReport::with(['reporter', 'thread', 'post.author'])
                ->where('status', $status)
                ->latest()
                ->paginate(25, ['*'], 'reports_page')
                ->withQueryString()
            : collect();

        $pendingPosts = ForumPost::with(['author', 'thread.category'])
            ->where('is_approved', false)
            ->latest()
            ->limit(50)
            ->get();

        $counts = [
            'pending' => ForumReport::pending()->count(),
            'reviewed' => ForumReport::where('status', 'reviewed')->count(),
            'dismissed' => ForumReport::where('status', 'dismissed')->count(),
        ];

        return view('admin.forum.moderation', [
            'reports' => $reports,
            'pendingPosts' => $pendingPosts,
            'status' => $status,
            'statuses' => ForumReport::statuses(),
            'counts' => $counts,
            'reasons' => ForumReport::reasons(),
        ]);
    }

    public function resolveReport(Request $request, ForumReport $report)
    {
        $validated = $request->validate(['status' => 'required|in:reviewed,dismissed']);

        $report->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        ForumNotifier::reportResolved($report, $validated['status']);

        return back()->with('success', 'Denúncia atualizada.');
    }

    public function moderators()
    {
        return view('admin.forum.moderators', [
            'moderators' => ForumModerator::with(['user', 'category'])->latest()->get(),
            'categories' => ForumCategory::orderBy('name')->get(),
        ]);
    }

    public function moderatorStore(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'forum_category_id' => 'nullable|integer|exists:forum_categories,id',
        ]);

        ForumModerator::firstOrCreate(
            [
                'user_id' => $validated['user_id'],
                'forum_category_id' => $validated['forum_category_id'] ?? null,
            ],
            ['granted_by' => $request->user()->id]
        );

        return back()->with('success', 'Moderador adicionado.');
    }

    public function moderatorDestroy(int $id)
    {
        ForumModerator::findOrFail($id)->delete();

        return back()->with('success', 'Moderador removido.');
    }

    public function bans()
    {
        return view('admin.forum.bans', [
            'bans' => ForumBan::with(['user', 'category', 'banner'])->latest()->paginate(25),
            'categories' => ForumCategory::orderBy('name')->get(),
        ]);
    }

    public function banStore(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'forum_category_id' => 'nullable|integer|exists:forum_categories,id',
            'reason' => 'nullable|string|max:500',
            'banned_until' => 'nullable|date|after:now',
        ]);

        $ban = ForumBan::create([
            'user_id' => $validated['user_id'],
            'forum_category_id' => $validated['forum_category_id'] ?? null,
            'banned_by' => $request->user()->id,
            'reason' => $validated['reason'] ?? null,
            'banned_until' => $validated['banned_until'] ?? null,
        ]);

        ForumNotifier::banned($ban);

        return back()->with('success', 'Usuário banido.');
    }

    public function banDestroy(int $id)
    {
        ForumBan::findOrFail($id)->delete();

        return back()->with('success', 'Banimento removido.');
    }

    public function settings()
    {
        return view('admin.forum.settings', [
            'enabled' => (bool) config('forum.enabled', true),
            'approvalMode' => (string) config('forum.approval_mode', 'none'),
            'editGraceMinutes' => (int) config('forum.edit_grace_minutes', 30),
            'minAccountAgeHours' => (int) config('forum.min_account_age_hours', 0),
            'maxAttachments' => (int) config('forum.max_attachments', 4),
            'allowedMime' => implode(', ', (array) config('forum.allowed_mime', [])),
            'postsPerPage' => (int) config('forum.posts_per_page', 15),
            'hotWindowDays' => (int) config('forum.hot_window_days', 7),
            'autoLockDays' => (int) config('forum.auto_lock_days', 0),
            'prohibitedWords' => implode(', ', (array) config('forum.prohibited_words', [])),
            'overridden' => ForumSetting::overridden(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'approval_mode' => 'required|in:none,first_post,all',
            'edit_grace_minutes' => 'required|integer|min:0|max:10080',
            'min_account_age_hours' => 'required|integer|min:0|max:8760',
            'max_attachments' => 'required|integer|min:0|max:20',
            'allowed_mime' => 'nullable|string|max:1000',
            'posts_per_page' => 'required|integer|min:5|max:100',
            'hot_window_days' => 'required|integer|min:1|max:365',
            'auto_lock_days' => 'required|integer|min:0|max:3650',
            'prohibited_words' => 'nullable|string|max:5000',
        ]);

        ForumSetting::store('enabled', $request->boolean('enabled'));
        ForumSetting::store('approval_mode', $validated['approval_mode']);
        ForumSetting::store('edit_grace_minutes', $validated['edit_grace_minutes']);
        ForumSetting::store('min_account_age_hours', $validated['min_account_age_hours']);
        ForumSetting::store('max_attachments', $validated['max_attachments']);
        ForumSetting::store('allowed_mime', $this->splitList($validated['allowed_mime'] ?? ''));
        ForumSetting::store('posts_per_page', $validated['posts_per_page']);
        ForumSetting::store('hot_window_days', $validated['hot_window_days']);
        ForumSetting::store('auto_lock_days', $validated['auto_lock_days']);
        ForumSetting::store('prohibited_words', $this->splitList($validated['prohibited_words'] ?? '', ','));
        ForumSetting::applyToConfig();

        return redirect()->route('admin.forum.settings')->with('success', 'Configurações do fórum salvas.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCategory(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|regex:/^[a-z0-9\-]+$/',
            'parent_id' => 'nullable|integer|exists:forum_categories,id',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:16',
            'color' => 'nullable|string|max:32',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'min_role' => 'required|in:'.implode(',', array_keys(ForumCategory::roles())),
        ]);

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'min_role' => $validated['min_role'],
            'is_visible' => $request->boolean('is_visible'),
            'is_locked' => $request->boolean('is_locked'),
        ];
    }

    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'categoria';
        $slug = $base;
        $suffix = 1;

        while (ForumCategory::query()->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @return array<int, string>
     */
    protected function splitList(string $value, string $separator = ','): array
    {
        return collect(explode($separator, $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
