// pixelrp: the housekeeping panel is a React app with its own Vite build,
// separate from the Blade themes. It compiles into public/build-housekeeping
// (see the Dockerfile) so the two builds never overwrite each other's
// manifest, and it needs no Composer output to build.
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import path from "path";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/housekeeping/main.tsx"],
            buildDirectory: "build-housekeeping",
            refresh: ["resources/views/housekeeping/**"],
        }),
        react(),
    ],
    resolve: {
        alias: {
            "@hk": path.resolve(__dirname, "resources/housekeeping"),
        },
    },
    build: {
        target: "es2020",
    },
});
