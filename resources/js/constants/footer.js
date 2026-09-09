export const footerFeatures = [
  { icon: 'truck', label: 'Free & Fast Delivery', tone: 'blue' },
  { icon: 'refresh', label: 'Easy Returns', tone: 'green' },
  { icon: 'card', label: 'Secure Payments', tone: 'pink' },
  { icon: 'award', label: 'Quality Assured', tone: 'yellow' },
];

export const footerBlurb =
  'A focused store for creative toys and everyday lunch boxes across India.';

const whatsappNumber = '919173279323';
const whatsappMessage =
  'Hello Ventures Mart! I have a question about your toys and lunch boxes. Please help me when you are available.';

export const footerWhatsApp = {
  href: `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMessage)}`,
  label: 'Chat on WhatsApp',
  message: whatsappMessage,
};

export const footerShopLinks = [
  { label: 'Toys', href: '/category/toys' },
  { label: 'Lunch Box', href: '/category/lunch-box' },
  { label: 'All products', href: '/shop' },
];

export const footerCustomerCareLinks = [
  { label: 'Shipping', href: '/shipping' },
  { label: 'Replacement', href: '/replacement' },
  { label: 'Track Order', href: '/orders' },
  { label: 'Payments', href: '/payments' },
];

export const footerSupportLinks = [
  ...footerCustomerCareLinks,
  { label: 'About', href: '/about' },
];

export const footerCompanyLinks = [
  { label: 'About', href: '/about' },
  { label: 'Blog', href: '/blog' },
  { label: 'Contact', href: '/contact' },
];

export const footerContact = {
  email: 'neelkanthventures1804@gmail.com',
  phone: '+91 91732 79323',
  phoneHref: 'tel:+919173279323',
};

export const footerPaymentPills = ['UPI', 'Cards', 'Net Banking', 'COD'];

export const footerBottomLinks = [
  { label: 'Privacy', href: '/privacy-policy' },
  { label: 'Terms', href: '/terms' },
  { label: 'Contact', href: '/contact' },
];
