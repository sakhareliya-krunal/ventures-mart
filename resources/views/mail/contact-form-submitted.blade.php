<x-mail::message>
# New contact message

Someone submitted the Venture Smart contact form.

**Name:** {{ $contactMessage->name }}

**Email:** {{ $contactMessage->email }}

**Message:**

{{ $contactMessage->message }}

**Submitted:** {{ $contactMessage->created_at?->timezone(config('app.timezone'))->toDayDateTimeString() }}

You can reply directly to this email to reach the customer.
</x-mail::message>
