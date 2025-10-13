import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    safelist: [
        // Badges & variants potentially composed dynamically in Blade
        'bg-emerald-50','text-emerald-700','ring-emerald-200',
        'bg-amber-50','text-amber-700','ring-amber-200',
        'bg-rose-50','text-rose-700','ring-rose-200',
        'bg-blue-50','text-blue-700','ring-blue-200',
        'bg-slate-100','text-slate-700','ring-slate-200',
        // Additional generic variants sometimes used in badges/status
        'bg-green-100','bg-yellow-100','bg-red-100',
        'text-green-600','text-red-600','text-yellow-600',
        'ring-green-200','ring-yellow-200','ring-red-200',
    ],

    plugins: [forms],
};
