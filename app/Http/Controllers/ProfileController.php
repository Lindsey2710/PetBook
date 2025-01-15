<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\PostAttachmentResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Follower;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function index(Request $request, User $user)
    {
        $isCurrentUserFollower = false;
        if (!Auth::guest()) {
            $isCurrentUserFollower = Follower::where('user_id', $user->id)->where('follower_id', Auth::id())->exists();
        }
        $followerCount = Follower::where('user_id', $user->id)->count();

        $posts = Post::postsForTimeline(Auth::id(), false)
            ->leftJoin('users AS u', 'u.pinned_post_id', 'posts.id')
            ->where('user_id', $user->id)
            ->whereNull('group_id')
            ->orderBy('u.pinned_post_id', 'desc')
            ->orderBy('posts.created_at', 'desc')
            ->paginate(10);

        $posts = PostResource::collection($posts);
        if ($request->wantsJson()) {
            return $posts;
        }

        $followers = $user->followers;

        $followings = $user->followings;

        $photos = PostAttachment::query()
            ->where('mime', 'like', 'image/%')
            ->where('created_by', $user->id)
            ->latest()
            ->get();

        return Inertia::render('Profile/View', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'success' => session('success'),
            'isCurrentUserFollower' => $isCurrentUserFollower,
            'followerCount' => $followerCount,
            'user' => new UserResource($user),
            'posts' => $posts,
            'followers' => UserResource::collection($followers),
            'followings' => UserResource::collection($followings),
            'photos' => PostAttachmentResource::collection($photos)
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile', $request->user())->with('success', 'Your profile details were updated.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateImage(Request $request)
    {
        // Add authorization check
        if ($request->user()->id !== (int)$request->get('user_id')) {
            return response()->json([
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'cover' => ['nullable', 'image', 'max:2048'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        DB::beginTransaction();
        $allFilePaths = [];
        try {
            $user = User::find($data['user_id']);

            if ($request->hasFile('cover')) {
                $path = $request->file('cover')->store('covers', 'public');
                $allFilePaths[] = $path;
                if ($user->cover_path) {
                    Storage::disk('public')->delete($user->cover_path);
                }
                $user->cover_path = $path;

                // Add this after cover upload
                copy(
                    storage_path('app/public/' . $path),
                    public_path('storage/' . $path)
                );
            }

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $allFilePaths[] = $path;
                if ($user->avatar_path) {
                    Storage::disk('public')->delete($user->avatar_path);
                }
                $user->avatar_path = $path;

                // Add this after avatar upload
                copy(
                    storage_path('app/public/' . $path),
                    public_path('storage/' . $path)
                );
            }

            $user->save();
            DB::commit();
        } catch (\Exception $e) {
            foreach ($allFilePaths as $path) {
                Storage::disk('public')->delete($path);
            }
            DB::rollBack();
            throw $e;
        }

        return back();
    }
}
