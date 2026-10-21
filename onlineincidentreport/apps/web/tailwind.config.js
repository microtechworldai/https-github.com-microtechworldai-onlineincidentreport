/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        uss: {
          navy: '#0F2042',
          blue: '#1E40AF',
          gold: '#C59A41',
          lightGold: '#E6C673',
          dark: '#0A1128',
          slate: '#F1F5F9',
        }
      }
    },
  },
  plugins: [],
}
