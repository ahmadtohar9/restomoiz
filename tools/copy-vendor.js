/**
 * Salin aset pihak ketiga dari node_modules ke public/assets/vendor supaya
 * aplikasi berjalan tanpa internet (tanpa CDN). Jalankan: npm run vendor
 */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const nm = path.join(root, 'node_modules');
const out = path.join(root, 'public/assets/vendor');

function copy(from, to) {
  fs.mkdirSync(path.dirname(path.join(out, to)), { recursive: true });
  fs.copyFileSync(path.join(nm, from), path.join(out, to));
  console.log('  ' + to);
}

// Bootstrap (grid, utilitas, komponen JS: modal, dropdown, offcanvas, toast)
copy('bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap/bootstrap.bundle.min.js');
copy('bootstrap/LICENSE', 'bootstrap/LICENSE');

// Bootstrap Icons (font)
copy('bootstrap-icons/font/bootstrap-icons.min.css', 'bootstrap-icons/bootstrap-icons.min.css');
copy('bootstrap-icons/font/fonts/bootstrap-icons.woff2', 'bootstrap-icons/fonts/bootstrap-icons.woff2');
copy('bootstrap-icons/font/fonts/bootstrap-icons.woff', 'bootstrap-icons/fonts/bootstrap-icons.woff');
copy('bootstrap-icons/LICENSE', 'bootstrap-icons/LICENSE');

// Font Inter (variable) latin + latin-ext
copy('@fontsource-variable/inter/files/inter-latin-wght-normal.woff2', 'inter/inter-latin-wght-normal.woff2');
copy('@fontsource-variable/inter/files/inter-latin-ext-wght-normal.woff2', 'inter/inter-latin-ext-wght-normal.woff2');
copy('@fontsource-variable/inter/LICENSE', 'inter/LICENSE');
fs.writeFileSync(path.join(out, 'inter/inter.css'), `/* Inter Variable (SIL OFL 1.1) - disajikan lokal */
@font-face { font-family: 'Inter Variable'; font-style: normal; font-display: swap; font-weight: 100 900;
  src: url(inter-latin-ext-wght-normal.woff2) format('woff2-variations');
  unicode-range: U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF; }
@font-face { font-family: 'Inter Variable'; font-style: normal; font-display: swap; font-weight: 100 900;
  src: url(inter-latin-wght-normal.woff2) format('woff2-variations');
  unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
}
`);
console.log('  inter/inter.css');
console.log('Vendor OK');
