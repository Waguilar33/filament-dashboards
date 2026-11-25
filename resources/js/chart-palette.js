export const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]

export const VARIED_FAMILIES = ['primary', 'info', 'gray']

const RAMP_SHADES = SHADES.slice(2, 10)

const MIN_CONTRAST = 2

// Bars get a darker edge, so a ramp may start paler than a line or a lone colour could.
const RAMP_CONTRAST = 1.5

const SNAP_LIGHTNESS = 0.02

const SNAP_SHARE_OF_STEP = 0.2

const ANCHOR_CONTRAST = 3

const ANCHOR_LIGHTNESS = { light: 0.6, dark: 0.72 }

const VARIED_BAND = { light: [0.4, 0.8], dark: [0.45, 0.9] }

const toLinear = (channel) => {
    const value = channel / 255

    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
}

const toChannel = (value) => {
    const clamped = Math.min(1, Math.max(0, value))

    return Math.round((clamped <= 0.0031308 ? 12.92 * clamped : 1.055 * clamped ** (1 / 2.4) - 0.055) * 255)
}

export function oklab([r, g, b]) {
    const [lr, lg, lb] = [r, g, b].map(toLinear)
    const l = Math.cbrt(0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb)
    const m = Math.cbrt(0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb)
    const s = Math.cbrt(0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb)

    return [0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s, 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s, 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s]
}

function fromOklab([L, a, b]) {
    const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3
    const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3
    const s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3

    return [4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s, -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s, -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s].map(toChannel)
}

const luminance = ([r, g, b]) => 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)

export function contrast(first, second) {
    const [high, low] = [luminance(first), luminance(second)].sort((a, b) => b - a)

    return (high + 0.05) / (low + 0.05)
}

export const rgbString = ([r, g, b]) => `rgb(${r}, ${g}, ${b})`

export function darken([r, g, b], amount) {
    const max = Math.max(r, g, b) / 255
    const min = Math.min(r, g, b) / 255
    const lightness = (max + min) / 2
    const target = lightness * (1 - amount)

    if (max === min) {
        return [r, g, b].map(() => Math.round(target * 255))
    }

    const scale = (target < 0.5 ? target : 1 - target) / (lightness < 0.5 ? lightness : 1 - lightness)

    return [r, g, b].map((channel) => Math.round((target + (channel / 255 - lightness) * scale) * 255))
}

function readable(family, surface, shades = SHADES, minimum = MIN_CONTRAST) {
    return shades.filter((shade) => family?.[shade] && contrast(family[shade], surface) >= minimum).map((shade) => family[shade])
}

function anchorIndex(shades, surface, target) {
    const scored = shades.map((rgb, index) => ({ index, distance: Math.abs(oklab(rgb)[0] - target), strong: contrast(rgb, surface) >= ANCHOR_CONTRAST }))
    const pool = scored.some((entry) => entry.strong) ? scored.filter((entry) => entry.strong) : scored

    return pool.reduce((best, entry) => (entry.distance < best.distance ? entry : best)).index
}

export function anchorColor(family, surface, mode) {
    const shades = readable(family, surface)

    return shades.length === 0 ? null : shades[anchorIndex(shades, surface, ANCHOR_LIGHTNESS[mode])]
}

function sample(shades, count) {
    const points = shades.map(oklab)
    const lightest = points[0][0]
    const darkest = points.at(-1)[0]
    const snap = Math.min(SNAP_LIGHTNESS, ((lightest - darkest) / Math.max(1, count - 1)) * SNAP_SHARE_OF_STEP)

    return Array.from({ length: count }, (_, index) => {
        const target = count === 1 ? (lightest + darkest) / 2 : lightest - ((lightest - darkest) * index) / (count - 1)
        const exact = shades.find((rgb, shade) => Math.abs(points[shade][0] - target) <= snap)

        if (exact) {
            return exact
        }

        const to = Math.max(1, points.findIndex((point) => point[0] <= target))
        const from = to - 1
        const t = (points[from][0] - target) / (points[from][0] - points[to][0] || 1)

        return fromOklab(points[from].map((value, channel) => value + (points[to][channel] - value) * t))
    })
}

export function orderedColors(family, surface, count) {
    const shades = readable(family, surface, RAMP_SHADES, RAMP_CONTRAST)

    return shades.length === 0 || count === 0 ? [] : sample(shades, count)
}

const distance = (first, second) => Math.hypot(...oklab(first).map((value, channel) => value - oklab(second)[channel]))

export function variedColors(families, surface, mode, count) {
    const lanes = VARIED_FAMILIES.map((name) => readable(families[name], surface).filter((rgb) => {
        const lightness = oklab(rgb)[0]

        return lightness >= VARIED_BAND[mode][0] && lightness <= VARIED_BAND[mode][1]
    })).filter((shades) => shades.length > 0)

    if (lanes.length === 0) {
        return []
    }

    const colors = [lanes[0][anchorIndex(lanes[0], surface, ANCHOR_LIGHTNESS[mode])]]

    for (let index = 1; index < count; index++) {
        const unused = lanes[index % lanes.length].filter((rgb) => !colors.includes(rgb))
        const candidates = unused.length > 0 ? unused : lanes[index % lanes.length]
        const score = (rgb) => Math.min(distance(rgb, colors.at(-1)), 2 * Math.min(...colors.map((placed) => distance(rgb, placed))))

        colors.push(candidates.reduce((best, rgb) => (score(rgb) > score(best) ? rgb : best)))
    }

    return colors
}

export function otherColor(families, surface) {
    const shades = readable(families.gray, surface)

    return shades[0] ?? null
}

export function seriesColors({ kind, count, other = false, named = [] }, families, surface, mode) {
    const unnamed = Array.from({ length: count }, (_, index) => !named[index] && !(other && index === count - 1)).filter(Boolean).length
    const base = kind === 'ordered' ? orderedColors(families.primary, surface, unnamed) : variedColors(families, surface, mode, unnamed)
    const colors = []
    let next = 0

    for (let index = 0; index < count; index++) {
        if (named[index]) {
            colors.push(anchorColor(families[named[index]], surface, mode))
        } else if (other && index === count - 1) {
            colors.push(otherColor(families, surface))
        } else {
            colors.push(base[next++] ?? null)
        }
    }

    return colors
}
