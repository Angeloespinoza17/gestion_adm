import { fileURLToPath, URL } from "node:url";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vitest/config";

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
    test: {
        environment: "node",
        include: ["tests/frontend/**/*.test.js"],
        clearMocks: true,
        restoreMocks: true,
    },
});
