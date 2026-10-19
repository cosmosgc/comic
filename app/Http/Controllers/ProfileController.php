<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Collection;
use App\Models\Post;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    /**
     * Display the user's profile.
     */
    public function show(Request $request)
    {
        $user = Auth::user(); // Authenticated user
        $user->loadMissing('badges');
        $tab = in_array($request->input('tab'), ['likes', 'collections', 'followers', 'following'], true)
            ? $request->input('tab')
            : 'comics';

        // Load only the active tab (pagination keeps ?tab via withQueryString).
        $comics = $tab === 'comics' ? $user->comics()->latest()->paginate(10)->withQueryString() : null;

        // Owners always see their own likes (newest liked first).
        $likedPosts = $tab === 'likes' ? $this->likedPostsFor($user) : null;
        $likedPostIds = $likedPosts ? $likedPosts->pluck('id')->all() : [];
        $likesTab = Schema::hasTable('post_likes');

        $collectionsTab = Schema::hasColumn('collections', 'user_id');
        $collections = ($tab === 'collections' && $collectionsTab)
            ? $user->collections()->withCount('comics')->latest()->paginate(10, ['*'], 'collections_page')->withQueryString()
            : null;

        if ($collections !== null) {
            // Two queries for the whole page instead of one per card.
            Collection::attachCovers($collections->getCollection());
        }

        [$followsTab, $followers, $following, $followersCount, $followingCount] = $this->followData($user, $tab);
        $bookmarkedPostIds = Schema::hasTable('bookmarks')
            ? $user->bookmarkedPosts()->pluck('posts.id')->all()
            : [];

        return view('profile.show', compact(
            'user', 'comics', 'likedPosts', 'likedPostIds', 'likesTab', 'collectionsTab', 'collections',
            'followsTab', 'followers', 'following', 'followersCount', 'followingCount', 'bookmarkedPostIds'
        ));
    }

    public function publicShowById($id)
    {
        try {
            $user = User::findOrFail($id); // Attempt to fetch user by ID
            $comics = $user->comics()->visibleTo(Auth::user())->paginate(10)->withQueryString(); // Fetch user's comics

            return $this->publicProfile($user, $comics);
        } catch (\Exception $e) {
            return redirect('/'); // Redirect to the root if user not found
        }
    }

    public function publicShowByUsername($username)
    {
        try {
            // Accept either the stable handle or the legacy display name.
            $user = User::where('handle', $username)
                ->orWhere('name', $username)
                ->firstOrFail();
            $comics = $user->comics()->visibleTo(Auth::user())->paginate(10)->withQueryString(); // Fetch user's comics

            return $this->publicProfile($user, $comics);
        } catch (\Exception $e) {
            return redirect('/'); // Redirect to the root if user not found
        }
    }

    /**
     * Shared public profile rendering. Liked posts show only when the
     * owner made them public; the viewer always sees filled hearts for
     * their own likes.
     */
    protected function publicProfile(User $user, $comics)
    {
        $user->loadMissing('badges');
        $tab = in_array(request()->input('tab'), ['likes', 'collections', 'followers', 'following'], true)
            ? request()->input('tab')
            : 'comics';
        $showLikes = (bool) $user->show_liked_posts;
        $likesTab = $showLikes && Schema::hasTable('post_likes');
        $likedPosts = ($tab === 'likes' && $likesTab)
            ? $this->likedPostsFor($user)
            : null;

        $collectionsTab = Schema::hasColumn('collections', 'user_id');
        $collections = null;
        if ($tab === 'collections' && $collectionsTab) {
            $query = $user->collections()->withCount('comics')->latest();
            if (Auth::id() !== $user->id) {
                $query->where('is_public', true);
            }
            $collections = $query->paginate(10, ['*'], 'collections_page')->withQueryString();
            // Two queries for the whole page instead of one per card.
            Collection::attachCovers($collections->getCollection());
        }

        $likedPostIds = [];
        if (Auth::check() && Schema::hasTable('post_likes')) {
            $likedPostIds = DB::table('post_likes')
                ->where('user_id', Auth::id())
                ->pluck('post_id')
                ->all();
        }

        // Comic-card hearts/counts for the viewer (guarded like everywhere).
        // The collections pivot predates all migrations, always countable.
        $comicCounts = ['collections'];
        if (Schema::hasTable('comments')) {
            $comicCounts[] = 'comments';
        }
        if (Schema::hasTable('comic_user_likes')) {
            $comicCounts[] = 'likedByUsers';
        }
        if ($comicCounts !== [] && $comics->count() > 0) {
            $comics->getCollection()->loadCount($comicCounts);
        }
        $likedComicIds = [];
        if (Auth::check() && Schema::hasTable('comic_user_likes')) {
            $likedComicIds = DB::table('comic_user_likes')
                ->where('user_id', Auth::id())
                ->pluck('comic_id')
                ->all();
        }

        [$followsTab, $followers, $following, $followersCount, $followingCount] = $this->followData($user, $tab);
        $bookmarkedPostIds = Auth::check() && Schema::hasTable('bookmarks')
            ? Auth::user()->bookmarkedPosts()->pluck('posts.id')->all()
            : [];

        $canMute = Auth::check() && Auth::id() !== $user->id && Schema::hasTable('mutes');
        $isMuted = $canMute && Auth::user()->mutedUsers()->whereKey($user->getKey())->exists();

        return view('profile.public', compact(
            'user', 'comics', 'likedPosts', 'likedPostIds', 'showLikes', 'likesTab', 'collectionsTab', 'collections',
            'likedComicIds', 'followsTab', 'followers', 'following', 'followersCount', 'followingCount', 'bookmarkedPostIds',
            'canMute', 'isMuted'
        ));
    }

    /**
     * Shared follow data for both profile views: whether the tab is
     * enabled, the paginated lists when the tab is open, and counts.
     *
     * @return array{0: bool, 1: mixed, 2: mixed, 3: int, 4: int}
     */
    protected function followData(User $user, string $tab): array
    {
        if (! Schema::hasTable('follows')) {
            return [false, null, null, 0, 0];
        }

        $followers = $tab === 'followers'
            ? $user->followers()->latest()->paginate(20, ['*'], 'followers_page')->withQueryString()
            : null;
        $following = $tab === 'following'
            ? $user->following()->latest()->paginate(20, ['*'], 'following_page')->withQueryString()
            : null;

        return [true, $followers, $following, $user->followers()->count(), $user->following()->count()];
    }

    /**
     * Newest-liked-first posts with everything the post card needs.
     * Null when the likes table doesn't exist yet (host without migration).
     * Counts degrade gracefully on partially-migrated databases.
     */
    protected function likedPostsFor(User $user)
    {
        if (! Schema::hasTable('post_likes')) {
            return null;
        }

        $counts = ['quotes'];
        if (Schema::hasColumn('posts', 'parent_id')) {
            $counts[] = 'replies';
        }
        $counts[] = 'likedByUsers';

        return $user->likedPosts()
            ->with(['author.badges', 'referencedPost.author.badges'])
            ->withCount($counts)
            ->orderByPivot('created_at', 'desc')
            ->paginate(10, ['*'], 'likes_page')
            ->withQueryString();
    }

    /**
     * Show the form for editing the user's profile.
     */
    public function edit()
    {
        $user = Auth::user();
        $user->loadMissing('badges');

        return view('profile.edit', [
            'user' => $user,
            'badgeDefinitions' => config('gamification.badges', []),
            'showcaseLimit' => (int) config('gamification.showcase_limit', 3),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        // Update user data
        $user->name = $request->input('name');
        $user->email = $request->input('email');

        // Handle avatar image upload (the previous file is replaced,
        // never left orphaned on disk).
        if ($request->hasFile('avatar_image')) {
            $this->deleteStoredFile($user->avatar_image_path);
            $user->avatar_image_path = ImageStore::put($request->file('avatar_image'), 'avatars');
        }

        // Handle banner image upload (profile header background)
        if ($request->hasFile('banner_image')) {
            $this->deleteStoredFile($user->banner_image_path);
            $user->banner_image_path = ImageStore::put($request->file('banner_image'), 'banners');
        } elseif ($request->boolean('remove_banner')) {
            $this->deleteStoredFile($user->banner_image_path);
            $user->banner_image_path = null;
        }

        $user->bio = $request->input('bio');
        $user->links = $request->input('links') ?: []; // Store links as an array
        $user->show_liked_posts = $request->boolean('show_liked_posts');
        $user->forum_digest = $request->boolean('forum_digest');

        // Featured badges: keep only badges the user actually earned,
        // most featured first, capped at the configured limit.
        $earned = $user->badges()->pluck('badge')->all();
        $showcase = array_values(array_intersect(
            array_unique($request->input('showcase_badges', [])),
            $earned
        ));
        $user->showcase_badges = array_slice($showcase, 0, (int) config('gamification.showcase_limit', 3)) ?: null;

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return redirect()->route('profile.show')->with('success', 'Perfil atualizado com sucesso.');
    }

    /**
     * Hover-card data for @mentions and profile links (public JSON).
     */
    public function card(string $username)
    {
        $user = User::where('name', $username)->firstOrFail();
        $user->loadMissing('badges');
        $topBadge = $user->topBadge();

        $viewer = Auth::user();
        $followsExist = Schema::hasTable('follows');

        return response()->json([
            'name' => $user->name,
            'handle' => $user->handleName(),
            'bio' => $user->bio,
            'avatar_url' => $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png'),
            'banner_url' => $user->banner_image_path ? asset($user->banner_image_path) : null,
            'profile_url' => route('profile.public.show.username', ['username' => $user->handleName()]),
            'comics_count' => $user->comics()->visibleTo(Auth::user())->count(),
            'posts_count' => Post::where('author_id', $user->id)->count(),
            'level' => (int) $user->level,
            'experience' => (int) $user->experience,
            'badge' => $topBadge ? ['icon' => $topBadge->icon, 'label' => $topBadge->label] : null,
            'follows_enabled' => $followsExist,
            'follow_url' => $followsExist ? route('users.follow', $user->getKey()) : null,
            'following' => $followsExist && $viewer && ! $viewer->is($user) && $viewer->isFollowing($user),
            'is_self' => $viewer && $viewer->is($user),
            'followers_count' => $followsExist ? $user->followers()->count() : 0,
        ]);
    }

    /**
     * Delete a previously stored public file, if it exists.
     */
    protected function deleteStoredFile(?string $relativePath): void
    {
        ImageStore::delete($relativePath);
    }
}
