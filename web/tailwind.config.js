import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/livewire/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['IBM Plex Sans', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                ink: {
                    950: '#0b0d11',
                    900: '#11141a',
                    800: '#171b22',
                    700: '#1e242d',
                    600: '#2a313c',
                },
                brass: {
                    400: '#e0b36a',
                    500: '#c9963d',
                },
                good: '#3d9a7a',
                warn: '#d4a054',
                bad: '#c45c4a',
            },
            boxShadow: {
                panel: '0 0 0 1px rgba(255,255,255,0.04), 0 12px 40px rgba(0,0,0,0.35)',
            },
        },
    },
    plugins: [],
};
