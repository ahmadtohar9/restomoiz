/** @type {import('tailwindcss').Config} */
// Prefix "tw-" + preflight off: Tailwind hidup berdampingan dengan Bootstrap tanpa bentrok.
module.exports = {
  prefix: 'tw-',
  corePlugins: { preflight: false },
  content: ['./application/views/**/*.php', './public/assets/js/*.js'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter Variable"', 'Inter', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
      },
      colors: {
        brand: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
      },
    },
  },
  plugins: [],
};
