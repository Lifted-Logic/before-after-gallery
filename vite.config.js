import { defineConfig } from 'vite';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';
import fullReload from 'vite-plugin-full-reload';
import basicSsl from '@vitejs/plugin-basic-ssl';

const __dirname  = dirname(fileURLToPath(import.meta.url));
const hotFile    = resolve(__dirname, 'hot');
const useHttps   = process.env.VITE_DEV_HTTPS === 'true';

function wordPressHMR() {
    return {
        name: 'wordpress-hmr',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const address  = server.httpServer.address();
                const port     = typeof address === 'object' ? address?.port : 5173;
                const protocol = useHttps ? 'https' : 'http';
                fs.writeFileSync(hotFile, `${protocol}://localhost:${port}`);

                const cleanup = () => {
                    if (fs.existsSync(hotFile)) fs.rmSync(hotFile);
                };

                process.once('exit',   cleanup);
                process.once('SIGINT', () => { cleanup(); process.exit(); });
            });
        },
        buildStart() {
            if (fs.existsSync(hotFile)) fs.rmSync(hotFile);
        },
    };
}

export default defineConfig({
    plugins: [
        wordPressHMR(),
        fullReload(['templates/**/*.php']),
        ...(useHttps ? [basicSsl()] : []),
    ],

    build: {
        outDir:   'public/build',
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: {
                admin:    resolve(__dirname, 'resources/js/admin.js'),
                frontend: resolve(__dirname, 'resources/js/frontend.js'),
            },
        },
    },

    server: {
        port: 5173,
        strictPort: true,
        cors: true,
    },
});
