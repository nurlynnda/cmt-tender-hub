<?php

namespace App\Livewire;

use Livewire\Component;

class NotificationBell extends Component
{
    public function open(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->redirectRoute('tenders.show', $notification->data['tender_id']);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(15)->get(),
        ]);
    }
}
