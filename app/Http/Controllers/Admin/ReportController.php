<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ReportLog;
use App\Models\ReportSetting;
use App\Services\AdminReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $setting = ReportSetting::current() ?? ReportSetting::defaults();
        $logs = ReportLog::latest('id')->limit(30)->get();

        $stats = [
            'sent' => ReportLog::where('status', 'sent')->count(),
            'failed' => ReportLog::where('status', 'failed')->count(),
            'last_sent_at' => ReportLog::where('status', 'sent')->latest('sent_at')->value('sent_at'),
        ];

        return view('admin.reports.index', compact('setting', 'logs', 'stats'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'recipient_number' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
            'send_time' => ['required', 'date_format:H:i'],
            'daily_enabled' => ['nullable', 'boolean'],
            'weekly_enabled' => ['nullable', 'boolean'],
            'monthly_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'recipient_number.regex' => 'Use international format, for example +6281234567890.',
        ]);

        $isActive = $request->boolean('is_active');

        if ($isActive && blank($validated['recipient_number'] ?? null)) {
            return back()->withErrors([
                'recipient_number' => 'A recipient WhatsApp number is required when scheduled reports are active.',
            ])->withInput();
        }

        $setting = ReportSetting::current() ?? new ReportSetting();
        $setting->fill([
            'recipient_number' => filled($validated['recipient_number'] ?? null)
                ? preg_replace('/[\s\-().]/', '', $validated['recipient_number'])
                : null,
            'send_time' => $validated['send_time'],
            'daily_enabled' => $request->boolean('daily_enabled'),
            'weekly_enabled' => $request->boolean('weekly_enabled'),
            'monthly_enabled' => $request->boolean('monthly_enabled'),
            'is_active' => $isActive,
        ])->save();

        ActivityLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'Updated scheduled admin report settings',
            'target_user_id' => null,
        ]);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Report settings saved.');
    }

    public function sendNow(Request $request, AdminReportService $reports)
    {
        $result = $reports->send('manual');

        ActivityLog::create([
            'admin_id' => $request->user()->id,
            'action' => $result['success'] ? 'Sent a manual admin report' : 'Attempted a manual admin report',
            'target_user_id' => null,
        ]);

        return redirect()->route('admin.reports.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
