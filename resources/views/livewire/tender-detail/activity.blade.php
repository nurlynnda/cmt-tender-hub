<x-card title="Activity" subtitle="Everything that happened to this tender, newest first" icon="clock">
    @include('partials.timeline', ['entries' => $activity])
</x-card>
