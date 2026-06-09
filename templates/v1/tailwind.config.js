/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./**/*.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: ['selector', '[data-theme="dark"]'],
  theme: {
    // ── Screens (from 3.html responsive breakpoints) ──
    screens: {
      'sm': '560px',
      'md': '900px',
      'lg': '1200px',
    },

    // ── Colors ──
    colors: {
      // Transparent & current
      transparent: 'transparent',
      current: 'currentColor',
      white: '#ffffff',
      black: '#000000',

      // Core palette — mirrors 3.html CSS custom properties
      bg: {
        DEFAULT: 'var(--bg)',
        dark: '#08080b',
        light: '#f5f5f7',
      },
      surface: {
        DEFAULT: 'var(--surface)',
        dark: 'rgba(255,255,255,0.025)',
        light: 'rgba(0,0,0,0.02)',
      },
      border: {
        DEFAULT: 'var(--border)',
        dark: 'rgba(255,255,255,0.06)',
        light: 'rgba(0,0,0,0.06)',
        hover: 'var(--border-h)',
      },
      text: {
        primary: 'var(--text)',
        secondary: 'var(--text-2)',
        muted: 'var(--text-3)',
      },
      accent: {
        DEFAULT: 'var(--accent)',
        purple: '#7b8cff',
        lavender: '#c49bff',
        'purple-light': '#5b6cf0',
        'lavender-light': '#8b5cf0',
      },
      warm: {
        DEFAULT: '#e0a870',
      },
      green: {
        DEFAULT: '#6ecf8a',
      },
      nav: {
        bg: 'var(--nav-bg)',
      },
      glow: {
        a: 'var(--glow-a)',
        b: 'var(--glow-b)',
        c: 'var(--glow-c)',
      },
    },

    // ── Font Family ──
    fontFamily: {
      sans: [
        '-apple-system', 'BlinkMacSystemFont',
        '"SF Pro Display"', '"SF Pro Text"',
        '"PingFang SC"', '"Helvetica Neue"',
        'sans-serif',
      ],
      mono: [
        '"SF Mono"', '"JetBrains Mono"', '"Fira Code"',
        'Menlo', 'monospace',
      ],
    },

    // ── Font Size (matching Apple-style optical sizing) ──
    fontSize: {
      // Eyebrow / label
      'eyebrow': ['11px', { lineHeight: '1.4', letterSpacing: '1.2px', fontWeight: '550' }],
      'eyebrow-hero': ['12px', { lineHeight: '1.4', letterSpacing: '1.5px', fontWeight: '550' }],
      // Tag
      'tag': ['11px', { lineHeight: '1.4', letterSpacing: '0.8px', fontWeight: '550' }],
      // Meta / caption
      'meta': ['12px', { lineHeight: '1.5' }],
      // Body sizes
      'body-sm': ['13px', { lineHeight: '1.6' }],
      'body': ['15px', { lineHeight: '1.7' }],
      'body-lg': ['18px', { lineHeight: '1.7', letterSpacing: '-0.2px' }],
      // Card
      'card-title': ['19px', { lineHeight: '1.3', letterSpacing: '-0.3px', fontWeight: '550' }],
      'card-title-lg': ['26px', { lineHeight: '1.3', letterSpacing: '-0.6px', fontWeight: '500' }],
      'card-title-tall': ['20px', { lineHeight: '1.3', letterSpacing: '-0.3px' }],
      // News
      'news-title': ['17px', { lineHeight: '1.4', letterSpacing: '-0.3px' }],
      // Section
      'section-h2': ['28px', { lineHeight: '1.3', letterSpacing: '-0.6px', fontWeight: '500' }],
      // Hero
      'hero-h1': ['clamp(40px, 6vw, 72px)', { lineHeight: '1.1', letterSpacing: '-2px' }],
      // Nav
      'nav-logo': ['18px', { letterSpacing: '-0.3px', fontWeight: '650' }],
      'nav-link': ['14px', { letterSpacing: '-0.1px', fontWeight: '450' }],
      'nav-cta': ['13px', { letterSpacing: '-0.1px', fontWeight: '550' }],
    },

    // ── Font Weight (Apple-style granular weights) ──
    fontWeight: {
      thin: '300',
      light: '380',
      normal: '400',
      medium: '450',
      semibold: '500',
      bold: '530',
      extrabold: '550',
      black: '600',
      heavy: '650',
    },

    // ── Border Radius ──
    borderRadius: {
      none: '0',
      sm: '6px',
      DEFAULT: '16px',   // var(--radius)
      md: '16px',
      lg: '20px',
      xl: '24px',
      full: '9999px',
      pill: '100px',
    },

    // ── Spacing (extends default) ──
    extend: {
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem',
      },

      maxWidth: {
        'container': '1200px',
      },

      // ── Backdrop Blur ──
      backdropBlur: {
        'nav': '20px',
      },

      // ── Opacity (subtle glass layers) ──
      opacity: {
        '2': '0.02',
        '3': '0.035',
        '6': '0.06',
        '10': '0.10',
        '12': '0.12',
      },

      // ── Transition ──
      transitionDuration: {
        '200': '200ms',
        '300': '300ms',
        '400': '400ms',
      },

      // ── Z-Index ──
      zIndex: {
        '0': '0',
        '1': '1',
        'nav': '100',
        'overlay': '50',
      },

      // ── Box Shadow ──
      boxShadow: {
        'glow-a': '0 0 140px var(--glow-a)',
        'glow-b': '0 0 140px var(--glow-b)',
        'glow-c': '0 0 140px var(--glow-c)',
        'card-sm': '0 1px 3px rgba(0,0,0,0.08)',
      },

      // ── Keyframes & Animation ──
      keyframes: {
        'fade-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        'slide-up': {
          '0%': { opacity: '0', transform: 'translateY(16px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'slide-down': {
          '0%': { opacity: '0', transform: 'translateY(-8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'scale-in': {
          '0%': { opacity: '0', transform: 'scale(0.97)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        'float': {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-6px)' },
        },
        'pulse-soft': {
          '0%, 100%': { opacity: '0.6' },
          '50%': { opacity: '1' },
        },
      },
      animation: {
        'fade-in': 'fade-in 0.6s ease-out forwards',
        'slide-up': 'slide-up 0.6s ease-out forwards',
        'slide-down': 'slide-down 0.3s ease-out forwards',
        'scale-in': 'scale-in 0.3s ease-out forwards',
        'float': 'float 3s ease-in-out infinite',
        'pulse-soft': 'pulse-soft 3s ease-in-out infinite',
      },

      // ── Background Image ──
      backgroundImage: {
        'hero-card': 'linear-gradient(135deg, var(--glow-a) 0%, transparent 50%)',
      },
    },
  },
  plugins: [],
}
