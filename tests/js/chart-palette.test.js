import assert from 'node:assert/strict'
import { test } from 'node:test'
import { contrast, oklab, seriesColors } from '../../resources/js/chart-palette.js'

const FILAMENT = {
    primary: { 50: [255, 251, 235], 100: [254, 243, 198], 200: [254, 230, 133], 300: [255, 210, 48], 400: [255, 185, 0], 500: [254, 154, 0], 600: [225, 113, 0], 700: [187, 77, 0], 800: [151, 60, 0], 900: [123, 51, 6], 950: [70, 25, 1] },
    info: { 50: [239, 246, 255], 100: [219, 234, 254], 200: [190, 219, 255], 300: [142, 197, 255], 400: [81, 162, 255], 500: [43, 127, 255], 600: [21, 93, 252], 700: [20, 71, 230], 800: [25, 60, 184], 900: [28, 57, 142], 950: [22, 36, 86] },
    gray: { 50: [250, 250, 250], 100: [244, 244, 245], 200: [228, 228, 231], 300: [212, 212, 216], 400: [159, 159, 169], 500: [113, 113, 123], 600: [82, 82, 92], 700: [63, 63, 70], 800: [39, 39, 42], 900: [24, 24, 27], 950: [9, 9, 11] },
    success: { 400: [5, 223, 114], 500: [0, 201, 80], 600: [0, 166, 62], 700: [0, 130, 54] },
    danger: { 400: [255, 100, 103], 500: [251, 44, 54], 600: [231, 0, 11], 700: [193, 0, 7] },
}

const FILAMENT_NAMES = ['danger', 'gray', 'info', 'primary', 'success', 'warning']

const SORTIVO = {
    ...FILAMENT,
    primary: { 50: [241, 248, 250], 100: [222, 241, 244], 200: [189, 227, 233], 300: [146, 208, 219], 400: [94, 179, 194], 500: [35, 127, 141], 600: [15, 107, 120], 700: [8, 90, 102], 800: [8, 76, 86], 900: [6, 58, 65], 950: [4, 36, 41] },
    earth: { 50: [255, 244, 239], 100: [255, 230, 219], 200: [249, 193, 171], 300: [247, 160, 125], 400: [230, 117, 72], 500: [176, 63, 0], 600: [140, 33, 0], 700: [118, 25, 0], 800: [99, 22, 0], 900: [78, 16, 0], 950: [49, 8, 0] },
    info: { 50: [240, 247, 255], 100: [222, 239, 255], 200: [187, 222, 255], 300: [145, 199, 255], 400: [94, 167, 255], 500: [36, 116, 207], 600: [16, 95, 180], 700: [8, 78, 152], 800: [9, 67, 129], 900: [5, 50, 100], 950: [3, 31, 64] },
    pink: { 50: [255, 243, 248], 100: [255, 227, 240], 200: [255, 199, 226], 300: [255, 163, 207], 400: [237, 118, 179], 500: [185, 64, 130], 600: [158, 45, 109], 700: [133, 35, 91], 800: [113, 30, 77], 900: [87, 21, 58], 950: [56, 11, 36] },
    violet: { 50: [249, 244, 255], 100: [243, 231, 255], 200: [231, 206, 255], 300: [216, 174, 255], 400: [190, 133, 249], 500: [142, 81, 199], 600: [120, 62, 172], 700: [100, 49, 145], 800: [85, 43, 123], 900: [64, 31, 95], 950: [40, 18, 61] },
    gray: { 50: [249, 251, 251], 100: [241, 243, 243], 200: [227, 231, 232], 300: [208, 214, 216], 400: [156, 168, 171], 500: [103, 120, 124], 600: [75, 88, 91], 700: [57, 68, 71], 800: [34, 41, 43], 900: [22, 27, 29], 950: [9, 11, 12] },
}

const SORTIVO_NAMES = ['danger', 'earth', 'gray', 'info', 'pink', 'primary', 'success', 'violet', 'warning']

const SURFACES = { light: [255, 255, 255], dark: [24, 24, 27] }

const lightness = (rgb) => oklab(rgb)[0]

const familyOf = (families, rgb) => Object.keys(families).find((name) => Object.values(families[name]).some((shade) => String(shade) === String(rgb)))

const distance = (first, second) => Math.hypot(...oklab(first).map((value, channel) => value - oklab(second)[channel]))

