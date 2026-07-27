@include('errors.layout', [
    'code' => 403,
    'title' => 'Access denied',
    'body' => "You don't have permission to view this page. Head home or keep shopping our catalog.",
    'showShop' => true,
])
