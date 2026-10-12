<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
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
        $tab = in_array($request->input('tab'), ['likes', 'collections'], true)
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

        return view('profile.show', compact('user', 'comics', 'likedPosts', 'likedPostIds', 'likesTab', 'collectionsTab', 'collections'));
    }

    public function publicShowById($id)
    {
        try {
            $user = User::findOrFail($id); // Attempt to fetch user by ID
            $comics = $user->comics()->paginate(10)->withQueryString(); // Fetch user's comics

            return $this->publicProfile($user, $comics);
        } catch (\Exception $e) {
            return redirect('/'); // Redirect to the root if user not found
        }
    }

    public function publicShowByUsername($username)
    {
        try {
            $user = User::where('name', $username)->firstOrFail(); // Attempt to fetch user by username
            $comics = $user->comics()->paginate(10)->withQueryString(); // Fetch user's comics

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
        $tab = in_array(request()->input('tab'), ['likes', 'collections'], true)
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

        return view('profile.public', compact('user', 'comics', 'likedPosts', 'likedPostIds', 'showLikes', 'likesTab', 'collectionsTab', 'collections', 'likedComicIds'));
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
            ->with(['author', 'referencedPost.author'])
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

        return view('profile.edit', compact('user'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'avatar_image' => 'nullable|image|max:2048', // Limit to 2MB
            'banner_image' => 'nullable|image|max:4096', // Limit to 4MB
            'remove_banner' => 'nullable|boolean',
            'bio' => 'nullable|string',
            'links' => 'nullable|array',
            'links.*' => 'url', // Each link should be valid URL
            'show_liked_posts' => 'nullable|boolean',
        ]);

        // Update user data
        $user->name = $request->input('name');
        $user->email = $request->input('email');

        // Handle avatar image upload
        if ($request->hasFile('avatar_image')) {
            $directory = public_path('storage/avatars');

            // Ensure the directory exists
            if (! file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            $avatar = $request->file('avatar_image');
            $filename = time().'_'.$avatar->getClientOriginalName(); // Generate a unique filename
            $avatar->move($directory, $filename); // Move the file to the directory

            $user->avatar_image_path = 'storage/avatars/'.$filename; // Store the relative path
        }

        // Handle banner image upload (profile header background)
        if ($request->hasFile('banner_image')) {
            $directory = public_path('storage/banners');

            if (! file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $this->deleteStoredFile($user->banner_image_path);

            $banner = $request->file('banner_image');
            $filename = time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $banner->getClientOriginalName());
            $banner->move($directory, $filename);

            $user->banner_image_path = 'storage/banners/'.$filename;
        } elseif ($request->boolean('remove_banner')) {
            $this->deleteStoredFile($user->banner_image_path);
            $user->banner_image_path = null;
        }

        $user->bio = $request->input('bio');
        $user->links = $request->input('links') ?: []; // Store links as an array
        $user->show_liked_posts = $request->boolean('show_liked_posts');

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }

    /**
     * Hover-card data for @mentions and profile links (public JSON).
     */
    public function card(string $username)
    {
        $user = User::where('name', $username)->firstOrFail();

        return response()->json([
            'name' => $user->name,
            'bio' => $user->bio,
            'avatar_url' => $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png'),
            'banner_url' => $user->banner_image_path ? asset($user->banner_image_path) : null,
            'profile_url' => route('profile.public.show.username', ['username' => $user->name]),
            'comics_count' => $user->comics()->count(),
            'posts_count' => Post::where('author_id', $user->id)->count(),
        ]);
    }

    /**
     * Delete a previously stored public file, if it exists.
     */
    protected function deleteStoredFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $fullPath = public_path('storage/'.ltrim(preg_replace('#^storage/#', '', $relativePath), '/'));
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
