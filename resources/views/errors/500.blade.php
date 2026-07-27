@include('errors.layout', [
    'code' => 500,
    'title' => 'Something went wrong',
    'body' => "We're sorry — something unexpected happened. You can retry, go home, or continue shopping.",
    'showRetry' => true,
    'showShop' => true,
])
