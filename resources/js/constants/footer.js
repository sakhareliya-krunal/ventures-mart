export const footerFeatures = [
  { icon: 'truck', label: 'Delivery across India' },
  { icon: 'shield', label: '7-day replacement support' },
  { icon: 'package', label: 'Curated toys & lunch boxes' },
];

export const footerBlurb =
  'A focused store for creative toys and everyday lunch boxes across India.';

const whatsappNumber = '919173279323';
const whatsappMessage =
  'Hello Venture Smart! I have a question about your toys and lunch boxes. Please help me when you are available.';

export const footerWhatsApp = {
  href: `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMessage)}`,
  label: 'Chat on WhatsApp',
  message: whatsappMessage,
};

export const footerShopLinks = [
  { label: 'Toys', href: '/category/toys' },
  { label: 'Lunch Box', href: '/category/lunch-box' },
  { label: 'Shop all', href: '/shop' },
  { label: 'Favourites', href: '/wishlist' },
];

export const footerSupportLinks = [
  { label: 'Shipping', href: '/shipping' },
  { label: 'Returns', href: '/returns' },
  { label: 'Track Order', href: '/orders' },
  { label: 'Contact', href: '/contact' },
];

export const footerCompanyLinks = [
  { label: 'Why Venture Smart', href: '/about' },
  { label: 'Blog', href: '/blog' },
  { label: 'Payments', href: '/payments' },
  { label: 'Privacy Policy', href: '/privacy-policy' },
  { label: 'Terms', href: '/terms' },
];

export const footerContact = {
  email: 'neelkanthventures1804@gmail.com',
  phone: '+91 91732 79323',
  phoneHref: 'tel:+919173279323',
};

export const footerPaymentPills = ['UPI', 'Cards', 'Net Banking', 'COD', 'Razorpay'];

export const footerBottomLinks = [
  { label: 'Privacy', href: '/privacy-policy' },
  { label: 'Terms', href: '/terms' },
  { label: 'Contact', href: '/contact' },
];
