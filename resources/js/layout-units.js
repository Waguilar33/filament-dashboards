export const finestRow = (cellHeight) => (cellHeight % 2 === 0 ? 2 : 1)

export function snapHeight(h, startHeight, rowsPerCell) {
    if (Math.abs(h - startHeight) < rowsPerCell / 2) {
        return startHeight
    }

    return Math.max(rowsPerCell, Math.round(h / rowsPerCell) * rowsPerCell)
}

// Layouts are stored in whole cells; packing again in cells keeps every card's order where rounding y could not.
export function storedLayout(nodes, rowsPerCell, fitsContent) {
    const placed = []

    return [...nodes]
        .sort((a, b) => a.y - b.y || a.x - b.x)
        .map((node) => {
            const fits = fitsContent(node)
            const h = fits ? Math.ceil(node.h / rowsPerCell) : Math.round(node.h / rowsPerCell)
            const y = placed
                .filter((other) => other.x < node.x + node.w && node.x < other.x + other.w)
                .reduce((top, other) => Math.max(top, other.y + other.h), 0)
            const stored = { id: Number(node.id), x: node.x, y, w: node.w, h, fitsContent: fits }

            placed.push(stored)

            return stored
        })
}
