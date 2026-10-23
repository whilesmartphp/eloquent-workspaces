<x-mail::message>
{{ $inviterName ? $inviterName.' invited you' : 'You are invited' }} to join **{{ $workspaceName }}** as {{ $role === 'admin' ? 'an admin' : 'a member' }}.

<x-mail::button :url="$url">
Open the invitation
</x-mail::button>

@if ($expiresAt)
The invitation expires on {{ $expiresAt->toFormattedDayDateString() }}. Sign in or create an account with this email address to accept it.
@else
Sign in or create an account with this email address to accept it.
@endif
</x-mail::message>
