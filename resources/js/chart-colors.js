import { SHADES, darken, rgbString, seriesColors } from './chart-palette.js'

const FAMILIES = ['primary', 'info', 'gray', 'success', 'warning', 'danger']

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

function themeFamilies() {
    const styles = getComputedStyle(document.documentElement)

    return Object.fromEntries(FAMILIES.map((name) => [
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

function paintDataset(dataset, type, colors) {
    const each = (transform) => {
        const values = colors.map((color) => (color ? rgbString(transform(color)) : undefined))

        return colors.perPoint ? values : values[0]
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
    const palette = chart.data?.palette
    const datasets = (chart.data?.datasets ?? []).filter((dataset) => dataset[PAINTED] || (dataset.backgroundColor === undefined && dataset.borderColor === undefined))

    if (!palette || datasets.length === 0) {
        return
    }

    const mode = document.documentElement.classList.contains('dark') ? 'dark' : 'light'
    const families = themeFamilies()
    const surface = surfaceColor(chart.canvas)
    const type = (dataset) => dataset.type ?? chart.config.type
    const kind = palette.kind ?? palette
    const other = palette.other === true

    if (datasets.length === 1 && chart.data.datasets.length === 1 && !LINE_TYPES.includes(type(datasets[0]))) {
        const count = datasets[0].data?.length ?? 0

        paintDataset(datasets[0], type(datasets[0]), Object.assign(seriesColors({ kind, count, other }, families, surface, mode), { perPoint: true }))

        return
    }

    const all = chart.data.datasets
    const colors = seriesColors({ kind, count: all.length, other, named: all.map((dataset) => (FAMILIES.includes(dataset.color) ? dataset.color : null)) }, families, surface, mode)

    all.forEach((dataset, index) => datasets.includes(dataset) && paintDataset(dataset, type(dataset), [colors[index]]))
}

window.filamentChartJsPlugins ??= []
window.filamentChartJsPlugins.push({ id: 'filamentDashboardsPalette', beforeUpdate: paint })
