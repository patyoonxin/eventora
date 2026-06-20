/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          purple: '#7c3aed',
          blue: '#3b82f6',
          'purple-secondary': '#a855f7',
          'blue-secondary': '#60a5fa',
          'purple-dark': '#5b21b6',
          'purple-light': '#e9d5ff',
        }
      },
      animation: {
        'float': 'float 20s ease-in-out infinite',
        'float-reverse': 'float 15s ease-in-out infinite reverse',
        'logo-float': 'logoFloat 3s ease-in-out infinite',
        'slide-up': 'slideUp 0.6s ease-out',
      },
      keyframes: {
        float: {
          '0%, 100%': { transform: 'translate(0, 0)' },
          '50%': { transform: 'translate(30px, -30px)' },
        },
        logoFloat: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        slideUp: {
          'from': {
            opacity: '0',
            transform: 'translateY(30px)',
          },
          'to': {
            opacity: '1',
            transform: 'translateY(0)',
          },
        },
      },
    },
  },
  plugins: [],
}
