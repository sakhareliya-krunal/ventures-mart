@include('errors.layout', [
    'code' => 404,
    'title' => 'Page not found',
    'body' => "That page isn't part of the Ventures Mart storefront. Try the shop or return home.",
    'showShop' => true,
])
