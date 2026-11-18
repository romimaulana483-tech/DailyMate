<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * User management only lists normal users and only account-level columns.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'user');

        $search = $this->queryString($request, 'q');
        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $status = $this->queryString($request, 'status');
        if (filled($status) && in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $users = $query->latest()->paginate(10)->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'status'));
    }

    /**
     * Show account information and admin audit history only.
     * Tasks, schedules and AI conversations remain private.
     */
    public function show(int $id)
    {
        $user = User::where('role', 'user')->findOrFail($id);

        $logs = ActivityLog::with('admin')
            ->where('target_user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.users.show', compact('user', 'logs'));
    }

    public function toggleStatus(Request $request, int $id)
    {
        $user = User::where('role', 'user')->findOrFail($id);

        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $this->log(
            $request->user()->id,
            $user->status === 'active' ? 'Activated account' : 'Deactivated account',
            $user->id
        );

        return back()->with('success', 'Account '.($user->status === 'active' ? 'activated' : 'deactivated').' successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $user = User::where('role', 'user')->findOrFail($id);

        // Write the audit record first; the target foreign key becomes null.
        $this->log($request->user()->id, 'Deleted account', $user->id);

        // Cascading foreign keys remove the user's tasks, schedules and chats.
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User account and associated private data were deleted.');
    }

    private function log(int $adminId, string $action, int $targetUserId): void
    {
        ActivityLog::create([
            'admin_id' => $adminId,
            'action' => $action,
            'target_user_id' => $targetUserId,
        ]);
    }
}
