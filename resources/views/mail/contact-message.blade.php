<x-mail::message>
# Neue Nachricht über das Kontaktformular

**Von:** {{ $senderName }}
**E-Mail:** {{ $senderEmail }}
**Betreff:** {{ $subject }}

<x-mail::panel>
{{ $body }}
</x-mail::panel>

Antworten Sie einfach auf diese E-Mail — sie geht direkt an {{ $senderEmail }}.

{{ config('app.name') }}
</x-mail::message>
