import { GridStack } from 'gridstack'

const COLUMNS = 12

const SPAN_VARIABLES = ['--col-span-csm', '--col-span-ncmd', '--col-span-cxl', '--col-span-nclg']

export default function dashboardGrid({ editable }) {
    return {
        grid: null,

        observer: null,

        init() {
            this.grid = GridStack.init(
                {
                    column: COLUMNS,
                    cellHeight: 40,
                    margin: 8,
                    float: false,
                    animate: true,
                    sizeToContent: true,
                    staticGrid: !editable,
                    draggable: { cancel: 'input,textarea,button,select,option,a,.fi-dashboards-item-actions,.fi-dashboards-stat-resize' },
                    resizable: { handles: 'e, w', autoHide: false },
                    columnOpts: { breakpointForWindow: true, breakpoints: [{ w: 1024, c: 1 }] },
                },
                this.$refs.grid,
            )

            // Widgets load lazily and charts draw after mount, so heights settle after Gridstack measured them.
            this.observer = new ResizeObserver((entries) => {
                entries.forEach((entry) => this.grid.resizeToContent(entry.target.closest('.grid-stack-item')))
            })

            this.$refs.grid.querySelectorAll('.fi-dashboards-item').forEach((item) => this.observer.observe(item))

            this.grid.on('dragstop resizestop', () => setTimeout(() => this.save()))

            if (editable) {
                this.$refs.grid.addEventListener('pointerdown', (event) => this.resizeCard(event))
            }
        },

        resizeCard(event) {
            const handle = event.target.closest('.fi-dashboards-stat-resize')

            if (!handle) {
                return
            }

            event.preventDefault()
            event.stopPropagation()

            const column = handle.closest('.fi-grid-col')
            const cards = column.parentElement
            const gap = parseFloat(getComputedStyle(cards).columnGap) || 0
            const step = (cards.clientWidth + gap) / COLUMNS
            const startWidth = column.getBoundingClientRect().width
            const startX = event.clientX

            handle.setPointerCapture(event.pointerId)

            const move = (moveEvent) => {
                const span = Math.min(COLUMNS, Math.max(1, Math.round((startWidth + gap + moveEvent.clientX - startX) / step)))

                SPAN_VARIABLES.forEach((variable) => column.style.setProperty(variable, `span ${span} / span ${span}`))
            }

            const stop = () => {
                handle.removeEventListener('pointermove', move)
                handle.removeEventListener('pointerup', stop)
                handle.removeEventListener('pointercancel', stop)

                const widths = {}

                cards.querySelectorAll(':scope > .fi-grid-col .fi-dashboards-stat').forEach((card) => {
                    widths[card.dataset.cardKey] = Math.round((card.closest('.fi-grid-col').getBoundingClientRect().width + gap) / step)
                })

                this.$wire.saveCardWidths(Number(handle.closest('.grid-stack-item').getAttribute('gs-id')), widths)
            }

            handle.addEventListener('pointermove', move)
            handle.addEventListener('pointerup', stop)
            handle.addEventListener('pointercancel', stop)
        },

        save() {
            // Narrow screens stack every widget in one column; saving that would lose the desktop layout.
            if (this.grid.getColumn() !== COLUMNS) {
                return
            }

            this.$wire.saveDashboardLayout(
                this.grid.save(false).map((node) => ({ id: Number(node.id), x: node.x, y: node.y, w: node.w, h: node.h })),
            )
        },

        destroy() {
            this.observer?.disconnect()
            this.grid?.destroy(false)
        },
    }
}
