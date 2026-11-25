import assert from 'node:assert/strict'
import { test } from 'node:test'
import { finestRow, snapHeight, storedLayout } from '../../resources/js/layout-units.js'

const CELL = 40
const ROWS_PER_CELL = CELL / finestRow(CELL)

const fine = (layout) => layout.map((item) => ({ ...item, y: item.y * ROWS_PER_CELL, h: item.h * ROWS_PER_CELL }))
const fixed = (ids) => (node) => !ids.includes(Number(node.id))

test('a saved layout loaded on the finer rows saves back unchanged', () => {
    const saved = [
        { id: 1, x: 0, y: 0, w: 6, h: 7, fitsContent: true },
        { id: 2, x: 6, y: 0, w: 6, h: 12, fitsContent: false },
        { id: 3, x: 0, y: 7, w: 6, h: 5, fitsContent: true },
        { id: 4, x: 0, y: 12, w: 12, h: 10, fitsContent: true },
    ]

    assert.deepEqual(storedLayout(fine(saved), ROWS_PER_CELL, fixed([2])), saved)
})

test('a card that fits its content saves as the cells it reaches into, keeping the order below it', () => {
    const nodes = [
        { id: 1, x: 0, y: 0, w: 12, h: 133 },
        { id: 2, x: 0, y: 133, w: 6, h: 61 },
        { id: 3, x: 6, y: 133, w: 6, h: 240 },
        { id: 4, x: 0, y: 194, w: 6, h: 41 },
        { id: 5, x: 0, y: 373, w: 12, h: 20 },
    ]

    assert.deepEqual(storedLayout(nodes, ROWS_PER_CELL, fixed([3])), [
        { id: 1, x: 0, y: 0, w: 12, h: 7, fitsContent: true },
        { id: 2, x: 0, y: 7, w: 6, h: 4, fitsContent: true },
        { id: 3, x: 6, y: 7, w: 6, h: 12, fitsContent: false },
        { id: 4, x: 0, y: 11, w: 6, h: 3, fitsContent: true },
        { id: 5, x: 0, y: 19, w: 12, h: 1, fitsContent: true },
    ])
})

test('a resized height moves in whole cells', () => {
    assert.equal(snapHeight(245, 240, ROWS_PER_CELL), 240)
    assert.equal(snapHeight(251, 240, ROWS_PER_CELL), 260)
    assert.equal(snapHeight(275, 240, ROWS_PER_CELL), 280)
    assert.equal(snapHeight(3, 240, ROWS_PER_CELL), ROWS_PER_CELL)
})

test('a height that fits its content stays put while only the width is resized', () => {
    assert.equal(snapHeight(233, 233, ROWS_PER_CELL), 233)
})

test('an odd cell height still gets pixel rows', () => {
    assert.equal(finestRow(41), 1)
    assert.equal(finestRow(50), 2)
})
