import { defineConfig } from 'vite';

// The 3D preview (STL and STEP) for the enquiry page, built on its own into public/js/viewer: npm run build:viewer
export default defineConfig({
    publicDir: false,
    base: '/js/viewer/',
    build: {
        outDir: 'public/js/viewer',
        emptyOutDir: true,
        assetsDir: '.',
        assetsInlineLimit: 0,
        lib: { entry: 'resources/js/model-viewer.js', formats: ['es'], fileName: () => 'model-viewer.js' },
    },
});
