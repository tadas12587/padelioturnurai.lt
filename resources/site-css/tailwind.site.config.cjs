// Tailwind v3 build for the public site (replaces the Play CDN, which
// generated the CSS in every visitor's browser). Rebuild after changing
// Blade classes:
//   npx -y tailwindcss@3.4.17 -c resources/site-css/tailwind.site.config.cjs -i resources/site-css/site.css -o public/css/site.css --minify
// and copy public/css/site.css to the server's ~/web/css/ on deploy.
module.exports = {
  content: [
    './resources/views/layouts/**/*.blade.php',
    './resources/views/pages/**/*.blade.php',
    './resources/views/partials/**/*.blade.php',
    './resources/views/components/**/*.blade.php',
    './resources/views/emails/**/*.blade.php',
  ],
  theme: {
    extend: {
      colors: {
        dark: '#0A0A0F',
        'dark-card': '#111118',
        'dark-border': '#1E1E2E',
        gold: '#C9A84C',
        'gold-light': '#E5C76B',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
};
