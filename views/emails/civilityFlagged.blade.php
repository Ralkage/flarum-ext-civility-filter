Your recent post in "{!! $blueprint->post->discussion->title !!}" was flagged by the civility filter.

Action: {{ $blueprint->getData()['action'] }}

@if($blueprint->getData()['reason'])
Reason: {{ $blueprint->getData()['reason'] }}
@endif

Please keep discussions respectful and constructive to avoid further action.
