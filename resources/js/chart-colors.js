import { SHADES, darken, rgbString, seriesColors } from './chart-palette.js'

const LINE_TYPES = ['line', 'scatter', 'radar']

const ROUND_TYPES = ['pie', 'doughnut', 'polarArea']

const PAINTED = Symbol('painted')

let probe = null

// Panel colours are oklch, which Chart.js' colour helper cannot parse, so each is read back from a canvas pixel as sRGB.
function toRgb(value) {
    probe ??= Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true })
    probe.clearRect(0, 0, 1, 1)
    probe.fillStyle = '#000'
    probe.fillStyle = value
    probe.fillRect(0, 0, 1, 1)

    const [r, g, b] = probe.getImageData(0, 0, 1, 1).data

    return [r, g, b]
}

function themeFamilies(names) {
    const styles = getComputedStyle(document.documentElement)

    return Object.fromEntries(names.map((name) => [
        name,
        Object.fromEntries(SHADES.map((shade) => [shade, styles.getPropertyValue(`--${name}-${shade}`).trim()]).filter(([, value]) => value !== '').map(([shade, value]) => [shade, toRgb(value)])),
    ]))
}

function surfaceColor(canvas) {
    for (let element = canvas.parentElement; element; element = element.parentElement) {
        const { backgroundColor } = getComputedStyle(element)

        if (backgroundColor && backgroundColor !== 'transparent' && backgroundColor !== 'rgba(0, 0, 0, 0)') {
            return toRgb(backgroundColor)
        }
    }

    return [255, 255, 255]
}

function paintDataset(dataset, type, colors, perPoint) {
    const each = (transform) => {
        const values = colors.map((color) => (color ? rgbString(transform(color)) : undefined))

        return perPoint ? values : values[0]
    }

    dataset.backgroundColor = each((color) => color)

    if (LINE_TYPES.includes(type)) {
        dataset.borderColor = dataset.backgroundColor
        dataset.pointBackgroundColor = dataset.backgroundColor
    } else if (!ROUND_TYPES.includes(type)) {
        dataset.borderColor = each((color) => darken(color, 0.2))
        dataset.hoverBorderColor = each((color) => darken(color, 0.3))
    }

    dataset[PAINTED] = true
}

export function paint(chart) {
    const config = window.filamentDashboardsChartColors
    const palette = chart.data?.palette
    const all = chart.data?.datasets ?? []
    const datasets = all.filter((dataset) => dataset[PAINTED] || (dataset.backgroundColor === undefined && dataset.borderColor === undefined))
    const type = (dataset) => dataset.type ?? chart.config.type
    const perPoint = all.length === 1 && ROUND_TYPES.includes(type(all[0]))

    if (!config || datasets.length === 0 || (!palette && !chart.canvas.closest('.grid-stack')) || (all.length === 1 && !perPoint)) {
        return
    }

    const count = perPoint ? (all[0].data?.length ?? 0) : all.length
    const named = perPoint ? [] : all.map((dataset) => (typeof dataset.color === 'string' && dataset.color !== '' ? dataset.color : null))
    const mode = document.documentElement.classList.contains('dark') ? 'dark' : 'light'
    const colors = seriesColors(
        { kind: palette?.kind ?? palette ?? 'varied', count, other: palette?.other === true, named, edged: all.every((dataset) => type(dataset) === 'bar'), fixed: config.fixed?.map(toRgb), chosen: config.palette },
        themeFamilies([...new Set([...config.names, 'gray', ...named.filter(Boolean)])]),
        config.names,
        surfaceColor(chart.canvas),
        mode,
    )

    if (perPoint) {
        paintDataset(all[0], type(all[0]), colors, true)

        return
    }

    all.forEach((dataset, index) => datasets.includes(dataset) && paintDataset(dataset, type(dataset), [colors[index]], false))
}

window.filamentChartJsPlugins ??= []
window.filamentChartJsPlugins.push({ id: 'filamentDashboardsPalette', beforeUpdate: paint })