for (const mode of ['light', 'dark']) {
    test(`series take the panel's colours, primary first, the rest in registration order, then repeats that stand out more first, gray last, on the ${mode} card`, () => {
        const sortivo = seriesColors({ count: 16 }, SORTIVO, SORTIVO_NAMES, SURFACES[mode], mode)
        const filament = seriesColors({ count: 7 }, FILAMENT, FILAMENT_NAMES, SURFACES[mode], mode)
        const lane = ['primary', 'earth', 'info', 'pink', 'violet']

        assert.deepEqual(sortivo.map((rgb) => familyOf(SORTIVO, rgb)), [...lane, ...lane, ...lane, 'gray'])
        assert.deepEqual(filament.map((rgb) => familyOf(FILAMENT, rgb)), ['primary', 'info', 'primary', 'info', 'primary', 'info', 'gray'])
        const [first, second] = mode === 'light' ? [1, -1] : [-1, 1]

        const towards = (round, sign) => lane.filter((name, index) => Math.sign(lightness(sortivo[index + round * 5]) - lightness(sortivo[index])) === sign).length

        assert.ok(towards(1, first) >= lane.length - 1, 'the first repeats mostly go the preferred way')
        assert.ok(towards(2, second) >= lane.length - 1, 'the second repeats mostly go the other way')
    })

    test(`repeats stay distinct from their neighbours and readable, on the ${mode} card`, () => {
        const colors = seriesColors({ count: 9 }, SORTIVO, SORTIVO_NAMES, SURFACES[mode], mode)

        assert.equal(new Set(colors.map(String)).size, 9)
        colors.forEach((color) => assert.ok(contrast(color, SURFACES[mode]) >= 2))
        colors.slice(1).forEach((color, index) => assert.ok(distance(color, colors[index]) >= 0.12, `series ${index} and ${index + 1} are too alike`))
    })

    test(`success, warning and danger are never picked unless a series names one, on the ${mode} card`, () => {
        const colors = seriesColors({ count: 12 }, FILAMENT, FILAMENT_NAMES, SURFACES[mode], mode)
        const named = seriesColors({ count: 3, named: [null, 'danger', null] }, FILAMENT, FILAMENT_NAMES, SURFACES[mode], mode)

        colors.forEach((color) => assert.ok(['primary', 'info', 'gray'].includes(familyOf(FILAMENT, color))))
        assert.equal(familyOf(FILAMENT, named[1]), 'danger')
        assert.deepEqual([named[0], named[2]], seriesColors({ count: 2 }, FILAMENT, FILAMENT_NAMES, SURFACES[mode], mode))
    })
}

test('a single colour shades primary from faint to strong: light to dark on a light card, dark to light on a dark one', () => {
    for (const count of [2, 4, 7]) {
        const light = seriesColors({ kind: 'single', count }, SORTIVO, SORTIVO_NAMES, SURFACES.light, 'light')
        const dark = seriesColors({ kind: 'single', count }, SORTIVO, SORTIVO_NAMES, SURFACES.dark, 'dark')

        light.slice(1).forEach((color, index) => assert.ok(lightness(color) < lightness(light[index])))
        dark.slice(1).forEach((color, index) => assert.ok(lightness(color) > lightness(dark[index])))
        dark.forEach((color) => assert.ok(contrast(color, SURFACES.dark) >= 2))
    }
})

test('a light-mode bar ramp may start paler, since Filament outlines bars', () => {
    const plain = seriesColors({ kind: 'single', count: 4 }, FILAMENT, FILAMENT_NAMES, SURFACES.light, 'light')
    const edged = seriesColors({ kind: 'single', count: 4, edged: true }, FILAMENT, FILAMENT_NAMES, SURFACES.light, 'light')

    plain.forEach((color) => assert.ok(contrast(color, SURFACES.light) >= 2))
    edged.forEach((color) => assert.ok(contrast(color, SURFACES.light) >= 1.5))
    assert.ok(lightness(edged[0]) > lightness(plain[0]))
})

test('a palette chosen by the panel is used as given, in its order, without gray', () => {
    const chosen = ['primary', 'earth', 'info', 'violet']

    for (const mode of ['light', 'dark']) {
        const colors = seriesColors({ count: 9, chosen }, SORTIVO, SORTIVO_NAMES, SURFACES[mode], mode)

        assert.deepEqual(colors.map((rgb) => familyOf(SORTIVO, rgb)), [...chosen, ...chosen, 'primary'])
        assert.equal(familyOf(SORTIVO, seriesColors({ kind: 'single', count: 2, chosen: ['earth'] }, SORTIVO, SORTIVO_NAMES, SURFACES[mode], mode)[0]), 'earth')
    }
})

for (const mode of ['light', 'dark']) {
    test(`a repeat stands clearly apart from every earlier use of its colour, on the ${mode} card`, () => {
        for (const lanes of [['primary', 'earth', 'info', 'violet'], ['primary', 'earth', 'info', 'pink', 'violet']]) {
            const colors = seriesColors({ count: lanes.length * 3, chosen: lanes }, SORTIVO, SORTIVO_NAMES, SURFACES[mode], mode)

            colors.forEach((color, index) => colors.slice(0, index)
                .filter((earlier) => familyOf(SORTIVO, earlier) === familyOf(SORTIVO, color))
                .forEach((earlier) => assert.ok(distance(color, earlier) >= 0.15, `series ${index} is too close to an earlier ${familyOf(SORTIVO, color)}`)))
        }
    })
}

test('Other closes the series in gray', () => {
    const colors = seriesColors({ count: 4, other: true }, SORTIVO, SORTIVO_NAMES, SURFACES.light, 'light')

    assert.equal(familyOf(SORTIVO, colors[3]), 'gray')
    assert.deepEqual(colors.slice(0, 3), seriesColors({ count: 3 }, SORTIVO, SORTIVO_NAMES, SURFACES.light, 'light'))
})

test('a fixed list from the panel replaces the theme colours, named series aside', () => {
    const fixed = [[17, 17, 17], [34, 34, 34]]
    const colors = seriesColors({ count: 4, named: [null, null, 'danger', null], fixed }, FILAMENT, FILAMENT_NAMES, SURFACES.light, 'light')

    assert.deepEqual([colors[0], colors[1], colors[3]], [fixed[0], fixed[1], fixed[0]])
    assert.equal(familyOf(FILAMENT, colors[2]), 'danger')
})
