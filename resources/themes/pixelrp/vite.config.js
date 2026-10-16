import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import tailwindcss from "@tailwindcss/postcss";
import autoprefixer from "autoprefixer";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                path.resolve(__dirname, "css/app.css"),
                path.resolve(__dirname, "js/app.js"),
                // Shared entries other layouts (installation, housekeeping's
                // Filament theme) still read from the same manifest.
                "resources/js/global.js",
                "resources/css/global.css",
                "resources/css/filament/housekeeping/theme.css",
            ],
        }),

        {
            name: "blade",
            handleHotUpdate({ file, server }) {
                if (file.endsWith(".blade.php")) {
                    server.ws.send({
                        type: "full-reload",
                        path: "*",
                    });
                }
            },
        },
    ],
    css: {
        postcss: {
            // Tailwind only touches the shared entries above; the pixelrp
            // theme itself is plain CSS on the design system's tokens.
            plugins: [tailwindcss(), autoprefixer()],
        },
    },
});
