<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $feedbacks = $request->user()
            ->feedbacks()
            ->latest('id')
            ->paginate(10);

        $feedbackCounts = $request->user()
            ->feedbacks()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('user.feedback.index', [
            'feedbacks' => $feedbacks,
            'categories' => Feedback::CATEGORIES,
            'feedbackCounts' => $feedbackCounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Feedback::CATEGORIES))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $request->user()->feedbacks()->create($validated + ['status' => 'pending']);

        return redirect()->route('user.feedback.index')
            ->with('success', 'Feedback sent. An administrator will review it.');
    }
}
