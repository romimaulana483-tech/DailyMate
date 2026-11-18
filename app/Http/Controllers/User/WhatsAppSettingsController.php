<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppSettingsController extends Controller
{
    public function update(Request $request)
    {
        $number = preg_replace('/[\s\-().]/', '', (string) $request->input('whatsapp_number'));
        $number = filled($number) ? $number : null;

        $request->merge([
            'whatsapp_number' => $number,
            'whatsapp_notifications_enabled' => $request->boolean('whatsapp_notifications_enabled') ? 1 : 0,
        ]);

        $enabled = $request->boolean('whatsapp_notifications_enabled');

        $validated = $request->validate([
            'whatsapp_number' => [
                Rule::requiredIf($enabled),
                'nullable',
                'string',
                'max:20',
                'regex:/^\+[1-9]\d{7,14}$/',
            ],
            'whatsapp_notifications_enabled' => ['required', 'boolean'],
            'whatsapp_consent' => $enabled
                ? ['required', 'accepted']
                : ['nullable'],
            'whatsapp_reminder_offsets' => ['nullable', 'array'],
            'whatsapp_reminder_offsets.*' => ['integer', 'in:60,180,720,1440'],
        ], [
            'whatsapp_number.regex' => 'Use an international number starting with +, for example +6281234567890.',
            'whatsapp_number.required' => 'A WhatsApp number is required when notifications are enabled.',
            'whatsapp_consent.required' => 'Please give consent before enabling WhatsApp notifications.',
            'whatsapp_consent.accepted' => 'Please give consent before enabling WhatsApp notifications.',
        ]);

        $user = $request->user();

        $offsets = collect($validated['whatsapp_reminder_offsets'] ?? [])
            ->map(fn ($offset) => (int) $offset)
            ->filter(fn (int $offset) => in_array($offset, [60, 180, 720, 1440], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($enabled && $offsets === []) {
            $offsets = [60];
        }

        $user->whatsapp_number = $validated['whatsapp_number'] ?? null;
        $user->whatsapp_notifications_enabled = $enabled;
        $user->whatsapp_reminder_offsets = $offsets;

        if ($enabled) {
            $user->whatsapp_consent_at = $user->whatsapp_consent_at ?? now();
        } else {
            $user->whatsapp_consent_at = null;
        }

        $user->save();

        return back()->with(
            'success',
            $enabled
                ? 'WhatsApp notifications are enabled. DailyMate will use this number for reminders.'
                : 'WhatsApp notifications are disabled. Your number was kept for future use.'
        );
    }
}
