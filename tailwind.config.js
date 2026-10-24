/** Tailwind (v3) — mismo tema del Sistema JZ: colores "azul" y "oro".
 *  Los colores 800 / 500 / 700 salen de variables CSS para que cada Cyber
 *  vea su propia marca. El CSS ya va compilado en public/css/tema.css;
 *  solo hace falta recompilar si se agregan clases nuevas:  npm run css
 */
export default {
  content: { relative: true, files: ['./resources/views/**/*.blade.php', './app/Support/**/*.php', './public/js/*.js'] },
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        azul: {
          950: '#061a33',
          800: 'rgb(var(--jz-azul-800) / <alpha-value>)',
          600: '#145ca6',
          400: '#3d84cf',
        },
        oro: {
          700: 'rgb(var(--jz-oro-700) / <alpha-value>)',
          500: 'rgb(var(--jz-oro-500) / <alpha-value>)',
          300: '#e8c766',
        },
      },
    },
  },
  plugins: [],
};
