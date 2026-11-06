<button type="button" class="notice-item tone-{{ $notice->data['tone'] ?? 'info' }} {{ $notice->read_at ? '' : 'unread' }}" wire:key="notice-{{ $notice->id }}" wire:click="openNotice('{{ $notice->id }}')">
    <strong>{{ $notice->data['title'] ?? '' }}</strong>
    <small>{{ $notice->data['body'] ?? '' }}</small>
    <small>{{ $notice->created_at->diffForHumans() }}</small>
</button>
