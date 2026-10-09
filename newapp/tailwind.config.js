import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                maroon: {
                    50: '#fdf6f6',
                    100: '#f8e9e9',
                    200: '#efd0d0',
                    300: '#e0a8a8',
                    400: '#c96d6d',
                    500: '#a83a3a',
                    600: '#8b1e1e',
                    700: '#721818',
                    800: '#5c1414',
                    900: '#4a1111',
                    950: '#2c0808',
                },
            },
        },
    },

    plugins: [forms],
};
