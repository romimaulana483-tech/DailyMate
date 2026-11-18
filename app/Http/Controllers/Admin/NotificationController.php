<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AiConfiguration;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $ai = AiConfiguration::current();
        $whatsapp = WhatsAppConfiguration::current();

        $status = [
            'sent' => WhatsAppNotification::where('status', 'sent')->count(),
            'pending' => WhatsAppNotification::where('status', 'pending')->count(),
            'failed' => WhatsAppNotification::where('status', 'failed')->count(),
            'skipped' => WhatsAppNotification::where('status', 'skipped')->count(),
        ];

        $alerts = [];

        if (! $ai?->isReady()) {
            $alerts[] = [
                'tone' => 'warning',
                'icon' => 'bi-cpu',
                'title' => 'Qwen AI is not ready',
                'message' => 'Friday is using the built-in assistant. Add a fresh API key in Friday AI Settings.',
                'route' => route('admin.ai.edit'),
                'action' => 'Open AI settings',
            ];
        }

        if (! $whatsapp?->isReadyToSend()) {
            $alerts[] = [
                'tone' => 'warning',
                'icon' => 'bi-whatsapp',
                'title' => 'WhatsApp delivery is not ready',
                'message' => 'Fonnte is not configured or is inactive. Reminders will not be delivered until it is ready.',
                'route' => route('admin.whatsapp.edit'),
                'action' => 'Open WhatsApp settings',
            ];
        }

        if ($status['failed'] > 0) {
            $alerts[] = [
                'tone' => 'danger',
                'icon' => 'bi-exclamation-triangle',
                'title' => $status['failed'].' WhatsApp delivery failure(s) recorded',
                'message' => 'Review technical delivery status. Private task and schedule content is never shown to administrators.',
                'route' => route('admin.whatsapp.edit'),
                'action' => 'Review delivery log',
            ];
        }

        if ($status['pending'] > 0) {
            $alerts[] = [
                'tone' => 'info',
                'icon' => 'bi-hourglass-split',
                'title' => $status['pending'].' reminder(s) waiting in the queue',
                'message' => 'Make sure a queue worker is running if queued delivery is enabled.',
                'route' => route('admin.whatsapp.edit'),
                'action' => 'View queue status',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'tone' => 'success',
                'icon' => 'bi-check2-circle',
                'title' => 'System integrations are healthy',
                'message' => 'No failed or pending WhatsApp deliveries were found, and Friday is ready.',
                'route' => route('admin.dashboard'),
                'action' => 'Back to dashboard',
            ];
        }

        $recentNotifications = WhatsAppNotification::with('user')
            ->latest()
            ->limit(20)
            ->get();

        $recentActivity = ActivityLog::with('admin')
            ->latest()
            ->limit(12)
            ->get();

        $userCounts = [
            'total' => User::where('role', 'user')->count(),
            'active' => User::where('role', 'user')->where('status', 'active')->count(),
            'inactive' => User::where('role', 'user')->where('status', 'inactive')->count(),
        ];

        return view('admin.notifications.index', compact(
            'ai',
            'whatsapp',
            'status',
            'alerts',
            'recentNotifications',
            'recentActivity',
            'userCounts'
        ));
    }
}
