export const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]

export const RESERVED_COLORS = ['gray', 'success', 'warning', 'danger']

const RAMP_SHADES = SHADES.slice(2, 10)

const MIN_CONTRAST = 2

const ANCHOR_CONTRAST = 3

// Below the 2:1 floor only for light-mode bars, which Filament outlines in a darker edge.
const EDGED_RAMP_CONTRAST = 1.5

const ANCHOR_LIGHTNESS = { light: 0.58, dark: 0.72 }

const ROUND_SHIFTS = { light: [0, 0.16, -0.12], dark: [0, -0.16, 0.14] }

const LANE_OFFSETS = { light: [0, -0.12], dark: [0.06, -0.08] }

const SNAP_LIGHTNESS = 0.02

const MIN_REPEAT_DISTANCE = 0.15

const SNAP_SHARE_OF_STEP = 0.2

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

const lightness = (rgb) => oklab(rgb)[0]

const distance = (first, second) => Math.hypot(...oklab(first).map((value, channel) => value - oklab(second)[channel]))

const luminance = ([r, g, b]) => 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)

export function contrast(first, second) {
    const [high, low] = [luminance(first), luminance(second)].sort((a, b) => b - a)

    return (high + 0.05) / (low + 0.05)
}

export const rgbString = ([r, g, b]) => `rgb(${r}, ${g}, ${b})`

export function darken([r, g, b], amount) {
    const max = Math.max(r, g, b) / 255
    const min = Math.min(r, g, b) / 255
    const level = (max + min) / 2
    const target = level * (1 - amount)

    if (max === min) {
        return [r, g, b].map(() => Math.round(target * 255))
    }

    const scale = (target < 0.5 ? target : 1 - target) / (level < 0.5 ? level : 1 - level)

    return [r, g, b].map((channel) => Math.round((target + (channel / 255 - level) * scale) * 255))
}

function readable(family, surface, shades = SHADES, minimum = MIN_CONTRAST) {
    return shades.filter((shade) => family?.[shade] && contrast(family[shade], surface) >= minimum).map((shade) => family[shade])
}

function nearest(shades, target) {
    return shades.reduce((best, rgb) => (Math.abs(lightness(rgb) - target) < Math.abs(lightness(best) - target) ? rgb : best))
}

export function anchorColor(family, surface, mode, offset = 0) {
    const shades = readable(family, surface)
    const strong = shades.filter((rgb) => contrast(rgb, surface) >= ANCHOR_CONTRAST)

    return shades.length === 0 ? null : nearest(strong.length > 0 ? strong : shades, ANCHOR_LIGHTNESS[mode] + offset)
}

export const defaultLanes = (names) => [...new Set(['primary', ...names.filter((name) => !RESERVED_COLORS.includes(name))])]

export function variedColors(families, names, surface, mode, count, chosen = null) {
    const lane = (name, index) => ({ name, shades: readable(families[name], surface), anchor: anchorColor(families[name], surface, mode, LANE_OFFSETS[mode][index % 2]) })
    const lanes = (chosen ? [...new Set(chosen)] : defaultLanes(names)).map(lane).filter((entry) => entry.anchor)
    const gray = chosen ? {} : lane('gray', lanes.length)
    const shifts = ROUND_SHIFTS[mode]
    const sequence = [
        ...shifts.flatMap((shift) => lanes.map((entry) => ({ ...entry, shift }))),
        ...(gray.anchor ? shifts.map((shift) => ({ ...gray, shift })) : []),
    ]

    if (sequence.length === 0) {
        return []
    }

    const used = {}

    return Array.from({ length: count }, (_, index) => {
        const { name, shades, anchor, shift } = sequence[index % sequence.length]
        const earlier = (used[name] ??= [])
        const spread = (rgb) => Math.min(...earlier.map((placed) => distance(rgb, placed)))
        const apart = shades.filter((rgb) => spread(rgb) >= MIN_REPEAT_DISTANCE)
        const color = shift === 0 || earlier.length === 0
            ? anchor
            : apart.length > 0
              ? nearest(apart, lightness(anchor) + shift)
              : shades.reduce((best, rgb) => (spread(rgb) > spread(best) ? rgb : best))

        if (!earlier.includes(color)) {
            earlier.push(color)
        }

        return color
    })
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

export function singleColors(family, surface, mode, count, edged = false) {
    const shades = readable(family, surface, RAMP_SHADES, edged && mode === 'light' ? EDGED_RAMP_CONTRAST : MIN_CONTRAST)

    if (shades.length === 0 || count === 0) {
        return []
    }

    const ramp = sample(shades, count)

    return mode === 'dark' ? ramp.reverse() : ramp
}

export function otherColor(families, surface) {
    return readable(families.gray, surface)[0] ?? null
}

export function seriesColors({ kind = 'varied', count, other = false, named = [], edged = false, fixed = null, chosen = null }, families, names, surface, mode) {
    const plain = Array.from({ length: count }, (_, index) => !named[index] && !(other && index === count - 1)).filter(Boolean).length
    const base = fixed?.length ? Array.from({ length: plain }, (_, index) => fixed[index % fixed.length]) : kind === 'single' ? singleColors(families[chosen?.[0] ?? 'primary'], surface, mode, plain, edged) : variedColors(families, names, surface, mode, plain, chosen)
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
