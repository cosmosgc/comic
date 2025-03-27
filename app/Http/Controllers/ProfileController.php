<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Display the user's profile.
     */
    public function show()
    {
        $user = Auth::user(); // Authenticated user
        $comics = $user->comics()->latest()->paginate(10); // Fetch and paginate comics


        return view('profile.show', compact('user', 'comics'));
    }

    public function publicShowById($id)
    {
        try {
            $user = User::findOrFail($id); // Attempt to fetch user by ID
            $comics = $user->comics()->paginate(10); // Fetch user's comics
            return view('profile.public', compact('user', 'comics')); // Return the public view with user and comics data
        } catch (\Exception $e) {
            return redirect('/'); // Redirect to the root if user not found
        }
    }

    public function publicShowByUsername($username)
    {
        try {
            $user = User::where('name', $username)->firstOrFail(); // Attempt to fetch user by username
            $comics = $user->comics()->paginate(10); // Fetch user's comics
            return view('profile.public', compact('user', 'comics')); // Return the public view with user and comics data
        } catch (\Exception $e) {
            return redirect('/'); // Redirect to the root if user not found
        }
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
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'avatar_image' => 'nullable|image|max:2048', // Limit to 2MB
            'bio' => 'nullable|string',
            'links' => 'nullable|array',
            'links.*' => 'url', // Each link should be a valid URL
        ]);

        // Update user data
        $user->name = $request->input('name');
        $user->email = $request->input('email');

        // Handle avatar image upload
        if ($request->hasFile('avatar_image')) {
            $directory = public_path('storage/avatars');
        
            // Ensure the directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
        
            $avatar = $request->file('avatar_image');
            $filename = time() . '_' . $avatar->getClientOriginalName(); // Generate a unique filename
            $avatar->move($directory, $filename); // Move the file to the directory
        
            $user->avatar_image_path = 'storage/avatars/' . $filename; // Store the relative path
        }
        

        $user->bio = $request->input('bio');
        $user->links = $request->input('links') ?: []; // Store links as an array

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }

}
