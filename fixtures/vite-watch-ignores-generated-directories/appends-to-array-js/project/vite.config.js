export default {
    server: {
        watch: {
            usePolling: true,
            ignored: [
                '**/coverage/**',
                '**/fixtures/**', // written by the seed script
            ],
        },
    },
}
