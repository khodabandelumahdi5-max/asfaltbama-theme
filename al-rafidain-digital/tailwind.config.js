/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./index.html', './assets/js/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        ar: ['"IBM Plex Sans Arabic"', 'system-ui', 'sans-serif'],
        ckb: ['Vazirmatn', '"IBM Plex Sans Arabic"', 'system-ui', 'sans-serif'],
      },
      colors: {
        gold: { 200: '#fde9b0', 300: '#f9d77e', 400: '#f5b342', 500: '#e29a1c', 600: '#b97710' },
      },
      boxShadow: {
        glow: '0 0 0 1px rgba(245,179,66,.25), 0 20px 80px -20px rgba(245,179,66,.35)',
      },
      keyframes: {
        float: { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } },
        pulseRing: { '0%': { transform: 'scale(1)', opacity: '.7' }, '100%': { transform: 'scale(1.8)', opacity: '0' } },
      },
      animation: {
        float: 'float 7s ease-in-out infinite',
        'pulse-ring': 'pulseRing 2s cubic-bezier(0,0,.2,1) infinite',
      },
    },
  },
  plugins: [],
};
