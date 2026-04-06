<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'sms_notifications_enabled' => ['nullable', 'boolean'],
            'whatsapp_notifications_enabled' => ['nullable', 'boolean'],
            'email_notifications_enabled' => ['nullable', 'boolean'],
            'sms_notification_preferences' => ['nullable', 'array'],
            'sms_notification_preferences.*' => ['nullable', 'boolean'],
            'whatsapp_notification_preferences' => ['nullable', 'array'],
            'whatsapp_notification_preferences.*' => ['nullable', 'boolean'],
            'email_notification_preferences' => ['nullable', 'array'],
            'email_notification_preferences.*' => ['nullable', 'boolean'],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,bmp,svg,heic,heif', 'max:2048'],
        ];
    }
}
