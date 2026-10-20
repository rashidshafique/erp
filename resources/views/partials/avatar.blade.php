{{-- Round profile picture with initials behind it; a Gravatar that does not exist (404) is removed so the initials show. $user, optional $size in px. --}}
@php
    $initials = strtoupper(mb_substr(collect(preg_split('/\s+/', trim($user->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''), 0, 2));
    $url = $user->avatarUrl();
    $size = $size ?? null;
@endphp
<span class="sp-avatar" aria-hidden="true" @if($size) style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ (int) ($size / 2.6) }}px" @endif>{{ $initials }}@if($url)<img src="{{ $url }}" alt="" onerror="this.remove()" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit">@endif</span>
