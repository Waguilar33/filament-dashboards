import * as esbuild from 'esbuild'

const isWatching = process.argv.includes('--watch')

const context = await esbuild.context({
    entryPoints: [
        { in: 'resources/js/dashboard-grid.js', out: 'dashboard-grid' },
        { in: 'resources/css/dashboards.css', out: 'dashboards' },
    ],
    outdir: 'resources/dist',
    bundle: true,
    minify: !isWatching,
    format: 'esm',
    platform: 'browser',
    target: ['es2022'],
    logLevel: 'info',
})

if (isWatching) {
    await context.watch()
} else {
    await context.rebuild()
    await context.dispose()
}
