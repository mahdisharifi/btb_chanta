import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',

    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Vazir', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                gold: {
                    50:  '#fbf7ec',
                    100: '#f5ecd0',
                    200: '#ebd79e',
                    300: '#e0c06b',
                    400: '#d4af37', // رنگ اصلی برند
                    500: '#c29b2c',
                    600: '#a37e23',
                    700: '#7d5f1c',
                    800: '#5a4415',
                    900: '#3b2c0e',
                },
            },
        },
    },


    plugins: [forms],
};
