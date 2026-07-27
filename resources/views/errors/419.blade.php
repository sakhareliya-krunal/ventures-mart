@include('errors.layout', [
    'code' => 419,
    'title' => 'Session expired',
    'body' => 'Your session timed out for security. Refresh and continue where you left off.',
    'showRetry' => true,
])
