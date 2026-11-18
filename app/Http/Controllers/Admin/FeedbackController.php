<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = Feedback::with('user')->latest('id');

        if (in_array($request->query('category'), array_keys(Feedback::CATEGORIES), true)) {
            $query->where('category', $request->query('category'));
        }

        if (in_array($request->query('status'), array_keys(Feedback::STATUSES), true)) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('search'))) {
            $term = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($term): void {
                $builder->where('subject', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%");
            });
        }

        $feedbacks = $query->paginate(15)->withQueryString();

        $counts = Feedback::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.feedback.index', [
            'feedbacks' => $feedbacks,
            'categories' => Feedback::CATEGORIES,
            'statuses' => Feedback::STATUSES,
            'counts' => $counts,
        ]);
    }

    public function show(int $id)
    {
        $feedback = Feedback::with(['user', 'responder'])->findOrFail($id);

        return view('admin.feedback.show', [
            'feedback' => $feedback,
            'statuses' => Feedback::STATUSES,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $feedback = Feedback::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Feedback::STATUSES))],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ]);

        $data = [
            'status' => $validated['status'],
            'admin_response' => filled($validated['admin_response'] ?? null)
                ? trim($validated['admin_response'])
                : null,
        ];

        if ($data['admin_response'] !== null) {
            $data['responded_by'] = $request->user()->id;
            $data['responded_at'] = now();
        }

        $feedback->update($data);

        ActivityLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'Updated feedback #'.$feedback->id.' status to '.$feedback->statusLabel(),
            'target_user_id' => $feedback->user_id,
        ]);

        return redirect()->route('admin.feedback.show', $feedback->id)
            ->with('success', 'Feedback updated.');
    }
}
