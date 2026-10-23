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
        brand: { 50: '#ecfdf5', 100: '#d1fae5', 500: '#10b981', 600: '#059669', 700: '#047857' },
        navy: { 800: '#112240', 900: '#0a192f', 950: '#060d1a' },
      },
    },
  },
  plugins: [],
};
