import assert from 'node:assert/strict'
import { test } from 'node:test'
import { contrast, oklab, seriesColors } from '../../resources/js/chart-palette.js'

const FAMILIES = {
    primary: { 50: [255, 251, 235], 100: [254, 243, 198], 200: [254, 230, 133], 300: [255, 210, 48], 400: [255, 185, 0], 500: [254, 154, 0], 600: [225, 113, 0], 700: [187, 77, 0], 800: [151, 60, 0], 900: [123, 51, 6], 950: [70, 25, 1] },
    info: { 50: [239, 246, 255], 100: [219, 234, 254], 200: [190, 219, 255], 300: [142, 197, 255], 400: [81, 162, 255], 500: [43, 127, 255], 600: [21, 93, 252], 700: [20, 71, 230], 800: [25, 60, 184], 900: [28, 57, 142], 950: [22, 36, 86] },
    gray: { 50: [250, 250, 250], 100: [244, 244, 245], 200: [228, 228, 231], 300: [212, 212, 216], 400: [159, 159, 169], 500: [113, 113, 123], 600: [82, 82, 92], 700: [63, 63, 70], 800: [39, 39, 42], 900: [24, 24, 27], 950: [9, 9, 11] },
}

const SUCCESS = { 400: [5, 223, 114], 500: [0, 201, 80], 600: [0, 166, 62] }

const SURFACES = { light: [255, 255, 255], dark: FAMILIES.gray[900] }

const lightness = (rgb) => oklab(rgb)[0]

const shadesOf = (name) => Object.values(FAMILIES[name]).map(String)

const distance = (first, second) => Math.hypot(...oklab(first).map((value, channel) => value - oklab(second)[channel]))

for (const mode of ['light', 'dark']) {
    test(`ordered series run light to dark in their order, in even steps, readable on the ${mode} card`, () => {
        for (const count of [2, 4, 7, 9]) {
            const colors = seriesColors({ kind: 'ordered', count }, FAMILIES, SURFACES[mode], mode)
            const edged = seriesColors({ kind: 'ordered', count, edged: true }, FAMILIES, SURFACES[mode], mode)
            const steps = colors.slice(1).map((color, index) => lightness(colors[index]) - lightness(color))

            assert.equal(colors.length, count)
            steps.forEach((step, index) => assert.ok(step > 0, `step ${index + 1} of ${count} is not darker`))
            assert.ok(Math.max(...steps) / Math.min(...steps) < 1.6, `uneven steps for ${count}`)
            colors.forEach((color) => assert.ok(contrast(color, SURFACES[mode]) >= 2))
            edged.forEach((color) => assert.ok(contrast(color, SURFACES[mode]) >= (mode === 'light' ? 1.5 : 2)))
        }

        assert.deepEqual(seriesColors({ kind: 'ordered', count: 2 }, FAMILIES, SURFACES[mode], mode), seriesColors({ kind: 'ordered', count: 2 }, FAMILIES, SURFACES[mode], mode).filter((color) => shadesOf('primary').includes(String(color))))
    })

    test(`varied series take turns through primary, info and gray, never a status colour, on the ${mode} card`, () => {
        const colors = seriesColors({ kind: 'varied', count: 6 }, { ...FAMILIES, success: SUCCESS }, SURFACES[mode], mode)

        colors.forEach((color, index) => assert.ok(shadesOf(['primary', 'info', 'gray'][index % 3]).includes(String(color))))
        assert.equal(new Set(colors.map(String)).size, colors.length)
        colors.slice(1).forEach((color, index) => assert.ok(distance(color, colors[index]) >= 0.15, `series ${index} and ${index + 1} are too alike`))
    })
}

test('Other closes an ordered ramp in gray, outside the ramp', () => {
    const colors = seriesColors({ kind: 'ordered', count: 4, other: true }, FAMILIES, SURFACES.light, 'light')

    assert.ok(shadesOf('gray').includes(String(colors[3])))
    assert.deepEqual(colors.slice(0, 3), seriesColors({ kind: 'ordered', count: 3 }, FAMILIES, SURFACES.light, 'light'))
})

test('a series that names a panel colour takes it, and the rest keep the palette', () => {
    const colors = seriesColors({ kind: 'varied', count: 3, named: [null, 'success', null] }, { ...FAMILIES, success: SUCCESS }, SURFACES.light, 'light')

    assert.ok(Object.values(SUCCESS).map(String).includes(String(colors[1])))
    assert.deepEqual([colors[0], colors[2]], seriesColors({ kind: 'varied', count: 2 }, FAMILIES, SURFACES.light, 'light'))
})

test('the same chart gets other shades in dark mode', () => {
    assert.notDeepEqual(seriesColors({ kind: 'varied', count: 3 }, FAMILIES, SURFACES.light, 'light'), seriesColors({ kind: 'varied', count: 3 }, FAMILIES, SURFACES.dark, 'dark'))
})
