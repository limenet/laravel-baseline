// server.watch.ignored: ['**/coverage/**', '**/playwright-report/**', '**/.trivycache/**']
const generated = ['**/coverage/**', '**/playwright-report/**', '**/.trivycache/**'];
const pattern = /['"]\/coverage\//;

export default {
    plugins: [],
    server: {
        watch: {
            ignored: [],
        },
    },
};
